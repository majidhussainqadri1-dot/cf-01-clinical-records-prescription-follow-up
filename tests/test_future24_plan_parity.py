import re
import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
FUTURE = ROOT / "sabri-clinical-records" / "includes" / "class-cf01-future24.php"
BOOT = ROOT / "sabri-clinical-records" / "sabri-clinical-records.php"
CSS = ROOT / "sabri-clinical-records" / "assets" / "css" / "clinical.css"


class Future24PlanParityTests(unittest.TestCase):
    def test_all_24_stable_requirements_are_present_once(self):
        source = FUTURE.read_text(encoding="utf-8")
        ids = re.findall(r"'CF01-FUT-(\d{3})'\s*=>\s*array\(", source)
        self.assertEqual(ids, [f"{number:03d}" for number in range(1, 25)])

    def test_future24_is_loaded_and_registered(self):
        source = BOOT.read_text(encoding="utf-8")
        self.assertIn("'class-cf01-future24.php'", source)
        self.assertIn("array('CF01_Future24', 'register_routes')", source)

    def test_governing_route_family_is_present(self):
        source = FUTURE.read_text(encoding="utf-8")
        for fragment in (
            "/future/features",
            "/future/sidecar-assurance",
            "/future/patients/(?P<patient>[a-f0-9-]{36})/timeline",
            "/future/patients/(?P<patient>[a-f0-9-]{36})/features/",
            "/future/decision-support",
            "/future/patient-reported-outcomes/",
            "/future/patients/(?P<patient>[a-f0-9-]{36})/research-consents",
            "/future/institutional/webhooks/",
            "/future/simulation",
            "/future/transparency/",
        ):
            self.assertIn(fragment, source)

    def test_future24_fails_closed_and_requires_governance(self):
        source = FUTURE.read_text(encoding="utf-8")
        self.assertIn("'disabled'", source)
        for gate in (
            "founder_approved",
            "privacy_reviewed",
            "clinical_safety_reviewed",
            "security_reviewed",
            "staging_accepted",
            "rollback_ready",
            "data_governance_approved",
        ):
            self.assertIn(gate, source)
        self.assertIn("source_presence_is_not_activation", source)

    def test_high_risk_mutation_and_clinical_safety_guards(self):
        source = FUTURE.read_text(encoding="utf-8")
        self.assertIn("Idempotency-Key", source)
        self.assertIn("If-Match", source)
        self.assertIn("diagnosis_autonomous", source)
        self.assertIn("prescription_automatic", source)
        self.assertIn("automatic_treatment_change", source)
        self.assertIn("not_for_patient_care", source)
        self.assertIn("donor_priority", source)
        self.assertIn("payment_priority", source)

    def test_sabri_green_fallback_is_current(self):
        source = CSS.read_text(encoding="utf-8").lower()
        self.assertIn("--cf01-primary: var(--sabri-color-primary, #087a4e);", source)
        self.assertNotIn("#ff8a00", source)


if __name__ == "__main__":
    unittest.main()
