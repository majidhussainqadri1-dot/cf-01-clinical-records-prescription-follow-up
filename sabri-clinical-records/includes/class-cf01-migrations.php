<?php
defined('ABSPATH') || exit;

final class CF01_Migrations {
    private const LOCK = 'cf01_migration_lock';

    public static function install_schema(): void {
        if (!CF01_Crypto::available()) {
            throw new RuntimeException('Encryption/key infrastructure must be ready before clinical schema installation.');
        }
        self::with_lock(function (): void {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            global $wpdb;
            $charset = $wpdb->get_charset_collate();
            foreach (self::schema($charset) as $sql) {
                dbDelta($sql);
            }
            update_option('cf01_schema_version', CF01_SCHEMA_VERSION, false);
            update_option('cf01_schema_status', 'installed_inactive', false);
            self::record('schema_install', 'completed', array('schema_version' => CF01_SCHEMA_VERSION));
        });
    }

    public static function activate_runtime(int $actor_id, array $evidence): void {
        if ($actor_id <= 0 || !current_user_can('cf01_activate_clinical')) {
            throw new RuntimeException('Explicit clinical activation authority is required.');
        }
        $membership = CF01_Contracts::membership($actor_id);
        $recent = CF01_Contracts::recent_auth($actor_id, 'activate_module');
        if (empty($membership['valid']) || empty($membership['approved']) || !empty($membership['suspended']) || empty($recent['valid']) || empty($recent['recent_auth']) || empty($recent['step_up']) || !CF01_Authorization::not_expired((string) ($recent['expires_at'] ?? '')) || !hash_equals((string) ($membership['platform_uuid'] ?? ''), (string) ($recent['subject_uuid'] ?? ''))) {
            throw new RuntimeException('Current membership and recent step-up authentication are required for activation.');
        }
        $required = array('founder_approval', 'legal_professional_acceptance', 'independent_security_acceptance', 'staging_acceptance', 'backup_restore_acceptance', 'rollback_rehearsal', 'operations_staffing');
        foreach ($required as $field) {
            if (empty($evidence[$field])) {
                throw new RuntimeException('Activation gate is incomplete: ' . $field);
            }
        }
        if (get_option('cf01_schema_status') !== 'installed_inactive' && get_option('cf01_schema_status') !== 'installed') {
            throw new RuntimeException('Clinical schema is not installed and verified.');
        }
        $dependencies = CF01_Contracts::dependency_report();
        foreach (array('membership','recent_auth','practitioner','care_context','communication_context','notifications','shell','visual','assurance','secure_media','prescription_safety') as $dependency) {
            if (empty($dependencies[$dependency])) {
                throw new RuntimeException('Mandatory native-owner contract is unavailable: ' . $dependency);
            }
        }
        $shell = CF01_Contracts::shell_routes();
        $visual = CF01_Contracts::visual_components();
        $assurance = CF01_Contracts::register_assurance();
        if (empty($shell['valid']) || empty($shell['registered']) || empty($shell['private']) || empty($shell['no_store']) || empty($visual['valid']) || empty($visual['accepted']) || empty($visual['rtl']) || empty($visual['accessibility']) || empty($assurance['valid']) || empty($assurance['registered']) || empty($assurance['native_enforcement_preserved'])) {
            throw new RuntimeException('Shell, visual or assurance activation contract was not accepted.');
        }
        $evidence['dependency_report'] = $dependencies;
        $evidence['shell_contract'] = $shell;
        $evidence['visual_contract'] = $visual;
        $evidence['assurance_contract'] = $assurance;
        update_option('cf01_activation_evidence', CF01_Crypto::encrypt($evidence, 'activation-evidence'), false);
        update_option('cf01_activation_state', 'enabled', false);
        update_option('cf01_schema_status', 'installed', false);
        if (!wp_next_scheduled('cf01_process_outbox')) {
            wp_schedule_event(time() + 60, 'hourly', 'cf01_process_outbox');
        }
        if (!wp_next_scheduled('cf01_retention_reconcile')) {
            wp_schedule_event(time() + 300, 'daily', 'cf01_retention_reconcile');
        }
        if (!wp_next_scheduled('cf01_followup_reconcile')) {
            wp_schedule_event(time() + 120, 'hourly', 'cf01_followup_reconcile');
        }
        CF01_Audit::record($actor_id, 'ClinicalRuntimeActivated', 'clinical_runtime', 'cf01', 'platform_governance', array('version' => CF01_VERSION));
    }

