<?php
defined('ABSPATH') || exit;

/**
 * Coordinates high-risk release transitions without taking ownership away from
 * native modules. It validates immutable provider acceptance, compensates
 * partial activation, and supplies the canonical migration/rollback entrypoints.
 */
final class CF01_Release_Orchestrator {
    private const FILE08_CONTRACT_VERSION = '1.0.0';
    private const TRANSITION_OPTION = 'cf01_release_orchestration_pending';
    private const EVIDENCE_BACKUP_OPTION = 'cf01_activation_previous_evidence';
    private const MIGRATION_LOCK_OPTION = 'cf01_file08_orchestration_lock';
    private const SCHEDULES = array(
        'cf01_process_outbox' => array('offset' => 60, 'recurrence' => 'hourly'),
        'cf01_retention_reconcile' => array('offset' => 300, 'recurrence' => 'daily'),
        'cf01_followup_reconcile' => array('offset' => 120, 'recurrence' => 'hourly'),
    );
    private const NATIVE_CONTRACTS = array(
        'file00_membership' => array('owner' => 'File 00', 'contract' => 'smc.cf01.membership-assurance', 'version' => '1.1.2'),
        'file20_shell' => array('owner' => 'File 20', 'contract' => 'cf01.private-shell', 'version' => '1.0.0'),
        'file24_assurance' => array('owner' => 'File 24', 'contract' => 'cf01.assurance-manifest', 'version' => '1.0.0'),
        'file25_visual' => array('owner' => 'File 25', 'contract' => 'cf01.clinical-visual-components', 'version' => '1.0.0'),
    );

    private static bool $compensating = false;
    private static array $legacy_batch_context = array();

    public static function register(): void {
        add_filter('pre_update_option_cf01_activation_evidence', array(__CLASS__, 'capture_previous_evidence'), 20, 3);
        add_filter('pre_update_option_cf01_activation_state', array(__CLASS__, 'guard_activation_state'), 20, 3);
        add_action('updated_option', array(__CLASS__, 'after_activation_state'), 20, 3);
        add_action('shutdown', array(__CLASS__, 'recover_abandoned_transition'), 999);
        add_filter('cf01_file08_extraction_batch', array(__CLASS__, 'legacy_extraction_preflight'), 1, 3);
        add_filter('cf01_file08_extraction_batch', array(__CLASS__, 'legacy_extraction_result'), 999, 3);
        add_filter('cf01_rollback_migration', array(__CLASS__, 'legacy_rollback_commit'), 999, 3);
    }

    public static function capture_previous_evidence($new_value, $old_value, string $option) {
        if (self::$compensating || $new_value === $old_value) {
            return $new_value;
        }
        $state = (string) get_option('cf01_activation_state', 'disabled');
        if ($state !== 'enabled') {
            update_option(self::EVIDENCE_BACKUP_OPTION, array(
                'existed' => $old_value !== false && $old_value !== null && $old_value !== '',
                'value' => $old_value,
                'captured_at' => CF01_DB::now(),
            ), false);
        }
        return $new_value;
    }

    public static function guard_activation_state($new_value, $old_value, string $option) {
        $new = sanitize_key((string) $new_value);
        $old = sanitize_key((string) $old_value);
        if ($new === 'enabled' && $old !== 'enabled') {
            $cipher = get_option('cf01_activation_evidence', '');
            $evidence = is_string($cipher) && $cipher !== '' ? CF01_Crypto::decrypt($cipher, 'activation-evidence') : null;
            if (!is_array($evidence)) {
                self::compensate_activation('activation_evidence_unavailable');
                throw new RuntimeException('Structured activation evidence is unavailable.');
            }
            try {
                $validation = CF01_Activation_Evidence::validate($evidence);
                $contracts = self::validate_native_owner_contracts($evidence, get_current_user_id());
                $added = self::ensure_schedules();
                update_option(self::TRANSITION_OPTION, array(
                    'type' => 'activation',
                    'fingerprint' => (string) $validation['fingerprint'],
                    'added_hooks' => $added,
                    'contract_fingerprint' => (string) $contracts['fingerprint'],
                    'started_at' => CF01_DB::now(),
                ), false);
            } catch (Throwable $error) {
                self::compensate_activation('activation_preflight_failed');
                throw $error;
            }
        }
        if ($new === 'disabled' && in_array($old, array('enabled', 'activating', 'degraded'), true)) {
            self::clear_and_verify_schedules();
            update_option(self::TRANSITION_OPTION, array(
                'type' => 'disable',
                'started_at' => CF01_DB::now(),
            ), false);
        }
        return $new_value;
    }

