from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
SOURCE = (ROOT / "sabri-clinical-records/includes/class-cf01-followups.php").read_text(encoding="utf-8")


def method_body(name: str) -> str:
    marker = f"public static function {name}("
    start = SOURCE.index(marker)
    brace = SOURCE.index("{", start)
    depth = 0
    for pos in range(brace, len(SOURCE)):
        if SOURCE[pos] == "{":
            depth += 1
        elif SOURCE[pos] == "}":
            depth -= 1
            if depth == 0:
                return SOURCE[brace + 1:pos]
    raise AssertionError(name)


class FollowupStateMachineRegression(unittest.TestCase):
    def test_review_enters_under_review_before_final_review_state(self):
        body = method_body("review")
        first = body.index("transition_allowed((string) $current_followup['status'], 'under_review')")
        second = body.index("transition_allowed((string) $current_followup['status'], $next)")
        self.assertLess(first, second)
        self.assertIn("array('status' => 'under_review')", body)

    def test_reschedule_uses_declared_transition_map_and_recomputes_overdue_window(self):
        body = method_body("reschedule")
        self.assertIn("transition_allowed((string) $row['status'], 'rescheduled')", body)
        self.assertIn("'overdue_at' => $new_overdue", body)
        self.assertIn("$old_overdue_ts", body)
        self.assertIn("$grace_seconds", body)

    def test_close_cannot_bypass_needs_contact_state(self):
        body = method_body("close")
        self.assertIn("transition_allowed((string) $row['status'], 'closed')", body)
        self.assertNotIn("array('reviewed', 'needs_contact')", body)

    def test_state_map_still_requires_contact_resolution_before_close(self):
        self.assertIn("'needs_contact' => array('under_review', 'reviewed', 'rescheduled')", SOURCE)
        self.assertIn("'reviewed' => array('closed', 'rescheduled')", SOURCE)


if __name__ == "__main__":
    unittest.main()
