from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
SOURCE = (ROOT / "sabri-clinical-records/includes/class-cf01-rest.php").read_text(encoding="utf-8")


class TimelineCursorRegression(unittest.TestCase):
    def test_cursor_keeps_timestamp_and_uuid_tie_breaker(self):
        self.assertIn("return array('occurred_at' => $occurred_at, 'uuid' => $uuid);", SOURCE)
        self.assertIn("$time_field . ' < %s OR (' . $time_field . ' = %s AND ' . $id_field . ' < %s)'", SOURCE)
        self.assertIn("occurred_at < %s OR (occurred_at = %s AND event_uuid < %s)", SOURCE)

    def test_old_inclusive_timestamp_only_pagination_is_gone(self):
        self.assertNotIn("$time_field . ' <= %s'", SOURCE)
        self.assertNotIn("occurred_at <= %s", SOURCE)

    def test_cursor_uuid_is_validated(self):
        self.assertIn("preg_match('/^[a-f0-9-]{36}$/', $uuid)", SOURCE)


if __name__ == "__main__":
    unittest.main()
