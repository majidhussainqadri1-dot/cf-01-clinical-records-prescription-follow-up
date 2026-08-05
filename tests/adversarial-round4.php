<?php
require __DIR__ . '/bootstrap.php';
require_once CF01_DIR . 'includes/class-cf01-activation-evidence.php';
require_once CF01_DIR . 'includes/class-cf01-release-orchestrator.php';

if (!function_exists('add_option')) {
    function add_option($key, $value, $deprecated = '', $autoload = 'yes'): bool {
        if (array_key_exists($key, $GLOBALS['cf01_options'])) {
            return false;
        }
        $GLOBALS['cf01_options'][$key] = $value;
        return true;
    }
}
if (!function_exists('delete_option')) {
    function delete_option($key): bool {
        $exists = array_key_exists($key, $GLOBALS['cf01_options']);
        unset($GLOBALS['cf01_options'][$key]);
        return $exists;
    }
}

$GLOBALS['cf01_force_schedule_failure'] = false;
add_filter('cf01_schedule_event_result', static function ($result, string $hook) {
    if (!empty($GLOBALS['cf01_force_schedule_failure']) && $hook === 'cf01_retention_reconcile') {
        return false;
    }
    return $result;
}, 10, 2);

add_filter('cf01_file08_extraction_batch', static function ($result, array $batch, string $cursor): array {
    return array(
        'migration_id' => (string) $batch['migration_id'],
        'source_snapshot_sha256' => (string) $batch['source_snapshot_sha256'],
        'source_contract_version' => (string) $batch['source_contract_version'],
        'records' => array(array(
            'source_reference' => 'file08-record-001',
            'patient_platform_uuid' => 'platform-user-88',
            'display_name' => 'Synthetic Subject',
            'date_of_birth' => '1992-04-17',
            'sex' => 'unspecified',
            'language' => 'en-US',
            'time_zone' => 'Asia/Karachi',
            'jurisdiction' => 'PK',
        )),
        'next_cursor' => '',
        'complete' => true,
    );
}, 10, 3);

add_filter('cf01_rollback_provider_receipt', static function ($result, array $migration, string $reason): array {
    $root = hash('sha256', 'synthetic-rollback-root');
    return array(
        'completed' => true,
        'reconciled' => true,
        'rollback_id' => '123e4567-e89b-42d3-a456-426614174111',
        'migration_uuid' => (string) $migration['migration_uuid'],
        'source_integrity_root' => $root,
        'post_rollback_integrity_root' => $root,
        'expected_counts' => array('patients' => 0),
        'post_counts' => array('patients' => 0),
        'orphaned_writes' => false,
        'authorization_drift' => false,
        'provider_reference' => 'synthetic-provider-receipt',
    );
}, 10, 3);

CF01_Release_Orchestrator::register();

$count = 0;
$failures = array();
$check = static function (bool $condition, string $message) use (&$count, &$failures): void {
    $count++;
    if (!$condition) {
        $failures[] = $message;
    }
};
$expect = static function (callable $callback, string $message, string $contains = '') use (&$count, &$failures): void {
    $count++;
    try {
        $callback();
        $failures[] = $message;
    } catch (Throwable $error) {
        if ($contains !== '' && !str_contains($error->getMessage(), $contains)) {
            $failures[] = $message . ' — unexpected: ' . $error->getMessage();
        }
    }
};

