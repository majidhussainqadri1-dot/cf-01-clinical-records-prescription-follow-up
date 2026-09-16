import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
GUARD = ROOT / 'sabri-clinical-records' / 'includes' / 'class-cf01-future24-guard.php'
BOOT = ROOT / 'sabri-clinical-records' / 'sabri-clinical-records.php'


class Future24SecurityGuardTests(unittest.TestCase):
    def test_guard_is_loaded_before_future_routes(self):
        source = BOOT.read_text(encoding='utf-8')
        self.assertIn("'class-cf01-future24-guard.php'", source)
        self.assertIn("CF01_Future24_Guard::register();", source)

    def test_mutations_require_idempotency(self):
        source = GUARD.read_text(encoding='utf-8')
        self.assertIn("Idempotency-Key", source)
        self.assertIn("strlen($key) < 16", source)

    def test_patient_fact_writes_are_doctor_scoped(self):
        source = GUARD.read_text(encoding='utf-8')
        self.assertIn("'clinical_care', 'doctor'", source)
        self.assertIn("future24_clinical_fact_write", source)

    def test_institutional_and_simulation_routes_are_privileged(self):
        source = GUARD.read_text(encoding='utf-8')
        self.assertIn("cf01_manage_clinical_records", source)
        self.assertIn("cf01_manage_clinical_keys", source)
        self.assertIn("cf01_future24_institutional_authorized", source)
        self.assertIn("cf01_audit_clinical", source)
        self.assertIn("future24_simulation", source)

    def test_transparency_requires_explicit_patient_scoped_authorization(self):
        source = GUARD.read_text(encoding='utf-8')
        self.assertIn("cf01_future24_transparency_authorized", source)
        self.assertIn("clinical_transparency", source)


if __name__ == '__main__':
    unittest.main()
