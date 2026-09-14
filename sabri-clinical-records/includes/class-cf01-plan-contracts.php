<?php
defined('ABSPATH') || exit;

/**
 * Completes the explicit command/query catalogue from the current CF-01 master plan.
 * It delegates canonical entity mutations to the existing domain owners wherever they
 * already exist and adds only the missing orchestration contracts.
 */
final class CF01_Plan_Commands {
    public static function merge_or_quarantine_duplicate(
        int $actor_id,
        string $source_uuid,
        string $target_uuid,
        string $decision,
        string $reason,
        int $expected_source_version,
        int $expected_target_version
    ): array {
        CF01_Authorization::actor($actor_id, 'merge_patient');
        $source = CF01_Patients::get($source_uuid);
        $target = CF01_Patients::get($target_uuid);
        CF01_Authorization::expected_version($source, $expected_source_version);
        CF01_Authorization::expected_version($target, $expected_target_version);
        if ($source_uuid === $target_uuid) {
            throw new InvalidArgumentException('Duplicate source and canonical target must be different records.');
        }
        if (in_array((string) $source['status'], array('merged'), true) || in_array((string) $target['status'], array('merged','quarantined'), true)) {
            throw new RuntimeException('Duplicate resolution requires two current non-merged records.');
        }
        $decision = sanitize_key($decision);
        $reason = trim($reason);
        if ($reason === '' || !in_array($decision, array('quarantine', 'merge'), true)) {
            throw new InvalidArgumentException('A reasoned merge or quarantine decision is required.');
        }
        if ($decision === 'quarantine') {
            $ok = CF01_DB::update_versioned('patients', array('status' => 'quarantined'), array('clinical_uuid' => $source_uuid), $expected_source_version);
            if (!$ok) {
                throw new RuntimeException('Duplicate patient changed concurrently.');
            }
            CF01_Audit::record($actor_id, 'ClinicalPatientIdentityQuarantined', 'clinical_patient', $source_uuid, 'identity_review', array('reason_code' => 'duplicate_review'));
            return array('decision' => 'quarantine', 'source' => CF01_Patients::public_row(CF01_Patients::get($source_uuid)), 'target' => CF01_Patients::public_row($target));
        }

        $evidence = apply_filters('cf01_patient_duplicate_merge_evidence', null, array(
            'contract_version' => '1.0.0',
            'source_uuid' => $source_uuid,
            'target_uuid' => $target_uuid,
            'actor_user_id' => $actor_id,
            'source_version' => $expected_source_version,
            'target_version' => $expected_target_version,
            'reason_hash' => hash('sha256', $reason),
        ));
        if (!is_array($evidence)
            || ($evidence['contract_version'] ?? '') !== '1.0.0'
            || empty($evidence['accepted'])
            || empty($evidence['identity_equivalent'])
            || empty($evidence['reconciled'])
            || empty($evidence['rollback_ready'])
            || empty($evidence['approved_by_user_id'])
            || (int) $evidence['approved_by_user_id'] === $actor_id
        ) {
            throw new RuntimeException('Patient merge requires independently approved identity, reconciliation and rollback evidence.');
        }
        $receipt = apply_filters('cf01_patient_duplicate_merge_execute', null, $source, $target, $evidence, $actor_id);
        if (!is_array($receipt)
            || empty($receipt['completed'])
            || empty($receipt['reconciled'])
            || empty($receipt['source_writes_disabled'])
            || empty($receipt['canonical_target_uuid'])
            || !hash_equals($target_uuid, (string) $receipt['canonical_target_uuid'])
        ) {
            throw new RuntimeException('Duplicate merge executor did not return an accepted reconciliation receipt.');
        }
        $ok = CF01_DB::update_versioned('patients', array('status' => 'merged'), array('clinical_uuid' => $source_uuid), $expected_source_version);
        if (!$ok) {
            throw new RuntimeException('Duplicate patient changed concurrently.');
        }
        CF01_Audit::record($actor_id, 'ClinicalPatientMerged', 'clinical_patient', $source_uuid, 'identity_review', array(
            'target_reference' => substr(hash('sha256', $target_uuid), 0, 16),
            'receipt_reference' => substr(hash('sha256', CF01_Crypto::canonical_json($receipt)), 0, 16),
        ));
        return array('decision' => 'merge', 'source' => CF01_Patients::public_row(CF01_Patients::get($source_uuid)), 'target' => CF01_Patients::public_row(CF01_Patients::get($target_uuid)));
    }

