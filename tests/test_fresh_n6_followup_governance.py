import unittest
from pathlib import Path

SOURCE = (Path(__file__).resolve().parents[1] / 'sabri-clinical-records' / 'includes' / 'class-cf01-followups.php').read_text(encoding='utf-8')


class FreshN6FollowupGovernanceTests(unittest.TestCase):
    def test_patient_outcome_always_rechecks_runtime_actor_and_patient_scope(self):
        self.assertIn("CF01_Authorization::actor($actor_id, 'submit_patient_outcome'", SOURCE)
        self.assertIn("CF01_Role_Context::resolve($actor_id, (string) $followup['patient_uuid'], 'clinical_care', 'guardian');", SOURCE)

    def test_unexpected_events_remain_supported_patient_reported_data(self):
        self.assertIn("'unexpected_events'", SOURCE)
        self.assertIn("'automatic_assessment'] = false", SOURCE)
        self.assertIn("'automatic_prescription_change'] = false", SOURCE)

    def test_followup_plan_records_method_and_responsible_clinician(self):
        self.assertIn("'method' => $method", SOURCE)
        self.assertIn("'responsible_clinician_user_id' => $actor_id", SOURCE)
        self.assertIn("'responsible_professional_uuid' => $professional_uuid", SOURCE)

    def test_clinician_review_requires_and_signs_next_plan(self):
        self.assertIn('A clinician-entered next plan is required', SOURCE)
        self.assertIn("'next_plan_hash'", SOURCE)
        self.assertIn("CF01_Crypto::sign($snapshot, 'followup-review-signature')", SOURCE)
        self.assertIn("'signed_next_plan' => true", SOURCE)


if __name__ == '__main__':
    unittest.main()
