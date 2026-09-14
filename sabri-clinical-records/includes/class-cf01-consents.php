<?php
defined('ABSPATH') || exit;

final class CF01_Consents {
    public const PURPOSES = array('clinical_care', 'teleconsultation', 'images', 'recording', 'education', 'transfer', 'research');

    /**
     * Persist a durable, privacy-minimal notice-presentation receipt without silently
     * invalidating an already active grant. The actual consent decision remains a
     * separate RecordConsent command and canonical clinical_consent row.
     */
    public static function present(int $actor_id, string $patient_uuid, string $purpose, string $notice_version, array $evidence = array()): array {
        $patient = CF01_Patients::get($patient_uuid);
        if (!in_array($purpose, self::PURPOSES, true)) {
            throw new InvalidArgumentException('Unsupported consent purpose.');
        }
        $notice_version = trim($notice_version);
        if ($notice_version === '' || strlen($notice_version) > 64) {
            throw new InvalidArgumentException('A valid consent notice version is required.');
        }
        self::authorize_subject($actor_id, $patient_uuid, $patient, $purpose, $evidence);
        $locale = sanitize_text_field((string) ($evidence['locale'] ?? ''));
        $channel = sanitize_key((string) ($evidence['channel'] ?? 'web'));
        $presentation_uuid = CF01_DB::uuid();
        $audit_uuid = CF01_Audit::record(
            $actor_id,
            'ClinicalConsentPresented',
            'clinical_consent_notice',
            $presentation_uuid,
            $purpose,
            array(
                'patient_reference' => substr(hash('sha256', $patient_uuid), 0, 16),
                'notice_version' => $notice_version,
                'locale' => $locale,
                'channel' => $channel,
            )
        );
        return array(
            'presentation_uuid' => $presentation_uuid,
            'audit_event_uuid' => $audit_uuid,
            'patient_uuid' => $patient_uuid,
            'purpose' => $purpose,
            'notice_version' => $notice_version,
            'presented_at' => CF01_DB::now(),
            'decision_recorded' => false,
        );
    }

    public static function record(int $actor_id, string $patient_uuid, string $purpose, string $status, array $evidence): array {
        $patient = CF01_Patients::get($patient_uuid);
        $authority = self::authorize_subject($actor_id, $patient_uuid, $patient, $purpose, $evidence);
        if (!in_array($purpose, self::PURPOSES, true)) {
            throw new InvalidArgumentException('Unsupported consent purpose.');
        }
        if (!in_array($status, array('granted', 'declined'), true)) {
            throw new InvalidArgumentException('Consent must be granted or declined.');
        }
        if (!empty($evidence['bundled_purposes']) || !empty($evidence['coerced'])) {
            throw new InvalidArgumentException('Bundled or coerced consent is prohibited.');
        }
        if (empty($evidence['notice_version']) || empty($evidence['subject_platform_uuid'])) {
            throw new InvalidArgumentException('Consent notice and subject evidence are required.');
        }
        self::validate_subject_identity($actor_id, $patient_uuid, $purpose, $patient, (string) $evidence['subject_platform_uuid'], $authority);
        $demographics = CF01_Patients::demographics($patient);
        $is_minor = self::is_legal_minor($patient_uuid, $demographics);
        if ($is_minor) {
            $guardian = CF01_Patients::guardian_context($patient);
            if (($guardian['status'] ?? '') !== 'verified') {
                throw new RuntimeException('Verified guardian authority is required for a legal minor.');
            }
            if (empty($evidence['minor_assent']) && empty($evidence['assent_not_applicable_reason'])) {
                throw new InvalidArgumentException('Minor assent or a documented non-applicability reason is required.');
            }
        }
        $consent_uuid = CF01_DB::uuid();
        CF01_DB::insert('consents', array(
            'consent_uuid' => $consent_uuid,
            'patient_uuid' => $patient_uuid,
            'purpose' => $purpose,
            'notice_version' => sanitize_text_field((string) $evidence['notice_version']),
            'status' => $status,
            'subject_hash' => CF01_Crypto::blind_index((string) $evidence['subject_platform_uuid'], 'consent-subject'),
            'guardian_reference_cipher' => CF01_Crypto::encrypt((array) ($evidence['guardian'] ?? array()), 'consent-guardian'),
            'evidence_cipher' => CF01_Crypto::encrypt($evidence, 'consent-evidence'),
            'granted_at' => $status === 'granted' ? CF01_DB::now() : null,
            'withdrawn_at' => null,
            'expires_at' => self::normalize_expiry($evidence['expires_at'] ?? null),
            'row_version' => 1,
            'created_at' => CF01_DB::now(),
            'updated_at' => CF01_DB::now(),
        ));
        $event = $status === 'granted' ? 'ClinicalConsentGranted' : 'ClinicalConsentDeclined';
        CF01_Audit::record($actor_id, $event, 'clinical_consent', $consent_uuid, $purpose, array('status' => $status));
        CF01_Outbox::enqueue($event, array('consent_uuid' => $consent_uuid, 'patient_uuid' => $patient_uuid, 'purpose' => $purpose), $consent_uuid);
        return self::get($consent_uuid);
    }