$activationEvidence = static function (): array {
    $head = str_repeat('a', 40);
    $environment = 'staging';
    $issued = time() - 60;
    $accepted = time() - 180;
    $site = hash('sha256', strtolower(rtrim(home_url('/'), '/')) . '|' . $environment);
    $gates = array(
        'founder_approval', 'legal_professional_acceptance', 'independent_security_acceptance',
        'staging_acceptance', 'backup_restore_acceptance', 'rollback_rehearsal', 'operations_staffing',
    );
    $evidence = array(
        'activation_id' => '123e4567-e89b-42d3-a456-426614174000',
        'activation_nonce' => str_repeat('N', 40),
        'environment' => $environment,
        'issued_at' => gmdate('c', $issued),
        'expires_at' => gmdate('c', $issued + 3600),
        'site_fingerprint' => $site,
        'release' => array(
            'head_sha' => $head,
            'package_sha256' => str_repeat('b', 64),
            'package_name' => 'cf-01-clinical-records-1.0.0.zip',
            'runtime_version' => CF01_VERSION,
            'schema_version' => CF01_SCHEMA_VERSION,
            'contract_version' => CF01_CONTRACT_VERSION,
            'built_at' => gmdate('c', time() - 300),
        ),
        'jurisdictions' => array('PK'),
        'provider_region' => array('provider' => 'approved-provider', 'region' => 'pk-1', 'data_residency_decision' => 'accepted'),
        'recovery_objectives' => array('rpo_minutes' => 15, 'rto_minutes' => 60, 'approved_by' => 'founder'),
    );
    foreach ($gates as $index => $gate) {
        $evidence[$gate] = array(
            'evidence_id' => 'round4-gate-' . ($index + 1),
            'document_hash' => hash('sha256', $gate),
            'approved_by' => 'approver-' . ($index + 1),
            'approved_at' => gmdate('c', $accepted),
            'scope' => 'CF-01 exact release',
            'status' => 'accepted',
            'tested_head' => $head,
            'environment' => $environment,
            'expires_at' => gmdate('c', time() + 7200),
        );
    }
    $evidence['founder_approval']['founder_authority'] = true;
    $evidence['independent_security_acceptance']['independent_reviewer'] = 'independent-reviewer';
    $evidence['operations_staffing']['assigned_roles'] = array(
        'treating_doctor', 'clinical_supervisor', 'privacy_officer', 'records_custodian', 'incident_escalation', 'patient_support',
    );
    $native = array(
        'file00_membership' => array('owner' => 'File 00', 'contract' => 'smc.cf01.membership-assurance', 'contract_version' => '1.1.2'),
        'file20_shell' => array('owner' => 'File 20', 'contract' => 'cf01.private-shell', 'contract_version' => '1.0.0'),
        'file24_assurance' => array('owner' => 'File 24', 'contract' => 'cf01.assurance-manifest', 'contract_version' => '1.0.0'),
        'file25_visual' => array('owner' => 'File 25', 'contract' => 'cf01.clinical-visual-components', 'contract_version' => '1.0.0'),
    );
    foreach ($native as $key => $proof) {
        $evidence['native_owner_contracts'][$key] = array_merge($proof, array(
            'status' => 'accepted',
            'document_hash' => hash('sha256', $key),
            'tested_head' => $head,
            'environment' => $environment,
            'site_fingerprint' => $site,
            'accepted_at' => gmdate('c', $accepted),
            'expires_at' => gmdate('c', time() + 3600),
            'revoked' => false,
            'suspended' => false,
        ));
    }
    return $evidence;
};

// Native-owner contracts are exact-version, exact-head, site and environment bound.
cf01_reset();
$evidence = $activationEvidence();
$contracts = CF01_Release_Orchestrator::validate_native_owner_contracts($evidence, 1);
$check(!empty($contracts['valid']) && preg_match('/^[a-f0-9]{64}$/', (string) $contracts['fingerprint']) === 1, 'Accepted File 00/20/24/25 contracts must produce a stable readiness fingerprint.');
$downgraded = $evidence;
$downgraded['native_owner_contracts']['file20_shell']['contract_version'] = '0.9.0';
$expect(fn() => CF01_Release_Orchestrator::validate_native_owner_contracts($downgraded, 1), 'A downgraded File 20 contract must fail closed.', 'downgraded');
$wrongHead = $evidence;
$wrongHead['native_owner_contracts']['file24_assurance']['tested_head'] = str_repeat('c', 40);
$expect(fn() => CF01_Release_Orchestrator::validate_native_owner_contracts($wrongHead, 1), 'A File 24 proof for another release head must fail.', 'exact release head');

// Partial schedule failure must compensate every side effect and restore prior evidence.
cf01_reset();
$GLOBALS['cf01_options']['cf01_activation_state'] = 'disabled';
$GLOBALS['cf01_options']['cf01_schema_status'] = 'installed_inactive';
$oldEvidence = 'previous-evidence-cipher';
$newEvidence = CF01_Crypto::encrypt($evidence, 'activation-evidence');
$GLOBALS['cf01_options']['cf01_activation_evidence'] = $oldEvidence;
CF01_Release_Orchestrator::capture_previous_evidence($newEvidence, $oldEvidence, 'cf01_activation_evidence');
$GLOBALS['cf01_options']['cf01_activation_evidence'] = $newEvidence;
$GLOBALS['cf01_force_schedule_failure'] = true;
$expect(fn() => CF01_Release_Orchestrator::guard_activation_state('enabled', 'disabled', 'cf01_activation_state'), 'Partial scheduling failure must abort activation.', 'schedule');
$check(empty($GLOBALS['cf01_schedules']) && get_option('cf01_activation_evidence') === $oldEvidence, 'Activation compensation must clear new schedules and restore the previous evidence value.');
$check(is_array(get_option('cf01_activation_compensation_receipt')), 'Activation compensation must leave a durable non-clinical receipt.');

