import unittest
from pathlib import Path

SOURCE = (Path(__file__).resolve().parents[1] / 'sabri-clinical-records' / 'includes' / 'class-cf01-future24.php').read_text(encoding='utf-8')


class Future24PatientReportedOutcomeSafetyTests(unittest.TestCase):
    def test_patient_reported_outcome_flags_are_forced_after_provider(self):
        start = SOURCE.index('public static function patient_reported_outcomes')
        end = SOURCE.index('public static function research_consents', start)
        block = SOURCE[start:end]
        self.assertIn("$result['patient_reported'] = true;", block)
        self.assertIn("$result['clinician_review_required'] = true;", block)
        self.assertIn("$result['automatic_treatment_change'] = false;", block)
        self.assertNotIn("$result + array('automatic_treatment_change' => false)", block)


if __name__ == '__main__':
    unittest.main()
