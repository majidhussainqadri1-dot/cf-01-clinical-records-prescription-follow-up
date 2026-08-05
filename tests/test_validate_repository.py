from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from tools.validate_repository import (
    EXPECTED_FUNCTIONAL_REQUIREMENTS,
    EXPECTED_PHASES,
    PHASE_TRACEABILITY_PATH,
    REQUIRED_FILES,
    REQUIRED_MARKERS,
    RUNTIME_TRACEABILITY_PATH,
    WORKFLOW_PATH,
    validate,
)


PINNED_CHECKOUT = "11d5960a326750d5838078e36cf38b85af677262"
PINNED_SETUP_PYTHON = "a26af69be951a213d495a4c3e4e4022e16d87065"


class RepositoryValidatorTests(unittest.TestCase):
    def workflow_content(self) -> str:
        return f"""name: Synthetic CF-01 gate
on: [push, pull_request]
permissions:
  contents: read
jobs:
  exact-head:
    runs-on: ubuntu-24.04
    strategy:
      matrix:
        php: [\"8.1\", \"8.3\"]
    steps:
      - uses: actions/checkout@{PINNED_CHECKOUT}
        with:
          persist-credentials: false
          ref: ${{{{ github.event.pull_request.head.sha || github.sha }}}}
      - uses: actions/setup-python@{PINNED_SETUP_PYTHON}
      - run: python3 tools/validate_repository.py .
      - run: python3 tools/validate_runtime.py .
      - run: cmp -s first.zip second.zip
  merge-ref-compatibility:
    name: merge-ref compatibility
    runs-on: ubuntu-24.04
    steps:
      - uses: actions/checkout@{PINNED_CHECKOUT}
        with:
          persist-credentials: false
"""

    def make_required_files(self, root: Path) -> None:
        for relative in REQUIRED_FILES:
            path = root / relative
            path.parent.mkdir(parents=True, exist_ok=True)
            if relative == WORKFLOW_PATH:
                content = self.workflow_content()
            elif relative == PHASE_TRACEABILITY_PATH:
                content = "Phase constitution\nDefinition of Done\nCurrent status\n"
                for index, phase in enumerate(sorted(EXPECTED_PHASES), start=1):
                    content += f"## {index}. {phase}\n"
                for requirement in sorted(EXPECTED_FUNCTIONAL_REQUIREMENTS):
                    content += f"| {requirement} | synthetic mapping |\n"
            elif relative == RUNTIME_TRACEABILITY_PATH:
                content = "# CF-01 Requirements Traceability\n"
                for requirement in sorted(EXPECTED_FUNCTIONAL_REQUIREMENTS):
                    content += f"| {requirement} synthetic requirement | source | evidence |\n"
            elif path.suffix == ".php":
                content = "<?php\n// synthetic non-identifying source\n"
            elif path.suffix == ".py":
                content = "# synthetic validator fixture\n"
            elif path.suffix == ".sh":
                content = "#!/usr/bin/env bash\n# synthetic\n"
            else:
                content = "synthetic public-safe content\n"

            for marker in REQUIRED_MARKERS.get(relative, ()):
                if marker not in content:
                    content += marker + "\n"
            path.write_text(content, encoding="utf-8")

    def test_clean_authorized_runtime_repository_passes(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            self.assertEqual(validate(root), [])

    def test_plugin_php_runtime_is_allowed(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            runtime = root / "sabri-clinical-records" / "includes" / "class-synthetic.php"
            runtime.parent.mkdir(parents=True, exist_ok=True)
            runtime.write_text("<?php\n// synthetic\n", encoding="utf-8")
            self.assertEqual(validate(root), [])

    def test_runtime_outside_approved_roots_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            runtime = root / "docs" / "examples" / "ClinicalRecord.php"
            runtime.parent.mkdir(parents=True, exist_ok=True)
            runtime.write_text("<?php // synthetic\n", encoding="utf-8")
            self.assertTrue(any("runtime file outside approved roots" in e for e in validate(root)))

    def test_browser_runtime_outside_plugin_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            script = root / "tests" / "browser.js"
            script.write_text("// synthetic\n", encoding="utf-8")
            self.assertTrue(any("browser runtime outside plugin root" in e for e in validate(root)))

    def test_private_key_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            marker = "-----BEGIN " + "PRIVATE KEY-----"
            (root / "bad.txt").write_text(f"{marker}\nsynthetic\n", encoding="utf-8")
            self.assertTrue(any("private key" in e for e in validate(root)))

    def test_database_dump_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            (root / "patients.sql").write_text("-- synthetic\n", encoding="utf-8")
            self.assertTrue(any("sensitive artifact" in e for e in validate(root)))

    def test_binary_office_artifact_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            (root / "evidence.docx").write_bytes(b"synthetic")
            self.assertTrue(any("binary/archive artifact" in e for e in validate(root)))

    def test_sensitive_path_variant_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            hidden = root / "production_clinical_data" / "sample.txt"
            hidden.parent.mkdir(parents=True, exist_ok=True)
            hidden.write_text("synthetic\n", encoding="utf-8")
            self.assertTrue(any("sensitive path variant" in e for e in validate(root)))

    def test_identifying_fixture_assignment_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            (root / "bad.txt").write_text("patient_name = 'Example Person'\n", encoding="utf-8")
            self.assertTrue(any("identifying clinical fixture" in e for e in validate(root)))

    def test_symbolic_link_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            target = root / "target.txt"
            target.write_text("synthetic\n", encoding="utf-8")
            (root / "linked.txt").symlink_to(target.name)
            self.assertTrue(any("symbolic links" in e for e in validate(root)))

    def test_git_lfs_pointer_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            marker = "version https://git-lfs." + "github.com/spec/v1"
            (root / "opaque.txt").write_text(
                f"{marker}\noid sha256:synthetic\nsize 10\n", encoding="utf-8"
            )
            self.assertTrue(any("Git LFS pointer" in e for e in validate(root)))

    def test_git_submodule_config_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            (root / ".gitmodules").write_text(
                "[submodule 'external']\npath = external\nurl = example.invalid/repo\n",
                encoding="utf-8",
            )
            self.assertTrue(any("indirect file" in e for e in validate(root)))

    def test_missing_required_document_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            (root / "SECURITY.md").unlink()
            self.assertIn("missing required repository file: SECURITY.md", validate(root))

    def test_missing_authorization_marker_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            change = root / "CHANGE_CONTROL.md"
            content = change.read_text(encoding="utf-8").replace(
                "CF01-CCR-2026-08-06-003\n", ""
            )
            change.write_text(content, encoding="utf-8")
            self.assertTrue(any("CF01-CCR-2026-08-06-003" in e for e in validate(root)))

    def test_missing_future_phase_requirement_is_rejected(self) -> None:
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
                f"future-phase traceability missing functional requirement: {missing}",
                validate(root),
            )

    def test_missing_runtime_requirement_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            traceability = root / RUNTIME_TRACEABILITY_PATH
            missing = "CF01-FR-031"
            content = traceability.read_text(encoding="utf-8").replace(
                f"| {missing} synthetic requirement | source | evidence |\n", ""
            )
            traceability.write_text(content, encoding="utf-8")
            self.assertIn(
                f"runtime traceability missing functional requirement: {missing}",
                validate(root),
            )

    def test_duplicate_runtime_requirement_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            traceability = root / RUNTIME_TRACEABILITY_PATH
            requirement = "CF01-FR-010"
            with traceability.open("a", encoding="utf-8") as handle:
                handle.write(f"| {requirement} duplicate | source | evidence |\n")
            self.assertTrue(any("must map CF01-FR-010 exactly once" in e for e in validate(root)))

    def test_missing_phase_heading_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            traceability = root / PHASE_TRACEABILITY_PATH
            missing_phase = "C1-G"
            phase_index = sorted(EXPECTED_PHASES).index(missing_phase) + 1
            content = traceability.read_text(encoding="utf-8").replace(
                f"## {phase_index}. {missing_phase}\n", ""
            )
            traceability.write_text(content, encoding="utf-8")
            self.assertIn(
                f"future-phase traceability missing phase heading: {missing_phase}",
                validate(root),
            )

    def test_unpinned_action_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.make_required_files(root)
            workflow = root / WORKFLOW_PATH
            content = workflow.read_text(encoding="utf-8").replace(
                f"actions/setup-python@{PINNED_SETUP_PYTHON}", "actions/setup-python@v5"
            )
            workflow.write_text(content, encoding="utf-8")
            self.assertTrue(any("not pinned" in e for e in validate(root)))


if __name__ == "__main__":
    unittest.main()