    /**
     * Renew/re-consent by creating a new immutable consent decision linked to the
     * prior record. Historical consent rows are never overwritten.
     */
    public static function renew(int $actor_id, string $consent_uuid, array $evidence): array {
        $prior = self::get($consent_uuid);
        $patient_uuid = (string) $prior['patient_uuid'];
        $purpose = (string) $prior['purpose'];
        if (!in_array((string) $prior['status'], array('granted', 'withdrawn', 'declined'), true)) {
            throw new RuntimeException('Consent is not in a state that can be renewed.');
        }
        if (empty($evidence['notice_version']) || empty($evidence['subject_platform_uuid'])) {
            throw new InvalidArgumentException('Renewed consent requires the current notice and subject evidence.');
        }
        $evidence['renewal_of'] = $consent_uuid;
        $evidence['prior_notice_version'] = (string) ($prior['notice_version'] ?? '');
        $renewed = self::record($actor_id, $patient_uuid, $purpose, 'granted', $evidence);
        CF01_Audit::record(
            $actor_id,
            'ClinicalConsentRenewed',
            'clinical_consent',
            (string) $renewed['consent_uuid'],
            $purpose,
            array('prior_consent_reference' => substr(hash('sha256', $consent_uuid), 0, 16))
        );
        return $renewed;
    }

    public static function withdraw(int $actor_id, string $consent_uuid, string $reason, int $expected_version): array {
        $row = self::get($consent_uuid);
        $patient = CF01_Patients::get((string) $row['patient_uuid']);
        self::authorize_subject($actor_id, (string) $row['patient_uuid'], $patient, (string) $row['purpose'], array());
        CF01_Authorization::expected_version($row, $expected_version);
        if (($row['status'] ?? '') !== 'granted') {
            throw new RuntimeException('Only an active consent may be withdrawn.');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Consent withdrawal reason is required.');
        }
        $ok = CF01_DB::update_versioned('consents', array(
            'status' => 'withdrawn',
            'withdrawn_at' => CF01_DB::now(),
            'withdrawal_reason_cipher' => CF01_Crypto::encrypt($reason, 'consent-withdrawal'),
        ), array('consent_uuid' => $consent_uuid), $expected_version);
        if (!$ok) {
            throw new RuntimeException('Consent changed concurrently.');
        }
        CF01_Audit::record($actor_id, 'ClinicalConsentWithdrawn', 'clinical_consent', $consent_uuid, (string) $row['purpose'], array());
        CF01_Outbox::enqueue('ClinicalConsentWithdrawn', array('consent_uuid' => $consent_uuid, 'patient_uuid' => $row['patient_uuid'], 'purpose' => $row['purpose']), $consent_uuid);
        return self::get($consent_uuid);
    }

    public static function get(string $uuid): array {
        $row = CF01_DB::row('SELECT * FROM ' . CF01_DB::table('consents') . ' WHERE consent_uuid = %s LIMIT 1', array($uuid));
        if (!$row) {
            throw new RuntimeException('Consent record is unavailable.');
        }
        return $row;
    }

    public static function active(string $patient_uuid, string $purpose): bool {
        try {
            CF01_Authorization::consent($patient_uuid, $purpose);
            return true;
        } catch (Throwable $error) {
            return false;
        }
    }

