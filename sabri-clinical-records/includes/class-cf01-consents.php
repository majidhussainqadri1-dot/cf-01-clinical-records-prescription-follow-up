<?php
defined('ABSPATH') || exit;

final class CF01_Consents {
    public const PURPOSES = array('clinical_care', 'teleconsultation', 'images', 'recording', 'educational_reuse', 'transfer', 'research');

    public static function record(int $actor_id, string $patient_uuid, string $purpose, string $status, array $evidence): array {
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

        $patient = CF01_Patients::get($patient_uuid);
        $authority = self::authorize_subject($actor_id, $patient_uuid, $patient, $purpose, $evidence);
        self::validate_subject_identity($actor_id, $patient_uuid, $purpose, $patient, (string) $evidence['subject_platform_uuid'], $authority);

        $demographics = CF01_Patients::demographics($patient);
        $jurisdiction = trim((string) ($patient['jurisdiction'] ?? ''));
        $is_minor = self::is_legal_minor($patient_uuid, $demographics, $jurisdiction);
        if ($is_minor) {
            if (($authority['role'] ?? '') === 'patient') {
                throw new RuntimeException('A legal minor cannot create a new clinical consent without a current verified guardian or an authorized clinician recording that guardian consent.');
            }
            self::require_current_minor_guardian($actor_id, $patient_uuid, $purpose, $patient, $evidence, $authority);
            if (empty($evidence['minor_assent']) && empty($evidence['assent_not_applicable_reason'])) {
                throw new InvalidArgumentException('Minor assent or a documented non-applicability reason is required.');
            }
        }

        $consent_uuid = CF01_DB::uuid();
        $event = $status === 'granted' ? 'ClinicalConsentGranted' : 'ClinicalConsentDeclined';
        CF01_DB::transaction(function () use ($actor_id, $patient_uuid, $purpose, $status, $evidence, $consent_uuid, $event): void {
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
            CF01_Audit::record($actor_id, $event, 'clinical_consent', $consent_uuid, $purpose, array('status' => $status));
            CF01_Outbox::enqueue($event, array('consent_uuid' => $consent_uuid, 'patient_uuid' => $patient_uuid, 'purpose' => $purpose), $consent_uuid);
        });
        return self::get($consent_uuid);
    }

    public static function withdraw(int $actor_id, string $consent_uuid, string $reason, int $expected_version): array {
        $row = self::get($consent_uuid);
        $patient = CF01_Patients::get((string) $row['patient_uuid']);
        self::authorize_withdrawal_subject($actor_id, (string) $row['patient_uuid'], $patient, (string) $row['purpose']);
        CF01_Authorization::expected_version($row, $expected_version);
        if (($row['status'] ?? '') !== 'granted') {
            throw new RuntimeException('Only an active consent may be withdrawn.');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Consent withdrawal reason is required.');
        }
        CF01_DB::transaction(function () use ($actor_id, $consent_uuid, $row, $reason, $expected_version): void {
            $current = self::get_for_update($consent_uuid);
            CF01_Authorization::expected_version($current, $expected_version);
            if (($current['status'] ?? '') !== 'granted') {
                throw new RuntimeException('Only an active consent may be withdrawn.');
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
        });
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

    private static function get_for_update(string $uuid): array {
        $row = CF01_DB::row('SELECT * FROM ' . CF01_DB::table('consents') . ' WHERE consent_uuid = %s LIMIT 1 FOR UPDATE', array($uuid));
        if (!$row) {
            throw new RuntimeException('Consent record is unavailable.');
        }
        return $row;
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
            return array('role' => 'doctor');
        }
    }

    private static function authorize_withdrawal_subject(int $actor_id, string $patient_uuid, array $patient, string $purpose): array {
        if (CF01_Authorization::patient_owner($actor_id, $patient_uuid)) {
            CF01_Authorization::actor($actor_id, 'withdraw_own_consent', array('patient_uuid' => $patient_uuid, 'purpose' => $purpose));
            return array('role' => 'patient');
        }
        $context = CF01_Role_Context::resolve($actor_id, $patient_uuid, $purpose, 'guardian');
        CF01_Authorization::actor($actor_id, 'withdraw_own_consent', array('patient_uuid' => $patient_uuid, 'purpose' => $purpose));
        return $context;
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
            if (empty($assertion['valid'])
                || (int) ($assertion['authority_version'] ?? 0) !== (int) ($guardian['authority_version'] ?? 0)
                || !hash_equals((string) ($guardian['contract_version'] ?? ''), (string) ($assertion['contract_version'] ?? ''))
            ) {
                throw new RuntimeException('Guardian authority is no longer current.');
            }
        }
    }

