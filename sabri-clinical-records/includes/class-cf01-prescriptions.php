<?php
defined('ABSPATH') || exit;

final class CF01_Prescriptions {
    private const STATES = array(
        'draft' => array('ready_to_sign', 'entered_in_error'),
        'ready_to_sign' => array('signed', 'draft', 'entered_in_error'),
        'signed' => array('superseded', 'discontinued', 'expired', 'entered_in_error'),
        'superseded' => array(),
        'discontinued' => array(),
        'expired' => array(),
        'entered_in_error' => array(),
    );

    public static function create(int $actor_id, string $patient_uuid, string $encounter_uuid, array $data): array {
        CF01_Authorization::clinician($actor_id, 'create_prescription');
        CF01_Authorization::relationship($patient_uuid, $actor_id, 'clinical_care');
        CF01_Authorization::consent($patient_uuid, 'clinical_care');
        $encounter = CF01_Encounters::get($encounter_uuid);
        if ((string) $encounter['patient_uuid'] !== $patient_uuid) {
            throw new RuntimeException('Prescription and encounter patient do not match.');
        }
        if (!in_array((string) $encounter['status'], array('signed', 'addended'), true)) {
            throw new RuntimeException('A signed encounter is required before prescription creation.');
        }
        $normalized = self::normalize($data);
        $uuid = CF01_DB::uuid();
        CF01_DB::insert('prescriptions', array(
            'prescription_uuid' => $uuid,
            'patient_uuid' => $patient_uuid,
            'encounter_uuid' => $encounter_uuid,
            'parent_prescription_uuid' => null,
            'status' => 'draft',
            'order_cipher' => CF01_Crypto::encrypt($normalized, 'prescription-order'),
            'order_hash' => hash('sha256', CF01_Crypto::canonical_json($normalized)),
            'author_user_id' => $actor_id,
            'signed_by_user_id' => null,
            'signed_at' => null,
            'signature' => null,
            'snapshot_cipher' => null,
            'effective_from' => self::utc($data['effective_from'] ?? 'now'),
            'effective_until' => self::nullable_utc($data['effective_until'] ?? null),
            'superseded_by_uuid' => null,
            'discontinuation_reason_cipher' => null,
            'row_version' => 1,
            'created_at' => CF01_DB::now(),
            'updated_at' => CF01_DB::now(),
        ));
        CF01_Audit::record($actor_id, 'PrescriptionDraftCreated', 'prescription', $uuid, 'clinical_care', array('encounter_uuid' => $encounter_uuid));
        return self::get($uuid);
    }

    public static function update(int $actor_id, string $uuid, array $data, string $next_status, int $expected_version): array {
        $row = self::get($uuid);
        CF01_Authorization::clinician($actor_id, 'update_prescription');
        CF01_Authorization::relationship((string) $row['patient_uuid'], $actor_id, 'clinical_care');
        CF01_Authorization::expected_version($row, $expected_version);
        self::transition_allowed((string) $row['status'], $next_status);
        if (!in_array($next_status, array('draft', 'ready_to_sign'), true)) {
            throw new RuntimeException('Prescription update may only remain draft or become ready to sign.');
        }
        $normalized = self::normalize($data);
        $ok = CF01_DB::update_versioned('prescriptions', array(
            'status' => $next_status,
            'order_cipher' => CF01_Crypto::encrypt($normalized, 'prescription-order'),
            'order_hash' => hash('sha256', CF01_Crypto::canonical_json($normalized)),
            'effective_from' => self::utc($data['effective_from'] ?? (string) $row['effective_from']),
            'effective_until' => self::nullable_utc($data['effective_until'] ?? $row['effective_until']),
        ), array('prescription_uuid' => $uuid), $expected_version);
        if (!$ok) {
            throw new RuntimeException('Prescription changed concurrently.');
        }
        CF01_Audit::record($actor_id, 'PrescriptionDraftUpdated', 'prescription', $uuid, 'clinical_care', array('status' => $next_status));
        return self::get($uuid);
    }

