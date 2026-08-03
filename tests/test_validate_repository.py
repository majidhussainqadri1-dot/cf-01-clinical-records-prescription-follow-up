from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from tools.validate_repository import (
    EXPECTED_FUNCTIONAL_REQUIREMENTS,
    PHASE_TRACEABILITY_PATH,
    REQUIRED_FILES,
    REQUIRED_MARKERS,
    validate,
)


class RepositoryValidatorTests(unittest.TestCase):
    def make_required_files(self, root: Path) -> None:
        for relative in REQUIRED_FILES:
            path = root / relative
            path.parent.mkdir(parents=True, exist_ok=True)
            markers = REQUIRED_MARKERS.get(relative, ())
            content = "synthetic governance content\n"
            if markers:
                content += "\n".join(markers) + "\n"
            if relative == PHASE_TRACEABILITY_PATH:
                content += "\n".join(sorted(EXPECTED_FUNCTIONAL_REQUIREMENTS)) + "\n"
            path.write_text(content, encoding="utf-8")

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

    def test_nested_php_runtime_is_rejected_during_c1_a(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            runtime = root / "docs" / "examples" / "ClinicalRecord.php"
            runtime.parent.mkdir(parents=True)
            runtime.write_text("<?php // synthetic\n", encoding="utf-8")
            errors = validate(root)
            self.assertTrue(any("runtime file" in error for error in errors))

    def test_extensionless_runtime_is_rejected_during_c1_a(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            runtime = root / "docs" / "clinical-runner"
            runtime.parent.mkdir(parents=True, exist_ok=True)
            runtime.write_text("#!/usr/bin/env php\nsynthetic\n", encoding="utf-8")
            errors = validate(root)
            self.assertTrue(any("runtime shebang" in error for error in errors))

    def test_binary_office_artifact_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            (root / "evidence.docx").write_bytes(b"synthetic")
            errors = validate(root)
            self.assertTrue(any("binary/data artifact" in error for error in errors))

    def test_composite_sensitive_path_variant_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            hidden = root / "my_clinical_data_backup" / "sample.txt"
            hidden.parent.mkdir(parents=True)
            hidden.write_text("synthetic\n", encoding="utf-8")
            errors = validate(root)
            self.assertTrue(any("sensitive path variant" in error for error in errors))

    def test_symbolic_link_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            target = root / "target.txt"
            target.write_text("synthetic\n", encoding="utf-8")
            link = root / "linked.txt"
            link.symlink_to(target.name)
            errors = validate(root)
            self.assertTrue(any("symbolic links" in error for error in errors))

    def test_git_lfs_pointer_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            marker = "version https://git-lfs." + "github.com/spec/v1"
            (root / "opaque.txt").write_text(
                f"{marker}\noid sha256:synthetic\nsize 10\n",
                encoding="utf-8",
            )
            errors = validate(root)
            self.assertTrue(any("Git LFS pointer" in error for error in errors))

    def test_git_submodule_config_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            (root / ".gitmodules").write_text(
                "[submodule 'external']\npath = external\nurl = example.invalid/repo\n",
                encoding="utf-8",
            )
            errors = validate(root)
            self.assertTrue(any("indirect file" in error for error in errors))

    def test_missing_required_document_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            (root / "SECURITY.md").unlink()
            errors = validate(root)
            self.assertIn("missing required governance file: SECURITY.md", errors)

    def test_missing_required_marker_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            readme = root / "README.md"
            readme.write_text("Current status:\nActivation law\n", encoding="utf-8")
            errors = validate(root)
            self.assertIn(
                "required governance marker missing from README.md: C1-A governance package",
                errors,
            )

    def test_missing_functional_requirement_mapping_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            traceability = root / PHASE_TRACEABILITY_PATH
            missing = "CF01-FR-032"
            content = traceability.read_text(encoding="utf-8").replace(missing, "")
            traceability.write_text(content, encoding="utf-8")
            errors = validate(root)
            self.assertIn(
                f"future-phase traceability missing functional requirement: {missing}",
                errors,
            )


if __name__ == "__main__":
    unittest.main()
