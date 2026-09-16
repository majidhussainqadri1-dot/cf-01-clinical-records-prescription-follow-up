import unittest
from pathlib import Path

SOURCE = (Path(__file__).resolve().parents[1] / 'sabri-clinical-records' / 'includes' / 'class-cf01-relationships.php').read_text(encoding='utf-8')


class FreshN4PatientRelationshipEndTests(unittest.TestCase):
    def test_patient_owner_can_only_use_self_service_path_for_end(self):
        self.assertIn("$next === 'ended' && CF01_Authorization::patient_owner", SOURCE)
        self.assertIn("CF01_Contracts::recent_auth($actor_id, 'end_own_relationship')", SOURCE)
        self.assertIn('Current recent step-up authentication is required to end a treating relationship.', SOURCE)

    def test_non_end_transitions_still_use_staff_relationship_authority(self):
        self.assertIn("self::authorize_relationship_actor($actor_id, $relationship, 'transition_relationship');", SOURCE)

    def test_termination_reconciliation_still_precedes_end_write(self):
        reconcile = SOURCE.index('self::require_termination_reconciliation($actor_id, $current, $reason);')
        write = SOURCE.index("'status' => 'ended'", reconcile)
        self.assertLess(reconcile, write)


if __name__ == '__main__':
    unittest.main()
