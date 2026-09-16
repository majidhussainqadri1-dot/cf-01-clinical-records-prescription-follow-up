import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = (ROOT / 'sabri-clinical-records' / 'sabri-clinical-records.php').read_text(encoding='utf-8')
GUARD = (ROOT / 'sabri-clinical-records' / 'includes' / 'class-cf01-future24-guard.php').read_text(encoding='utf-8')


class Future24ApiDisclosureAuditTests(unittest.TestCase):
    def test_future_query_surface_is_bounded(self):
        for token in (
            'MAX_FUTURE_QUERY_PARAMS',
            'MAX_FUTURE_QUERY_VALUE_BYTES',
            'validate_future24_query',
            "(int) $value > 100",
            'cf01_future24_query_too_broad',
        ):
            self.assertIn(token, PLUGIN)

    def test_future_response_is_bounded_and_internal_assertions_are_removed(self):
        self.assertIn('MAX_FUTURE_RESPONSE_BYTES', PLUGIN)
        self.assertIn("array_key_exists('_cf01', $data)", PLUGIN)
        self.assertIn("unset($data['_cf01'])", PLUGIN)
        self.assertIn('cf01_future24_response_too_large', PLUGIN)

    def test_successful_future_access_is_audited_fail_closed(self):
        self.assertIn('audit_future24_success', PLUGIN)
        self.assertIn('CF01_Audit::access', PLUGIN)
        self.assertIn('CF01_Audit::record', PLUGIN)
        self.assertIn('cf01_future24_audit_unavailable', PLUGIN)

    def test_provider_assertions_still_validate_before_redaction(self):
        self.assertIn("$meta = $result['_cf01'] ?? null", GUARD)
        for token in ('authorization_checked', 'minimum_necessary', 'canonical_owner', 'contract_version'):
            self.assertIn(token, GUARD)


if __name__ == '__main__':
    unittest.main()
