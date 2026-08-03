from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from tools.validate_repository import REQUIRED_FILES, validate


class RepositoryValidatorTests(unittest.TestCase):
    def make_required_files(self, root: Path) -> None:
        for relative in REQUIRED_FILES:
            path = root / relative
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text("synthetic governance content\n", encoding="utf-8")

    def test_clean_governance_repository_passes(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            self.assertEqual(validate(root), [])

    def test_private_key_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            marker = "-----BEGIN " + "PRIVATE KEY-----"
            (root / "bad.txt").write_text(
                f"{marker}\nsynthetic\n",
                encoding="utf-8",
            )
            errors = validate(root)
            self.assertTrue(any("private key" in error for error in errors))

    def test_database_dump_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            (root / "patients.sql").write_text("-- synthetic\n", encoding="utf-8")
            errors = validate(root)
            self.assertTrue(any("sensitive artifact" in error for error in errors))

    def test_runtime_source_is_rejected_during_c1_a(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            runtime = root / "src" / "ClinicalRecord.php"
            runtime.parent.mkdir(parents=True)
            runtime.write_text("<?php // synthetic\n", encoding="utf-8")
            errors = validate(root)
            self.assertTrue(any("not authorized during C1-A" in error for error in errors))

    def test_missing_required_document_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            (root / "SECURITY.md").unlink()
            errors = validate(root)
            self.assertIn("missing required governance file: SECURITY.md", errors)


if __name__ == "__main__":
    unittest.main()
