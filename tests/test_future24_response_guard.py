import unittest
from pathlib import Path

SOURCE = (Path(__file__).resolve().parents[1] / 'sabri-clinical-records' / 'includes' / 'class-cf01-future24-guard.php').read_text(encoding='utf-8')


class Future24ResponseGuardTests(unittest.TestCase):
    def test_provider_must_assert_minimum_necessary_and_authorization(self):
        for token in ('authorization_checked', 'minimum_necessary', 'canonical_owner', 'contract_version'):
            self.assertIn(token, SOURCE)

    def test_nested_autonomous_clinical_outputs_are_rejected(self):
        for token in ('dose', 'dosage', 'potency', 'diagnosis_autonomous', 'prescription_automatic', 'automatic_treatment_change'):
            self.assertIn(token, SOURCE)
        self.assertIn('contains_nonempty_key', SOURCE)

    def test_nested_financial_bias_is_rejected(self):
        for token in ('donor_priority', 'payment_priority', 'paid_rank', 'donor_advantage'):
            self.assertIn(token, SOURCE)

    def test_raw_secrets_and_simulation_subjects_are_rejected(self):
        for token in ('provider_secret', 'raw_payload', 'private_key', 'patient_uuid', 'platform_uuid'):
            self.assertIn(token, SOURCE)


if __name__ == '__main__':
    unittest.main()
