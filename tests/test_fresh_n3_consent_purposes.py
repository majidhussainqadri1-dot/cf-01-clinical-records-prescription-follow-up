import re
import unittest
from pathlib import Path

SOURCE = (Path(__file__).resolve().parents[1] / 'sabri-clinical-records' / 'includes' / 'class-cf01-consents.php').read_text(encoding='utf-8')


class FreshN3ConsentPurposeTests(unittest.TestCase):
    def test_governing_educational_reuse_purpose_is_canonical(self):
        match = re.search(r"public const PURPOSES\s*=\s*array\((.*?)\);", SOURCE, re.S)
        self.assertIsNotNone(match)
        purposes = re.findall(r"'([^']+)'", match.group(1))
        self.assertEqual(
            purposes,
            ['clinical_care', 'teleconsultation', 'images', 'recording', 'educational_reuse', 'transfer', 'research'],
        )

    def test_ambiguous_legacy_education_identifier_is_not_a_consent_purpose(self):
        match = re.search(r"public const PURPOSES\s*=\s*array\((.*?)\);", SOURCE, re.S)
        self.assertIsNotNone(match)
        purposes = re.findall(r"'([^']+)'", match.group(1))
        self.assertNotIn('education', purposes)


if __name__ == '__main__':
    unittest.main()
