from pathlib import Path
import re
import unittest


ROOT = Path(__file__).resolve().parents[1]
CRYPTO = ROOT / "sabri-clinical-records" / "includes" / "class-cf01-crypto.php"


class FinalCodeHardeningTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.source = CRYPTO.read_text(encoding="utf-8")

    def test_new_envelope_aad_is_not_bound_to_plugin_runtime_version(self):
        aad_method = re.search(
            r"private static function aad\([^}]+\}", self.source, re.S
        )
        self.assertIsNotNone(aad_method)
        self.assertNotIn("CF01_VERSION", aad_method.group(0))
        self.assertIn("CRYPTO_CONTEXT_VERSION", self.source)

    def test_legacy_release_context_is_explicit_and_bounded(self):
        self.assertIn("private const LEGACY_RUNTIME_VERSION = '1.0.0';", self.source)
        self.assertIn("Version-1 envelopes were created by release 1.0.0", self.source)
        self.assertNotIn("$legacy_aad = 'cf01|' . CF01_VERSION", self.source)

    def test_envelope_algorithm_and_authentication_fields_fail_closed(self):
        required = (
            "Unsupported clinical encryption envelope version.",
            "Unsupported clinical encryption algorithm.",
            "Corrupt clinical encryption authentication context.",
            "Unsupported clinical encryption context version.",
            "Clinical encryption purpose mismatch.",
        )
        for message in required:
            self.assertIn(message, self.source)
        self.assertRegex(self.source, r"strlen\(\$iv\) !== 12")
        self.assertRegex(self.source, r"strlen\(\$tag\) !== 16")

    def test_purpose_is_normalized_and_bounded(self):
        self.assertIn("private static function normalize_purpose", self.source)
        self.assertIn("strlen($purpose) > 96", self.source)
        self.assertGreaterEqual(self.source.count("self::normalize_purpose($purpose)"), 2)

    def test_signature_verification_rejects_malformed_values(self):
        self.assertIn("preg_match('/^[a-f0-9]{64}$/', $signature)", self.source)
        self.assertIn("hash_equals(self::sign($snapshot, $purpose), strtolower($signature))", self.source)


if __name__ == "__main__":
    unittest.main()