    public static function sign(int $actor_id, string $uuid, int $expected_version): array {
        $row = self::get($uuid);
        $context = CF01_Authorization::clinician($actor_id, 'sign_prescription');
        CF01_Authorization::relationship((string) $row['patient_uuid'], $actor_id, 'clinical_care');
        CF01_Authorization::consent((string) $row['patient_uuid'], 'clinical_care');
        CF01_Authorization::expected_version($row, $expected_version);
        self::transition_allowed((string) $row['status'], 'signed');
        $order = self::order($row);
        self::validate_signable($order);
        $safety = CF01_Contracts::prescription_safety((string) $row['patient_uuid'], $actor_id, $order);
        if (empty($safety['valid']) || empty($safety['passed']) || !empty($safety['blocking'])) {
            throw new RuntimeException('Prescription safety review did not pass.');
        }
        $snapshot = array(
            'prescription_uuid' => $uuid,
            'patient_uuid' => (string) $row['patient_uuid'],
            'encounter_uuid' => (string) $row['encounter_uuid'],
            'signer_user_id' => $actor_id,
            'professional_uuid' => (string) $context['professional']['professional_uuid'],
            'order_hash' => (string) $row['order_hash'],
            'row_version' => $expected_version,
            'signed_at' => CF01_DB::now(),
            'contract_version' => CF01_CONTRACT_VERSION,
            'safety_evidence_reference' => (string) $safety['evidence_reference'],
            'safety_warnings_hash' => hash('sha256', CF01_Crypto::canonical_json((array) $safety['warnings'])),
        );
        $ok = CF01_DB::update_versioned('prescriptions', array(
            'status' => 'signed',
            'signed_by_user_id' => $actor_id,
            'signed_at' => $snapshot['signed_at'],
            'signature' => CF01_Crypto::sign($snapshot, 'prescription-signature'),
            'snapshot_cipher' => CF01_Crypto::encrypt($snapshot, 'prescription-snapshot'),
        ), array('prescription_uuid' => $uuid), $expected_version);
        if (!$ok) {
            throw new RuntimeException('Prescription signature was rejected because the record changed.');
        }
        CF01_Audit::record($actor_id, 'PrescriptionSigned', 'prescription', $uuid, 'clinical_care', array('order_hash' => $row['order_hash']));
        CF01_Outbox::enqueue('PrescriptionSigned', array('prescription_uuid' => $uuid, 'patient_uuid' => $row['patient_uuid']), $uuid);
        return self::get($uuid);
    }

    public static function supersede(int $actor_id, string $old_uuid, array $replacement_data, int $expected_version): array {
        $old = self::get($old_uuid);
        CF01_Authorization::clinician($actor_id, 'sign_prescription');
        CF01_Authorization::expected_version($old, $expected_version);
        if (($old['status'] ?? '') !== 'signed') {
            throw new RuntimeException('Only a signed prescription can be superseded.');
        }
        $new = self::create($actor_id, (string) $old['patient_uuid'], (string) $old['encounter_uuid'], $replacement_data);
        $new = self::update($actor_id, (string) $new['prescription_uuid'], $replacement_data, 'ready_to_sign', 1);
        $new = self::sign($actor_id, (string) $new['prescription_uuid'], (int) $new['row_version']);
        $ok = CF01_DB::update_versioned('prescriptions', array(
            'status' => 'superseded',
            'superseded_by_uuid' => (string) $new['prescription_uuid'],
        ), array('prescription_uuid' => $old_uuid), $expected_version);
        if (!$ok) {
            $replacement = self::get((string) $new['prescription_uuid']);
            CF01_DB::update_versioned('prescriptions', array(
                'status' => 'entered_in_error',
                'discontinuation_reason_cipher' => CF01_Crypto::encrypt('Supersession compensation: original prescription changed concurrently.', 'prescription-discontinuation'),
                'effective_until' => CF01_DB::now(),
            ), array('prescription_uuid' => (string) $new['prescription_uuid']), (int) $replacement['row_version']);
            CF01_Audit::record($actor_id, 'PrescriptionSupersessionCompensated', 'prescription', (string) $new['prescription_uuid'], 'clinical_integrity', array('original_uuid' => $old_uuid));
            throw new RuntimeException('Original prescription changed concurrently; replacement was invalidated.');
        }
        CF01_Audit::record($actor_id, 'PrescriptionSuperseded', 'prescription', $old_uuid, 'clinical_care', array('replacement_uuid' => $new['prescription_uuid']));
        CF01_Outbox::enqueue('PrescriptionSuperseded', array('prescription_uuid' => $old_uuid, 'replacement_uuid' => $new['prescription_uuid']), $old_uuid);
        return $new;
    }