    public static function disable_runtime(int $actor_id, string $reason): void {
        CF01_Authorization::actor($actor_id, 'disable_module');
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Disable reason is required.');
        }
        update_option('cf01_activation_state', 'disabled', false);
        wp_clear_scheduled_hook('cf01_process_outbox');
        wp_clear_scheduled_hook('cf01_retention_reconcile');
        wp_clear_scheduled_hook('cf01_followup_reconcile');
        CF01_Audit::record($actor_id, 'ClinicalRuntimeDisabled', 'clinical_runtime', 'cf01', 'platform_governance', array('reason_code' => 'controlled_disable'));
    }

    public static function extract_from_file08(int $actor_id, array $batch, string $cursor = ''): array {
        CF01_Authorization::actor($actor_id, 'run_clinical_migration');
        if (CF01_DB::is_enabled()) {
            throw new RuntimeException('Legacy extraction must use approved migration mode, not live mutation mode.');
        }
        $required = array('dry_run', 'source_contract_version', 'reconciliation_plan', 'rollback_plan');
        foreach ($required as $field) {
            if (empty($batch[$field])) {
                throw new InvalidArgumentException('Migration evidence is incomplete: ' . $field);
            }
        }
        $result = apply_filters('cf01_file08_extraction_batch', null, $batch, $cursor);
        if (!is_array($result) || !isset($result['records'], $result['next_cursor'], $result['complete'])) {
            throw new RuntimeException('File 08 extraction contract is unavailable or invalid.');
        }
        $counts = array('seen' => 0, 'eligible' => 0, 'quarantined' => 0, 'written' => 0);
        foreach ((array) $result['records'] as $record) {
            $counts['seen']++;
            if (!is_array($record) || empty($record['source_reference']) || empty($record['patient_platform_uuid'])) {
                $counts['quarantined']++;
                continue;
            }
            $counts['eligible']++;
            if (empty($batch['dry_run'])) {
                self::write_extracted_record($actor_id, $record);
                $counts['written']++;
            }
        }
        $receipt = array(
            'cursor' => $cursor,
            'next_cursor' => sanitize_text_field((string) $result['next_cursor']),
            'complete' => !empty($result['complete']),
            'dry_run' => !empty($batch['dry_run']),
            'counts' => $counts,
            'source_contract_version' => sanitize_text_field((string) $batch['source_contract_version']),
        );
        self::record('file08_extraction', 'completed', $receipt, (string) $receipt['next_cursor']);
        return $receipt;
    }

    public static function reconciliation(array $expected): array {
        $actual = array();
        foreach (array('patients', 'relationships', 'consents', 'encounters', 'prescriptions', 'followups', 'attachments') as $key) {
            $row = CF01_DB::row('SELECT COUNT(*) AS total FROM ' . CF01_DB::table($key));
            $actual[$key] = (int) ($row['total'] ?? 0);
        }
        $diff = array();
        foreach ($expected as $key => $count) {
            if (isset($actual[$key]) && $actual[$key] !== (int) $count) {
                $diff[$key] = array('expected' => (int) $count, 'actual' => $actual[$key]);
            }
        }
        return array('passed' => !$diff, 'actual' => $actual, 'differences' => $diff);
    }



    public static function verify_restore(int $actor_id, array $expected): array {
        CF01_Authorization::actor($actor_id, 'run_clinical_rollback');
        foreach (array('backup_reference', 'expected_counts', 'expected_integrity_root', 'key_recovery_tested_at') as $field) {
            if (empty($expected[$field])) {
                throw new InvalidArgumentException('Restore verification evidence is incomplete: ' . $field);
            }
        }
        $actual = apply_filters('cf01_restore_verification', null, $expected);
        if (!is_array($actual) || empty($actual['restore_completed']) || empty($actual['key_recovery_passed']) || empty($actual['authorization_revalidated']) || !isset($actual['counts'], $actual['integrity_root'], $actual['deleted_record_resurrection'])) {
            throw new RuntimeException('Independent restore verification evidence is unavailable.');
        }
        if (!hash_equals((string) $expected['expected_integrity_root'], (string) $actual['integrity_root']) || (array) $expected['expected_counts'] !== (array) $actual['counts'] || !empty($actual['deleted_record_resurrection'])) {
            throw new RuntimeException('Restore reconciliation failed or deleted records were resurrected.');
        }
        $receipt = array(
            'verified_at' => CF01_DB::now(),
            'backup_reference_hash' => hash('sha256', (string) $expected['backup_reference']),
            'counts' => $actual['counts'],
            'integrity_root' => $actual['integrity_root'],
            'key_recovery_passed' => true,
            'authorization_revalidated' => true,
            'deleted_record_resurrection' => false,
        );
        self::record('restore_verification', 'completed', $receipt);
        CF01_Audit::record($actor_id, 'ClinicalRestoreVerified', 'clinical_runtime', 'cf01', 'resilience', array('integrity_root' => $actual['integrity_root']));
        return $receipt;
    }

    public static function rollback(int $actor_id, string $migration_uuid, string $reason): array {
        CF01_Authorization::actor($actor_id, 'run_clinical_rollback');
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Rollback reason is required.');
        }
        $migration = CF01_DB::row('SELECT * FROM ' . CF01_DB::table('migrations') . ' WHERE migration_uuid = %s LIMIT 1', array($migration_uuid));
        if (!$migration || ($migration['status'] ?? '') !== 'completed') {
            throw new RuntimeException('Completed migration record is required.');
        }
        $receipt = apply_filters('cf01_rollback_migration', null, $migration, $reason);
        if (!is_array($receipt) || empty($receipt['completed']) || empty($receipt['reconciled'])) {
            throw new RuntimeException('Verified rollback and reconciliation receipt is required.');
        }
        CF01_DB::update_versioned('migrations', array(
            'status' => 'rolled_back',
            'rollback_cipher' => CF01_Crypto::encrypt(array('reason' => $reason, 'receipt' => $receipt), 'migration-rollback'),
            'completed_at' => CF01_DB::now(),
        ), array('migration_uuid' => $migration_uuid), (int) $migration['row_version']);
        CF01_Audit::record($actor_id, 'ClinicalMigrationRolledBack', 'migration', $migration_uuid, 'migration', array());
        return $receipt;
    }

    public static function schema(string $charset): array {
        $t = static fn(string $key): string => CF01_DB::table($key);
        return array(
            "CREATE TABLE {$t('patients')} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                clinical_uuid char(36) NOT NULL,
                platform_subject_hash char(64) DEFAULT NULL,
                platform_subject_cipher longtext DEFAULT NULL,
                demographics_cipher longtext NOT NULL,
                guardian_context_cipher longtext DEFAULT NULL,
                jurisdiction varchar(64) NOT NULL DEFAULT '',
                status varchar(32) NOT NULL DEFAULT 'active',
                row_version bigint unsigned NOT NULL DEFAULT 1,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY clinical_uuid (clinical_uuid), UNIQUE KEY platform_subject_hash (platform_subject_hash), KEY status (status)
            ) $charset;",
            "CREATE TABLE {$t('relationships')} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                relationship_uuid char(36) NOT NULL, patient_uuid char(36) NOT NULL, doctor_user_id bigint unsigned NOT NULL,
                clinic_reference varchar(191) NOT NULL DEFAULT '', source_reference varchar(191) NOT NULL DEFAULT '',
                purpose varchar(64) NOT NULL, scope_json longtext NOT NULL, status varchar(32) NOT NULL,
                starts_at datetime DEFAULT NULL, ends_at datetime DEFAULT NULL, authorized_by bigint unsigned NOT NULL,
                reason_cipher longtext DEFAULT NULL, row_version bigint unsigned NOT NULL DEFAULT 1,
                created_at datetime NOT NULL, updated_at datetime NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY relationship_uuid (relationship_uuid), KEY patient_doctor_status (patient_uuid,doctor_user_id,status), KEY purpose_status (purpose,status)
            ) $charset;",
            "CREATE TABLE {$t('consents')} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                consent_uuid char(36) NOT NULL, patient_uuid char(36) NOT NULL, purpose varchar(64) NOT NULL,
                notice_version varchar(64) NOT NULL, status varchar(32) NOT NULL, subject_hash char(64) NOT NULL,
                guardian_reference_cipher longtext DEFAULT NULL, evidence_cipher longtext NOT NULL,
                granted_at datetime DEFAULT NULL, withdrawn_at datetime DEFAULT NULL, expires_at datetime DEFAULT NULL,
                withdrawal_reason_cipher longtext DEFAULT NULL, row_version bigint unsigned NOT NULL DEFAULT 1,
                created_at datetime NOT NULL, updated_at datetime NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY consent_uuid (consent_uuid), KEY patient_purpose_status (patient_uuid,purpose,status), KEY expires_at (expires_at)
            ) $charset;",
            "CREATE TABLE {$t('encounters')} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                encounter_uuid char(36) NOT NULL, patient_uuid char(36) NOT NULL, relationship_uuid char(36) NOT NULL,
                parent_encounter_uuid char(36) DEFAULT NULL, encounter_type varchar(32) NOT NULL, mode varchar(32) NOT NULL,
                location_reference varchar(191) NOT NULL DEFAULT '', starts_at datetime NOT NULL, ends_at datetime DEFAULT NULL,
                status varchar(32) NOT NULL, content_cipher longtext NOT NULL, content_hash char(64) NOT NULL,
                template_key varchar(64) NOT NULL, template_version varchar(32) NOT NULL, author_user_id bigint unsigned NOT NULL,
                signed_by_user_id bigint unsigned DEFAULT NULL, signed_at datetime DEFAULT NULL, signature char(64) DEFAULT NULL,
                snapshot_cipher longtext DEFAULT NULL, error_reason_cipher longtext DEFAULT NULL,
                row_version bigint unsigned NOT NULL DEFAULT 1, created_at datetime NOT NULL, updated_at datetime NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY encounter_uuid (encounter_uuid), KEY patient_status_time (patient_uuid,status,starts_at), KEY parent_encounter_uuid (parent_encounter_uuid)
            ) $charset;",
            "CREATE TABLE {$t('observations')} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                observation_uuid char(36) NOT NULL, patient_uuid char(36) NOT NULL, encounter_uuid char(36) NOT NULL,
                observation_type varchar(64) NOT NULL, value_cipher longtext NOT NULL, provenance_cipher longtext NOT NULL,
                status varchar(32) NOT NULL DEFAULT 'active', author_user_id bigint unsigned NOT NULL,
                corrected_by_uuid char(36) DEFAULT NULL, row_version bigint unsigned NOT NULL DEFAULT 1,
                created_at datetime NOT NULL, updated_at datetime NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY observation_uuid (observation_uuid), KEY patient_encounter (patient_uuid,encounter_uuid)
            ) $charset;",
            "CREATE TABLE {$t('attachments')} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                attachment_uuid char(36) NOT NULL, patient_uuid char(36) NOT NULL, encounter_uuid char(36) NOT NULL,
                asset_reference_cipher longtext NOT NULL, sha256 char(64) NOT NULL, declared_type varchar(127) NOT NULL,
                detected_type varchar(127) DEFAULT NULL, source_cipher longtext NOT NULL, scan_status varchar(32) NOT NULL,
                scanner_evidence_cipher longtext DEFAULT NULL, interpretation_status varchar(32) NOT NULL,
                author_user_id bigint unsigned NOT NULL, row_version bigint unsigned NOT NULL DEFAULT 1,
                created_at datetime NOT NULL, updated_at datetime NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY attachment_uuid (attachment_uuid), KEY patient_scan (patient_uuid,scan_status), KEY sha256 (sha256)
            ) $charset;",
            "CREATE TABLE {$t('assessments')} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                assessment_uuid char(36) NOT NULL, patient_uuid char(36) NOT NULL, encounter_uuid char(36) NOT NULL,
                assessment_cipher longtext NOT NULL, assessment_hash char(64) NOT NULL, author_user_id bigint unsigned NOT NULL,
                status varchar(32) NOT NULL DEFAULT 'draft', signed_at datetime DEFAULT NULL, signature char(64) DEFAULT NULL,
                row_version bigint unsigned NOT NULL DEFAULT 1, created_at datetime NOT NULL, updated_at datetime NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY assessment_uuid (assessment_uuid), KEY patient_encounter (patient_uuid,encounter_uuid)
            ) $charset;",
            "CREATE TABLE {$t('prescriptions')} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                prescription_uuid char(36) NOT NULL, patient_uuid char(36) NOT NULL, encounter_uuid char(36) NOT NULL,
                parent_prescription_uuid char(36) DEFAULT NULL, status varchar(32) NOT NULL, order_cipher longtext NOT NULL,
                order_hash char(64) NOT NULL, author_user_id bigint unsigned NOT NULL, signed_by_user_id bigint unsigned DEFAULT NULL,
                signed_at datetime DEFAULT NULL, signature char(64) DEFAULT NULL, snapshot_cipher longtext DEFAULT NULL,
                effective_from datetime NOT NULL, effective_until datetime DEFAULT NULL, superseded_by_uuid char(36) DEFAULT NULL,
                discontinuation_reason_cipher longtext DEFAULT NULL, row_version bigint unsigned NOT NULL DEFAULT 1,
                created_at datetime NOT NULL, updated_at datetime NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY prescription_uuid (prescription_uuid), KEY patient_status_time (patient_uuid,status,effective_from), KEY encounter_uuid (encounter_uuid)
            ) $charset;",
            "CREATE TABLE {$t('followups')} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                followup_uuid char(36) NOT NULL, patient_uuid char(36) NOT NULL, prescription_uuid char(36) NOT NULL,
                status varchar(32) NOT NULL, due_at datetime NOT NULL, overdue_at datetime NOT NULL, reminder_policy_json longtext NOT NULL,
                questionnaire_cipher longtext NOT NULL, plan_cipher longtext NOT NULL, created_by_user_id bigint unsigned NOT NULL,
                reviewed_by_user_id bigint unsigned DEFAULT NULL, reviewed_at datetime DEFAULT NULL, closed_at datetime DEFAULT NULL,
                reschedule_reason_cipher longtext DEFAULT NULL, closure_reason_cipher longtext DEFAULT NULL,
                row_version bigint unsigned NOT NULL DEFAULT 1, created_at datetime NOT NULL, updated_at datetime NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY followup_uuid (followup_uuid), KEY patient_status_due (patient_uuid,status,due_at), KEY status_overdue (status,overdue_at), KEY prescription_uuid (prescription_uuid)
            ) $charset;",
            "CREATE TABLE {$t('outcomes')} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                outcome_uuid char(36) NOT NULL, followup_uuid char(36) NOT NULL, patient_uuid char(36) NOT NULL,
                response_cipher longtext NOT NULL, red_flag tinyint(1) NOT NULL DEFAULT 0, submitted_by_user_id bigint unsigned NOT NULL,
                review_status varchar(32) NOT NULL, review_cipher longtext DEFAULT NULL, reviewed_by_user_id bigint unsigned DEFAULT NULL,
                reviewed_at datetime DEFAULT NULL, row_version bigint unsigned NOT NULL DEFAULT 1,
                created_at datetime NOT NULL, updated_at datetime NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY outcome_uuid (outcome_uuid), KEY followup_uuid (followup_uuid), KEY patient_review (patient_uuid,review_status)
            ) $charset;",
            "CREATE TABLE {$t('access')} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                event_uuid char(36) NOT NULL, patient_uuid char(36) NOT NULL, actor_pseudonym char(64) NOT NULL,
                action varchar(64) NOT NULL, object_type varchar(64) NOT NULL, object_uuid varchar(64) NOT NULL,
                purpose varchar(64) NOT NULL, result varchar(32) NOT NULL, occurred_at datetime NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY event_uuid (event_uuid), KEY patient_time (patient_uuid,occurred_at), KEY actor_time (actor_pseudonym,occurred_at)
            ) $charset;",
            "CREATE TABLE {$t('rights')} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                case_uuid char(36) NOT NULL, patient_uuid char(36) NOT NULL, request_type varchar(32) NOT NULL,
                status varchar(32) NOT NULL, request_cipher longtext NOT NULL, decision_cipher longtext DEFAULT NULL,
                requested_by_user_id bigint unsigned NOT NULL, assigned_user_id bigint unsigned DEFAULT NULL,
                fulfilled_at datetime DEFAULT NULL, export_reference_cipher longtext DEFAULT NULL, export_token_hash char(64) DEFAULT NULL,
                export_token_expires_at datetime DEFAULT NULL, export_token_consumed_at datetime DEFAULT NULL,
                row_version bigint unsigned NOT NULL DEFAULT 1, created_at datetime NOT NULL, updated_at datetime NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY case_uuid (case_uuid), KEY patient_type_status (patient_uuid,request_type,status)
            ) $charset;",
            "CREATE TABLE {$t('breakglass')} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                grant_uuid char(36) NOT NULL, patient_uuid char(36) NOT NULL, actor_user_id bigint unsigned NOT NULL,
                status varchar(32) NOT NULL, reason_cipher longtext NOT NULL, context_cipher longtext NOT NULL,
                granted_at datetime NOT NULL, expires_at datetime NOT NULL, revoked_at datetime DEFAULT NULL,
                revocation_reason_cipher longtext DEFAULT NULL, review_status varchar(32) NOT NULL,
                review_cipher longtext DEFAULT NULL, reviewed_by_user_id bigint unsigned DEFAULT NULL, reviewed_at datetime DEFAULT NULL,
                row_version bigint unsigned NOT NULL DEFAULT 1, created_at datetime NOT NULL, updated_at datetime NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY grant_uuid (grant_uuid), KEY patient_actor_status (patient_uuid,actor_user_id,status), KEY expires_at (expires_at)
            ) $charset;",
            "CREATE TABLE {$t('audit')} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                event_uuid char(36) NOT NULL, actor_pseudonym char(64) NOT NULL, action varchar(64) NOT NULL,
                object_type varchar(64) NOT NULL, object_uuid varchar(64) NOT NULL, purpose varchar(64) NOT NULL,
                result varchar(32) NOT NULL, metadata_cipher longtext NOT NULL, record_hash char(64) NOT NULL,
                previous_hash char(64) NOT NULL, chain_hash char(64) NOT NULL, occurred_at datetime NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY event_uuid (event_uuid), UNIQUE KEY chain_hash (chain_hash), KEY object_time (object_type,object_uuid,occurred_at)
            ) $charset;",
            "CREATE TABLE {$t('outbox')} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                event_uuid char(36) NOT NULL, event_name varchar(96) NOT NULL, aggregate_uuid varchar(64) NOT NULL,
                payload_cipher longtext NOT NULL, status varchar(32) NOT NULL, attempts int unsigned NOT NULL DEFAULT 0,
                available_at datetime NOT NULL, last_error_code varchar(64) DEFAULT NULL, delivery_reference varchar(191) DEFAULT NULL,
                delivered_at datetime DEFAULT NULL, row_version bigint unsigned NOT NULL DEFAULT 1,
                created_at datetime NOT NULL, updated_at datetime NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY event_uuid (event_uuid), KEY status_available (status,available_at), KEY aggregate_uuid (aggregate_uuid)
            ) $charset;",
            "CREATE TABLE {$t('retention')} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                retention_uuid char(36) NOT NULL, object_type varchar(64) NOT NULL, object_uuid varchar(64) NOT NULL,
                policy_key varchar(64) NOT NULL, eligible_at datetime DEFAULT NULL, hold_status varchar(32) NOT NULL,
                holds_cipher longtext NOT NULL, status varchar(32) NOT NULL, purge_started_at datetime DEFAULT NULL,
                purged_at datetime DEFAULT NULL, purge_receipt_cipher longtext DEFAULT NULL,
                row_version bigint unsigned NOT NULL DEFAULT 1, created_at datetime NOT NULL, updated_at datetime NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY retention_uuid (retention_uuid), KEY eligible_hold_status (eligible_at,hold_status,status), KEY object_ref (object_type,object_uuid)
            ) $charset;",
            "CREATE TABLE {$t('migrations')} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                migration_uuid char(36) NOT NULL, migration_key varchar(96) NOT NULL, status varchar(32) NOT NULL,
                cursor_value varchar(191) NOT NULL DEFAULT '', receipt_cipher longtext NOT NULL, rollback_cipher longtext DEFAULT NULL,
                row_version bigint unsigned NOT NULL DEFAULT 1, started_at datetime NOT NULL, completed_at datetime DEFAULT NULL,
                created_at datetime NOT NULL, updated_at datetime NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY migration_uuid (migration_uuid), KEY migration_status (migration_key,status)
            ) $charset;",
            "CREATE TABLE {$t('commands')} (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                receipt_uuid char(36) NOT NULL, actor_pseudonym char(64) NOT NULL, command_name varchar(96) NOT NULL,
                key_hash char(64) NOT NULL, request_hash char(64) NOT NULL, status varchar(32) NOT NULL,
                response_cipher longtext DEFAULT NULL, error_code varchar(64) DEFAULT NULL,
                row_version bigint unsigned NOT NULL DEFAULT 1, created_at datetime NOT NULL, updated_at datetime NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY receipt_uuid (receipt_uuid), UNIQUE KEY key_hash (key_hash), KEY command_status (command_name,status)
            ) $charset;",
        );
    }

    private static function with_lock(callable $callback): void {
        if (get_transient(self::LOCK)) {
            throw new RuntimeException('A clinical migration is already running.');
        }
        set_transient(self::LOCK, CF01_DB::uuid(), 10 * MINUTE_IN_SECONDS);
        try {
            $callback();
        } finally {
            delete_transient(self::LOCK);
        }
    }

    private static function record(string $key, string $status, array $receipt, string $cursor = ''): string {
        $uuid = CF01_DB::uuid();
        CF01_DB::insert('migrations', array(
            'migration_uuid' => $uuid,
            'migration_key' => sanitize_key($key),
            'status' => sanitize_key($status),
            'cursor_value' => sanitize_text_field($cursor),
            'receipt_cipher' => CF01_Crypto::encrypt($receipt, 'migration-receipt'),
            'rollback_cipher' => null,
            'row_version' => 1,
            'started_at' => CF01_DB::now(),
            'completed_at' => $status === 'completed' ? CF01_DB::now() : null,
            'created_at' => CF01_DB::now(),
            'updated_at' => CF01_DB::now(),
        ));
        return $uuid;
    }

    private static function write_extracted_record(int $actor_id, array $record): void {
        $existing = CF01_DB::row('SELECT clinical_uuid FROM ' . CF01_DB::table('patients') . ' WHERE platform_subject_hash = %s LIMIT 1', array(CF01_Crypto::blind_index((string) $record['patient_platform_uuid'], 'platform-subject')));
        if ($existing) {
            return;
        }
        CF01_Patients::create($actor_id, (string) $record['patient_platform_uuid'], array(
            'display_name' => (string) ($record['display_name'] ?? ''),
            'date_of_birth' => (string) ($record['date_of_birth'] ?? ''),
            'sex' => (string) ($record['sex'] ?? ''),
            'language' => (string) ($record['language'] ?? 'en-US'),
            'time_zone' => (string) ($record['time_zone'] ?? 'Asia/Karachi'),
        ), (string) ($record['jurisdiction'] ?? 'PK'));
    }
}
