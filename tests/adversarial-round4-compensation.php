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

// A post-state-update receipt failure must never leave the runtime enabled.
cf01_reset();
$GLOBALS['cf01_options']['cf01_activation_state'] = 'enabled';
$GLOBALS['cf01_options']['cf01_activation_evidence'] = 'new-evidence-cipher';
$GLOBALS['cf01_options']['cf01_activation_previous_evidence'] = array(
    'existed' => true,
    'value' => 'previous-evidence-cipher',
    'captured_at' => CF01_DB::now(),
);
$GLOBALS['cf01_schedules'] = array(
    'cf01_process_outbox' => time() + 60,
    'cf01_retention_reconcile' => time() + 300,
    'cf01_followup_reconcile' => time() + 120,
);
CF01_Release_Orchestrator::after_activation_state('cf01_activation_state', 'disabled', 'enabled');
$check(get_option('cf01_activation_state') === 'disabled', 'Missing activation completion receipt must force the runtime back to disabled.');
$check(get_option('cf01_activation_evidence') === 'previous-evidence-cipher', 'Post-transition compensation must restore the previous activation evidence.');
$check(empty($GLOBALS['cf01_schedules']), 'Post-transition compensation must clear every clinical schedule.');
$check((get_option('cf01_activation_compensation_receipt')['state'] ?? '') === 'disabled', 'Post-transition compensation must record the final disabled state.');

// Canonical migration receipt must expose and match the durable ledger UUID.
cf01_reset();
$GLOBALS['cf01_options']['cf01_activation_state'] = 'disabled';
add_filter('cf01_file08_extraction_batch', static function ($result, array $batch, string $cursor): array {
    return array(
        'migration_id' => (string) $batch['migration_id'],
        'source_snapshot_sha256' => (string) $batch['source_snapshot_sha256'],
        'source_contract_version' => (string) $batch['source_contract_version'],
        'records' => array(),
        'next_cursor' => '',
        'complete' => true,
    );
}, 10, 3);
$batch = array(
    'migration_id' => '123e4567-e89b-42d3-a456-426614174333',
    'source_snapshot_sha256' => hash('sha256', 'round4-ledger-snapshot'),
    'source_contract_version' => '1.0.0',
    'dry_run' => true,
    'reconciliation_plan' => array('evidence_id' => 'reconcile-ledger', 'document_hash' => hash('sha256', 'reconcile-ledger-plan')),
    'rollback_plan' => array('evidence_id' => 'rollback-ledger', 'document_hash' => hash('sha256', 'rollback-ledger-plan')),
);
$receipt = CF01_Release_Orchestrator::extract_file08_batch(1, $batch, '');
$ledgerUuid = (string) ($receipt['ledger_migration_uuid'] ?? '');
$ledger = CF01_DB::row('SELECT * FROM ' . CF01_DB::table('migrations') . ' WHERE migration_uuid = %s LIMIT 1', array($ledgerUuid));
$check((bool) preg_match('/^[a-f0-9-]{36}$/', $ledgerUuid), 'Migration receipt must expose a durable ledger UUID.');
$check(is_array($ledger) && hash_equals($ledgerUuid, (string) $ledger['migration_uuid']), 'Migration receipt ledger UUID must resolve to the exact durable migration record.');
$storedReceipt = is_array($ledger) ? CF01_Crypto::decrypt((string) $ledger['receipt_cipher'], 'migration-receipt') : null;
$check(is_array($storedReceipt) && hash_equals($ledgerUuid, (string) ($storedReceipt['ledger_migration_uuid'] ?? '')), 'Encrypted migration receipt must preserve the same ledger UUID.');

// Legacy rollback cannot bypass the canonical disabled-runtime boundary.
$GLOBALS['cf01_options']['cf01_activation_state'] = 'enabled';
$expect(
    fn() => CF01_Release_Orchestrator::legacy_rollback_commit(array(), array(), 'legacy attempt'),
    'Legacy rollback must fail while the clinical runtime is active.',
    'active'
);

if ($failures) {
    fwrite(STDERR, "CF-01 Round-4 compensation review FAILED:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "CF-01 Round-4 compensation and ledger review: {$count} PASS, 0 FAIL\n";
