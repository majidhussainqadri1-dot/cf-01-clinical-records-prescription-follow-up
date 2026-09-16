import unittest
from pathlib import Path

SOURCE = (Path(__file__).resolve().parents[1] / 'sabri-clinical-records' / 'includes' / 'class-cf01-attachments.php').read_text(encoding='utf-8')


class FreshN5AttachmentConsentTests(unittest.TestCase):
    def test_attachment_consent_is_derived_from_declared_media_type(self):
        self.assertIn("str_starts_with($declared_type, 'image/')", SOURCE)
        self.assertIn("return 'images';", SOURCE)
        self.assertIn("str_starts_with($declared_type, 'audio/')", SOURCE)
        self.assertIn("str_starts_with($declared_type, 'video/')", SOURCE)
        self.assertIn("return 'recording';", SOURCE)
        self.assertIn("return 'clinical_care';", SOURCE)

    def test_attach_uses_derived_consent_purpose(self):
        self.assertIn("$consent_purpose = self::consent_purpose_for_type($declared_type);", SOURCE)
        self.assertIn("CF01_Authorization::consent($patient_uuid, $consent_purpose);", SOURCE)
        self.assertNotIn("CF01_Authorization::consent($patient_uuid, 'images');", SOURCE)

    def test_provider_and_audit_receive_the_same_purpose(self):
        self.assertIn("'consent_purpose' => $consent_purpose", SOURCE)
        self.assertIn("'ClinicalAttachmentQuarantined', 'clinical_attachment', $uuid, $consent_purpose", SOURCE)


if __name__ == '__main__':
    unittest.main()
