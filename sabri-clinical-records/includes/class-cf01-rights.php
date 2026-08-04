<?php
defined('ABSPATH') || exit;

final class CF01_Rights {
    private const TYPES = array('access', 'export', 'correction', 'transfer', 'restriction', 'appeal');
    private const STATES = array(
        'submitted' => array('identity_verification', 'under_review', 'withdrawn'),
        'identity_verification' => array('under_review', 'rejected', 'withdrawn'),
        'under_review' => array('approved', 'partially_approved', 'rejected', 'more_information'),
        'more_information' => array('under_review', 'withdrawn'),
        'approved' => array('fulfilled', 'implementation_failed'),
        'partially_approved' => array('fulfilled', 'implementation_failed', 'appealed'),
        'rejected' => array('appealed', 'closed'),
        'implementation_failed' => array('approved', 'closed'),
        'appealed' => array('under_review', 'closed'),
        'fulfilled' => array('closed'),
        'withdrawn' => array('closed'),
        'closed' => array(),
    );

    public static function request(int $actor_id, string $patient_uuid, string $type, array $request): array {
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Unsupported clinical rights request.');
        }
        CF01_Patients::get($patient_uuid);
        CF01_Authorization::actor($actor_id, 'request_clinical_right');
        if (!CF01_Authorization::patient_owner($actor_id, $patient_uuid)) {
            self::guardian_or_representative($actor_id, $patient_uuid, $request);
        }
        $uuid = CF01_DB::uuid();
        CF01_DB::insert('rights', array(
            'case_uuid' => $uuid,
            'patient_uuid' => $patient_uuid,
            'request_type' => $type,
            'status' => 'submitted',
            'request_cipher' => CF01_Crypto::encrypt(self::sanitize($request), 'rights-request'),
            'decision_cipher' => null,
            'requested_by_user_id' => $actor_id,
            'assigned_user_id' => null,
            'fulfilled_at' => null,
            'export_token_hash' => null,
            'export_token_expires_at' => null,
            'export_token_consumed_at' => null,
            'row_version' => 1,
            'created_at' => CF01_DB::now(),
            'updated_at' => CF01_DB::now(),
        ));
        CF01_Audit::record($actor_id, 'ClinicalRightsRequestSubmitted', 'clinical_rights_case', $uuid, $type, array());
        CF01_Outbox::enqueue('ClinicalRightsRequestSubmitted', array('case_uuid' => $uuid, 'patient_uuid' => $patient_uuid, 'request_type' => $type), $uuid);
        return self::get($uuid);
    }

    public static function decide(int $actor_id, string $uuid, string $decision, array $details, int $expected_version): array {
        $row = self::get($uuid);
        CF01_Authorization::actor($actor_id, 'decide_clinical_right');
        CF01_Authorization::expected_version($row, $expected_version);
        if ((int) ($row['requested_by_user_id'] ?? 0) === $actor_id) {
            throw new RuntimeException('A rights requester cannot decide the same request.');
        }
        if (!in_array($decision, array('approved', 'partially_approved', 'rejected', 'more_information'), true)) {
            throw new InvalidArgumentException('Invalid rights decision.');
        }
        if (!in_array((string) $row['status'], array('submitted', 'identity_verification', 'under_review', 'more_information', 'appealed'), true)) {
            throw new RuntimeException('Rights request is not awaiting a decision.');
        }
        if (empty($details['reason'])) {
            throw new InvalidArgumentException('A reasoned rights decision is required.');
        }
        $ok = CF01_DB::update_versioned('rights', array(
            'status' => $decision,
            'decision_cipher' => CF01_Crypto::encrypt(self::sanitize($details), 'rights-decision'),
            'assigned_user_id' => $actor_id,
        ), array('case_uuid' => $uuid), $expected_version);
        if (!$ok) {
            throw new RuntimeException('Rights case changed concurrently.');
        }
        CF01_Audit::record($actor_id, 'ClinicalRightsRequestDecided', 'clinical_rights_case', $uuid, (string) $row['request_type'], array('decision' => $decision));
        CF01_Outbox::enqueue('ClinicalRightsRequestDecided', array('case_uuid' => $uuid, 'patient_uuid' => $row['patient_uuid'], 'decision' => $decision), $uuid);
        return self::get($uuid);
    }

    public static function fulfill_export(int $actor_id, string $uuid, string $export_reference, int $expected_version): array {
        $row = self::get($uuid);
        CF01_Authorization::actor($actor_id, 'fulfill_clinical_export');
        CF01_Authorization::expected_version($row, $expected_version);
        if (($row['request_type'] ?? '') !== 'export' || !in_array((string) $row['status'], array('approved', 'partially_approved'), true)) {
            throw new RuntimeException('Only an approved export request may be fulfilled.');
        }
        if ((int) ($row['requested_by_user_id'] ?? 0) === $actor_id || (int) ($row['assigned_user_id'] ?? 0) === $actor_id) {
            throw new RuntimeException('Export review and fulfillment require separated duties.');
        }
        if (trim($export_reference) === '' || str_contains($export_reference, '://') || preg_match('/[?&](token|key|secret|signature|session|authorization)=/i', $export_reference)) {
            throw new InvalidArgumentException('Secure opaque export reference is required.');
        }
        $provider = CF01_Contracts::secure_media('register_export', array(
            'asset_reference' => $export_reference,
            'purpose' => 'clinical_export',
            'patient_uuid' => $row['patient_uuid'],
            'case_uuid' => $uuid,
        ));
        if (empty($provider['valid']) || empty($provider['accepted']) || ($provider['privacy_class'] ?? '') !== 'C5') {
            throw new RuntimeException('Secure clinical export provider did not accept the package.');
        }
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $expires = gmdate('Y-m-d H:i:s', time() + (15 * MINUTE_IN_SECONDS));
        $ok = CF01_DB::update_versioned('rights', array(
            'status' => 'fulfilled',
            'fulfilled_at' => CF01_DB::now(),
            'export_reference_cipher' => CF01_Crypto::encrypt($export_reference, 'rights-export-reference'),
            'export_token_hash' => hash('sha256', $token),
            'export_token_expires_at' => $expires,
            'export_token_consumed_at' => null,
        ), array('case_uuid' => $uuid), $expected_version);
        if (!$ok) {
            throw new RuntimeException('Rights case changed concurrently.');
        }
        CF01_Audit::record($actor_id, 'ClinicalExportPrepared', 'clinical_rights_case', $uuid, 'export', array('expires_at' => $expires));
        return array('case' => self::get($uuid), 'token' => $token, 'expires_at' => $expires);
    }

    public static function consume_export(int $actor_id, string $uuid, string $token, int $expected_version): string {
        $row = self::get($uuid);
        if (!CF01_Authorization::patient_owner($actor_id, (string) $row['patient_uuid'])) {
            throw new RuntimeException('Export is unavailable.');
        }
        CF01_Authorization::actor($actor_id, 'export_record');
        CF01_Authorization::expected_version($row, $expected_version);
        if (($row['status'] ?? '') !== 'fulfilled' || empty($row['export_token_hash']) || !hash_equals((string) $row['export_token_hash'], hash('sha256', $token))) {
            throw new RuntimeException('Export is unavailable.');
        }
        if (!empty($row['export_token_consumed_at']) || !CF01_Authorization::not_expired((string) ($row['export_token_expires_at'] ?? ''))) {
            throw new RuntimeException('Export token expired or was already used.');
        }
        $reference = CF01_Crypto::decrypt((string) $row['export_reference_cipher'], 'rights-export-reference');
        if (!is_string($reference) || $reference === '') {
            throw new RuntimeException('Export is unavailable.');
        }
        $delivery = CF01_Contracts::secure_media('delivery', array(
            'asset_reference' => $reference,
            'purpose' => 'clinical_export_download',
            'actor_user_id' => $actor_id,
            'patient_uuid' => $row['patient_uuid'],
            'ttl_seconds' => 300,
            'single_use' => true,
            'no_store' => true,
        ));
        if (empty($delivery['valid']) || empty($delivery['accepted']) || empty($delivery['delivery_grant'])) {
            throw new RuntimeException('Export delivery is unavailable.');
        }
        $ok = CF01_DB::update_versioned('rights', array('export_token_consumed_at' => CF01_DB::now()), array('case_uuid' => $uuid), $expected_version);
        if (!$ok) {
            throw new RuntimeException('Export token was consumed concurrently.');
        }
        CF01_Audit::record($actor_id, 'ClinicalExportDownloaded', 'clinical_rights_case', $uuid, 'export', array());
        return (string) $delivery['delivery_grant'];
    }

    public static function correction_addendum(int $actor_id, string $case_uuid, string $encounter_uuid, array $correction, int $expected_version): array {
        $case = self::get($case_uuid);
        CF01_Authorization::actor($actor_id, 'fulfill_clinical_correction');
        CF01_Authorization::expected_version($case, $expected_version);
        if (($case['request_type'] ?? '') !== 'correction' || !in_array((string) $case['status'], array('approved', 'partially_approved'), true)) {
            throw new RuntimeException('Approved correction request is required.');
        }
        $encounter = CF01_Encounters::get($encounter_uuid);
        if (!hash_equals((string) $case['patient_uuid'], (string) $encounter['patient_uuid'])) {
            throw new RuntimeException('Correction case and encounter patient do not match.');
        }
        $result = CF01_DB::transaction(function () use ($actor_id, $case_uuid, $encounter_uuid, $correction, $case, $expected_version): array {
            $addendum = CF01_Encounters::addendum($actor_id, $encounter_uuid, array(
                'narrative' => (string) ($correction['narrative'] ?? ''),
                'provenance' => array('rights_case_uuid' => $case_uuid, 'requested_by_user_id' => $case['requested_by_user_id']),
            ));
            $ok = CF01_DB::update_versioned('rights', array('status' => 'fulfilled', 'fulfilled_at' => CF01_DB::now()), array('case_uuid' => $case_uuid), $expected_version);
            if (!$ok) {
                throw new RuntimeException('Rights case changed concurrently.');
            }
            CF01_Audit::record($actor_id, 'ClinicalCorrectionFulfilled', 'clinical_rights_case', $case_uuid, 'correction', array('addendum_uuid' => $addendum['encounter_uuid']));
            return $addendum;
        });
        return $result;
    }

    public static function export_manifest(int $actor_id, string $patient_uuid, array $scope): array {
        if (!CF01_Authorization::patient_owner($actor_id, $patient_uuid)) {
            throw new RuntimeException('Export is unavailable.');
        }
        CF01_Authorization::actor($actor_id, 'export_record');
        return self::build_export_manifest($patient_uuid, $scope, 'patient_self_service');
    }

    public static function export_manifest_for_case(int $actor_id, string $case_uuid, array $scope): array {
        $case = self::get($case_uuid);
        CF01_Authorization::actor($actor_id, 'fulfill_clinical_export');
        if (($case['request_type'] ?? '') !== 'export' || !in_array((string) $case['status'], array('approved', 'partially_approved'), true)) {
            throw new RuntimeException('An approved export case is required.');
        }
        if ((int) ($case['requested_by_user_id'] ?? 0) === $actor_id || (int) ($case['assigned_user_id'] ?? 0) === $actor_id) {
            throw new RuntimeException('Export review and generation require separated duties.');
        }
        $manifest = self::build_export_manifest((string) $case['patient_uuid'], $scope, 'approved_rights_case');
        $manifest['case_uuid'] = $case_uuid;
        $manifest['decision_version'] = (int) $case['row_version'];
        $manifest['manifest_hash'] = hash('sha256', CF01_Crypto::canonical_json(array_diff_key($manifest, array('manifest_hash' => true))));
        return $manifest;
    }

    private static function build_export_manifest(string $patient_uuid, array $scope, string $authority): array {
        $allowed = array_values(array_unique(array_intersect(array_map('sanitize_key', $scope), array('demographics', 'consents', 'encounters', 'observations', 'assessments', 'prescriptions', 'followups', 'outcomes', 'access_history'))));
        if (!$allowed) {
            throw new InvalidArgumentException('At least one export scope is required.');
        }
        $manifest = array(
            'contract' => 'cf01.clinical-export',
            'version' => '1.0.0',
            'patient_uuid' => $patient_uuid,
            'scope' => $allowed,
            'authority' => $authority,
            'generated_at' => CF01_DB::now(),
            'records' => array(),
        );
        foreach (array('consents','encounters','observations','assessments','prescriptions','followups','outcomes') as $key) {
            if (in_array($key, $allowed, true)) {
                $rows = self::bounded_rows($key, $patient_uuid, 10000);
                $manifest['records'][$key] = array_map(static fn(array $row): array => self::export_row($key, $row), $rows);
            }
        }
        if (in_array('demographics', $allowed, true)) {
            $manifest['records']['demographics'] = CF01_Patients::demographics(CF01_Patients::get($patient_uuid));
        }
        if (in_array('access_history', $allowed, true)) {
            $rows = CF01_DB::rows(
                'SELECT event_uuid, actor_pseudonym, action, object_type, purpose, result, occurred_at FROM ' . CF01_DB::table('access') . ' WHERE patient_uuid = %s ORDER BY occurred_at DESC LIMIT 1001',
                array($patient_uuid)
            );
            if (count($rows) > 1000) {
                throw new RuntimeException('Clinical access history exceeds the bounded export package; use an approved paginated export job.');
            }
            $manifest['records']['access_history'] = $rows;
        }
        $manifest['manifest_hash'] = hash('sha256', CF01_Crypto::canonical_json($manifest));
        return $manifest;
    }

    private static function bounded_rows(string $key, string $patient_uuid, int $limit): array {
        $rows = CF01_DB::rows(
            'SELECT * FROM ' . CF01_DB::table($key) . ' WHERE patient_uuid = %s ORDER BY id ASC LIMIT ' . ($limit + 1),
            array($patient_uuid)
        );
        if (count($rows) > $limit) {
            throw new RuntimeException('Clinical export exceeds the bounded synchronous package; use an approved paginated export job.');
        }
        return $rows;
    }

    private static function export_row(string $entity, array $row): array {
        $maps = array(
            'consents' => array('guardian_reference_cipher' => array('guardian', 'consent-guardian'), 'evidence_cipher' => array('evidence', 'consent-evidence'), 'withdrawal_reason_cipher' => array('withdrawal_reason', 'consent-withdrawal')),
            'encounters' => array('content_cipher' => array('content', 'encounter-content'), 'snapshot_cipher' => array('signed_snapshot', 'encounter-snapshot'), 'error_reason_cipher' => array('entered_in_error_reason', 'encounter-error-reason')),
            'observations' => array('value_cipher' => array('value', 'observation-value'), 'provenance_cipher' => array('provenance', 'observation-provenance')),
            'assessments' => array('assessment_cipher' => array('assessment', 'clinical-assessment')),
            'prescriptions' => array('order_cipher' => array('order', 'prescription-order'), 'snapshot_cipher' => array('signed_snapshot', 'prescription-snapshot'), 'discontinuation_reason_cipher' => array('discontinuation_reason', 'prescription-discontinuation')),
            'followups' => array('questionnaire_cipher' => array('questionnaire', 'followup-questionnaire'), 'plan_cipher' => array('plan', 'followup-plan'), 'reschedule_reason_cipher' => array('reschedule_reason', 'followup-reschedule'), 'closure_reason_cipher' => array('closure_reason', 'followup-closure')),
            'outcomes' => array('response_cipher' => array('response', 'patient-outcome'), 'review_cipher' => array('review', 'outcome-review')),
        );
        foreach ($maps[$entity] ?? array() as $cipher => $definition) {
            if (!empty($row[$cipher])) {
                $row[$definition[0]] = CF01_Crypto::decrypt((string) $row[$cipher], $definition[1]);
            }
            unset($row[$cipher]);
        }
        foreach (array_keys($row) as $field) {
            if ($field === 'id' || str_ends_with($field, '_hash') || str_ends_with($field, '_signature')) {
                unset($row[$field]);
                continue;
            }
            if (str_ends_with($field, '_user_id') && !empty($row[$field])) {
                $reference_field = substr($field, 0, -8) . '_reference';
                $row[$reference_field] = (string) apply_filters('cf01_export_actor_reference', 'actor-' . hash('sha256', (string) $row[$field]), (int) $row[$field], $entity);
                unset($row[$field]);
            }
        }
        return $row;
    }

    public static function access_history(int $actor_id, string $patient_uuid, int $limit = 50, string $before = ''): array {
        CF01_Patients::get($patient_uuid);
        if (CF01_Authorization::patient_owner($actor_id, $patient_uuid)) {
            CF01_Authorization::actor($actor_id, 'view_own_access_history');
        } else {
            CF01_Authorization::actor($actor_id, 'view_access_history');
        }
        $limit = max(1, min(100, $limit));
        $sql = 'SELECT event_uuid, actor_pseudonym, action, object_type, purpose, result, occurred_at FROM ' . CF01_DB::table('access') . ' WHERE patient_uuid = %s';
        $args = array($patient_uuid);
        if ($before !== '') {
            $sql .= ' AND occurred_at < %s';
            $args[] = $before;
        }
        $sql .= ' ORDER BY occurred_at DESC LIMIT ' . $limit;
        return CF01_DB::rows($sql, $args);
    }

    public static function get(string $uuid): array {
        $row = CF01_DB::row('SELECT * FROM ' . CF01_DB::table('rights') . ' WHERE case_uuid = %s LIMIT 1', array($uuid));
        if (!$row) {
            throw new RuntimeException('Clinical rights case is unavailable.');
        }
        return $row;
    }

    public static function state_map(): array {
        return self::STATES;
    }

    private static function guardian_or_representative(int $actor_id, string $patient_uuid, array $request): void {
        $patient = CF01_Patients::get($patient_uuid);
        $guardian = CF01_Patients::guardian_context($patient);
        $membership = CF01_Contracts::membership($actor_id);
        $same_actor = !empty($membership['valid'])
            && !empty($membership['approved'])
            && empty($membership['suspended'])
            && !empty($guardian['platform_uuid'])
            && hash_equals((string) $guardian['platform_uuid'], (string) ($membership['platform_uuid'] ?? ''));
        if (($guardian['status'] ?? '') !== 'verified' || !$same_actor) {
            throw new RuntimeException('Verified representative authority is required.');
        }
        $reference = (string) ($request['representative_reference'] ?? '');
        if ($reference !== '' && !empty($guardian['reference']) && !hash_equals((string) $guardian['reference'], $reference)) {
            throw new RuntimeException('Representative evidence does not match current verified authority.');
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
}
