<?php
require __DIR__ . '/bootstrap.php';
cf01_reset();
$count = 0;
$ok = function (bool $condition, string $message) use (&$count): void { cf01_assert($condition, $message); $count++; };
$schema = CF01_Migrations::schema('utf8mb4_unicode_ci');
$ok(count($schema) === 18, 'All canonical and operational tables must be defined.');
$joined = implode("\n", $schema);
foreach (array('cf01_clinical_patients','cf01_care_relationships','cf01_clinical_consents','cf01_encounters','cf01_observations','cf01_attachments','cf01_assessments','cf01_prescriptions','cf01_followups','cf01_patient_outcomes','cf01_access_events','cf01_rights_cases','cf01_break_glass','cf01_audit_ledger','cf01_outbox','cf01_retention_ledger','cf01_migrations','cf01_command_receipts') as $table) {
    $ok(str_contains($joined, $table), 'Schema missing table: ' . $table);
}
$ok(str_contains($joined, 'UNIQUE KEY platform_subject_hash'), 'Identity collision key missing.');
$ok(str_contains($joined, 'UNIQUE KEY key_hash'), 'Idempotency key missing.');
$ok(str_contains($joined, 'patient_status_due'), 'Follow-up due index missing.');
$ok(str_contains($joined, 'eligible_hold_status'), 'Retention reconciliation index missing.');
$ok(str_contains($joined, 'chain_hash'), 'Audit chain fields missing.');
$migrationsSource = file_get_contents(CF01_DIR . 'includes/class-cf01-migrations.php');
$ok(str_contains($migrationsSource, 'cf01_migration_lock'), 'Migration lock missing.');
$ok(str_contains($migrationsSource, "array_key_exists('dry_run', \$batch)") && str_contains($migrationsSource, "is_bool(\$batch['dry_run'])") && str_contains($migrationsSource, "if (!\$batch['dry_run'])"), 'Explicit boolean dry-run switch missing.');
$ok(str_contains($migrationsSource, 'next_cursor'), 'Resumable cursor missing.');
$ok(str_contains($migrationsSource, 'reconciliation'), 'Reconciliation missing.');
$ok(str_contains($migrationsSource, 'rollback'), 'Rollback missing.');
$ok(get_option('cf01_activation_state') === 'enabled', 'Test activation state invalid.');
$GLOBALS['cf01_options']['cf01_activation_state'] = 'disabled';
cf01_expect_exception(fn() => CF01_Authorization::actor(1, 'view_own_clinical_record'), 'disabled'); $count++;
$ok(str_contains($migrationsSource, 'legal_professional_acceptance'), 'Legal/professional activation gate missing.');
$ok(str_contains($migrationsSource, 'independent_security_acceptance'), 'Independent security gate missing.');
$ok(str_contains($migrationsSource, 'backup_restore_acceptance'), 'Backup/restore gate missing.');
$ok(str_contains($migrationsSource, 'rollback_rehearsal'), 'Rollback rehearsal gate missing.');
$ok(str_contains($migrationsSource, 'operations_staffing'), 'Operational staffing gate missing.');
echo "CF-01 migration/rollback review: {$count} PASS, 0 FAIL\n";