    public static function appeal_rights_decision(int $actor_id, string $case_uuid, string $reason, array $evidence, int $expected_version): array {
        $case = CF01_Rights::get($case_uuid);
        CF01_Authorization::expected_version($case, $expected_version);
        if (!in_array((string) $case['status'], array('rejected', 'partially_approved'), true)) {
            throw new RuntimeException('Only a rejected or partially approved rights decision may be appealed.');
        }
        CF01_Authorization::actor($actor_id, 'request_clinical_right', array('patient_uuid' => (string) $case['patient_uuid']));
        if ((int) $case['requested_by_user_id'] !== $actor_id && !CF01_Authorization::patient_owner($actor_id, (string) $case['patient_uuid'])) {
            CF01_Role_Context::resolve($actor_id, (string) $case['patient_uuid'], 'clinical_rights', 'guardian');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A reasoned rights appeal is required.');
        }
        $decision = CF01_Crypto::decrypt((string) ($case['decision_cipher'] ?? ''), 'rights-decision');
        $decision = is_array($decision) ? $decision : array();
        $decision['appeal'] = array(
            'reason' => sanitize_textarea_field($reason),
            'evidence' => self::sanitize($evidence),
            'submitted_by_user_id' => $actor_id,
            'submitted_at' => CF01_DB::now(),
            'prior_status' => (string) $case['status'],
        );
        $ok = CF01_DB::update_versioned('rights', array(
            'status' => 'appealed',
            'decision_cipher' => CF01_Crypto::encrypt($decision, 'rights-decision'),
            'assigned_user_id' => null,
        ), array('case_uuid' => $case_uuid), $expected_version);
        if (!$ok) {
            throw new RuntimeException('Rights appeal changed concurrently.');
        }
        CF01_Audit::record($actor_id, 'ClinicalRightsDecisionAppealed', 'clinical_rights_case', $case_uuid, (string) $case['request_type'], array());
        CF01_Outbox::enqueue('ClinicalRightsDecisionAppealed', array('case_uuid' => $case_uuid, 'patient_uuid' => $case['patient_uuid']), $case_uuid);
        return CF01_Rights::get($case_uuid);
    }

    public static function fulfill_export(int $actor_id, string $case_uuid, string $export_reference, int $expected_version): array {
        $result = CF01_Rights::fulfill_export($actor_id, $case_uuid, $export_reference, $expected_version);
        $case = is_array($result['case'] ?? null) ? $result['case'] : CF01_Rights::get($case_uuid);
        CF01_Audit::record($actor_id, 'ClinicalExportFulfilled', 'clinical_rights_case', $case_uuid, 'export', array('status' => (string) ($case['status'] ?? 'fulfilled')));
        CF01_Outbox::enqueue('ClinicalExportFulfilled', array('case_uuid' => $case_uuid, 'patient_uuid' => $case['patient_uuid'], 'status' => 'fulfilled'), $case_uuid);
        return $result;
    }

