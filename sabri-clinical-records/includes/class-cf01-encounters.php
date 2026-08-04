<?php
defined('ABSPATH') || exit;

final class CF01_Encounters {
    private const STATES = array(
        'draft' => array('in_progress', 'entered_in_error'),
        'in_progress' => array('ready_to_sign', 'draft', 'entered_in_error'),
        'ready_to_sign' => array('in_progress', 'signed', 'entered_in_error'),
        'signed' => array('addended', 'entered_in_error'),
        'addended' => array('addended', 'entered_in_error'),
        'entered_in_error' => array(),
    );

    public static function create(int $actor_id, string $patient_uuid, array $data): array {
        CF01_Authorization::clinician($actor_id, 'create_encounter');
        $relationship = CF01_Authorization::relationship($patient_uuid, $actor_id, 'clinical_care');
        CF01_Authorization::consent($patient_uuid, 'clinical_care');
        self::validate_context($data);
        if (!empty($data['relationship_uuid']) && !hash_equals((string) $relationship['relationship_uuid'], (string) $data['relationship_uuid'])) {
            throw new RuntimeException('Encounter relationship reference does not match current treating authority.');
        }
        $data['relationship_uuid'] = (string) $relationship['relationship_uuid'];
        $uuid = CF01_DB::uuid();
        $content = self::normalize_content($data['content'] ?? array());
        CF01_DB::transaction(function () use ($actor_id, $patient_uuid, $data, $content, $uuid): void {
            CF01_DB::insert('encounters', array(
                'encounter_uuid' => $uuid,
                'patient_uuid' => $patient_uuid,
                'relationship_uuid' => sanitize_text_field((string) ($data['relationship_uuid'] ?? '')),
                'parent_encounter_uuid' => null,
                'encounter_type' => sanitize_key((string) ($data['encounter_type'] ?? 'consultation')),
                'mode' => sanitize_key((string) ($data['mode'] ?? 'in_person')),
                'location_reference' => sanitize_text_field((string) ($data['location_reference'] ?? '')),
                'starts_at' => self::utc((string) ($data['starts_at'] ?? 'now')),
                'ends_at' => null,
                'status' => 'draft',
                'content_cipher' => CF01_Crypto::encrypt($content, 'encounter-content'),
                'content_hash' => hash('sha256', CF01_Crypto::canonical_json($content)),
                'template_key' => sanitize_key((string) ($data['template_key'] ?? 'general')),
                'template_version' => sanitize_text_field((string) ($data['template_version'] ?? '1.0.0')),
                'author_user_id' => $actor_id,
                'signed_by_user_id' => null,
                'signed_at' => null,
                'signature' => null,
                'row_version' => 1,
                'created_at' => CF01_DB::now(),
                'updated_at' => CF01_DB::now(),
            ));
            CF01_Audit::record($actor_id, 'EncounterCreated', 'encounter', $uuid, 'clinical_care', array('status' => 'draft'));
        });
        return self::get($uuid);
    }

    public static function update_draft(int $actor_id, string $uuid, array $content, string $next_status, int $expected_version): array {
        $row = self::get($uuid);
        CF01_Authorization::clinician($actor_id, 'update_encounter');
        CF01_Authorization::relationship((string) $row['patient_uuid'], $actor_id, 'clinical_care');
        CF01_Authorization::expected_version($row, $expected_version);
        if (in_array((string) $row['status'], array('signed', 'addended', 'entered_in_error'), true)) {
            throw new RuntimeException('Signed or tombstoned encounter content is immutable.');
        }
        self::transition_allowed((string) $row['status'], $next_status);
        $normalized = self::normalize_content($content);
        $ok = CF01_DB::update_versioned('encounters', array(
            'status' => $next_status,
            'content_cipher' => CF01_Crypto::encrypt($normalized, 'encounter-content'),
            'content_hash' => hash('sha256', CF01_Crypto::canonical_json($normalized)),
        ), array('encounter_uuid' => $uuid), $expected_version);
        if (!$ok) {
            throw new RuntimeException('Encounter changed concurrently.');
        }
        CF01_Audit::record($actor_id, 'EncounterDraftUpdated', 'encounter', $uuid, 'clinical_care', array('status' => $next_status));
        return self::get($uuid);
    }