    public static function discontinue(int $actor_id, string $uuid, string $reason, int $expected_version): array {
        $row = self::get($uuid);
        CF01_Authorization::clinician($actor_id, 'sign_prescription');
        CF01_Authorization::relationship((string) $row['patient_uuid'], $actor_id, 'clinical_care');
        CF01_Authorization::expected_version($row, $expected_version);
        if (($row['status'] ?? '') !== 'signed') {
            throw new RuntimeException('Only an active signed prescription can be discontinued.');
        }
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A discontinuation reason is required.');
        }
        $ok = CF01_DB::update_versioned('prescriptions', array(
            'status' => 'discontinued',
            'discontinuation_reason_cipher' => CF01_Crypto::encrypt($reason, 'prescription-discontinuation'),
            'effective_until' => CF01_DB::now(),
        ), array('prescription_uuid' => $uuid), $expected_version);
        if (!$ok) {
            throw new RuntimeException('Prescription changed concurrently.');
        }
        CF01_Audit::record($actor_id, 'PrescriptionDiscontinued', 'prescription', $uuid, 'clinical_care', array());
        CF01_Outbox::enqueue('PrescriptionDiscontinued', array('prescription_uuid' => $uuid, 'patient_uuid' => $row['patient_uuid']), $uuid);
        return self::get($uuid);
    }

    public static function get(string $uuid): array {
        $row = CF01_DB::row('SELECT * FROM ' . CF01_DB::table('prescriptions') . ' WHERE prescription_uuid = %s LIMIT 1', array($uuid));
        if (!$row) {
            throw new RuntimeException('Prescription is unavailable.');
        }
        return $row;
    }

    public static function order(array $row): array {
        $value = CF01_Crypto::decrypt((string) $row['order_cipher'], 'prescription-order');
        return is_array($value) ? $value : array();
    }

    public static function state_map(): array {
        return self::STATES;
    }

    private static function transition_allowed(string $current, string $next): void {
        if (!isset(self::STATES[$current]) || !in_array($next, self::STATES[$current], true)) {
            throw new RuntimeException('Invalid prescription transition.');
        }
    }

    private static function normalize(array $data): array {
        if (!empty($data['autonomous_ai']) || !empty($data['radar_autonomous']) || !empty($data['autonomous_radar'])) {
            throw new RuntimeException('Autonomous AI or Radar prescription is prohibited.');
        }
        $required = array('remedy', 'potency', 'form', 'dose', 'frequency', 'duration', 'repetition', 'instructions', 'rationale');
        foreach ($required as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                throw new InvalidArgumentException('Prescription field is required: ' . $field);
            }
        }
        $allowed = array_merge($required, array('warnings', 'language', 'links', 'safety_status', 'review_due_at'));
        $normalized = array();
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $normalized[$field] = self::sanitize($data[$field]);
            }
        }
        $normalized['clinician_entered'] = true;
        $normalized['autonomous_ai'] = false;
        $normalized['autonomous_radar'] = false;
        $normalized['emergency_replacement'] = false;
        if (empty($normalized['warnings'])) {
            $normalized['warnings'] = array('Follow the clinician-entered instructions; seek local emergency care for urgent symptoms.');
        }
        return $normalized;
    }

    private static function validate_signable(array $order): void {
        foreach (array('remedy', 'potency', 'dose', 'frequency', 'duration', 'instructions', 'rationale') as $field) {
            if (empty($order[$field])) {
                throw new RuntimeException('Prescription is incomplete and cannot be signed.');
            }
        }
        if (!empty($order['autonomous_ai']) || !empty($order['autonomous_radar'])) {
            throw new RuntimeException('Autonomous AI or Radar prescription is prohibited.');
        }
    }

    private static function sanitize($value) {
        if (is_array($value)) {
            return array_map(array(__CLASS__, 'sanitize'), $value);
        }
        if (is_bool($value) || is_numeric($value) || $value === null) {
            return $value;
        }
        return sanitize_textarea_field((string) $value);
    }

    private static function utc($value): string {
        $timestamp = $value === 'now' ? time() : strtotime((string) $value);
        if (!is_int($timestamp)) {
            throw new InvalidArgumentException('Invalid prescription time.');
        }
        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    private static function nullable_utc($value): ?string {
        return ($value === null || $value === '') ? null : self::utc($value);
    }
}
