import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
AUTH = (ROOT / 'sabri-clinical-records' / 'includes' / 'class-cf01-authorization.php').read_text(encoding='utf-8')
ENCOUNTERS = (ROOT / 'sabri-clinical-records' / 'includes' / 'class-cf01-encounters.php').read_text(encoding='utf-8')
BREAKGLASS = (ROOT / 'sabri-clinical-records' / 'includes' / 'class-cf01-break-glass.php').read_text(encoding='utf-8')


class FreshR2AuthorizationHighRiskTests(unittest.TestCase):
    def test_entered_in_error_runtime_action_requires_step_up(self):
        self.assertIn("'mark_encounter_error'", AUTH)
        self.assertIn("clinician($actor_id, 'mark_encounter_error')", ENCOUNTERS)

    def test_break_glass_use_requires_fresh_step_up(self):
        self.assertIn("'use_break_glass'", AUTH)
        self.assertIn("clinician($actor_id, 'use_break_glass'", BREAKGLASS)

    def test_existing_high_risk_aliases_remain_present(self):
        for action in ("'mark_encounter_entered_in_error'", "'grant_break_glass'", "'review_break_glass'", "'revoke_break_glass'"):
            self.assertIn(action, AUTH)


if __name__ == '__main__':
    unittest.main()