    public static function resolve_correction(int $actor_id, string $case_uuid, string $encounter_uuid, array $correction, int $expected_version): array {
        $case = CF01_Rights::get($case_uuid);
        $result = CF01_Rights::correction_addendum($actor_id, $case_uuid, $encounter_uuid, $correction, $expected_version);
        CF01_Audit::record($actor_id, 'ClinicalCorrectionResolved', 'clinical_rights_case', $case_uuid, 'correction', array('status' => 'fulfilled'));
        CF01_Outbox::enqueue('ClinicalCorrectionResolved', array('case_uuid' => $case_uuid, 'patient_uuid' => $case['patient_uuid'], 'status' => 'fulfilled'), $case_uuid);
        return $result;
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

final class CF01_Plan_Queries {
    public static function my_consents(int $actor_id): array {
        $patient = self::actor_patient($actor_id);
        $patient_uuid = (string) $patient['clinical_uuid'];
        $rows = CF01_DB::rows('SELECT consent_uuid, purpose, notice_version, status, granted_at, withdrawn_at, expires_at, row_version, created_at, updated_at FROM ' . CF01_DB::table('consents') . ' WHERE patient_uuid = %s ORDER BY id DESC LIMIT 100', array($patient_uuid));
        CF01_Audit::access($actor_id, $patient_uuid, 'MyClinicalConsentsViewed', 'clinical_patient', $patient_uuid, 'privacy_transparency', 'success');
        return array('patient_uuid' => $patient_uuid, 'items' => $rows);
    }

    public static function my_active_instructions(int $actor_id): array {
        $patient = self::actor_patient($actor_id);
        $patient_uuid = (string) $patient['clinical_uuid'];
        $rows = CF01_DB::rows('SELECT * FROM ' . CF01_DB::table('prescriptions') . ' WHERE patient_uuid = %s AND status = %s AND effective_from <= %s AND (effective_until IS NULL OR effective_until > %s) ORDER BY effective_from DESC, id DESC LIMIT 20', array($patient_uuid, 'signed', CF01_DB::now(), CF01_DB::now()));
        $items = array();
        foreach ($rows as $row) {
            $order = CF01_Prescriptions::order($row);
            $items[] = array(
                'prescription_uuid' => (string) $row['prescription_uuid'],
                'status' => (string) $row['status'],
                'effective_from' => (string) $row['effective_from'],
                'effective_until' => (string) ($row['effective_until'] ?? ''),
                'instructions' => $order['instructions'] ?? '',
                'language' => $order['language'] ?? '',
                'remedy' => $order['remedy'] ?? '',
                'potency' => $order['potency'] ?? '',
                'dose' => $order['dose'] ?? '',
                'frequency' => $order['frequency'] ?? '',
            );
        }
        CF01_Audit::access($actor_id, $patient_uuid, 'MyActiveClinicalInstructionsViewed', 'clinical_patient', $patient_uuid, 'clinical_care', 'success');
        return array('patient_uuid' => $patient_uuid, 'items' => $items);
    }

    public static function relationship_eligibility(int $actor_id, string $patient_uuid, string $purpose = 'clinical_care'): array {
        CF01_Patients::get($patient_uuid);
        $context = CF01_Role_Context::resolve($actor_id, $patient_uuid, $purpose);
        $result = array('eligible' => true, 'role' => (string) $context['role'], 'purpose' => sanitize_key($purpose), 'checked_at' => CF01_DB::now());
        if (!empty($context['relationship_uuid'])) {
            $relationship = CF01_Relationships::get((string) $context['relationship_uuid']);
            $result['relationship_uuid'] = (string) $relationship['relationship_uuid'];
            $result['status'] = (string) $relationship['status'];
            $result['row_version'] = (int) $relationship['row_version'];
        }
        return $result;
    }

    public static function field_authorization(int $actor_id, string $patient_uuid, string $purpose, array $requested, string $role = ''): array {
        $context = CF01_Role_Context::resolve($actor_id, $patient_uuid, $purpose, $role);
        return array(
            'patient_uuid' => $patient_uuid,
            'role' => (string) $context['role'],
            'purpose' => sanitize_key($purpose),
            'allowed_fields' => CF01_Role_Context::fields($context, $requested),
            'evaluated_at' => CF01_DB::now(),
        );
    }

    public static function clinical_record_version(int $actor_id, string $patient_uuid): array {
        CF01_Role_Context::resolve($actor_id, $patient_uuid, 'clinical_care');
        $patient = CF01_Patients::get($patient_uuid);
        $latest = array();
        $map = array(
            'relationships' => 'relationship_uuid', 'consents' => 'consent_uuid', 'encounters' => 'encounter_uuid',
            'observations' => 'observation_uuid', 'assessments' => 'assessment_uuid', 'prescriptions' => 'prescription_uuid',
            'followups' => 'followup_uuid', 'outcomes' => 'outcome_uuid', 'rights' => 'case_uuid', 'breakglass' => 'grant_uuid'
        );
        foreach ($map as $table => $id_field) {
            $row = CF01_DB::row('SELECT ' . $id_field . ', row_version, updated_at FROM ' . CF01_DB::table($table) . ' WHERE patient_uuid = %s ORDER BY updated_at DESC, id DESC LIMIT 1', array($patient_uuid));
            $latest[$table] = $row ?: null;
        }
        return array('patient' => CF01_Patients::public_row($patient), 'latest_versions' => $latest, 'generated_at' => CF01_DB::now());
    }

    public static function safety_alerts(int $actor_id, string $patient_uuid): array {
        $context = CF01_Role_Context::resolve($actor_id, $patient_uuid, 'clinical_care');
        $allowed = CF01_Role_Context::fields($context, array('red_flags','followups','active_prescriptions'));
        if (!$allowed) {
            throw new RuntimeException('Clinical safety alert view is unavailable for this role.');
        }
        $red_flags = CF01_DB::rows('SELECT outcome_uuid, followup_uuid, review_status, created_at FROM ' . CF01_DB::table('outcomes') . ' WHERE patient_uuid = %s AND red_flag = 1 AND review_status <> %s ORDER BY id DESC LIMIT 50', array($patient_uuid, 'reviewed'));
        $break_glass = CF01_DB::rows('SELECT grant_uuid, status, expires_at, review_status, created_at FROM ' . CF01_DB::table('breakglass') . ' WHERE patient_uuid = %s AND status IN (%s,%s,%s,%s) ORDER BY id DESC LIMIT 20', array($patient_uuid, 'requested','granted','used','expired'));
        return array('red_flag_outcomes' => $red_flags, 'break_glass_activity' => $break_glass, 'generated_at' => CF01_DB::now());
    }

    public static function unsigned_encounters(int $actor_id, string $patient_uuid): array {
        CF01_Authorization::clinician($actor_id, 'view_unsigned_encounters', array('patient_uuid' => $patient_uuid));
        CF01_Authorization::relationship($patient_uuid, $actor_id, 'clinical_care', 'view_unsigned_encounters');
        return CF01_DB::rows('SELECT encounter_uuid, encounter_type, mode, status, row_version, created_at, updated_at FROM ' . CF01_DB::table('encounters') . ' WHERE patient_uuid = %s AND status IN (%s,%s,%s) ORDER BY updated_at ASC LIMIT 100', array($patient_uuid, 'draft','in_progress','ready_to_sign'));
    }

    public static function pending_followups(int $actor_id, string $patient_uuid): array {
        CF01_Role_Context::resolve($actor_id, $patient_uuid, 'clinical_care');
        return CF01_DB::rows('SELECT followup_uuid, prescription_uuid, status, due_at, overdue_at, row_version, created_at, updated_at FROM ' . CF01_DB::table('followups') . ' WHERE patient_uuid = %s AND status NOT IN (%s,%s) ORDER BY due_at ASC LIMIT 100', array($patient_uuid, 'closed','cancelled'));
    }

    public static function care_transfer_status(int $actor_id, string $patient_uuid): array {
        CF01_Role_Context::resolve($actor_id, $patient_uuid, 'clinical_rights');
        $transfer = CF01_DB::row('SELECT case_uuid, status, assigned_user_id, fulfilled_at, row_version, created_at, updated_at FROM ' . CF01_DB::table('rights') . ' WHERE patient_uuid = %s AND request_type = %s ORDER BY id DESC LIMIT 1', array($patient_uuid, 'transfer'));
        $relationship = CF01_DB::row('SELECT relationship_uuid, status, starts_at, ends_at, row_version, updated_at FROM ' . CF01_DB::table('relationships') . ' WHERE patient_uuid = %s ORDER BY id DESC LIMIT 1', array($patient_uuid));
        return array('transfer_case' => $transfer, 'relationship' => $relationship, 'generated_at' => CF01_DB::now());
    }

    public static function access_anomalies(int $actor_id, string $patient_uuid): array {
        CF01_Role_Context::resolve($actor_id, $patient_uuid, 'security_review', 'auditor');
        $denied = CF01_DB::rows('SELECT event_uuid, action, object_type, purpose, result, occurred_at FROM ' . CF01_DB::table('access') . ' WHERE patient_uuid = %s AND result <> %s ORDER BY occurred_at DESC LIMIT 100', array($patient_uuid, 'success'));
        $break_glass = CF01_DB::rows('SELECT grant_uuid, status, review_status, expires_at, created_at FROM ' . CF01_DB::table('breakglass') . ' WHERE patient_uuid = %s ORDER BY id DESC LIMIT 50', array($patient_uuid));
        return array('denied_or_failed_access' => $denied, 'break_glass_activity' => $break_glass, 'generated_at' => CF01_DB::now());
    }

    public static function break_glass_review_queue(int $actor_id): array {
        CF01_Authorization::actor($actor_id, 'review_break_glass');
        return CF01_DB::rows('SELECT grant_uuid, patient_uuid, status, review_status, expires_at, row_version, created_at, updated_at FROM ' . CF01_DB::table('breakglass') . ' WHERE review_status = %s AND status IN (%s,%s) ORDER BY updated_at ASC LIMIT 100', array('pending','revoked','expired'));
    }

    public static function retention_due(int $actor_id): array {
        CF01_Authorization::actor($actor_id, 'manage_retention');
        return CF01_DB::rows('SELECT retention_uuid, object_type, object_uuid, policy_key, eligible_at, hold_status, status, row_version FROM ' . CF01_DB::table('retention') . ' WHERE status = %s AND eligible_at IS NOT NULL AND eligible_at <= %s ORDER BY eligible_at ASC LIMIT 100', array('scheduled', CF01_DB::now()));
    }

    public static function restore_reconciliation(int $actor_id): array {
        CF01_Authorization::actor($actor_id, 'run_clinical_rollback');
        $row = CF01_DB::row('SELECT * FROM ' . CF01_DB::table('migrations') . ' WHERE migration_key = %s AND status = %s ORDER BY id DESC LIMIT 1', array('restore_verification', 'completed'));
        if (!$row) {
            return array('available' => false, 'status' => 'not_verified');
        }
        $receipt = CF01_Crypto::decrypt((string) $row['receipt_cipher'], 'migration-receipt');
        return array(
            'available' => is_array($receipt),
            'migration_uuid' => (string) $row['migration_uuid'],
            'row_version' => (int) $row['row_version'],
            'completed_at' => (string) ($row['completed_at'] ?? ''),
            'receipt' => is_array($receipt) ? $receipt : array(),
        );
    }

    public static function export_package_status(int $actor_id, string $case_uuid): array {
        $case = CF01_Rights::get($case_uuid);
        if (!CF01_Authorization::patient_owner($actor_id, (string) $case['patient_uuid'])) {
            CF01_Role_Context::resolve($actor_id, (string) $case['patient_uuid'], 'clinical_rights', 'records');
        }
        if (($case['request_type'] ?? '') !== 'export') {
            throw new RuntimeException('Clinical rights case is not an export request.');
        }
        return array(
            'case_uuid' => $case_uuid,
            'status' => (string) $case['status'],
            'fulfilled_at' => (string) ($case['fulfilled_at'] ?? ''),
            'delivery_expires_at' => (string) ($case['export_token_expires_at'] ?? ''),
            'downloaded' => !empty($case['export_token_consumed_at']),
            'row_version' => (int) $case['row_version'],
        );
    }

    private static function actor_patient(int $actor_id): array {
        $membership = CF01_Contracts::membership($actor_id);
        if (empty($membership['valid']) || empty($membership['approved']) || !empty($membership['suspended']) || empty($membership['platform_uuid'])) {
            throw new RuntimeException('Current member identity is unavailable.');
        }
        $patient = CF01_Patients::for_platform_subject((string) $membership['platform_uuid']);
        CF01_Authorization::actor($actor_id, 'view_own_clinical_record', array('patient_uuid' => $patient['clinical_uuid']));
        return $patient;
    }
}
