from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from tools.validate_repository import (
    CONTRACT_TRACKING_PATH,
    EVIDENCE_MANIFEST_PATH,
    EXPECTED_FUNCTIONAL_REQUIREMENTS,
    EXPECTED_PHASES,
    FINAL_REVIEW_REGISTER_PATH,
    PHASE_TRACEABILITY_PATH,
    REQUIRED_FILES,
    REQUIRED_MARKERS,
    WORKFLOW_PATH,
    validate,
)


PINNED_WORKFLOW = """name: CF-01 Governance Gate

on:
  push:
  pull_request:

permissions:
  contents: read

jobs:
  exact-head:
    name: C1-A exact-head governance review
    runs-on: ubuntu-24.04
    steps:
      - name: Checkout exact head
        uses: actions/checkout@11d5960a326750d5838078e36cf38b85af677262
        with:
          persist-credentials: false
          ref: ${{ github.event.pull_request.head.sha || github.sha }}
      - name: Set up Python
        uses: actions/setup-python@a26af69be951a213d495a4c3e4e4022e16d87065
      - run: git diff --check
  merge:
    name: C1-A merge-ref compatibility
    runs-on: ubuntu-24.04
    steps:
      - name: Checkout merge ref
        uses: actions/checkout@11d5960a326750d5838078e36cf38b85af677262
        with:
          persist-credentials: false
      - run: git diff --check
"""


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
                for index, phase in enumerate(sorted(EXPECTED_PHASES), start=1):
                    content += f"## {index}. {phase}\n"
                for requirement in sorted(EXPECTED_FUNCTIONAL_REQUIREMENTS):
                    content += f"| {requirement} | synthetic mapping |\n"
            if relative == WORKFLOW_PATH:
                content = PINNED_WORKFLOW
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
            (root / "bad.txt").write_text(f"{marker}\nsynthetic\n", encoding="utf-8")
            self.assertTrue(any("private key" in error for error in validate(root)))

    def test_database_dump_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            (root / "patients.sql").write_text("-- synthetic\n", encoding="utf-8")
            self.assertTrue(any("sensitive artifact" in error for error in validate(root)))

    def test_nested_php_runtime_is_rejected_during_c1_a(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            runtime = root / "docs" / "examples" / "ClinicalRecord.php"
            runtime.parent.mkdir(parents=True)
            runtime.write_text("<?php // synthetic\n", encoding="utf-8")
            self.assertTrue(any("runtime file" in error for error in validate(root)))

    def test_extensionless_runtime_is_rejected_during_c1_a(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            runtime = root / "docs" / "clinical-runner"
            runtime.parent.mkdir(parents=True, exist_ok=True)
            runtime.write_text("#!/usr/bin/env php\nsynthetic\n", encoding="utf-8")
            self.assertTrue(any("runtime shebang" in error for error in validate(root)))

    def test_binary_office_artifact_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            (root / "evidence.docx").write_bytes(b"synthetic")
            self.assertTrue(any("binary/data artifact" in error for error in validate(root)))

    def test_composite_sensitive_path_variant_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            hidden = root / "my_clinical_data_backup" / "sample.txt"
            hidden.parent.mkdir(parents=True)
            hidden.write_text("synthetic\n", encoding="utf-8")
            self.assertTrue(any("sensitive path variant" in error for error in validate(root)))

    def test_split_sensitive_path_variant_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            hidden = root / "clinical" / "data" / "sample.txt"
            hidden.parent.mkdir(parents=True)
            hidden.write_text("synthetic\n", encoding="utf-8")
            self.assertTrue(any("sensitive path variant" in error for error in validate(root)))

    def test_generated_or_dependency_tree_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            hidden = root / "node_modules" / "opaque.txt"
            hidden.parent.mkdir(parents=True)
            hidden.write_text("synthetic\n", encoding="utf-8")
            self.assertTrue(any("generated/dependency path" in error for error in validate(root)))

    def test_symbolic_link_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            target = root / "target.txt"
            target.write_text("synthetic\n", encoding="utf-8")
            link = root / "linked.txt"
            link.symlink_to(target.name)
            self.assertTrue(any("symbolic links" in error for error in validate(root)))

    def test_git_lfs_pointer_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            marker = "version https://git-lfs." + "github.com/spec/v1"
            (root / "opaque.txt").write_text(
                f"{marker}\noid sha256:synthetic\nsize 10\n", encoding="utf-8"
            )
            self.assertTrue(any("Git LFS pointer" in error for error in validate(root)))

    def test_git_submodule_config_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            (root / ".gitmodules").write_text(
                "[submodule 'external']\npath = external\nurl = example.invalid/repo\n",
                encoding="utf-8",
            )
            self.assertTrue(any("indirect file" in error for error in validate(root)))

    def test_missing_required_document_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            (root / EVIDENCE_MANIFEST_PATH).unlink()
            self.assertIn(
                f"missing required governance file: {EVIDENCE_MANIFEST_PATH}", validate(root)
            )

    def test_missing_contract_tracking_register_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            (root / CONTRACT_TRACKING_PATH).unlink()
            self.assertIn(
                f"missing required governance file: {CONTRACT_TRACKING_PATH}", validate(root)
            )

    def test_missing_final_review_register_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            (root / FINAL_REVIEW_REGISTER_PATH).unlink()
            self.assertIn(
                f"missing required governance file: {FINAL_REVIEW_REGISTER_PATH}", validate(root)
            )

    def test_missing_required_marker_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            readme = root / "README.md"
            readme.write_text("Current status:\nActivation law\n", encoding="utf-8")
            self.assertIn(
                "required governance marker missing from README.md: C1-A governance package",
                validate(root),
            )

    def test_missing_functional_requirement_table_mapping_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            traceability = root / PHASE_TRACEABILITY_PATH
            missing = "CF01-FR-032"
            content = traceability.read_text(encoding="utf-8").replace(
                f"| {missing} | synthetic mapping |\n", ""
            )
            traceability.write_text(content, encoding="utf-8")
            self.assertIn(
                f"future-phase table mapping missing functional requirement: {missing}",
                validate(root),
            )

    def test_duplicate_functional_requirement_table_mapping_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            traceability = root / PHASE_TRACEABILITY_PATH
            duplicate = "CF01-FR-001"
            with traceability.open("a", encoding="utf-8") as handle:
                handle.write(f"| {duplicate} | duplicate synthetic mapping |\n")
            self.assertIn(
                f"future-phase table duplicates functional requirement mapping: {duplicate}",
                validate(root),
            )

    def test_missing_phase_heading_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            traceability = root / PHASE_TRACEABILITY_PATH
            missing_phase = "C1-G"
            content = traceability.read_text(encoding="utf-8")
            phase_index = sorted(EXPECTED_PHASES).index(missing_phase) + 1
            content = content.replace(f"## {phase_index}. {missing_phase}\n", "")
            traceability.write_text(content, encoding="utf-8")
            self.assertIn(
                f"future-phase traceability missing phase heading: {missing_phase}",
                validate(root),
            )

    def test_floating_workflow_action_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            workflow = root / WORKFLOW_PATH
            workflow.write_text(
                PINNED_WORKFLOW.replace(
                    "actions/checkout@11d5960a326750d5838078e36cf38b85af677262",
                    "actions/checkout@v4",
                ),
                encoding="utf-8",
            )
            self.assertTrue(any("not pinned" in error for error in validate(root)))

    def test_pull_request_target_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            workflow = root / WORKFLOW_PATH
            workflow.write_text(
                PINNED_WORKFLOW.replace("pull_request:", "pull_request_target:"),
                encoding="utf-8",
            )
            self.assertIn("workflow must not use pull_request_target", validate(root))

    def test_latest_runner_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            workflow = root / WORKFLOW_PATH
            workflow.write_text(
                PINNED_WORKFLOW.replace("ubuntu-24.04", "ubuntu-latest"),
                encoding="utf-8",
            )
            errors = validate(root)
            self.assertTrue(any("fixed" in error or "latest" in error for error in errors))


if __name__ == "__main__":
    unittest.main()