    public static function sign(int $actor_id, string $uuid, int $expected_version): array {
        $row = self::get($uuid);
        $context = CF01_Authorization::clinician($actor_id, 'sign_encounter');
        CF01_Authorization::relationship((string) $row['patient_uuid'], $actor_id, 'clinical_care');
        CF01_Authorization::consent((string) $row['patient_uuid'], 'clinical_care');
        CF01_Authorization::expected_version($row, $expected_version);
        self::transition_allowed((string) $row['status'], 'signed');
        $content = self::content($row);
        self::validate_signable($content);
        $snapshot = array(
            'encounter_uuid' => $uuid,
            'patient_uuid' => (string) $row['patient_uuid'],
            'author_user_id' => (int) $row['author_user_id'],
            'signer_user_id' => $actor_id,
            'professional_uuid' => (string) $context['professional']['professional_uuid'],
            'content_hash' => (string) $row['content_hash'],
            'row_version' => $expected_version,
            'signed_at' => CF01_DB::now(),
            'contract_version' => CF01_CONTRACT_VERSION,
        );
        $signature = CF01_Crypto::sign($snapshot, 'encounter-signature');
        $ok = CF01_DB::update_versioned('encounters', array(
            'status' => 'signed',
            'signed_by_user_id' => $actor_id,
            'signed_at' => $snapshot['signed_at'],
            'signature' => $signature,
            'snapshot_cipher' => CF01_Crypto::encrypt($snapshot, 'encounter-snapshot'),
            'ends_at' => $snapshot['signed_at'],
        ), array('encounter_uuid' => $uuid), $expected_version);
        if (!$ok) {
            throw new RuntimeException('Encounter signature was rejected because the record changed.');
        }
        CF01_Audit::record($actor_id, 'EncounterSigned', 'encounter', $uuid, 'clinical_care', array('content_hash' => $row['content_hash']));
        CF01_Outbox::enqueue('EncounterSigned', array('encounter_uuid' => $uuid, 'patient_uuid' => $row['patient_uuid']), $uuid);
        return self::get($uuid);
    }

    public static function addendum(int $actor_id, string $parent_uuid, array $content): array {
        $parent = self::get($parent_uuid);
        CF01_Authorization::clinician($actor_id, 'add_encounter_addendum');
        CF01_Authorization::relationship((string) $parent['patient_uuid'], $actor_id, 'clinical_care');
        if (!in_array((string) $parent['status'], array('signed', 'addended'), true)) {
            throw new RuntimeException('Addenda require a signed parent encounter.');
        }
        $normalized = self::normalize_content($content);
        if (empty($normalized['narrative'])) {
            throw new InvalidArgumentException('Addendum narrative is required.');
        }
        $uuid = CF01_DB::uuid();
        $signed_at = CF01_DB::now();
        $snapshot = array(
            'encounter_uuid' => $uuid,
            'parent_encounter_uuid' => $parent_uuid,
            'patient_uuid' => $parent['patient_uuid'],
            'signer_user_id' => $actor_id,
            'content_hash' => hash('sha256', CF01_Crypto::canonical_json($normalized)),
            'signed_at' => $signed_at,
        );
        CF01_DB::transaction(function () use ($actor_id, $parent, $parent_uuid, $normalized, $uuid, $snapshot, $signed_at): void {
            CF01_DB::insert('encounters', array(
                'encounter_uuid' => $uuid,
                'patient_uuid' => $parent['patient_uuid'],
                'relationship_uuid' => $parent['relationship_uuid'],
                'parent_encounter_uuid' => $parent_uuid,
                'encounter_type' => 'addendum',
                'mode' => $parent['mode'],
                'location_reference' => $parent['location_reference'],
                'starts_at' => $signed_at,
                'ends_at' => $signed_at,
                'status' => 'signed',
                'content_cipher' => CF01_Crypto::encrypt($normalized, 'encounter-content'),
                'content_hash' => $snapshot['content_hash'],
                'template_key' => 'addendum',
                'template_version' => '1.0.0',
                'author_user_id' => $actor_id,
                'signed_by_user_id' => $actor_id,
                'signed_at' => $signed_at,
                'signature' => CF01_Crypto::sign($snapshot, 'encounter-signature'),
                'snapshot_cipher' => CF01_Crypto::encrypt($snapshot, 'encounter-snapshot'),
                'row_version' => 1,
                'created_at' => $signed_at,
                'updated_at' => $signed_at,
            ));
            CF01_Audit::record($actor_id, 'EncounterAddendumAdded', 'encounter', $uuid, 'clinical_care', array('parent' => $parent_uuid));
        });
        return self::get($uuid);
    }

