from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
DB_SOURCE = ROOT / "sabri-clinical-records" / "includes" / "class-cf01-db.php"


class CoreRestIdempotencyResourceScopeTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.source = DB_SOURCE.read_text(encoding="utf-8")

    def test_receipt_key_is_scoped_to_concrete_rest_resource(self):
        self.assertIn("$resource_scope = self::idempotency_resource_scope();", self.source)
        self.assertIn("$actor_id . '|' . $command . '|' . $resource_scope . '|' . $key", self.source)
        self.assertIn("'resource_scope' => $resource_scope", self.source)

    def test_pretty_and_query_style_rest_paths_are_supported(self):
        self.assertIn("$_SERVER['REQUEST_URI']", self.source)
        self.assertIn("parse_url($uri, PHP_URL_PATH)", self.source)
        self.assertIn("parse_url($uri, PHP_URL_QUERY)", self.source)
        self.assertIn("$params['rest_route']", self.source)

    def test_background_callers_keep_empty_scope(self):
        self.assertIn("if ($uri === '')", self.source)
        self.assertIn("return '';", self.source)


if __name__ == "__main__":
    unittest.main()
