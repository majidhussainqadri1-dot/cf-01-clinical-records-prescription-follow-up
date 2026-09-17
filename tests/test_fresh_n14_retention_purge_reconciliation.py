from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
SOURCE = (ROOT / "sabri-clinical-records/includes/class-cf01-retention.php").read_text(encoding="utf-8")


class RetentionPurgeReconciliationRegression(unittest.TestCase):
    def test_purge_uses_record_serialization_and_locked_revalidation(self):
        self.assertIn("SELECT GET_LOCK(%s, 2)", SOURCE)
        self.assertIn("SELECT RELEASE_LOCK(%s)", SOURCE)
        self.assertIn("get_for_update($uuid)", SOURCE)
        self.assertIn("self::assert_purge_eligible($current", SOURCE)

    def test_provider_failure_is_an_explicit_retriable_state(self):
        self.assertIn("'purge_pending'", SOURCE)
        self.assertIn("'purge_failed'", SOURCE)
        self.assertIn("self::mark_purge_failed($uuid", SOURCE)
        self.assertIn("ClinicalRetentionPurgeFailed", SOURCE)
        self.assertIn("WHERE status IN (%s,%s,%s)", SOURCE)

    def test_hold_and_purge_share_the_same_serialization_boundary(self):
        self.assertIn("return self::with_record_lock($uuid, function () use ($actor_id, $uuid, $hold", SOURCE)
        self.assertIn("return self::with_record_lock($uuid, function () use ($actor_id, $uuid, $expected_version, $system)", SOURCE)
        self.assertIn("A purged retention record cannot receive a new hold.", SOURCE)

    def test_canonical_purge_transitions_are_audited(self):
        self.assertIn("ClinicalRetentionPurgeStarted", SOURCE)
        self.assertIn("ClinicalRecordPurged", SOURCE)
        self.assertIn("CF01_DB::transaction(function () use", SOURCE)


if __name__ == "__main__":
    unittest.main()
