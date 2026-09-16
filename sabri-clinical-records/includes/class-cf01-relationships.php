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
        if ($doctor_user_id <= 0) {
            throw new InvalidArgumentException('A treating doctor is required.');
        }
        $purpose = sanitize_key((string) ($data['purpose'] ?? 'clinical_care'));
        if (!in_array($purpose, array('clinical_care', 'teleconsultation'), true)) {
            throw new InvalidArgumentException('Unsupported treating relationship purpose.');
        }
        self::require_target_practitioner($doctor_user_id, 'propose_relationship', $purpose);
        $scope = array_values(array_unique(array_filter(array_map('sanitize_key', (array) ($data['scope'] ?? array('care'))))));
        if (!$scope) {
            throw new InvalidArgumentException('Relationship scope is required.');
        }
        $source_reference = sanitize_text_field((string) ($data['source_reference'] ?? ''));
        $source = CF01_Contracts::relationship_source($source_reference, $actor_id, $patient_uuid, $doctor_user_id, $purpose, $scope);
        if (empty($source['valid'])) {
            throw new RuntimeException('A current native-owner relationship source assertion is required.');
        }
        $source_reference = sanitize_text_field((string) $source['source_reference']);
        $relationship_uuid = CF01_DB::uuid();
        CF01_DB::transaction(function () use ($relationship_uuid, $patient_uuid, $doctor_user_id, $data, $source_reference, $purpose, $scope, $actor_id): void {
            CF01_DB::insert('relationships', array(
                'relationship_uuid' => $relationship_uuid,
                'patient_uuid' => $patient_uuid,
                'doctor_user_id' => $doctor_user_id,
                'clinic_reference' => sanitize_text_field((string) ($data['clinic_reference'] ?? '')),
                'source_reference' => $source_reference,
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
        });
        return self::get($relationship_uuid);
    }

    public static function activate(int $actor_id, string $relationship_uuid, int $expected_version): array {
        $row = self::get($relationship_uuid);
        self::authorize_relationship_actor($actor_id, $row, 'activate_relationship');
        CF01_Authorization::expected_version($row, $expected_version);
        self::require_target_practitioner((int) $row['doctor_user_id'], 'activate_relationship', (string) $row['purpose']);
        self::require_current_source($actor_id, $row);
        CF01_Authorization::consent((string) $row['patient_uuid'], (string) $row['purpose']);

        CF01_DB::transaction(function () use ($actor_id, $relationship_uuid, $row, $expected_version): void {
            $current = $row;
            $version = $expected_version;
            if (($current['status'] ?? '') === 'proposed') {
                $pending = CF01_DB::update_versioned(
                    'relationships',
                    array('status' => 'identity_consent_pending', 'authorized_by' => $actor_id),
                    array('relationship_uuid' => $relationship_uuid),
                    $version
                );
                if (!$pending) {
                    throw new RuntimeException('Relationship changed concurrently.');
                }
                $current = self::get($relationship_uuid);
                $version = (int) $current['row_version'];
            }
            self::transition_allowed((string) $current['status'], 'active');
            $ok = CF01_DB::update_versioned('relationships', array(
                'status' => 'active',
                'starts_at' => CF01_DB::now(),
                'ends_at' => null,
                'authorized_by' => $actor_id,
            ), array('relationship_uuid' => $relationship_uuid), $version);
            if (!$ok) {
                throw new RuntimeException('Relationship changed concurrently.');
            }
            CF01_Audit::record($actor_id, 'CareRelationshipActivated', 'care_relationship', $relationship_uuid, (string) $current['purpose'], array());
            CF01_Outbox::enqueue('CareRelationshipActivated', array('relationship_uuid' => $relationship_uuid, 'patient_uuid' => $current['patient_uuid']), $relationship_uuid);
        });
        return self::get($relationship_uuid);
    }

    public static function transition(int $actor_id, string $relationship_uuid, string $next, string $reason, int $expected_version): array {
        $row = self::get($relationship_uuid);
        self::authorize_relationship_actor($actor_id, $row, 'transition_relationship');
        CF01_Authorization::expected_version($row, $expected_version);
        $next = sanitize_key($next);
        self::transition_allowed((string) $row['status'], $next);
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A relationship transition reason is required.');
        }
        if ($next === 'active') {
            self::require_target_practitioner((int) $row['doctor_user_id'], 'reactivate_relationship', (string) $row['purpose']);
            self::require_current_source($actor_id, $row);
            CF01_Authorization::consent((string) $row['patient_uuid'], (string) $row['purpose']);
        }

        if ($next === 'ended') {
            return CF01_DB::transaction(function () use ($actor_id, $relationship_uuid, $reason, $expected_version): array {
                $current = self::get_for_update($relationship_uuid);
                CF01_Authorization::expected_version($current, $expected_version);
                self::transition_allowed((string) $current['status'], 'ended');
                self::require_termination_reconciliation($actor_id, $current, $reason);
                $ok = CF01_DB::update_versioned('relationships', array(
                    'status' => 'ended',
                    'reason_cipher' => CF01_Crypto::encrypt($reason, 'relationship-reason'),
                    'authorized_by' => $actor_id,
                    'ends_at' => CF01_DB::now(),
                ), array('relationship_uuid' => $relationship_uuid), $expected_version);
                if (!$ok) {
                    throw new RuntimeException('Relationship changed concurrently.');
                }
                CF01_Audit::record($actor_id, 'CareRelationshipEnded', 'care_relationship', $relationship_uuid, (string) $current['purpose'], array('status' => 'ended'));
                CF01_Outbox::enqueue('CareRelationshipEnded', array(
                    'relationship_uuid' => $relationship_uuid,
                    'patient_uuid' => (string) $current['patient_uuid'],
                    'status' => 'ended',
                ), $relationship_uuid);
                return self::get($relationship_uuid);
            });
        }

        return CF01_DB::transaction(function () use ($actor_id, $relationship_uuid, $row, $next, $reason, $expected_version): array {
            $current = self::get_for_update($relationship_uuid);
            CF01_Authorization::expected_version($current, $expected_version);
            self::transition_allowed((string) $current['status'], $next);
            $data = array(
                'status' => $next,
                'reason_cipher' => CF01_Crypto::encrypt($reason, 'relationship-reason'),
                'authorized_by' => $actor_id,
            );
            if ($next === 'active') {
                $data['starts_at'] = CF01_DB::now();
                $data['ends_at'] = null;
            }
            $ok = CF01_DB::update_versioned('relationships', $data, array('relationship_uuid' => $relationship_uuid), $expected_version);
            if (!$ok) {
                throw new RuntimeException('Relationship changed concurrently.');
            }
            CF01_Audit::record($actor_id, 'CareRelationshipStatusChanged', 'care_relationship', $relationship_uuid, (string) $row['purpose'], array('status' => $next));
            CF01_Outbox::enqueue('CareRelationshipStatusChanged', array(
                'relationship_uuid' => $relationship_uuid,
                'patient_uuid' => (string) $row['patient_uuid'],
                'status' => $next,
            ), $relationship_uuid);
            return self::get($relationship_uuid);
        });
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

    private static function get_for_update(string $uuid): array {
        $row = CF01_DB::row(
            'SELECT * FROM ' . CF01_DB::table('relationships') . ' WHERE relationship_uuid = %s LIMIT 1 FOR UPDATE',
            array($uuid)
        );
        if (!$row) {
            throw new RuntimeException('Care relationship is unavailable.');
        }
        return $row;
    }

    private static function termination_open_items(array $relationship): array {
        $encounters = CF01_DB::table('encounters');
        $prescriptions = CF01_DB::table('prescriptions');
        $followups = CF01_DB::table('followups');
        $relationship_uuid = (string) $relationship['relationship_uuid'];

        $open_encounters = CF01_DB::rows(
            'SELECT encounter_uuid, status FROM ' . $encounters . ' WHERE relationship_uuid = %s AND status IN (%s,%s,%s) LIMIT 101',
            array($relationship_uuid, 'draft', 'in_progress', 'ready_to_sign')
        );
        $open_prescriptions = CF01_DB::rows(
            'SELECT p.prescription_uuid, p.status FROM ' . $prescriptions . ' p INNER JOIN ' . $encounters . ' e ON e.encounter_uuid = p.encounter_uuid WHERE e.relationship_uuid = %s AND p.status IN (%s,%s,%s) LIMIT 101',
            array($relationship_uuid, 'draft', 'ready_to_sign', 'signed')
        );
        $open_followups = CF01_DB::rows(
            'SELECT f.followup_uuid, f.status FROM ' . $followups . ' f INNER JOIN ' . $prescriptions . ' p ON p.prescription_uuid = f.prescription_uuid INNER JOIN ' . $encounters . ' e ON e.encounter_uuid = p.encounter_uuid WHERE e.relationship_uuid = %s AND f.status NOT IN (%s,%s) LIMIT 101',
            array($relationship_uuid, 'closed', 'cancelled')
        );

        if (count($open_encounters) > 100 || count($open_prescriptions) > 100 || count($open_followups) > 100) {
            throw new RuntimeException('Relationship termination reconciliation exceeds the bounded synchronous workload; use the approved reconciliation job.');
        }
        return array(
            'encounters' => $open_encounters,
            'prescriptions' => $open_prescriptions,
            'followups' => $open_followups,
        );
    }

    private static function require_termination_reconciliation(int $actor_id, array $relationship, string $reason): void {
        $open = self::termination_open_items($relationship);
        if (!$open['encounters'] && !$open['prescriptions'] && !$open['followups']) {
            return;
        }
        $request = array(
            'contract_version' => CF01_CONTRACT_VERSION,
            'relationship_uuid' => (string) $relationship['relationship_uuid'],
            'patient_uuid' => (string) $relationship['patient_uuid'],
            'doctor_user_id' => (int) $relationship['doctor_user_id'],
            'reason_hash' => hash('sha256', trim($reason)),
            'open_encounters' => array_column($open['encounters'], 'encounter_uuid'),
            'open_prescriptions' => array_column($open['prescriptions'], 'prescription_uuid'),
            'open_followups' => array_column($open['followups'], 'followup_uuid'),
            'future_access_revoked_on_commit' => true,
        );
        $result = apply_filters('cf01_relationship_termination_reconciliation', null, $request, $actor_id);
        if (!is_array($result)
            || !hash_equals(CF01_CONTRACT_VERSION, (string) ($result['contract_version'] ?? ''))
            || empty($result['accepted'])
            || empty($result['reconciled'])
            || empty($result['continuity_instructions_recorded'])
            || empty($result['open_items_resolved'])
        ) {
            throw new RuntimeException('Open clinical work must be reconciled before relationship termination.');
        }
        $remaining = self::termination_open_items($relationship);
        if ($remaining['encounters'] || $remaining['prescriptions'] || $remaining['followups']) {
            throw new RuntimeException('Relationship termination reconciliation did not resolve all local clinical work.');
        }
    }

    private static function authorize_relationship_actor(int $actor_id, array $relationship, string $action): void {
        CF01_Authorization::actor($actor_id, $action, array(
            'patient_uuid' => (string) $relationship['patient_uuid'],
            'relationship_uuid' => (string) $relationship['relationship_uuid'],
        ));
        $manager = function_exists('user_can')
            ? user_can($actor_id, 'cf01_manage_clinical_records')
            : current_user_can('cf01_manage_clinical_records');
        if ((int) $relationship['doctor_user_id'] !== $actor_id && !$manager) {
            throw new RuntimeException('Only the assigned treating doctor or an authorized records operator may change this relationship.');
        }
    }

    private static function require_target_practitioner(int $doctor_user_id, string $action, string $purpose): array {
        $professional = CF01_Contracts::practitioner($doctor_user_id, $action);
        if (empty($professional['valid']) || empty($professional['eligible']) || empty($professional['verified']) || !empty($professional['suspended'])) {
            throw new RuntimeException('Current professional eligibility is required for the assigned doctor.');
        }
        if (!CF01_Authorization::not_expired((string) ($professional['expires_at'] ?? ''))) {
            throw new RuntimeException('Assigned doctor professional eligibility has expired.');
        }
        $scopes = array_map('sanitize_key', (array) ($professional['scopes'] ?? array()));
        if ($scopes && !in_array($purpose, $scopes, true) && !in_array('clinical_care', $scopes, true)) {
            throw new RuntimeException('Assigned doctor professional scope does not authorize this purpose.');
        }
        return $professional;
    }

    private static function require_current_source(int $actor_id, array $relationship): void {
        $scope = json_decode((string) ($relationship['scope_json'] ?? ''), true);
        if (!is_array($scope) || !$scope) {
            throw new RuntimeException('Treating relationship source scope is unavailable.');
        }
        $source = CF01_Contracts::relationship_source(
            (string) ($relationship['source_reference'] ?? ''),
            $actor_id,
            (string) $relationship['patient_uuid'],
            (int) $relationship['doctor_user_id'],
            (string) $relationship['purpose'],
            $scope
        );
        if (empty($source['valid'])) {
            throw new RuntimeException('Current native-owner relationship source assertion is required before access may be activated.');
        }
    }
}