    public static function mark_entered_in_error(int $actor_id, string $uuid, string $reason, int $expected_version): array {
        $row = self::get($uuid);
        CF01_Authorization::clinician($actor_id, 'mark_encounter_error');
        CF01_Authorization::expected_version($row, $expected_version);
        if (($row['status'] ?? '') === 'entered_in_error') {
            return $row;
        }
        if ($reason === '') {
            throw new InvalidArgumentException('Entered-in-error reason is required.');
        }
        $ok = CF01_DB::update_versioned('encounters', array(
            'status' => 'entered_in_error',
            'error_reason_cipher' => CF01_Crypto::encrypt($reason, 'encounter-error-reason'),
        ), array('encounter_uuid' => $uuid), $expected_version);
        if (!$ok) {
            throw new RuntimeException('Encounter changed concurrently.');
        }
        CF01_Audit::record($actor_id, 'EncounterMarkedInError', 'encounter', $uuid, 'clinical_integrity', array());
        CF01_Outbox::enqueue('EncounterMarkedInError', array('encounter_uuid' => $uuid, 'patient_uuid' => $row['patient_uuid']), $uuid);
        return self::get($uuid);
    }


    public static function add_observation(int $actor_id, string $encounter_uuid, string $type, $value, array $provenance): array {
        $encounter = self::get($encounter_uuid);
        CF01_Authorization::clinician($actor_id, 'add_observation');
        CF01_Authorization::relationship((string) $encounter['patient_uuid'], $actor_id, 'clinical_care');
        if (in_array((string) $encounter['status'], array('signed', 'addended', 'entered_in_error'), true)) {
            throw new RuntimeException('New observations require an open encounter or a separate addendum.');
        }
        if ($type === '' || empty($provenance['source']) || empty($provenance['observed_at'])) {
            throw new InvalidArgumentException('Observation type, source and observed time are required.');
        }
        $uuid = CF01_DB::uuid();
        CF01_DB::insert('observations', array(
            'observation_uuid' => $uuid,
            'patient_uuid' => $encounter['patient_uuid'],
            'encounter_uuid' => $encounter_uuid,
            'observation_type' => sanitize_key($type),
            'value_cipher' => CF01_Crypto::encrypt(self::sanitize_value($value), 'observation-value'),
            'provenance_cipher' => CF01_Crypto::encrypt(self::sanitize_value($provenance), 'observation-provenance'),
            'status' => 'active',
            'author_user_id' => $actor_id,
            'corrected_by_uuid' => null,
            'row_version' => 1,
            'created_at' => CF01_DB::now(),
            'updated_at' => CF01_DB::now(),
        ));
        CF01_Audit::record($actor_id, 'ClinicalObservationAdded', 'clinical_observation', $uuid, 'clinical_care', array('type' => $type));
        return self::observation($uuid);
    }

