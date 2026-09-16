import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
GOV = (ROOT / 'sabri-clinical-records' / 'includes' / 'class-cf01-future24-governance.php').read_text(encoding='utf-8')
BOOT = (ROOT / 'sabri-clinical-records' / 'sabri-clinical-records.php').read_text(encoding='utf-8')


class Future24ActivationBindingTests(unittest.TestCase):
    def test_governance_is_loaded_and_registered(self):
        self.assertIn("'class-cf01-future24-governance.php'", BOOT)
        self.assertIn('CF01_Future24_Governance::register();', BOOT)

    def test_feature_states_fail_closed_without_core_activation(self):
        self.assertIn('CF01_DB::is_enabled()', GOV)
        self.assertIn('shadow or enabled state', GOV)
        self.assertIn('explicitly set it to disabled', GOV)

    def test_evidence_is_bound_to_exact_activation_and_release(self):
        for token in (
            'activation_fingerprint', 'activation_generation', 'head_sha',
            'package_sha256', 'environment', 'runtime_version',
            'schema_version', 'contract_version'
        ):
            self.assertIn(token, GOV)
        self.assertIn('cf01_activation_receipt', GOV)

    def test_required_acceptance_evidence_is_hashed(self):
        for token in (
            'founder_approval_hash', 'privacy_review_hash',
            'clinical_safety_review_hash', 'security_review_hash',
            'staging_acceptance_hash', 'rollback_evidence_hash'
        ):
            self.assertIn(token, GOV)
        self.assertIn('data_governance_hash', GOV)

    def test_evidence_is_immutable_while_feature_is_active(self):
        self.assertIn('immutable while any feature is shadow or enabled', GOV)
        self.assertIn('pre_update_option_', GOV)


if __name__ == '__main__':
    unittest.main()
