from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from tools.generate_sbom import generate_sbom


class DeterministicSbomTests(unittest.TestCase):
    def make_stage(self, root: Path) -> Path:
        stage = root / "sabri-clinical-records"
        (stage / "includes").mkdir(parents=True)
        (stage / "plugin.php").write_text("<?php\n// synthetic\n", encoding="utf-8")
        (stage / "includes" / "class-example.php").write_text(
            "<?php\n// synthetic component\n", encoding="utf-8"
        )
        return stage

    def test_sbom_is_deterministic_and_spdx_23(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            stage = self.make_stage(Path(directory))
            first = generate_sbom(stage, "cf-01-1.0.0", "1.0.0", 1785792000)
            second = generate_sbom(stage, "cf-01-1.0.0", "1.0.0", 1785792000)
            self.assertEqual(first, second)
            self.assertEqual(first["spdxVersion"], "SPDX-2.3")
            self.assertEqual(first["dataLicense"], "CC0-1.0")
            self.assertEqual(first["creationInfo"]["created"], "2026-08-03T21:20:00Z")
            self.assertEqual(len(first["packages"]), 1)
            self.assertEqual(len(first["files"]), 2)
            self.assertTrue(first["documentNamespace"].startswith("https://sabrihomeopathy.com/spdx/cf-01/1.0.0/"))

    def test_file_entries_are_sorted_and_checksummed(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            stage = self.make_stage(Path(directory))
            document = generate_sbom(stage, "cf-01-1.0.0", "1.0.0", 1785792000)
            names = [entry["fileName"] for entry in document["files"]]
            self.assertEqual(names, sorted(names))
            for entry in document["files"]:
                algorithms = {item["algorithm"] for item in entry["checksums"]}
                self.assertEqual(algorithms, {"SHA1", "SHA256"})
                self.assertTrue(entry["SPDXID"].startswith("SPDXRef-File-"))

    def test_namespace_and_verification_code_change_with_content(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            stage = self.make_stage(Path(directory))
            first = generate_sbom(stage, "cf-01-1.0.0", "1.0.0", 1785792000)
            (stage / "plugin.php").write_text("<?php\n// changed synthetic content\n", encoding="utf-8")
            second = generate_sbom(stage, "cf-01-1.0.0", "1.0.0", 1785792000)
            self.assertNotEqual(first["documentNamespace"], second["documentNamespace"])
            self.assertNotEqual(
                first["packages"][0]["packageVerificationCode"],
                second["packages"][0]["packageVerificationCode"],
            )

    def test_empty_stage_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            with self.assertRaisesRegex(ValueError, "contains no files"):
                generate_sbom(Path(directory), "cf-01-1.0.0", "1.0.0", 1785792000)


if __name__ == "__main__":
    unittest.main()