// Successful orchestration must schedule all jobs; controlled disable must clear them first.
$GLOBALS['cf01_force_schedule_failure'] = false;
CF01_Release_Orchestrator::capture_previous_evidence($newEvidence, $oldEvidence, 'cf01_activation_evidence');
$GLOBALS['cf01_options']['cf01_activation_evidence'] = $newEvidence;
$result = CF01_Release_Orchestrator::guard_activation_state('enabled', 'disabled', 'cf01_activation_state');
$check($result === 'enabled' && count($GLOBALS['cf01_schedules']) === 3, 'Successful activation preflight must verify all mandatory schedules.');
$GLOBALS['cf01_options']['cf01_activation_state'] = 'enabled';
CF01_Release_Orchestrator::after_activation_state('cf01_activation_state', 'disabled', 'enabled');
$check((get_option('cf01_release_orchestration_receipt')['type'] ?? '') === 'activation', 'Successful activation must record an orchestration receipt.');
CF01_Release_Orchestrator::guard_activation_state('disabled', 'enabled', 'cf01_activation_state');
$GLOBALS['cf01_options']['cf01_activation_state'] = 'disabled';
CF01_Release_Orchestrator::after_activation_state('cf01_activation_state', 'enabled', 'disabled');
$check(empty($GLOBALS['cf01_schedules']) && (get_option('cf01_release_orchestration_receipt')['type'] ?? '') === 'disable', 'Controlled disable must clear every clinical schedule before recording completion.');

// Canonical File 08 extraction accepts explicit false dry-run and is idempotent by batch identity.
cf01_reset();
$GLOBALS['cf01_options']['cf01_activation_state'] = 'disabled';
$batch = array(
    'migration_id' => '123e4567-e89b-42d3-a456-426614174222',
    'source_snapshot_sha256' => hash('sha256', 'file08-snapshot'),
    'source_contract_version' => '1.0.0',
    'dry_run' => false,
    'reconciliation_plan' => array('evidence_id' => 'reconcile-1', 'document_hash' => hash('sha256', 'reconcile-plan')),
    'rollback_plan' => array('evidence_id' => 'rollback-1', 'document_hash' => hash('sha256', 'rollback-plan')),
);
$receipt = CF01_Release_Orchestrator::extract_file08_batch(1, $batch, '');
$check(($receipt['counts']['written'] ?? 0) === 1 && empty($receipt['dry_run']), 'A controlled non-dry File 08 batch must write exactly one eligible synthetic record.');
$replay = CF01_Release_Orchestrator::extract_file08_batch(1, $batch, '');
$patients = $GLOBALS['wpdb']->tables[CF01_DB::table('patients')] ?? array();
$check(!empty($replay['replayed']) && count($patients) === 1, 'Exact migration replay must return the receipt without duplicate writes.');
$tamperedBatch = $batch;
$tamperedBatch['rollback_plan']['document_hash'] = hash('sha256', 'different-plan');
$expect(fn() => CF01_Release_Orchestrator::extract_file08_batch(1, $tamperedBatch, ''), 'Reused migration identity with changed evidence must fail.', 'different content');

// Rollback must validate integrity, commit once with optimistic concurrency, and reject repeats.
$migrations = $GLOBALS['wpdb']->tables[CF01_DB::table('migrations')] ?? array();
$migration = end($migrations);
$rollback = CF01_Release_Orchestrator::rollback_migration(1, (string) $migration['migration_uuid'], 'Verified rollback rehearsal');
$check(!empty($rollback['orchestrator_committed']), 'Verified rollback must be committed by the canonical orchestrator.');
$expect(fn() => CF01_Release_Orchestrator::rollback_migration(1, (string) $migration['migration_uuid'], 'Second attempt'), 'Repeated rollback must fail closed.', 'completed unreversed');

// Live provider outage or degraded File 20 response overrides documentary acceptance.
add_filter('cf01_shell_route_registration', static fn($result, array $manifest): array => array(
    'contract_version' => '1.0.0', 'registered' => false, 'private' => true, 'no_store' => true,
), 999, 2);
$expect(fn() => CF01_Release_Orchestrator::validate_native_owner_contracts($evidence, 1), 'A live File 20 registration outage must block activation.', 'File20');

if ($failures) {
    fwrite(STDERR, "CF-01 Round-4 adversarial review FAILED:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "CF-01 Round-4 integration/rollback adversarial review: {$count} PASS, 0 FAIL\n";