    public static function after_activation_state(string $option, $old_value, $new_value): void {
        if ($option !== 'cf01_activation_state') {
            return;
        }
        if ((string) $new_value === 'enabled') {
            $pending = get_option(self::TRANSITION_OPTION, array());
            if (!is_array($pending) || ($pending['type'] ?? '') !== 'activation') {
                self::compensate_activation('activation_receipt_missing');
                return;
            }
            update_option('cf01_release_orchestration_receipt', array(
                'type' => 'activation',
                'fingerprint' => sanitize_text_field((string) ($pending['fingerprint'] ?? '')),
                'contract_fingerprint' => sanitize_text_field((string) ($pending['contract_fingerprint'] ?? '')),
                'completed_at' => CF01_DB::now(),
                'generation' => (int) get_option('cf01_activation_generation', 0),
            ), false);
            self::delete_option(self::EVIDENCE_BACKUP_OPTION);
            self::delete_option(self::TRANSITION_OPTION);
            return;
        }
        if ((string) $new_value === 'disabled') {
            update_option('cf01_release_orchestration_receipt', array(
                'type' => 'disable',
                'completed_at' => CF01_DB::now(),
                'generation' => (int) get_option('cf01_activation_generation', 0),
                'schedules_cleared' => true,
            ), false);
            self::delete_option(self::TRANSITION_OPTION);
        }
    }

    public static function recover_abandoned_transition(): void {
        $pending = get_option(self::TRANSITION_OPTION, null);
        if (!is_array($pending) || ($pending['type'] ?? '') !== 'activation') {
            return;
        }
        if ((string) get_option('cf01_activation_state', 'disabled') !== 'enabled') {
            self::compensate_activation('abandoned_activation_transition');
        }
    }

