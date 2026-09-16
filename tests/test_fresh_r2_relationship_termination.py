import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
REL = (ROOT / 'sabri-clinical-records' / 'includes' / 'class-cf01-relationships.php').read_text(encoding='utf-8')
SCHEMA = (ROOT / 'sabri-clinical-records' / 'includes' / 'class-cf01-migrations.php').read_text(encoding='utf-8')


class FreshR2RelationshipTerminationTests(unittest.TestCase):
    def test_prescription_reconciliation_joins_through_encounter_relationship(self):
        self.assertIn('INNER JOIN', REL)
        self.assertIn('e.relationship_uuid = %s', REL)
        self.assertIn('p.encounter_uuid', REL)
        prescription_schema = SCHEMA.split("CREATE TABLE {$t('prescriptions')}", 1)[1].split(') $charset;', 1)[0]
        self.assertNotIn('relationship_uuid', prescription_schema)

    def test_followups_are_included_in_termination_reconciliation(self):
        self.assertIn("$followups = CF01_DB::table('followups')", REL)
        self.assertIn("'open_followups' => array_column($open['followups'], 'followup_uuid')", REL)
        self.assertIn("f.status NOT IN (%s,%s)", REL)

    def test_termination_is_versioned_and_serialized_on_relationship_row(self):
        self.assertIn('CF01_DB::transaction', REL)
        self.assertIn('FOR UPDATE', REL)
        self.assertIn('CF01_Authorization::expected_version($current, $expected_version)', REL)

    def test_status_notifications_keep_patient_context(self):
        transition = REL.split('public static function transition', 1)[1]
        self.assertIn("'patient_uuid' => (string) $current['patient_uuid']", transition)
        self.assertIn("'patient_uuid' => (string) $row['patient_uuid']", transition)


if __name__ == '__main__':
    unittest.main()
