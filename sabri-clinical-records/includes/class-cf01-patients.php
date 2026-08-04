<?php
defined('ABSPATH') || exit;

final class CF01_Patients {
    public static function create(int $actor_id, string $platform_uuid, array $minimum_demographics, string $jurisdiction): array {
        CF01_Authorization::actor($actor_id, 'create_clinical_patient');
        self::validate_demographics($minimum_demographics);
        $subject_hash = CF01_Crypto::blind_index($platform_uuid, 'platform-subject');
        $existing = CF01_DB::row(
            'SELECT * FROM ' . CF01_DB::table('patients') . ' WHERE platform_subject_hash = %s ORDER BY id DESC LIMIT 1',
            array($subject_hash)
        );
        if ($existing) {
            if (($existing['status'] ?? '') === 'quarantined') {
                throw new RuntimeException('Clinical identity requires duplicate review.');
            }
            return self::public_row($existing);
        }

        $clinical_uuid = CF01_DB::uuid();
        return CF01_DB::transaction(function () use ($actor_id, $platform_uuid, $subject_hash, $minimum_demographics, $jurisdiction, $clinical_uuid): array {
            CF01_DB::insert('patients', array(
                'clinical_uuid' => $clinical_uuid,
                'platform_subject_hash' => $subject_hash,
                'platform_subject_cipher' => CF01_Crypto::encrypt($platform_uuid, 'patient-platform-link'),
                'demographics_cipher' => CF01_Crypto::encrypt($minimum_demographics, 'patient-demographics'),
                'jurisdiction' => sanitize_text_field($jurisdiction),
                'guardian_context_cipher' => CF01_Crypto::encrypt(array(), 'guardian-context'),
                'status' => 'active',
                'row_version' => 1,
                'created_at' => CF01_DB::now(),
                'updated_at' => CF01_DB::now(),
            ));
            CF01_Audit::record($actor_id, 'ClinicalPatientCreated', 'clinical_patient', $clinical_uuid, 'clinical_care', array('status' => 'active'));
            CF01_Outbox::enqueue('ClinicalPatientLinked', array('clinical_uuid' => $clinical_uuid), $clinical_uuid);
            return array('clinical_uuid' => $clinical_uuid, 'status' => 'active', 'row_version' => 1);
        });
    }

    public static function link_platform_identity(int $actor_id, string $clinical_uuid, string $platform_uuid, int $expected_version): array {
        CF01_Authorization::actor($actor_id, 'link_platform_identity');
        $patient = self::get($clinical_uuid);
        CF01_Authorization::expected_version($patient, $expected_version);
        $hash = CF01_Crypto::blind_index($platform_uuid, 'platform-subject');
        $collision = CF01_DB::row(
            'SELECT clinical_uuid FROM ' . CF01_DB::table('patients') . ' WHERE platform_subject_hash = %s AND clinical_uuid <> %s LIMIT 1',
            array($hash, $clinical_uuid)
        );
        if ($collision) {
            CF01_DB::update_versioned('patients', array('status' => 'quarantined'), array('clinical_uuid' => $clinical_uuid), $expected_version);
            CF01_Audit::record($actor_id, 'ClinicalPatientIdentityQuarantined', 'clinical_patient', $clinical_uuid, 'identity_review', array('collision' => true));
            throw new RuntimeException('Duplicate clinical identity was quarantined.');
        }
        $ok = CF01_DB::update_versioned('patients', array(
            'platform_subject_hash' => $hash,
            'platform_subject_cipher' => CF01_Crypto::encrypt($platform_uuid, 'patient-platform-link'),
        ), array('clinical_uuid' => $clinical_uuid), $expected_version);
        if (!$ok) {
            throw new RuntimeException('Clinical identity changed concurrently.');
        }
        CF01_Audit::record($actor_id, 'ClinicalPatientIdentityLinked', 'clinical_patient', $clinical_uuid, 'identity_link', array());
        return self::public_row(self::get($clinical_uuid));
    }

    public static function update_guardian_context(int $actor_id, string $clinical_uuid, array $guardian, int $expected_version): array {
        CF01_Authorization::actor($actor_id, 'update_guardian_context');
        $patient = self::get($clinical_uuid);
        CF01_Authorization::expected_version($patient, $expected_version);
        $allowed_status = array('verified', 'revoked', 'expired', 'not_required', 'pending');
        if (!isset($guardian['status']) || !in_array($guardian['status'], $allowed_status, true)) {
            throw new InvalidArgumentException('Invalid guardian status.');
        }
        if (($guardian['status'] ?? '') === 'verified' && empty($guardian['scope'])) {
            throw new InvalidArgumentException('Guardian scope is required.');
        }
        $ok = CF01_DB::update_versioned('patients', array(
            'guardian_context_cipher' => CF01_Crypto::encrypt($guardian, 'guardian-context'),
        ), array('clinical_uuid' => $clinical_uuid), $expected_version);
        if (!$ok) {
            throw new RuntimeException('Guardian context changed concurrently.');
        }
        CF01_Audit::record($actor_id, 'GuardianAuthorityChanged', 'clinical_patient', $clinical_uuid, 'guardian_governance', array('status' => $guardian['status']));
        CF01_Outbox::enqueue('GuardianAuthorityChanged', array('clinical_uuid' => $clinical_uuid, 'status' => $guardian['status']), $clinical_uuid);
        return self::public_row(self::get($clinical_uuid));
    }

    public static function get(string $clinical_uuid): array {
        $row = CF01_DB::row(
            'SELECT * FROM ' . CF01_DB::table('patients') . ' WHERE clinical_uuid = %s LIMIT 1',
            array($clinical_uuid)
        );
        if (!$row) {
            throw new RuntimeException('Clinical record is unavailable.');
        }
        return $row;
    }

    public static function public_row(array $row): array {
        return array(
            'clinical_uuid' => (string) $row['clinical_uuid'],
            'status' => (string) $row['status'],
            'jurisdiction' => (string) ($row['jurisdiction'] ?? ''),
            'row_version' => (int) $row['row_version'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        );
    }

    public static function demographics(array $row): array {
        $value = CF01_Crypto::decrypt((string) $row['demographics_cipher'], 'patient-demographics');
        return is_array($value) ? $value : array();
    }

    public static function guardian_context(array $row): array {
        $value = CF01_Crypto::decrypt((string) $row['guardian_context_cipher'], 'guardian-context');
        return is_array($value) ? $value : array();
    }

    private static function validate_demographics(array $data): void {
        $allowed = array('display_name', 'date_of_birth', 'sex', 'language', 'time_zone');
        if (array_diff(array_keys($data), $allowed)) {
            throw new InvalidArgumentException('Only minimum verified demographics may enter the clinical chart.');
        }
        if (empty($data['date_of_birth']) || !self::valid_date((string) $data['date_of_birth'])) {
            throw new InvalidArgumentException('A valid date of birth is required.');
        }
        if (strtotime((string) $data['date_of_birth'] . ' UTC') > time()) {
            throw new InvalidArgumentException('Date of birth cannot be in the future.');
        }
    }

    private static function valid_date(string $date): bool {
        $value = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
        return $value instanceof DateTimeImmutable && $value->format('Y-m-d') === $date;
    }
}
