from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sabri-clinical-records"


def read(relative: str) -> str:
    return (ROOT / relative).read_text(encoding="utf-8")


class FortyRoundFreshReviewR2Tests(unittest.TestCase):
    def test_database_transactions_fail_closed_and_support_nested_savepoints(self):
        source = read("sabri-clinical-records/includes/class-cf01-db.php")
        for marker in (
            "private static int $transaction_depth = 0;",
            "SAVEPOINT ' . $savepoint",
            "ROLLBACK TO SAVEPOINT ' . $savepoint",
            "RELEASE SAVEPOINT ' . $savepoint",
            "Clinical database transaction could not be started.",
            "Clinical database transaction could not be committed.",
            "Clinical database rollback failed.",
        ):
            self.assertIn(marker, source)
        self.assertGreaterEqual(source.count("$wpdb->query("), 5)
        self.assertIn("=== false", source)

    def test_preactivation_governance_is_bounded_not_blanket_enabled(self):
        source = read("sabri-clinical-records/includes/class-cf01-authorization.php")
        self.assertIn("private const PRE_ACTIVATION_ACTIONS", source)
        for action in (
            "view_clinical_health",
            "run_clinical_migration",
            "run_clinical_rollback",
            "rotate_clinical_key",
        ):
            self.assertIn(f"'{action}'", source)
        self.assertIn("if (!in_array($action, self::PRE_ACTIVATION_ACTIONS, true))", source)
        self.assertIn("self::require_enabled();", source)
        self.assertIn("Current recent step-up authentication is required.", source)

    def test_shortcode_surfaces_receive_the_same_private_request_classification(self):
        source = read("sabri-clinical-records/includes/class-cf01-ui-health.php")
        self.assertIn("has_shortcode((string) $post->post_content, 'sabri_clinical_records')", source)
        self.assertIn("function_exists('is_singular')", source)
        self.assertNotIn("add_action('send_headers', array(__CLASS__, 'headers'))", source)
        self.assertNotIn("public static function headers(): void", source)
        self.assertIn("if (str_contains($pattern, '('))", source)

    def test_central_private_headers_cannot_be_weakened_by_ui_duplicate(self):
        bootstrap = read("sabri-clinical-records/sabri-clinical-records.php")
        ui = read("sabri-clinical-records/includes/class-cf01-ui-health.php")
        self.assertIn("'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()'", bootstrap)
        self.assertIn("'X-Robots-Tag' => 'noindex, nofollow, noarchive, nosnippet, noimageindex'", bootstrap)
        self.assertNotIn("Permissions-Policy:", ui)
        self.assertNotIn("Cache-Control:", ui)

    def test_server_error_identifier_is_fully_generic(self):
        source = read("sabri-clinical-records/sabri-clinical-records.php")
        self.assertIn("'code' => 'cf01_internal_error'", source)
        self.assertNotIn("isset($data['code'])", source)
        self.assertNotIn("sanitize_key((string) $data['code'])", source)


if __name__ == "__main__":
    unittest.main()
