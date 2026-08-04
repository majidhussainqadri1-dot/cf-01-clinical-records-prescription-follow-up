<?php
defined('ABSPATH') || exit;

final class CF01_Relationships {
    private const TRANSITIONS = array(
        'proposed' => array('identity_consent_pending', 'ended'),
        'identity_consent_pending' => array('active', 'ended'),
        'active' => array('restricted', 'suspended', 'ended'),
        'restricted' => array('active', 'suspended', 'ended'),
        'suspended' => array('active', 'ended'),
        'ended' => array('archived'),
        'archived' => array(),
    );

    public static function propose(int $actor_id, string $patient_uuid, int $doctor_user_id, array $data): array {
        CF01_Authorization::actor($actor_id, 'propose_relationship');
        CF01_Patients::get($patient_uuid);
        $purpose = sanitize_key((string) ($data['purpose'] ?? 'clinical_care'));
        $scope = array_values(array_unique(array_map('sanitize_key', (array) ($data['scope'] ?? array('care')))));
        if (!$scope) {
            throw new InvalidArgumentException('Relationship scope is required.');
        }
        $relationship_uuid = CF01_DB::uuid();
        CF01_DB::insert('relationships', array(
            'relationship_uuid' => $relationship_uuid,
            'patient_uuid' => $patient_uuid,
            'doctor_user_id' => $doctor_user_id,
            'clinic_reference' => sanitize_text_field((string) ($data['clinic_reference'] ?? '')),
            'source_reference' => sanitize_text_field((string) ($data['source_reference'] ?? '')),
            'purpose' => $purpose,
            'scope_json' => wp_json_encode($scope),
            'status' => 'proposed',
            'starts_at' => null,
            'ends_at' => null,
            'authorized_by' => $actor_id,
            'row_version' => 1,
            'created_at' => CF01_DB::now(),
            'updated_at' => CF01_DB::now(),
        ));
        CF01_Audit::record($actor_id, 'CareRelationshipProposed', 'care_relationship', $relationship_uuid, $purpose, array());
        return self::get($relationship_uuid);
    }

    public static function activate(int $actor_id, string $relationship_uuid, int $expected_version): array {
        $row = self::get($relationship_uuid);
        CF01_Authorization::actor($actor_id, 'activate_relationship');
        CF01_Authorization::expected_version($row, $expected_version);
        CF01_Authorization::clinician((int) $row['doctor_user_id'], 'activate_relationship');
        CF01_Authorization::consent((string) $row['patient_uuid'], (string) $row['purpose']);
        if (($row['status'] ?? '') === 'proposed') {
            $pending = CF01_DB::update_versioned('relationships', array('status' => 'identity_consent_pending'), array('relationship_uuid' => $relationship_uuid), $expected_version);
            if (!$pending) {
                throw new RuntimeException('Relationship changed concurrently.');
            }
            $row = self::get($relationship_uuid);
            $expected_version = (int) $row['row_version'];
        }
        self::transition_allowed((string) $row['status'], 'active');
        $ok = CF01_DB::update_versioned('relationships', array(
            'status' => 'active',
            'starts_at' => CF01_DB::now(),
            'authorized_by' => $actor_id,
        ), array('relationship_uuid' => $relationship_uuid), $expected_version);
        if (!$ok) {
            throw new RuntimeException('Relationship changed concurrently.');
        }
        CF01_Audit::record($actor_id, 'CareRelationshipActivated', 'care_relationship', $relationship_uuid, (string) $row['purpose'], array());
        CF01_Outbox::enqueue('CareRelationshipActivated', array('relationship_uuid' => $relationship_uuid, 'patient_uuid' => $row['patient_uuid']), $relationship_uuid);
        return self::get($relationship_uuid);
    }

    public static function transition(int $actor_id, string $relationship_uuid, string $next, string $reason, int $expected_version): array {
        $row = self::get($relationship_uuid);
        CF01_Authorization::expected_version($row, $expected_version);
        self::transition_allowed((string) $row['status'], $next);
        if ($reason === '') {
            throw new InvalidArgumentException('A relationship transition reason is required.');
        }
        $data = array('status' => $next, 'reason_cipher' => CF01_Crypto::encrypt($reason, 'relationship-reason'));
        if ($next === 'ended') {
            $data['ends_at'] = CF01_DB::now();
        }
        $ok = CF01_DB::update_versioned('relationships', $data, array('relationship_uuid' => $relationship_uuid), $expected_version);
        if (!$ok) {
            throw new RuntimeException('Relationship changed concurrently.');
        }
        $event = $next === 'ended' ? 'CareRelationshipEnded' : 'CareRelationshipStatusChanged';
        CF01_Audit::record($actor_id, $event, 'care_relationship', $relationship_uuid, (string) $row['purpose'], array('status' => $next));
        CF01_Outbox::enqueue($event, array('relationship_uuid' => $relationship_uuid, 'status' => $next), $relationship_uuid);
        return self::get($relationship_uuid);
    }

    public static function get(string $uuid): array {
        $row = CF01_DB::row('SELECT * FROM ' . CF01_DB::table('relationships') . ' WHERE relationship_uuid = %s LIMIT 1', array($uuid));
        if (!$row) {
            throw new RuntimeException('Care relationship is unavailable.');
        }
        return $row;
    }

    public static function transition_allowed(string $current, string $next): void {
        if (!isset(self::TRANSITIONS[$current]) || !in_array($next, self::TRANSITIONS[$current], true)) {
            throw new RuntimeException('Invalid care relationship transition.');
        }
    }

    public static function transition_map(): array {
        return self::TRANSITIONS;
    }
}