    public static function correct_observation(int $actor_id, string $observation_uuid, $value, string $reason, int $expected_version): array {
        $old = self::observation($observation_uuid);
        CF01_Authorization::clinician($actor_id, 'correct_observation');
        CF01_Authorization::relationship((string) $old['patient_uuid'], $actor_id, 'clinical_care');
        CF01_Authorization::expected_version($old, $expected_version);
        if (($old['status'] ?? '') !== 'active' || trim($reason) === '') {
            throw new RuntimeException('Active observation and correction reason are required.');
        }
        $replacement = self::add_observation($actor_id, (string) $old['encounter_uuid'], (string) $old['observation_type'], $value, array('source' => 'correction', 'observed_at' => CF01_DB::now(), 'reason' => $reason, 'corrects' => $observation_uuid));
        $ok = CF01_DB::update_versioned('observations', array('status' => 'corrected', 'corrected_by_uuid' => $replacement['observation_uuid']), array('observation_uuid' => $observation_uuid), $expected_version);
        if (!$ok) {
            throw new RuntimeException('Observation changed concurrently.');
        }
        CF01_Audit::record($actor_id, 'ClinicalObservationCorrected', 'clinical_observation', $observation_uuid, 'clinical_integrity', array('replacement_uuid' => $replacement['observation_uuid']));
        return $replacement;
    }

    public static function create_assessment(int $actor_id, string $encounter_uuid, array $assessment): array {
        $encounter = self::get($encounter_uuid);
        CF01_Authorization::clinician($actor_id, 'create_assessment');
        CF01_Authorization::relationship((string) $encounter['patient_uuid'], $actor_id, 'clinical_care');
        foreach (array('totality', 'temperament', 'miasmatic_assessment', 'clinical_reasoning') as $field) {
            if (empty($assessment[$field])) {
                throw new InvalidArgumentException('Assessment field is required: ' . $field);
            }
        }
        if (!empty($assessment['ai_generated']) || !empty($assessment['radar_autonomous'])) {
            throw new RuntimeException('Autonomous AI or Radar clinical assessment is prohibited.');
        }
        $normalized = self::sanitize_value($assessment);
        $normalized['clinician_entered'] = true;
        $uuid = CF01_DB::uuid();
        CF01_DB::insert('assessments', array(
            'assessment_uuid' => $uuid,
            'patient_uuid' => $encounter['patient_uuid'],
            'encounter_uuid' => $encounter_uuid,
            'assessment_cipher' => CF01_Crypto::encrypt($normalized, 'clinical-assessment'),
            'assessment_hash' => hash('sha256', CF01_Crypto::canonical_json($normalized)),
            'author_user_id' => $actor_id,
            'status' => 'draft',
            'signed_at' => null,
            'signature' => null,
            'row_version' => 1,
            'created_at' => CF01_DB::now(),
            'updated_at' => CF01_DB::now(),
        ));
        CF01_Audit::record($actor_id, 'ClinicalAssessmentCreated', 'clinical_assessment', $uuid, 'clinical_care', array('encounter_uuid' => $encounter_uuid));
        return self::assessment($uuid);
    }

    public static function sign_assessment(int $actor_id, string $assessment_uuid, int $expected_version): array {
        $row = self::assessment($assessment_uuid);
        $context = CF01_Authorization::clinician($actor_id, 'sign_encounter');
        CF01_Authorization::relationship((string) $row['patient_uuid'], $actor_id, 'clinical_care');
        CF01_Authorization::expected_version($row, $expected_version);
        if (($row['status'] ?? '') !== 'draft') {
            throw new RuntimeException('Only draft assessment can be signed.');
        }
        $snapshot = array('assessment_uuid' => $assessment_uuid, 'patient_uuid' => $row['patient_uuid'], 'encounter_uuid' => $row['encounter_uuid'], 'assessment_hash' => $row['assessment_hash'], 'signer_user_id' => $actor_id, 'professional_uuid' => $context['professional']['professional_uuid'], 'row_version' => $expected_version, 'signed_at' => CF01_DB::now());
        $ok = CF01_DB::update_versioned('assessments', array('status' => 'signed', 'signed_at' => $snapshot['signed_at'], 'signature' => CF01_Crypto::sign($snapshot, 'assessment-signature')), array('assessment_uuid' => $assessment_uuid), $expected_version);
        if (!$ok) {
            throw new RuntimeException('Assessment changed concurrently.');
        }
        CF01_Audit::record($actor_id, 'ClinicalAssessmentSigned', 'clinical_assessment', $assessment_uuid, 'clinical_care', array());
        return self::assessment($assessment_uuid);
    }