    public static function validate_native_owner_contracts(array $evidence, int $actor_id): array {
        $release = is_array($evidence['release'] ?? null) ? $evidence['release'] : array();
        $head = strtolower(trim((string) ($release['head_sha'] ?? '')));
        $environment = sanitize_key((string) ($evidence['environment'] ?? ''));
        $site_fingerprint = strtolower(trim((string) ($evidence['site_fingerprint'] ?? '')));
        $proofs = is_array($evidence['native_owner_contracts'] ?? null) ? $evidence['native_owner_contracts'] : array();
        $errors = array();
        $normalized = array();

        foreach (self::NATIVE_CONTRACTS as $key => $expected) {
            $proof = $proofs[$key] ?? null;
            if (!is_array($proof)) {
                $errors[] = $key . ': structured native-owner acceptance is required';
                continue;
            }
            foreach (array('owner', 'contract', 'contract_version', 'status', 'document_hash', 'tested_head', 'environment', 'site_fingerprint', 'accepted_at', 'expires_at') as $field) {
                if (!isset($proof[$field]) || trim((string) $proof[$field]) === '') {
                    $errors[] = $key . '.' . $field . ': required';
                }
            }
            if (!hash_equals($expected['owner'], (string) ($proof['owner'] ?? ''))) {
                $errors[] = $key . '.owner: canonical owner mismatch';
            }
            if (!hash_equals($expected['contract'], (string) ($proof['contract'] ?? ''))) {
                $errors[] = $key . '.contract: contract identity mismatch';
            }
            if (!hash_equals($expected['version'], (string) ($proof['contract_version'] ?? ''))) {
                $errors[] = $key . '.contract_version: incompatible or downgraded contract';
            }
            if (($proof['status'] ?? '') !== 'accepted' || !empty($proof['revoked']) || !empty($proof['suspended'])) {
                $errors[] = $key . '.status: current accepted non-revoked contract is required';
            }
            if (!self::sha256((string) ($proof['document_hash'] ?? ''))) {
                $errors[] = $key . '.document_hash: SHA-256 is required';
            }
            if (!self::sha1($head) || !hash_equals($head, strtolower((string) ($proof['tested_head'] ?? '')))) {
                $errors[] = $key . '.tested_head: exact release head mismatch';
            }
            if (!hash_equals($environment, sanitize_key((string) ($proof['environment'] ?? '')))) {
                $errors[] = $key . '.environment: target environment mismatch';
            }
            if (!hash_equals($site_fingerprint, strtolower((string) ($proof['site_fingerprint'] ?? '')))) {
                $errors[] = $key . '.site_fingerprint: site binding mismatch';
            }
            if (!self::non_future((string) ($proof['accepted_at'] ?? ''))) {
                $errors[] = $key . '.accepted_at: valid non-future acceptance time is required';
            }
            if (!CF01_Authorization::not_expired((string) ($proof['expires_at'] ?? ''))) {
                $errors[] = $key . '.expires_at: native-owner acceptance expired';
            }
            $normalized[$key] = array(
                'owner' => $expected['owner'],
                'contract' => $expected['contract'],
                'contract_version' => $expected['version'],
                'document_hash' => strtolower((string) ($proof['document_hash'] ?? '')),
            );
        }

        $membership = CF01_Contracts::membership($actor_id);
        $recent = CF01_Contracts::recent_auth($actor_id, 'activate_module');
        $shell = CF01_Contracts::shell_routes();
        $visual = CF01_Contracts::visual_components();
        $assurance = CF01_Contracts::register_assurance();
        if (empty($membership['valid']) || empty($membership['approved']) || !empty($membership['suspended'])) {
            $errors[] = 'file00_membership: current activating membership is not accepted';
        }
        if (empty($recent['valid']) || empty($recent['recent_auth']) || empty($recent['step_up']) || !CF01_Authorization::not_expired((string) ($recent['expires_at'] ?? '')) || !hash_equals((string) ($membership['platform_uuid'] ?? ''), (string) ($recent['subject_uuid'] ?? ''))) {
            $errors[] = 'file00_membership: current step-up subject assurance is unavailable';
        }
        if (empty($shell['valid']) || empty($shell['registered']) || empty($shell['private']) || empty($shell['no_store']) || !hash_equals('1.0.0', (string) ($shell['contract_version'] ?? ''))) {
            $errors[] = 'file20_shell: accepted private no-store shell registration is unavailable';
        }
        if (empty($visual['valid']) || empty($visual['accepted']) || empty($visual['rtl']) || empty($visual['accessibility']) || !hash_equals('1.0.0', (string) ($visual['contract_version'] ?? ''))) {
            $errors[] = 'file25_visual: accepted RTL/accessibility visual contract is unavailable';
        }
        if (empty($assurance['valid']) || empty($assurance['registered']) || empty($assurance['native_enforcement_preserved']) || !hash_equals('1.0.0', (string) ($assurance['contract_version'] ?? ''))) {
            $errors[] = 'file24_assurance: assurance registration must preserve native CF-01 enforcement';
        }

        if ($errors) {
            throw new RuntimeException('Native-owner activation contracts are invalid: ' . implode('; ', $errors));
        }
        return array(
            'valid' => true,
            'contracts' => $normalized,
            'fingerprint' => hash('sha256', CF01_Crypto::canonical_json($normalized)),
            'validated_at' => CF01_DB::now(),
        );
    }