    private static function authorize_subject(int $actor_id, string $patient_uuid, array $patient, string $purpose, array $evidence): array {
        if (CF01_Authorization::patient_owner($actor_id, $patient_uuid)) {
            CF01_Authorization::actor($actor_id, 'record_own_consent', array('patient_uuid' => $patient_uuid, 'purpose' => $purpose));
            return array('role' => 'patient');
        }
        try {
            $context = CF01_Role_Context::resolve($actor_id, $patient_uuid, $purpose, 'guardian');
            $presented_reference = (string) ($evidence['guardian']['reference'] ?? '');
            if ($presented_reference !== '' && !empty($context['guardian_reference']) && !hash_equals((string) $context['guardian_reference'], $presented_reference)) {
                throw new RuntimeException('Guardian evidence does not match current verified authority.');
            }
            CF01_Authorization::actor($actor_id, 'record_own_consent', array('patient_uuid' => $patient_uuid, 'purpose' => $purpose));
            return $context;
        } catch (Throwable $guardian_error) {
            CF01_Authorization::clinician($actor_id, 'record_consent', array('patient_uuid' => $patient_uuid, 'purpose' => $purpose));
            CF01_Authorization::relationship($patient_uuid, $actor_id, 'clinical_care', 'record_consent');
            $guardian_actor_id = (int) ($evidence['guardian']['actor_user_id'] ?? 0);
            $reference = (string) ($evidence['guardian']['reference'] ?? '');
            if (self::is_legal_minor($patient_uuid, CF01_Patients::demographics($patient))) {
                $assertion = $guardian_actor_id > 0 ? CF01_Contracts::guardian_authority($guardian_actor_id, $patient_uuid, $purpose, $reference) : array('valid' => false);
                if (empty($assertion['valid'])) {
                    throw new RuntimeException('Current guardian authority evidence is required for clinician-recorded minor consent.');
                }
            }
            return array('role' => 'doctor');
        }
    }

    private static function validate_subject_identity(int $actor_id, string $patient_uuid, string $purpose, array $patient, string $subject_platform_uuid, array $authority): void {
        $patient_platform_uuid = CF01_Crypto::decrypt((string) ($patient['platform_subject_cipher'] ?? ''), 'patient-platform-link');
        $guardian = CF01_Patients::guardian_context($patient);
        $guardian_platform_uuid = (string) ($guardian['platform_uuid'] ?? '');
        $matches_patient = is_string($patient_platform_uuid) && $patient_platform_uuid !== '' && hash_equals($patient_platform_uuid, $subject_platform_uuid);
        $matches_guardian = ($authority['role'] ?? '') === 'guardian'
            && ($guardian['status'] ?? '') === 'verified'
            && $guardian_platform_uuid !== ''
            && hash_equals($guardian_platform_uuid, $subject_platform_uuid);
        if (!$matches_patient && !$matches_guardian) {
            throw new RuntimeException('Consent subject does not match the patient or current verified guardian.');
        }
        if ($matches_guardian) {
            $assertion = CF01_Contracts::guardian_authority($actor_id, $patient_uuid, $purpose, (string) ($guardian['reference'] ?? ''));
            if (empty($assertion['valid'])) {
                throw new RuntimeException('Guardian authority is no longer current.');
            }
        }
    }

    private static function is_legal_minor(string $patient_uuid, array $demographics): bool {
        $date_of_birth = trim((string) ($demographics['date_of_birth'] ?? ''));
        if ($date_of_birth === '') {
            throw new RuntimeException('Clinical age evidence is unavailable.');
        }
        try {
            $birth = new DateTimeImmutable($date_of_birth, new DateTimeZone('UTC'));
            $today = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        } catch (Throwable $error) {
            throw new RuntimeException('Clinical age evidence is invalid.');
        }
        $majority_age = (int) apply_filters('cf01_legal_majority_age', 18, $patient_uuid, $demographics);
        $majority_age = max(12, min(25, $majority_age));
        return $birth->modify('+' . $majority_age . ' years') > $today;
    }

    private static function normalize_expiry($value): ?string {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            $date = new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));
        } catch (Throwable $error) {
            throw new InvalidArgumentException('Consent expiry must be a future date.');
        }
        if ($date->getTimestamp() <= time()) {
            throw new InvalidArgumentException('Consent expiry must be a future date.');
        }
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