    public static function observation(string $uuid): array {
        $row = CF01_DB::row('SELECT * FROM ' . CF01_DB::table('observations') . ' WHERE observation_uuid = %s LIMIT 1', array($uuid));
        if (!$row) {
            throw new RuntimeException('Clinical observation is unavailable.');
        }
        return $row;
    }

    public static function assessment(string $uuid): array {
        $row = CF01_DB::row('SELECT * FROM ' . CF01_DB::table('assessments') . ' WHERE assessment_uuid = %s LIMIT 1', array($uuid));
        if (!$row) {
            throw new RuntimeException('Clinical assessment is unavailable.');
        }
        return $row;
    }

    public static function get(string $uuid): array {
        $row = CF01_DB::row('SELECT * FROM ' . CF01_DB::table('encounters') . ' WHERE encounter_uuid = %s LIMIT 1', array($uuid));
        if (!$row) {
            throw new RuntimeException('Encounter is unavailable.');
        }
        return $row;
    }

    public static function content(array $row): array {
        $content = CF01_Crypto::decrypt((string) $row['content_cipher'], 'encounter-content');
        return is_array($content) ? $content : array();
    }

    public static function transition_allowed(string $current, string $next): void {
        if (!isset(self::STATES[$current]) || !in_array($next, self::STATES[$current], true)) {
            throw new RuntimeException('Invalid encounter transition.');
        }
    }

    public static function state_map(): array {
        return self::STATES;
    }

    private static function normalize_content(array $content): array {
        $keys = array(
            'chief_complaints', 'onset', 'causes', 'modalities', 'concomitants', 'history',
            'family_history', 'personal_history', 'lifestyle', 'objective_findings', 'narrative',
            'totality', 'mental_generals', 'physical_generals', 'particulars', 'temperament',
            'miasmatic_assessment', 'assessment', 'red_flags', 'provenance',
            'clinical_codes', 'interoperability_mapping'
        );
        $normalized = array();
        foreach ($keys as $key) {
            if (array_key_exists($key, $content)) {
                $normalized[$key] = self::sanitize_value($content[$key]);
            }
        }
        $normalized['incomplete'] = empty($normalized['chief_complaints']) || empty($normalized['narrative']);
        $normalized['clinician_entered'] = true;
        $normalized['automatic_prescription'] = false;
        return $normalized;
    }

    private static function sanitize_value($value) {
        if (is_array($value)) {
            return array_map(array(__CLASS__, 'sanitize_value'), $value);
        }
        if (is_bool($value) || is_numeric($value) || $value === null) {
            return $value;
        }
        return sanitize_textarea_field((string) $value);
    }

    private static function validate_context(array $data): void {
        if (empty($data['starts_at'])) {
            throw new InvalidArgumentException('Encounter start time is required.');
        }
        if (!in_array((string) ($data['mode'] ?? ''), array('in_person', 'teleconsultation', 'home_visit'), true)) {
            throw new InvalidArgumentException('Invalid encounter mode.');
        }
    }

    private static function validate_signable(array $content): void {
        if (!empty($content['incomplete'])) {
            throw new RuntimeException('An incomplete encounter cannot be signed.');
        }
        if (empty($content['chief_complaints']) || empty($content['narrative'])) {
            throw new RuntimeException('Required clinical documentation is missing.');
        }
    }

    private static function utc(string $value): string {
        $timestamp = $value === 'now' ? time() : strtotime($value);
        if (!is_int($timestamp)) {
            throw new InvalidArgumentException('Invalid encounter time.');
        }
        return gmdate('Y-m-d H:i:s', $timestamp);
    }
}
