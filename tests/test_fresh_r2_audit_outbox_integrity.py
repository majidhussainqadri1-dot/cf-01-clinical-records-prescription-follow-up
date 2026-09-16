import unittest
from pathlib import Path

SOURCE = (Path(__file__).resolve().parents[1] / 'sabri-clinical-records' / 'includes' / 'class-cf01-audit-outbox.php').read_text(encoding='utf-8')


class FreshR2AuditOutboxIntegrityTests(unittest.TestCase):
    def test_audit_successor_write_is_transactional_and_locking(self):
        audit = SOURCE.split('final class CF01_Audit', 1)[1].split('final class CF01_Outbox', 1)[0]
        self.assertIn('CF01_DB::transaction', audit)
        self.assertIn('ORDER BY id DESC LIMIT 1 FOR UPDATE', audit)
        self.assertIn('WHERE previous_hash = %s FOR UPDATE', audit)
        self.assertIn('count($successors) !== 1', audit)
        self.assertIn('Clinical audit chain continuity conflict detected', audit)

    def test_one_poison_outbox_row_does_not_abort_batch(self):
        outbox = SOURCE.split('final class CF01_Outbox', 1)[1]
        self.assertIn('foreach ($rows as $row)', outbox)
        self.assertIn('catch (Throwable $error)', outbox)
        self.assertIn('self::record_delivery_exception($row)', outbox)
        self.assertIn("'delivery_exception'", outbox)

    def test_decrypted_payload_and_notification_contract_are_typed(self):
        outbox = SOURCE.split('final class CF01_Outbox', 1)[1]
        self.assertIn('if (!is_array($payload))', outbox)
        self.assertIn('if (!is_array($result))', outbox)
        self.assertIn('notification_contract_invalid', outbox)


if __name__ == '__main__':
    unittest.main()
