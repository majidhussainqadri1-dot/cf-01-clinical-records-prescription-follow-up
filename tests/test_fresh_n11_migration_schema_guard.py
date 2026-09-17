from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
SOURCE = (ROOT / "sabri-clinical-records/includes/class-cf01-migrations.php").read_text(encoding="utf-8")


class MigrationSchemaGuardRegression(unittest.TestCase):
    def test_migration_lock_uses_database_atomic_lock_not_transient_check_then_set(self):
        self.assertIn("SELECT GET_LOCK(%s, 0)", SOURCE)
        self.assertIn("SELECT RELEASE_LOCK(%s)", SOURCE)
        self.assertNotIn("get_transient(self::LOCK)", SOURCE)
        self.assertNotIn("set_transient(self::LOCK", SOURCE)

    def test_schema_inventory_validates_columns_and_indexes_not_only_table_names(self):
        self.assertIn("information_schema.COLUMNS", SOURCE)
        self.assertIn("information_schema.STATISTICS", SOURCE)
        self.assertIn("$missing_columns", SOURCE)
        self.assertIn("$missing_indexes", SOURCE)
        self.assertIn("'PRIMARY'", SOURCE)

    def test_schema_validation_is_derived_from_current_canonical_create_statements(self):
        self.assertIn("foreach (self::schema('') as $sql)", SOURCE)
        self.assertIn("Clinical schema definition could not be inspected", SOURCE)


if __name__ == "__main__":
    unittest.main()
