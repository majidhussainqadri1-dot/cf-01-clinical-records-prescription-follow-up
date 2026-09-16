import unittest
from pathlib import Path

SOURCE = (Path(__file__).resolve().parents[1] / 'sabri-clinical-records' / 'includes' / 'class-cf01-consents.php').read_text(encoding='utf-8')


class FreshR2MinorGuardianConsentTests(unittest.TestCase):
    def test_supported_purpose_is_validated_before_authorization(self):
        purpose_check = SOURCE.index("if (!in_array($purpose, self::PURPOSES, true))")
        patient_load = SOURCE.index("$patient = CF01_Patients::get($patient_uuid);")
        authorization = SOURCE.index("$authority = self::authorize_subject")
        self.assertLess(purpose_check, patient_load)
        self.assertLess(purpose_check, authorization)

    def test_platform_minimum_and_jurisdiction_are_part_of_minor_gate(self):
        for token in (
            "Clinical jurisdiction evidence is unavailable",
            "$sex === 'male' ? 15",
            "$sex === 'female' ? 12",
            'max($platform_minimum, $legal_majority_age)',
        ):
            self.assertIn(token, SOURCE)

    def test_minor_patient_cannot_self_create_clinical_consent(self):
        self.assertIn("($authority['role'] ?? '') === 'patient'", SOURCE)
        self.assertIn('A legal minor cannot create a new clinical consent', SOURCE)

    def test_current_guardian_authority_scope_and_expiry_are_rechecked(self):
        for token in (
            'require_current_minor_guardian',
            'CF01_Contracts::guardian_authority',
            'Verified guardian authority has expired',
            'Verified guardian scope does not authorize this consent purpose',
            'Current guardian authority evidence is required for minor clinical consent',
        ):
            self.assertIn(token, SOURCE)


if __name__ == '__main__':
    unittest.main()