    private static function require_current_minor_guardian(int $actor_id, string $patient_uuid, string $purpose, array $patient, array $evidence, array $authority): void {
        $guardian = CF01_Patients::guardian_context($patient);
        if (($guardian['status'] ?? '') !== 'verified') {
            throw new RuntimeException('Verified guardian authority is required for a legal minor.');
        }
        $stored_reference = trim((string) ($guardian['reference'] ?? ''));
        $stored_subject = trim((string) ($guardian['platform_uuid'] ?? ''));
        $stored_authority_version = (int) ($guardian['authority_version'] ?? 0);
        $stored_contract_version = (string) ($guardian['contract_version'] ?? '');
        if ($stored_reference === '' || $stored_subject === '' || $stored_authority_version < 1 || $stored_contract_version === '') {
            throw new RuntimeException('Verified guardian authority evidence is incomplete.');
        }
        if (empty($guardian['expires_at']) || !CF01_Authorization::not_expired((string) $guardian['expires_at'])) {
            throw new RuntimeException('Verified guardian authority has expired.');
        }
        $scope = array_values(array_unique(array_map('sanitize_key', (array) ($guardian['scope'] ?? array()))));
        if (!$scope || !in_array(sanitize_key($purpose), $scope, true)) {
            throw new RuntimeException('Verified guardian scope does not authorize this consent purpose.');
        }

        $presented_reference = trim((string) ($evidence['guardian']['reference'] ?? $stored_reference));
        if ($presented_reference === '' || !hash_equals($stored_reference, $presented_reference)) {
            throw new RuntimeException('Guardian evidence does not match current verified authority.');
        }
        $guardian_actor_id = ($authority['role'] ?? '') === 'guardian'
            ? $actor_id
            : (int) ($evidence['guardian']['actor_user_id'] ?? 0);
        if ($guardian_actor_id <= 0) {
            throw new RuntimeException('Current guardian actor evidence is required for minor clinical consent.');
        }
        $assertion = CF01_Contracts::guardian_authority($guardian_actor_id, $patient_uuid, $purpose, $presented_reference);
        if (empty($assertion['valid'])
            || !hash_equals($stored_subject, (string) ($assertion['actor_platform_uuid'] ?? ''))
            || !hash_equals($stored_reference, (string) ($assertion['guardian_reference'] ?? ''))
            || (int) ($assertion['authority_version'] ?? 0) !== $stored_authority_version
            || !hash_equals($stored_contract_version, (string) ($assertion['contract_version'] ?? ''))
        ) {
            throw new RuntimeException('Current guardian authority evidence is required for minor clinical consent.');
        }
    }

    private static function is_legal_minor(string $patient_uuid, array $demographics, string $jurisdiction): bool {
        $date_of_birth = trim((string) ($demographics['date_of_birth'] ?? ''));
        if ($date_of_birth === '') {
            throw new RuntimeException('Clinical age evidence is unavailable.');
        }
        $jurisdiction = trim($jurisdiction);
        if ($jurisdiction === '') {
            throw new RuntimeException('Clinical jurisdiction evidence is unavailable.');
        }
        try {
            $birth = new DateTimeImmutable($date_of_birth, new DateTimeZone('UTC'));
            $today = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        } catch (Throwable $error) {
            throw new RuntimeException('Clinical age evidence is invalid.');
        }
        if ($birth > $today) {
            throw new RuntimeException('Clinical age evidence is invalid.');
        }

        $sex = sanitize_key((string) ($demographics['sex'] ?? ''));
        $platform_minimum = $sex === 'male' ? 15 : ($sex === 'female' ? 12 : 18);
        $legal_majority_age = (int) apply_filters('cf01_legal_majority_age', 18, $patient_uuid, $demographics, $jurisdiction);
        $legal_majority_age = max(12, min(25, $legal_majority_age));
        $consent_age = max($platform_minimum, $legal_majority_age);
        return $birth->modify('+' . $consent_age . ' years') > $today;
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
