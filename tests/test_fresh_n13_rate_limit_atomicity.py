from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
SOURCE = (ROOT / "sabri-clinical-records/includes/class-cf01-authorization.php").read_text(encoding="utf-8")


class RateLimitAtomicityRegression(unittest.TestCase):
    def test_rate_limit_counter_is_serialized_with_database_lock(self):
        self.assertIn("SELECT GET_LOCK(%s, 1)", SOURCE)
        self.assertIn("SELECT RELEASE_LOCK(%s)", SOURCE)
        self.assertIn("$lock_name = 'cf01_rl_'", SOURCE)

    def test_rate_limit_update_occurs_inside_lock_scope(self):
        acquire = SOURCE.index("SELECT GET_LOCK(%s, 1)")
        read = SOURCE.index("$count = (int) get_transient($key)")
        write = SOURCE.index("set_transient($key, $count + 1")
        release = SOURCE.index("SELECT RELEASE_LOCK(%s)")
        self.assertLess(acquire, read)
        self.assertLess(read, write)
        self.assertLess(write, release)

    def test_rate_limit_fails_closed_if_lock_or_persistence_fails(self):
        self.assertIn("Clinical rate-limit guard is temporarily unavailable.", SOURCE)
        self.assertIn("Clinical rate-limit state could not be persisted.", SOURCE)


if __name__ == "__main__":
    unittest.main()
