from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sabri-clinical-records"
BOOTSTRAP = PLUGIN / "sabri-clinical-records.php"
GUARD = PLUGIN / "includes" / "class-cf01-runtime-privacy.php"


class RuntimePrivacyHardeningTests(unittest.TestCase):
    def test_guard_is_loaded_and_registered(self):
        bootstrap = BOOTSTRAP.read_text(encoding="utf-8")
        self.assertIn("'class-cf01-runtime-privacy.php'", bootstrap)
        self.assertIn("CF01_Runtime_Privacy::register();", bootstrap)
        self.assertIn("Version: 1.0.1", bootstrap)
        self.assertIn("define('CF01_VERSION', '1.0.1');", bootstrap)

    def test_every_clinical_response_is_no_store_and_non_frameable(self):
        source = GUARD.read_text(encoding="utf-8")
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
        self.assertIn("rest_post_dispatch", source)
        self.assertIn("rest_pre_serve_request", source)
        self.assertIn("send_headers", source)

    def test_server_errors_are_redacted_by_default(self):
        source = GUARD.read_text(encoding="utf-8")
        self.assertRegex(source, r"\$status\s*>=\s*500")
        self.assertIn("!self::may_expose_diagnostics()", source)
        self.assertIn("cf01_allow_runtime_diagnostics", source)
        self.assertIn("cf01_view_clinical_health", source)
        self.assertIn("The clinical service could not complete this request.", source)
        self.assertNotIn("$error->getMessage()", source)
        self.assertNotIn("trace", source.lower())

    def test_guard_is_scoped_to_canonical_namespace(self):
        source = GUARD.read_text(encoding="utf-8")
        self.assertIn("private const REST_PREFIX = '/clinical/v1/';", source)
        self.assertIn("str_starts_with($route, self::REST_PREFIX)", source)
        self.assertNotRegex(source, r"localStorage|sessionStorage|indexedDB")


if __name__ == "__main__":
    unittest.main()
