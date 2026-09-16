import unittest
from pathlib import Path

SOURCE = (Path(__file__).resolve().parents[1] / 'sabri-clinical-records' / 'includes' / 'class-cf01-break-glass.php').read_text(encoding='utf-8')


class FreshN7BreakGlassConcurrencyTests(unittest.TestCase):
    def test_active_grant_check_is_serialized(self):
        self.assertIn("status = %s ORDER BY id DESC LIMIT 1 FOR UPDATE", SOURCE)
        transaction_pos = SOURCE.index("CF01_DB::transaction(function () use ($actor_id, $patient_uuid")
        active_lock_pos = SOURCE.index("LIMIT 1 FOR UPDATE", transaction_pos)
        insert_pos = SOURCE.index("CF01_DB::insert('breakglass'", active_lock_pos)
        self.assertLess(active_lock_pos, insert_pos)

    def test_expired_grant_is_transitioned_before_replacement(self):
        self.assertIn("'status' => 'expired', 'review_status' => 'pending'", SOURCE)
        self.assertIn("BreakGlassExpired", SOURCE)

    def test_misuse_enforcement_occurs_after_locked_revalidation(self):
        review_transaction = SOURCE.index("CF01_DB::transaction(function () use ($actor_id, $grant_uuid, $review")
        lock_pos = SOURCE.index("self::get_for_update($grant_uuid)", review_transaction)
        enforcement_pos = SOURCE.index("cf01_break_glass_misuse_enforcement", review_transaction)
        self.assertLess(lock_pos, enforcement_pos)


if __name__ == '__main__':
    unittest.main()
