from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "sabri-clinical-records" / "includes" / "class-cf01-future-rest.php"


class FutureRestIdempotencyContextTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.source = SOURCE.read_text(encoding="utf-8")

    def test_route_identity_is_part_of_idempotency_fingerprint(self):
        self.assertIn("'route_context' => $route_context", self.source)
        self.assertIn("'body' => $data", self.source)
        self.assertIn("$fingerprint", self.source)
        self.assertIn("CF01_DB::idempotent(get_current_user_id(), $command, $key, $fingerprint", self.source)

    def test_path_scoped_mutations_supply_canonical_route_identity(self):
        for token in [
            "array('feature_id' => (string) $request['id'])",
            "array('feature_id' => (string) $request['id'], 'patient_uuid' => (string) $request['patient'])",
            "array('followup_uuid' => (string) $request['followup'])",
            "array('event_name' => (string) $request['event'])",
        ]:
            with self.subTest(token=token):
                self.assertIn(token, self.source)


if __name__ == "__main__":
    unittest.main()
