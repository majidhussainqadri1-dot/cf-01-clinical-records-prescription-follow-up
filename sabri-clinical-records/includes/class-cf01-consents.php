<?php
defined('ABSPATH') || exit;

final class CF01_Consents {
    public const PURPOSES = array('clinical_care', 'teleconsultation', 'images', 'recording', 'education', 'transfer', 'research');

    public static function record(int $actor_id, string $patient_uuid, string $purpose, string $status, array $evidence): array {
        $patient = CF01_Patients::get($patient_uuid);
        self::authorize_subject($actor_id, $patient_uuid, $patient, $evidence);
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
        self::validate_subject_identity($patient, (string) $evidence['subject_platform_uuid']);
        $demographics = CF01_Patients::demographics($patient);
        $dob = isset($demographics['date_of_birth']) ? strtotime((string) $demographics['date_of_birth'] . ' UTC') : false;
        $is_minor = is_int($dob) && strtotime('-18 years') < $dob;
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

    public static function withdraw(int $actor_id, string $consent_uuid, string $reason, int $expected_version): array {
        $row = self::get($consent_uuid);
        $patient = CF01_Patients::get((string) $row['patient_uuid']);
        self::authorize_subject($actor_id, (string) $row['patient_uuid'], $patient, array());
        CF01_Authorization::expected_version($row, $expected_version);
        if (($row['status'] ?? '') !== 'granted') {
            throw new RuntimeException('Only an active consent may be withdrawn.');
        }
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



    private static function authorize_subject(int $actor_id, string $patient_uuid, array $patient, array $evidence): void {
        if (CF01_Authorization::patient_owner($actor_id, $patient_uuid)) {
            CF01_Authorization::actor($actor_id, 'request_clinical_right');
            return;
        }
        $guardian = CF01_Patients::guardian_context($patient);
        $membership = CF01_Contracts::membership($actor_id);
        $guardian_actor = !empty($membership['valid']) && !empty($guardian['platform_uuid']) && hash_equals((string) $guardian['platform_uuid'], (string) $membership['platform_uuid']);
        $guardian_reference = !empty($evidence['guardian']['reference']) && !empty($guardian['reference']) && hash_equals((string) $guardian['reference'], (string) $evidence['guardian']['reference']);
        if (($guardian['status'] ?? '') === 'verified' && ($guardian_actor || $guardian_reference)) {
            CF01_Authorization::actor($actor_id, 'request_clinical_right');
            return;
        }
        CF01_Authorization::clinician($actor_id, 'record_consent');
        CF01_Authorization::relationship($patient_uuid, $actor_id, 'clinical_care');
    }

    private static function validate_subject_identity(array $patient, string $subject_platform_uuid): void {
        $patient_platform_uuid = CF01_Crypto::decrypt((string) ($patient['platform_subject_cipher'] ?? ''), 'patient-platform-link');
        $guardian = CF01_Patients::guardian_context($patient);
        $guardian_platform_uuid = (string) ($guardian['platform_uuid'] ?? '');
        $matches_patient = is_string($patient_platform_uuid) && $patient_platform_uuid !== '' && hash_equals($patient_platform_uuid, $subject_platform_uuid);
        $matches_guardian = ($guardian['status'] ?? '') === 'verified' && $guardian_platform_uuid !== '' && hash_equals($guardian_platform_uuid, $subject_platform_uuid);
        if (!$matches_patient && !$matches_guardian) {
            throw new RuntimeException('Consent subject does not match the patient or verified guardian.');
        }
    }

    private static function normalize_expiry($value): ?string {
        if ($value === null || $value === '') {
            return null;
        }
        $timestamp = strtotime((string) $value);
        if (!is_int($timestamp) || $timestamp <= time()) {
            throw new InvalidArgumentException('Consent expiry must be a future date.');
        }
        return gmdate('Y-m-d H:i:s', $timestamp);
    }
}