    /**
     * Canonical File 08 extraction entrypoint. The legacy method remains for
     * compatibility but real write batches must use this fail-closed path.
     */
    public static function extract_file08_batch(int $actor_id, array $batch, string $cursor = ''): array {
        self::authorize_governance_actor($actor_id, 'run_clinical_migration', 'cf01_run_clinical_migrations');
        if ((string) get_option('cf01_activation_state', 'disabled') !== 'disabled') {
            throw new RuntimeException('Clinical extraction requires an explicitly disabled runtime.');
        }
        $validated = self::validate_file08_batch($batch, $cursor);
        $claim_key = 'cf01_file08_claim_' . substr(hash('sha256', $validated['migration_id'] . '|' . $cursor), 0, 40);
        $claim = get_option($claim_key, null);
        if (is_array($claim)) {
            if (!hash_equals((string) ($claim['request_hash'] ?? ''), $validated['request_hash'])) {
                throw new RuntimeException('Migration batch identity was replayed with different content.');
            }
            if (($claim['status'] ?? '') === 'completed' && is_array($claim['receipt'] ?? null)) {
                $receipt = $claim['receipt'];
                $receipt['replayed'] = true;
                return $receipt;
            }
        }

        self::acquire_migration_lock($validated['migration_id']);
        update_option($claim_key, array('status' => 'in_progress', 'request_hash' => $validated['request_hash'], 'started_at' => CF01_DB::now()), false);
        try {
            $result = apply_filters('cf01_file08_extraction_batch', null, $batch, $cursor);
            self::validate_file08_result($result, $validated);
            $counts = array('seen' => 0, 'eligible' => 0, 'quarantined' => 0, 'written' => 0, 'existing' => 0);
            CF01_DB::transaction(function () use ($actor_id, $batch, $result, &$counts): void {
                foreach ((array) $result['records'] as $record) {
                    $counts['seen']++;
                    if (!is_array($record) || empty($record['source_reference']) || empty($record['patient_platform_uuid'])) {
                        $counts['quarantined']++;
                        continue;
                    }
                    $counts['eligible']++;
                    if (!empty($batch['dry_run'])) {
                        continue;
                    }
                    $written = self::write_file08_patient($actor_id, $record);
                    $counts[$written ? 'written' : 'existing']++;
                }
            });
            $receipt = array(
                'migration_id' => $validated['migration_id'],
                'request_hash' => $validated['request_hash'],
                'source_snapshot_sha256' => $validated['source_snapshot_sha256'],
                'source_contract_version' => self::FILE08_CONTRACT_VERSION,
                'cursor' => $cursor,
                'next_cursor' => sanitize_text_field((string) $result['next_cursor']),
                'complete' => !empty($result['complete']),
                'dry_run' => (bool) $batch['dry_run'],
                'counts' => $counts,
                'integrity_root' => hash('sha256', CF01_Crypto::canonical_json(array($validated['request_hash'], $counts, (string) $result['next_cursor']))),
                'completed_at' => CF01_DB::now(),
                'replayed' => false,
            );
            self::record_migration_batch($receipt);
            update_option($claim_key, array('status' => 'completed', 'request_hash' => $validated['request_hash'], 'receipt' => $receipt), false);
            return $receipt;
        } catch (Throwable $error) {
            update_option($claim_key, array('status' => 'failed', 'request_hash' => $validated['request_hash'], 'failed_at' => CF01_DB::now(), 'error_code' => 'migration_batch_failed'), false);
            throw $error;
        } finally {
            self::release_migration_lock();
        }
    }

