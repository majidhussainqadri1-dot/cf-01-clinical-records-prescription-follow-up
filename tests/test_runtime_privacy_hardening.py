from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
BOOTSTRAP = ROOT / "sabri-clinical-records" / "sabri-clinical-records.php"


class RuntimePrivacyHardeningTests(unittest.TestCase):
    def source(self):
        return BOOTSTRAP.read_text(encoding="utf-8")

    def test_guard_hooks_are_registered(self):
        source = self.source()
        self.assertIn("add_action('send_headers'", source)
        self.assertIn("add_filter('rest_post_dispatch'", source)
        self.assertIn("add_filter('rest_pre_serve_request'", source)
        self.assertIn("private const REST_PREFIX = '/clinical/v1/';", source)

    def test_every_clinical_response_is_no_store_and_non_frameable(self):
        source = self.source()
        required = {
            "Cache-Control": "private, no-store, no-cache, must-revalidate, max-age=0",
            "Referrer-Policy": "no-referrer",
            "X-Content-Type-Options": "nosniff",
            "X-Frame-Options": "DENY",
            "Cross-Origin-Resource-Policy": "same-origin",
            "Permissions-Policy": "camera=(), microphone=(), geolocation=(), payment=(), usb=()",
        }
        for name, value in required.items():
            self.assertIn(f"'{name}' => '{value}'", source)

    def test_server_errors_are_redacted_by_default(self):
        source = self.source()
        self.assertRegex(source, r"\$status\s*>=\s*500")
        self.assertIn("!self::may_expose_diagnostics()", source)
        self.assertIn("cf01_allow_runtime_diagnostics", source)
        self.assertIn("cf01_view_clinical_health", source)
        self.assertIn("The clinical service could not complete this request.", source)
        self.assertNotIn("$error->getMessage()", source)
        self.assertNotIn("debug_backtrace", source)

    def test_guard_is_scoped_to_canonical_namespace(self):
        source = self.source()
        self.assertIn("str_starts_with($route, self::REST_PREFIX)", source)
        self.assertNotRegex(source, r"localStorage|sessionStorage|indexedDB")


if __name__ == "__main__":
    unittest.main()
