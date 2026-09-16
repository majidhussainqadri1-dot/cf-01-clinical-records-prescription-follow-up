import unittest
from pathlib import Path

PLUGIN = (Path(__file__).resolve().parents[1] / 'sabri-clinical-records' / 'sabri-clinical-records.php').read_text(encoding='utf-8')


class Future24SafeErrorPrestoreTests(unittest.TestCase):
    def test_callback_response_is_bounded_before_idempotency_receipt_completion(self):
        self.assertIn('harden_future24_callback_response', PLUGIN)
        self.assertIn('PHP_INT_MAX - 1', PLUGIN)
        self.assertIn("unset($data['_cf01'])", PLUGIN)
        self.assertIn('MAX_FUTURE_RESPONSE_BYTES', PLUGIN)

    def test_decision_support_version_is_checked_against_patient_row(self):
        self.assertIn("$route === '/clinical/v1/future/decision-support'", PLUGIN)
        self.assertIn('CF01_Patients::get($patient_uuid)', PLUGIN)
        self.assertIn('CF01_Authorization::expected_version', PLUGIN)
        self.assertIn('cf01_future24_version_conflict', PLUGIN)

    def test_future_identifiers_are_canonical_uuids(self):
        self.assertIn("array('patient', 'followup')", PLUGIN)
        self.assertIn("[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}", PLUGIN)

    def test_4xx_errors_are_normalized_and_403_404_do_not_distinguish_existence(self):
        self.assertIn('safe_future_error', PLUGIN)
        self.assertGreaterEqual(PLUGIN.count("cf01_future24_access_unavailable"), 2)
        self.assertIn("'trace_id' => CF01_DB::uuid()", PLUGIN)

    def test_denied_future_requests_are_audited_when_runtime_is_enabled(self):
        self.assertIn('audit_future24_denial', PLUGIN)
        self.assertIn('CF01_Audit::denied', PLUGIN)
        self.assertIn('CF01_DB::is_enabled()', PLUGIN)


if __name__ == '__main__':
    unittest.main()