    public static function rollback_migration(int $actor_id, string $migration_uuid, string $reason): array {
        self::authorize_governance_actor($actor_id, 'run_clinical_rollback', 'cf01_run_clinical_migrations');
        if ((string) get_option('cf01_activation_state', 'disabled') !== 'disabled') {
            throw new RuntimeException('Clinical rollback requires an explicitly disabled runtime.');
        }
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Rollback reason is required.');
        }
        $migration = CF01_DB::row('SELECT * FROM ' . CF01_DB::table('migrations') . ' WHERE migration_uuid = %s LIMIT 1', array($migration_uuid));
        if (!$migration || ($migration['status'] ?? '') !== 'completed') {
            throw new RuntimeException('A completed unreversed migration batch is required.');
        }
        $receipt = apply_filters('cf01_rollback_provider_receipt', null, $migration, $reason);
        return self::commit_rollback_receipt($receipt, $migration, $reason);
    }

    public static function legacy_extraction_preflight($result, array $batch, string $cursor) {
        self::$legacy_batch_context = self::validate_file08_batch($batch, $cursor);
        return $result;
    }

    public static function legacy_extraction_result($result, array $batch, string $cursor) {
        if (!self::$legacy_batch_context) {
            throw new RuntimeException('Legacy extraction preflight context is unavailable.');
        }
        self::validate_file08_result($result, self::$legacy_batch_context);
        return $result;
    }

    public static function legacy_rollback_commit($receipt, array $migration, string $reason): array {
        return self::commit_rollback_receipt($receipt, $migration, $reason);
    }

    private static function validate_file08_batch(array $batch, string $cursor): array {
        foreach (array('migration_id', 'source_snapshot_sha256', 'source_contract_version', 'reconciliation_plan', 'rollback_plan') as $field) {
            if (!array_key_exists($field, $batch) || $batch[$field] === '' || $batch[$field] === null) {
                throw new InvalidArgumentException('Migration evidence is incomplete: ' . $field);
            }
        }
        if (!array_key_exists('dry_run', $batch) || !is_bool($batch['dry_run'])) {
            throw new InvalidArgumentException('Migration dry_run must be an explicit boolean.');
        }
        $migration_id = sanitize_text_field((string) $batch['migration_id']);
        if (!self::uuid($migration_id)) {
            throw new InvalidArgumentException('Migration ID must be a valid UUID.');
        }
        $snapshot = strtolower(trim((string) $batch['source_snapshot_sha256']));
        if (!self::sha256($snapshot)) {
            throw new InvalidArgumentException('Source snapshot SHA-256 is required.');
        }
        if (!hash_equals(self::FILE08_CONTRACT_VERSION, (string) $batch['source_contract_version'])) {
            throw new RuntimeException('File 08 extraction contract is incompatible or downgraded.');
        }
        if (!is_array($batch['reconciliation_plan']) || empty($batch['reconciliation_plan']['evidence_id']) || !self::sha256((string) ($batch['reconciliation_plan']['document_hash'] ?? ''))) {
            throw new InvalidArgumentException('A structured reconciliation plan is required.');
        }
        if (!is_array($batch['rollback_plan']) || empty($batch['rollback_plan']['evidence_id']) || !self::sha256((string) ($batch['rollback_plan']['document_hash'] ?? ''))) {
            throw new InvalidArgumentException('A structured rollback plan is required.');
        }
        if (strlen($cursor) > 191 || ($cursor !== '' && !preg_match('/^[A-Za-z0-9._:\-]+$/', $cursor))) {
            throw new InvalidArgumentException('Migration cursor is malformed.');
        }
        $request_hash = hash('sha256', CF01_Crypto::canonical_json(array(
            'migration_id' => $migration_id,
            'source_snapshot_sha256' => $snapshot,
            'source_contract_version' => self::FILE08_CONTRACT_VERSION,
            'dry_run' => $batch['dry_run'],
            'cursor' => $cursor,
            'reconciliation_plan' => $batch['reconciliation_plan'],
            'rollback_plan' => $batch['rollback_plan'],
        )));
        return array(
            'migration_id' => $migration_id,
            'source_snapshot_sha256' => $snapshot,
            'request_hash' => $request_hash,
        );
    }

    private static function validate_file08_result($result, array $validated): void {
        if (!is_array($result) || !isset($result['records'], $result['next_cursor'], $result['complete'])) {
            throw new RuntimeException('File 08 extraction provider returned an invalid batch.');
        }
        if (!hash_equals($validated['migration_id'], (string) ($result['migration_id'] ?? ''))) {
            throw new RuntimeException('File 08 extraction result migration identity mismatch.');
        }
        if (!hash_equals($validated['source_snapshot_sha256'], strtolower((string) ($result['source_snapshot_sha256'] ?? '')))) {
            throw new RuntimeException('File 08 extraction result snapshot mismatch.');
        }
        if (!hash_equals(self::FILE08_CONTRACT_VERSION, (string) ($result['source_contract_version'] ?? ''))) {
            throw new RuntimeException('File 08 extraction provider contract mismatch.');
        }
        if (!is_array($result['records']) || strlen((string) $result['next_cursor']) > 191) {
            throw new RuntimeException('File 08 extraction provider returned unsafe records or cursor.');
        }
    }

    private static function write_file08_patient(int $actor_id, array $record): bool {
        $platform_uuid = trim((string) $record['patient_platform_uuid']);
        $subject_hash = CF01_Crypto::blind_index($platform_uuid, 'platform-subject');
        $existing = CF01_DB::row('SELECT clinical_uuid FROM ' . CF01_DB::table('patients') . ' WHERE platform_subject_hash = %s LIMIT 1', array($subject_hash));
        if ($existing) {
            return false;
        }
        $date_of_birth = (string) ($record['date_of_birth'] ?? '');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $date_of_birth, new DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d') !== $date_of_birth || $date->getTimestamp() > time()) {
            throw new RuntimeException('File 08 record has an invalid date of birth.');
        }
        $clinical_uuid = CF01_DB::uuid();
        $demographics = array(
            'display_name' => sanitize_text_field((string) ($record['display_name'] ?? '')),
            'date_of_birth' => $date_of_birth,
            'sex' => sanitize_key((string) ($record['sex'] ?? 'unspecified')),
            'language' => sanitize_text_field((string) ($record['language'] ?? 'en-US')),
            'time_zone' => sanitize_text_field((string) ($record['time_zone'] ?? 'Asia/Karachi')),
        );
        CF01_DB::insert('patients', array(
            'clinical_uuid' => $clinical_uuid,
            'platform_subject_hash' => $subject_hash,
            'platform_subject_cipher' => CF01_Crypto::encrypt($platform_uuid, 'patient-platform-link'),
            'demographics_cipher' => CF01_Crypto::encrypt($demographics, 'patient-demographics'),
            'guardian_context_cipher' => CF01_Crypto::encrypt(array(), 'guardian-context'),
            'jurisdiction' => sanitize_text_field((string) ($record['jurisdiction'] ?? 'PK')),
            'status' => 'active',
            'row_version' => 1,
            'created_at' => CF01_DB::now(),
            'updated_at' => CF01_DB::now(),
        ));
        CF01_Audit::record($actor_id, 'ClinicalPatientExtracted', 'clinical_patient', $clinical_uuid, 'migration', array('source_reference_hash' => hash('sha256', (string) $record['source_reference'])));
        CF01_Outbox::enqueue('ClinicalPatientLinked', array('clinical_uuid' => $clinical_uuid), $clinical_uuid);
        return true;
    }

    private static function record_migration_batch(array $receipt): void {
        CF01_DB::insert('migrations', array(
            'migration_uuid' => CF01_DB::uuid(),
            'migration_key' => 'file08_' . substr(hash('sha256', (string) $receipt['migration_id']), 0, 40),
            'status' => !empty($receipt['complete']) ? 'completed' : 'in_progress',
            'cursor_value' => sanitize_text_field((string) $receipt['next_cursor']),
            'receipt_cipher' => CF01_Crypto::encrypt($receipt, 'migration-receipt'),
            'rollback_cipher' => null,
            'row_version' => 1,
            'started_at' => CF01_DB::now(),
            'completed_at' => !empty($receipt['complete']) ? CF01_DB::now() : null,
            'created_at' => CF01_DB::now(),
            'updated_at' => CF01_DB::now(),
        ));
    }

    private static function commit_rollback_receipt($receipt, array $migration, string $reason): array {
        if (!is_array($receipt)
            || empty($receipt['completed'])
            || empty($receipt['reconciled'])
            || !self::uuid((string) ($receipt['rollback_id'] ?? ''))
            || !hash_equals((string) $migration['migration_uuid'], (string) ($receipt['migration_uuid'] ?? ''))
            || !self::sha256((string) ($receipt['source_integrity_root'] ?? ''))
            || !self::sha256((string) ($receipt['post_rollback_integrity_root'] ?? ''))
            || !hash_equals((string) $receipt['source_integrity_root'], (string) $receipt['post_rollback_integrity_root'])
            || !isset($receipt['expected_counts'], $receipt['post_counts'])
            || (array) $receipt['expected_counts'] !== (array) $receipt['post_counts']
            || !empty($receipt['orphaned_writes'])
            || !empty($receipt['authorization_drift'])
        ) {
            throw new RuntimeException('Verified rollback, reconciliation and integrity receipt is required.');
        }
        $result = CF01_DB::transaction(function () use ($receipt, $migration, $reason): array {
            $updated = CF01_DB::update_versioned('migrations', array(
                'status' => 'rolled_back',
                'rollback_cipher' => CF01_Crypto::encrypt(array('reason' => trim($reason), 'receipt' => $receipt), 'migration-rollback'),
                'completed_at' => CF01_DB::now(),
            ), array('migration_uuid' => (string) $migration['migration_uuid']), (int) $migration['row_version']);
            if (!$updated) {
                throw new RuntimeException('Migration rollback changed concurrently or was already applied.');
            }
            return $receipt;
        });
        CF01_Audit::record(get_current_user_id(), 'ClinicalMigrationRolledBack', 'migration', (string) $migration['migration_uuid'], 'migration', array('rollback_id' => (string) $receipt['rollback_id']));
        $result['orchestrator_committed'] = true;
        return $result;
    }

    private static function authorize_governance_actor(int $actor_id, string $purpose, string $capability): void {
        if ($actor_id <= 0 || get_current_user_id() !== $actor_id) {
            throw new RuntimeException('Explicit current governance actor identity is required.');
        }
        $allowed = function_exists('user_can') ? user_can($actor_id, $capability) : current_user_can($capability);
        if (!$allowed) {
            throw new RuntimeException('Current governance capability is required.');
        }
        $membership = CF01_Contracts::membership($actor_id);
        $recent = CF01_Contracts::recent_auth($actor_id, $purpose);
        if (empty($membership['valid']) || empty($membership['approved']) || !empty($membership['suspended'])
            || empty($recent['valid']) || empty($recent['recent_auth']) || empty($recent['step_up'])
            || !CF01_Authorization::not_expired((string) ($recent['expires_at'] ?? ''))
            || !hash_equals((string) ($membership['platform_uuid'] ?? ''), (string) ($recent['subject_uuid'] ?? ''))
        ) {
            throw new RuntimeException('Current membership and recent step-up governance assurance are required.');
        }
    }

    private static function ensure_schedules(): array {
        $added = array();
        foreach (self::SCHEDULES as $hook => $spec) {
            $already = wp_next_scheduled($hook);
            if (!$already) {
                $result = wp_schedule_event(time() + (int) $spec['offset'], (string) $spec['recurrence'], $hook);
                $result = apply_filters('cf01_schedule_event_result', $result, $hook, $spec);
                if (wp_next_scheduled($hook)) {
                    $added[] = $hook;
                }
                if ($result === false || (function_exists('is_wp_error') && is_wp_error($result))) {
                    self::clear_hooks($added);
                    throw new RuntimeException('Mandatory clinical schedule could not be created: ' . $hook);
                }
            }
            if (!wp_next_scheduled($hook)) {
                self::clear_hooks($added);
                throw new RuntimeException('Mandatory clinical schedule is not verifiably active: ' . $hook);
            }
        }
        return $added;
    }

    private static function clear_and_verify_schedules(): void {
        self::clear_hooks(array_keys(self::SCHEDULES));
        foreach (array_keys(self::SCHEDULES) as $hook) {
            if (wp_next_scheduled($hook)) {
                throw new RuntimeException('Clinical schedule could not be cleared safely: ' . $hook);
            }
        }
    }

    private static function clear_hooks(array $hooks): void {
        foreach (array_unique($hooks) as $hook) {
            wp_clear_scheduled_hook((string) $hook);
        }
    }

    private static function compensate_activation(string $reason): void {
        self::$compensating = true;
        try {
            self::clear_hooks(array_keys(self::SCHEDULES));
            $backup = get_option(self::EVIDENCE_BACKUP_OPTION, null);
            if (is_array($backup)) {
                if (!empty($backup['existed'])) {
                    update_option('cf01_activation_evidence', $backup['value'], false);
                } else {
                    self::delete_option('cf01_activation_evidence');
                }
            }
            self::delete_option('cf01_pending_activation_fingerprint');
            self::delete_option('cf01_activation_transition_lock');
            self::delete_option(self::TRANSITION_OPTION);
            update_option('cf01_activation_compensation_receipt', array('reason' => sanitize_key($reason), 'compensated_at' => CF01_DB::now()), false);
        } finally {
            self::$compensating = false;
        }
    }

    private static function acquire_migration_lock(string $migration_id): void {
        $existing = get_option(self::MIGRATION_LOCK_OPTION, null);
        if (is_array($existing) && (int) ($existing['expires_at'] ?? 0) > time()) {
            throw new RuntimeException('Another clinical migration batch is already running.');
        }
        $value = array('migration_id' => $migration_id, 'expires_at' => time() + 600);
        if (function_exists('add_option')) {
            self::delete_option(self::MIGRATION_LOCK_OPTION);
            if (!add_option(self::MIGRATION_LOCK_OPTION, $value, '', false)) {
                throw new RuntimeException('Another clinical migration batch is already running.');
            }
            return;
        }
        update_option(self::MIGRATION_LOCK_OPTION, $value, false);
    }

    private static function release_migration_lock(): void {
        self::delete_option(self::MIGRATION_LOCK_OPTION);
    }

    private static function delete_option(string $key): void {
        if (function_exists('delete_option')) {
            delete_option($key);
            return;
        }
        update_option($key, null, false);
    }

    private static function non_future(string $value): bool {
        try {
            return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->getTimestamp() <= time();
        } catch (Throwable $error) {
            return false;
        }
    }

    private static function uuid(string $value): bool {
        return (bool) preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $value);
    }

    private static function sha1(string $value): bool {
        return (bool) preg_match('/^[a-f0-9]{40}$/i', $value);
    }

    private static function sha256(string $value): bool {
        return (bool) preg_match('/^[a-f0-9]{64}$/i', $value);
    }
}
