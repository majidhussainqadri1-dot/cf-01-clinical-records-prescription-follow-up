from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sabri-clinical-records"
INCLUDES = PLUGIN / "includes"


class FutureClinicalIntelligence24Tests(unittest.TestCase):
    def read(self, path: Path) -> str:
        return path.read_text(encoding="utf-8")

    def test_bootstrap_loads_future_runtime_and_rest(self):
        plugin = self.read(PLUGIN / "sabri-clinical-records.php")
        self.assertIn("'class-cf01-future-clinical-intelligence.php'", plugin)
        self.assertIn("'class-cf01-future-rest.php'", plugin)
        self.assertIn("array('CF01_Future_REST', 'register_routes')", plugin)
        self.assertIn("update_option('cf01_future_clinical_intelligence_24', array(), false)", plugin)

    def test_exact_24_feature_catalogue_and_names(self):
        source = self.read(INCLUDES / "class-cf01-future-clinical-intelligence.php")
        registry = source.split("private const FEATURES = array(", 1)[1].split("private const OBSERVATION_TYPES", 1)[0]
        ids = sorted(set(re.findall(r"CF01-FUT-\d{3}", registry)))
        self.assertEqual(ids, [f"CF01-FUT-{i:03d}" for i in range(1, 25)])
        names = [
            "Clinical Sidecar & Membership Assurance",
            "Longitudinal Clinical Timeline",
            "Problem List & Diagnosis Tracking",
            "Allergy & Intolerance Management",
            "Medication & Therapy Management",
            "Lab Results & Trends",
            "Imaging & Diagnostic Reports",
            "Clinical Documents & Attachments",
            "Vitals & Measurements",
            "Immunization & Preventive Care",
            "Care Plans & Goals",
            "Encounters & Clinical Notes",
            "Referrals & Care Coordination",
            "Orders & Results Routing",
            "Clinical Decision Support",
            "Patient-Reported Outcomes",
            "Family & Social History",
            "Reproductive & Maternal Health",
            "Behavioral & Mental Health",
            "Genomics & Precision Medicine",
            "Research & Consent Registry",
            "Institutional Support API + Webhooks",
            "Agent Training & Simulation Lab",
            "Transparency & Fairness Center",
        ]
        for name in names:
            with self.subTest(name=name):
                self.assertIn(name, registry)

    def test_future_features_are_separately_governance_gated_and_default_disabled(self):
        source = self.read(INCLUDES / "class-cf01-future-clinical-intelligence.php")
        for token in [
            "private const FEATURE_STATES = array('disabled', 'shadow', 'enabled')",
            "'founder_approval'",
            "'privacy_review'",
            "'clinical_safety_review'",
            "'security_review'",
            "'staging_acceptance'",
            "'rollback_ready'",
            "'data_governance_approval'",
            "Future clinical capability is not enabled for this runtime",
        ]:
            self.assertIn(token, source)

    def test_clinical_facts_reuse_encrypted_canonical_observations(self):
        source = self.read(INCLUDES / "class-cf01-future-clinical-intelligence.php")
        self.assertIn("CF01_Encounters::add_observation", source)
        self.assertIn("CF01_Crypto::decrypt((string) $row['value_cipher'], 'observation-value')", source)
        self.assertIn("CF01_Crypto::decrypt((string) $row['provenance_cipher'], 'observation-provenance')", source)
        self.assertNotIn("CREATE TABLE", source)
        self.assertIn("source_of_truth_takeover' => false", source)

    def test_decision_support_never_owns_diagnosis_prescription_or_dose(self):
        source = self.read(INCLUDES / "class-cf01-future-clinical-intelligence.php")
        for token in [
            "autonomous_diagnosis",
            "autonomous_prescription",
            "automatic_prescription",
            "automatic_prescription_change",
            "dose_recommendation",
            "potency_recommendation",
            "clinician_review_required",
            "advisory",
        ]:
            self.assertIn(token, source)
        self.assertIn("CF01_Authorization::relationship($patient_uuid, $actor_id, 'clinical_care', 'clinical_decision_support')", source)
        self.assertIn("CF01_Authorization::consent($patient_uuid, 'clinical_care')", source)

    def test_patient_reported_outcome_cannot_change_treatment_before_review(self):
        source = self.read(INCLUDES / "class-cf01-future-clinical-intelligence.php")
        self.assertIn("CF01_Followups::submit_outcome", source)
        self.assertIn("review_status'] ?? '') !== 'pending'", source)
        self.assertIn("'clinician_review_required' => true", source)
        self.assertIn("'automatic_treatment_change' => false", source)

    def test_genomics_research_webhooks_and_simulation_are_fail_closed(self):
        source = self.read(INCLUDES / "class-cf01-future-clinical-intelligence.php")
        self.assertIn("CF01_Authorization::consent($patient_uuid, 'genomics')", source)
        self.assertIn("purpose = %s", source)
        self.assertIn("array($patient_uuid, 'research')", source)
        self.assertIn("minimum_necessary", source)
        self.assertIn("signed_transport", source)
        self.assertIn("idempotent_receiver", source)
        self.assertIn("contains_raw_clinical_data' => false", source)
        self.assertIn("synthetic_case", source)
        self.assertIn("contains_real_patient_data", source)
        self.assertIn("not_for_patient_care", source)
        self.assertNotIn("wp_remote_post(", source)

    def test_transparency_prohibits_financial_or_donor_ranking_signals(self):
        source = self.read(INCLUDES / "class-cf01-future-clinical-intelligence.php")
        for token in ["donor", "donation", "payment", "paid_rank", "financial_priority", "no_covert_health_profiling"]:
            self.assertIn(token, source)
        self.assertIn("human_clinical_authority_preserved", source)

    def test_rest_surface_is_private_idempotent_and_concurrency_aware(self):
        source = self.read(INCLUDES / "class-cf01-future-rest.php")
        for route in [
            "/future/features",
            "/future/sidecar-assurance",
            "/future/patients/",
            "/timeline",
            "/features/",
            "/facts",
            "/future/decision-support",
            "/future/patient-reported-outcomes/",
            "/research-consents",
            "/future/institutional/webhooks/",
            "/future/simulation",
            "/future/transparency/",
        ]:
            with self.subTest(route=route):
                self.assertIn(route, source)
        self.assertIn("permission_callback", source)
        self.assertIn("CF01_REST::permission", source)
        self.assertIn("Idempotency-Key", source)
        self.assertIn("If-Match", source)
        self.assertIn("Cache-Control", source)
        self.assertIn("no-store", source)
        self.assertNotIn("__return_true", source)


if __name__ == "__main__":
    unittest.main()
