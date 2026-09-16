import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
MIGRATIONS = (ROOT / 'sabri-clinical-records' / 'includes' / 'class-cf01-migrations.php').read_text(encoding='utf-8')
ORCHESTRATOR = (ROOT / 'sabri-clinical-records' / 'includes' / 'class-cf01-release-orchestrator.php').read_text(encoding='utf-8')


class FreshR2LifecycleMigrationIntegrityTests(unittest.TestCase):
    def test_legacy_migration_accepts_explicit_false_dry_run(self):
        section = MIGRATIONS.split('public static function extract_from_file08', 1)[1].split('public static function reconciliation', 1)[0]
        self.assertIn("array_key_exists('dry_run', $batch)", section)
        self.assertIn("is_bool($batch['dry_run'])", section)
        self.assertIn("if (!$batch['dry_run'])", section)
        self.assertNotIn("empty($batch['dry_run'])", section)

    def test_schema_status_follows_verified_inventory_and_receipt(self):
        section = MIGRATIONS.split('public static function install_schema', 1)[1].split('public static function activate_runtime', 1)[0]
        self.assertLess(section.index('self::verify_schema_inventory();'), section.index("update_option('cf01_schema_status'"))
        self.assertLess(section.index("self::record('schema_install'"), section.index("update_option('cf01_schema_status'"))
        self.assertIn('information_schema.TABLES', MIGRATIONS)

    def test_activation_failure_compensates_enabled_state_and_schedules(self):
        section = MIGRATIONS.split('public static function activate_runtime', 1)[1].split('public static function disable_runtime', 1)[0]
        self.assertIn('catch (Throwable $error)', section)
        self.assertIn("update_option('cf01_activation_state', 'disabled'", section)
        self.assertIn("wp_clear_scheduled_hook('cf01_process_outbox')", section)
        self.assertIn('Clinical runtime activation failed and was compensated.', section)

    def test_restore_receipt_and_audit_share_transaction(self):
        section = MIGRATIONS.split('public static function verify_restore', 1)[1].split('private static function verify_signed_records', 1)[0]
        self.assertIn('CF01_DB::transaction', section)
        self.assertIn("self::record('restore_verification'", section)
        self.assertIn("CF01_Audit::record($actor_id, 'ClinicalRestoreVerified'", section)

    def test_legacy_rollback_does_not_double_commit_or_double_audit(self):
        section = MIGRATIONS.split('public static function rollback', 1)[1].split('public static function schema', 1)[0]
        self.assertIn("!empty($receipt['orchestrator_committed'])", section)
        self.assertIn('return CF01_DB::transaction', section)
        self.assertIn('if (!$updated)', section)

    def test_canonical_migration_writes_and_ledger_are_atomic(self):
        section = ORCHESTRATOR.split('public static function extract_file08_batch', 1)[1].split('public static function rollback_migration', 1)[0]
        transaction = section.split('CF01_DB::transaction', 1)[1]
        self.assertIn('self::write_file08_patient', transaction)
        self.assertIn('self::record_migration_batch', transaction)

    def test_canonical_rollback_mutation_and_audit_are_atomic(self):
        section = ORCHESTRATOR.split('private static function commit_rollback_receipt', 1)[1].split('private static function authorize_governance_actor', 1)[0]
        transaction = section.split('CF01_DB::transaction', 1)[1]
        self.assertIn('CF01_DB::update_versioned', transaction)
        self.assertIn("CF01_Audit::record(get_current_user_id(), 'ClinicalMigrationRolledBack'", transaction)


if __name__ == '__main__':
    unittest.main()
