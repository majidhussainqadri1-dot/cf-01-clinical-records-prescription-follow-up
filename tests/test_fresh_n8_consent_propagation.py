from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[1]
ENCOUNTERS = (ROOT / "sabri-clinical-records/includes/class-cf01-encounters.php").read_text(encoding="utf-8")
PRESCRIPTIONS = (ROOT / "sabri-clinical-records/includes/class-cf01-prescriptions.php").read_text(encoding="utf-8")


def method_body(source: str, method: str) -> str:
    marker = f"public static function {method}("
    start = source.index(marker)
    brace = source.index("{", start)
    depth = 0
    for pos in range(brace, len(source)):
        if source[pos] == "{":
            depth += 1
        elif source[pos] == "}":
            depth -= 1
            if depth == 0:
                return source[brace + 1 : pos]
    raise AssertionError(f"Unclosed method: {method}")


class ConsentWithdrawalPropagationTests(unittest.TestCase):
    def assert_clinical_consent(self, body: str):
        self.assertIn("CF01_Authorization::consent((string) $row['patient_uuid'], 'clinical_care');", body)

    def test_encounter_draft_updates_revalidate_clinical_and_teleconsultation_consent(self):
        body = method_body(ENCOUNTERS, "update_draft")
        self.assert_clinical_consent(body)
        self.assertIn("($row['mode'] ?? '') === 'teleconsultation'", body)
        self.assertIn("CF01_Authorization::consent((string) $row['patient_uuid'], 'teleconsultation');", body)

    def test_encounter_signature_revalidates_teleconsultation_consent(self):
        body = method_body(ENCOUNTERS, "sign")
        self.assert_clinical_consent(body)
        self.assertIn("($row['mode'] ?? '') === 'teleconsultation'", body)
        self.assertIn("CF01_Authorization::consent((string) $row['patient_uuid'], 'teleconsultation');", body)

    def test_addendum_revalidates_current_care_and_teleconsultation_consent(self):
        body = method_body(ENCOUNTERS, "addendum")
        self.assertIn("CF01_Authorization::consent((string) $parent['patient_uuid'], 'clinical_care');", body)
        self.assertIn("($parent['mode'] ?? '') === 'teleconsultation'", body)
        self.assertIn("CF01_Authorization::consent((string) $parent['patient_uuid'], 'teleconsultation');", body)

    def test_prescription_draft_update_revalidates_current_clinical_consent(self):
        body = method_body(PRESCRIPTIONS, "update")
        self.assertIn("CF01_Authorization::consent((string) $row['patient_uuid'], 'clinical_care');", body)

    def test_integrity_and_safety_cessation_paths_are_not_blanket_blocked_by_new_guard(self):
        mark_error = method_body(ENCOUNTERS, "mark_entered_in_error")
        discontinue = method_body(PRESCRIPTIONS, "discontinue")
        self.assertNotIn("CF01_Authorization::consent", mark_error)
        self.assertNotIn("CF01_Authorization::consent", discontinue)


if __name__ == "__main__":
    unittest.main()
