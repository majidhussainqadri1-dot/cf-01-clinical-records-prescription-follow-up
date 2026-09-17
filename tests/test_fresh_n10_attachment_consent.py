from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
SOURCE = (ROOT / "sabri-clinical-records/includes/class-cf01-attachments.php").read_text(encoding="utf-8")


def body(name: str) -> str:
    marker = f"public static function {name}("
    start = SOURCE.index(marker)
    brace = SOURCE.index("{", start)
    depth = 0
    for i in range(brace, len(SOURCE)):
        if SOURCE[i] == "{": depth += 1
        elif SOURCE[i] == "}":
            depth -= 1
            if depth == 0: return SOURCE[brace + 1:i]
    raise AssertionError(name)


class AttachmentConsentRegression(unittest.TestCase):
    def test_ready_scan_requires_detected_type_and_current_consent(self):
        scan = body("review_scan")
        self.assertIn("A detected media type is required", scan)
        self.assertIn("required_consent_purposes", scan)
        self.assertIn("CF01_Authorization::consent", scan)

    def test_delivery_revalidates_purpose_specific_consent(self):
        delivery = body("delivery_reference")
        self.assertIn("required_consent_purposes", delivery)
        self.assertIn("CF01_Authorization::consent", delivery)
        self.assertIn("'consent_purposes' => $consent_purposes", delivery)

    def test_declared_and_detected_types_are_both_considered(self):
        self.assertIn("self::consent_purpose_for_type(strtolower(trim($declared_type)))", SOURCE)
        self.assertIn("self::consent_purpose_for_type($detected_type)", SOURCE)
        self.assertIn("array_values(array_unique($purposes))", SOURCE)


if __name__ == "__main__":
    unittest.main()
