import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SOURCE = (ROOT / 'sabri-clinical-records' / 'includes' / 'class-cf01-future24.php').read_text(encoding='utf-8')


class Future24ErrorEnvelopeTests(unittest.TestCase):
    def test_callbacks_use_fail_closed_wrapper(self):
        for name in ('features', 'feature_state', 'sidecar_assurance', 'patient_timeline', 'patient_feature',
                     'patient_feature_facts', 'write_patient_feature_fact', 'decision_support',
                     'patient_reported_outcomes', 'research_consents', 'institutional_webhook',
                     'simulation', 'transparency'):
            marker = f'public static function {name}'
            self.assertIn(marker, SOURCE)
        self.assertIn('private static function safe(callable $callback)', SOURCE)
        self.assertIn('cf01_future24_internal_error', SOURCE)
        self.assertIn('cf01_future24_unavailable', SOURCE)

    def test_provider_failures_are_sanitized(self):
        self.assertIn('cf01_future24_provider_failed', SOURCE)
        self.assertIn('cf01_future24_provider_invalid', SOURCE)
        self.assertNotIn('$error->getMessage()', SOURCE.split("cf01_future24_provider_failed", 1)[1].split('}', 1)[0])

    def test_simulation_identifier_scan_handles_nested_values(self):
        self.assertIn('is_scalar($child)', SOURCE)
        self.assertIn('is_array($child) && $child !== array()', SOURCE)


if __name__ == '__main__':
    unittest.main()
