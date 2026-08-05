#!/usr/bin/env python3
"""Public-repository safety and governance-integrity gate for CF-01 C1-A.

C1-A is governance and architecture only. This validator blocks sensitive
artifacts, runtime code, supply-chain indirection, hidden generated trees and
premature clinical implementation until a later Founder-approved change-control
record authorizes C1-B. It also requires the complete public-safe evidence
package, validates workflow hardening, and enforces unique future-phase
requirement mappings.
"""

from __future__ import annotations

import argparse
import re
import sys
from collections import Counter
from pathlib import Path
from typing import Iterator

PHASE_TRACEABILITY_PATH = "docs/C1-B-TO-C1-H-IMPLEMENTATION-TRACEABILITY.md"
CONTRACT_TRACKING_PATH = "docs/C1-A-CROSS-REPOSITORY-CONTRACT-TRACKING.md"
EVIDENCE_MANIFEST_PATH = "docs/C1-A-EVIDENCE-MANIFEST.md"
FINAL_REVIEW_REGISTER_PATH = "docs/C1-A-FINAL-REVIEW-CORRECTION-REGISTER.md"
WORKFLOW_PATH = ".github/workflows/governance.yml"

EXPECTED_FUNCTIONAL_REQUIREMENTS = {
    f"CF01-FR-{number:03d}" for number in range(1, 33)
}
EXPECTED_PHASES = {f"C1-{letter}" for letter in "BCDEFGH"}

REQUIRED_FILES = {
    WORKFLOW_PATH,
    "README.md",
    "CHANGE_CONTROL.md",
    "SECURITY.md",
    "docs/C1-A-FOUNDATION.md",
    "docs/C1-A-CROSS-FILE-CONTRACTS.md",
    CONTRACT_TRACKING_PATH,
    "docs/C1-A-LEGAL-PROFESSIONAL-APPLICABILITY-REGISTER.md",
    "docs/C1-A-RETENTION-LEGAL-HOLD-MATRIX.md",
    "docs/C1-A-CRYPTOGRAPHY-STORAGE-ATTACHMENT-SECURITY.md",
    "docs/C1-A-OPERATIONAL-OWNERSHIP-AND-ESCALATION.md",
    "docs/C1-A-INDEPENDENT-REVIEW-PLAN.md",
    "docs/C1-A-REQUIREMENTS-TRACEABILITY.md",
    EVIDENCE_MANIFEST_PATH,
    FINAL_REVIEW_REGISTER_PATH,
    PHASE_TRACEABILITY_PATH,
    "tests/test_validate_repository.py",
    "tools/validate_repository.py",
}

REQUIRED_MARKERS = {
    "README.md": (
        "Current status:",
        "Activation law",
        "C1-A governance package",
    ),
    "docs/C1-A-CROSS-FILE-CONTRACTS.md": (
        "Contract constitution",
        "Canonical ownership matrix",
        "Freeze gate",
    ),
    CONTRACT_TRACKING_PATH: (
        "Contract issue register",
        "Freeze criteria for every contract",
        "Current decision",
    ),
    "docs/C1-A-LEGAL-PROFESSIONAL-APPLICABILITY-REGISTER.md": (
        "Governing rule",
        "Jurisdiction register",
        "Current decision",
    ),
    "docs/C1-A-RETENTION-LEGAL-HOLD-MATRIX.md": (
        "Retention constitution",
        "Record-category matrix",
        "Current decision",
    ),
    "docs/C1-A-CRYPTOGRAPHY-STORAGE-ATTACHMENT-SECURITY.md": (
        "Security objectives",
        "Key hierarchy",
        "Attachment pipeline",
        "Current decision",
    ),
    "docs/C1-A-OPERATIONAL-OWNERSHIP-AND-ESCALATION.md": (
        "Mandatory roles",
        "Separation-of-duties rules",
        "Current decision",
    ),
    "docs/C1-A-INDEPENDENT-REVIEW-PLAN.md": (
        "Independence constitution",
        "Required review streams",
        "Current decision",
    ),
    "docs/C1-A-REQUIREMENTS-TRACEABILITY.md": (
        "CF01-A-020",
        "Evidence-package inventory",
        "Release rule",
    ),
    EVIDENCE_MANIFEST_PATH: (
        "Evidence law",
        "Truthful status",
        "Phase-exit decision",
    ),
    FINAL_REVIEW_REGISTER_PATH: (
        "Confirmed defects corrected",
        "Fresh final review",
        "Merge-readiness boundary",
    ),
    PHASE_TRACEABILITY_PATH: (
        "Phase constitution",
        "C1-B",
        "C1-H",
        "Definition of Done",
        "Current status",
    ),
}

FORBIDDEN_SENSITIVE_SUFFIXES = {
    ".sql", ".sqlite", ".sqlite3", ".db", ".dump", ".bak",
    ".pem", ".key", ".p12", ".pfx", ".jks", ".keystore",
}

FORBIDDEN_BINARY_OR_DATA_SUFFIXES = {
    ".csv", ".tsv", ".xls", ".xlsx", ".ods", ".doc", ".docx", ".pdf",
    ".zip", ".7z", ".rar", ".tar", ".gz", ".tgz", ".bz2", ".xz",
    ".jpg", ".jpeg", ".png", ".webp", ".gif", ".bmp", ".tif", ".tiff",
    ".heic", ".mp3", ".wav", ".mp4", ".mov", ".avi", ".mkv", ".dcm",
}

FORBIDDEN_RUNTIME_SUFFIXES = {
    ".php", ".js", ".jsx", ".ts", ".tsx", ".vue", ".svelte",
    ".css", ".scss", ".less",
}

FORBIDDEN_NAMES = {
    ".env", ".gitmodules", "wp-config.php", "credentials.json", "secrets.json",
}

FORBIDDEN_PATH_PARTS = {
    "patient-data", "clinical-data", "attachments", "exports", "backups",
    "private-runbooks", "secrets",
}

FORBIDDEN_NORMALIZED_PATH_TOKENS = {
    "patientdata", "clinicaldata", "realpatientdata", "productionpatientdata",
    "privateclinicaldata",
}

FORBIDDEN_GENERATED_PATH_PARTS = {
    "__pycache__", ".pytest_cache", ".mypy_cache", ".venv", "venv",
    "node_modules", "vendor", "build", "dist", "artifacts",
}

FORBIDDEN_RUNTIME_ROOTS = {"src", "includes", "plugin", "mu-plugins", "wordpress"}
IGNORED_PATH_PARTS = {".git"}

ALLOWED_TEXT_SUFFIXES = {
    "", ".md", ".txt", ".py", ".yml", ".yaml", ".json", ".xml",
    ".ini", ".cfg", ".toml", ".sh",
}

SENSITIVE_PATTERNS = {
    "private key": re.compile(r"-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----"),
    "generic API secret": re.compile(
        r"(?i)(?:api[_-]?key|client[_-]?secret|access[_-]?token|password)\s*[:=]\s*['\"][^'\"\s]{12,}"
    ),
    "AWS access key": re.compile(r"\bAKIA[0-9A-Z]{16}\b"),
    "GitHub token": re.compile(r"\bgh[pousr]_[A-Za-z0-9_]{30,}\b"),
    "Git LFS pointer": re.compile(r"(?m)^version https://git-lfs\.github\.com/spec/v1\s*$"),
    "Git LFS filter": re.compile(r"(?m)^\s*\S+\s+filter=lfs\b"),
}

RUNTIME_CONTENT_PATTERNS = {
    "PHP opening tag": re.compile(r"<\?php\b", re.IGNORECASE),
    "runtime shebang": re.compile(
        r"\A#![^\n]*(?:\bphp\b|\bnode\b|\bdeno\b|\bbun\b)", re.IGNORECASE
    ),
}

MAPPED_FUNCTIONAL_REQUIREMENT_PATTERN = re.compile(
    r"(?m)^\|\s*(CF01-FR-\d{3})(?:\s+[^|]*)?\|"
)
PHASE_HEADING_PATTERN = re.compile(r"(?m)^##\s+\d+\.\s+(C1-[B-H])\b")
ACTION_USE_PATTERN = re.compile(r"(?m)^\s*uses:\s+[^@\s]+@([^\s#]+)")
EXACT_SHA_PATTERN = re.compile(r"^[0-9a-f]{40}$")
MAX_TEXT_FILE_BYTES = 2_000_000


def normalize_path_token(part: str) -> str:
    return re.sub(r"[^a-z0-9]+", "", part.lower())


def iter_repository_paths(root: Path) -> Iterator[tuple[Path, Path]]:
    for path in root.rglob("*"):
        relative = path.relative_to(root)
        normalized_parts = {part.lower() for part in relative.parts}
        if normalized_parts & IGNORED_PATH_PARTS:
            continue
        if path.is_symlink() or path.is_file():
            yield path, relative


def validate_future_phase_traceability(content: str) -> list[str]:
    requirement_list = MAPPED_FUNCTIONAL_REQUIREMENT_PATTERN.findall(content)
    requirement_counts = Counter(requirement_list)
    mapped_requirements = set(requirement_list)

    phase_list = PHASE_HEADING_PATTERN.findall(content)
    phase_counts = Counter(phase_list)
    found_phases = set(phase_list)
    errors: list[str] = []

    for requirement in sorted(EXPECTED_FUNCTIONAL_REQUIREMENTS - mapped_requirements):
        errors.append(
            f"future-phase table mapping missing functional requirement: {requirement}"
        )

    for requirement in sorted(mapped_requirements - EXPECTED_FUNCTIONAL_REQUIREMENTS):
        errors.append(
            f"future-phase table contains unrecognized functional requirement: {requirement}"
        )

    for requirement, count in sorted(requirement_counts.items()):
        if count > 1:
            errors.append(
                f"future-phase table duplicates functional requirement mapping: {requirement}"
            )

    for phase in sorted(EXPECTED_PHASES - found_phases):
        errors.append(f"future-phase traceability missing phase heading: {phase}")

    for phase, count in sorted(phase_counts.items()):
        if count > 1:
            errors.append(f"future-phase traceability duplicates phase heading: {phase}")

    return errors


def validate_workflow(content: str) -> list[str]:
    errors: list[str] = []
    action_refs = ACTION_USE_PATTERN.findall(content)

    if "pull_request_target" in content:
        errors.append("workflow must not use pull_request_target")
    if "ubuntu-latest" in content:
        errors.append("workflow runner must be fixed, not ubuntu-latest")
    if "runs-on: ubuntu-24.04" not in content:
        errors.append("workflow must use the fixed ubuntu-24.04 runner")
    if "persist-credentials: false" not in content:
        errors.append("workflow checkout must disable persisted credentials")
    if "github.event.pull_request.head.sha || github.sha" not in content:
        errors.append("workflow must validate the exact pull-request head")
    if "C1-A merge-ref compatibility" not in content:
        errors.append("workflow must separately validate pull-request merge compatibility")
    if "git diff --check" not in content:
        errors.append("workflow must run whitespace integrity validation")
    if not action_refs:
        errors.append("workflow contains no reviewable action references")
    for reference in action_refs:
        if not EXACT_SHA_PATTERN.fullmatch(reference):
            errors.append(f"workflow action is not pinned to an exact commit SHA: {reference}")

    return errors


def validate(root: Path) -> list[str]:
    errors: list[str] = []
    paths = list(iter_repository_paths(root))
    existing = {str(relative).replace("\\", "/") for _, relative in paths}

    for required in sorted(REQUIRED_FILES - existing):
        errors.append(f"missing required governance file: {required}")

    for path, relative in paths:
        relative_string = str(relative).replace("\\", "/")
        normalized_parts = {part.lower() for part in relative.parts}
        compact_parts = {normalize_path_token(part) for part in relative.parts}
        compact_relative = normalize_path_token(relative_string)
        name_lower = path.name.lower()
        suffix_lower = path.suffix.lower()
        first_part = relative.parts[0].lower() if relative.parts else ""

        if path.is_symlink():
            errors.append(f"symbolic links require explicit review and are blocked: {relative}")
            continue

        if normalized_parts & FORBIDDEN_GENERATED_PATH_PARTS:
            errors.append(f"generated/dependency path is prohibited during C1-A: {relative}")

        if name_lower in FORBIDDEN_NAMES:
            errors.append(f"forbidden sensitive or indirect file: {relative}")

        if suffix_lower in FORBIDDEN_SENSITIVE_SUFFIXES:
            errors.append(f"forbidden sensitive artifact: {relative}")

        if suffix_lower in FORBIDDEN_BINARY_OR_DATA_SUFFIXES:
            errors.append(f"binary/data artifact is prohibited during C1-A: {relative}")

        if suffix_lower in FORBIDDEN_RUNTIME_SUFFIXES:
            errors.append(f"clinical runtime file is not authorized during C1-A: {relative}")

        if normalized_parts & FORBIDDEN_PATH_PARTS:
            errors.append(f"forbidden sensitive path: {relative}")

        if any(
            token in compact_part
            for compact_part in compact_parts
            for token in FORBIDDEN_NORMALIZED_PATH_TOKENS
        ) or any(token in compact_relative for token in FORBIDDEN_NORMALIZED_PATH_TOKENS):
            errors.append(f"forbidden sensitive path variant: {relative}")

        if first_part in FORBIDDEN_RUNTIME_ROOTS:
            errors.append(f"clinical runtime path is not authorized during C1-A: {relative}")

        if suffix_lower == ".py" and first_part not in {"tools", "tests"}:
            errors.append(f"Python outside tools/tests requires phase approval: {relative}")

        if suffix_lower == ".sh" and first_part != "tools":
            errors.append(f"shell scripts outside tools require phase approval: {relative}")

        if suffix_lower not in ALLOWED_TEXT_SUFFIXES:
            errors.append(f"unreviewed file type is blocked during C1-A: {relative}")
            continue

        if path.stat().st_size > MAX_TEXT_FILE_BYTES:
            errors.append(f"text file exceeds C1-A review limit: {relative}")
            continue

        try:
            content = path.read_text(encoding="utf-8")
        except UnicodeDecodeError:
            errors.append(f"non-UTF-8 file requires explicit review: {relative}")
            continue

        for marker in REQUIRED_MARKERS.get(relative_string, ()):
            if marker not in content:
                errors.append(
                    f"required governance marker missing from {relative_string}: {marker}"
                )

        if relative_string == PHASE_TRACEABILITY_PATH:
            errors.extend(validate_future_phase_traceability(content))

        if relative_string == WORKFLOW_PATH:
            errors.extend(validate_workflow(content))

        if first_part not in {"tools", "tests"}:
            for label, pattern in RUNTIME_CONTENT_PATTERNS.items():
                if pattern.search(content):
                    errors.append(f"possible {label} runtime detected in {relative}")

        for label, pattern in SENSITIVE_PATTERNS.items():
            if pattern.search(content):
                errors.append(f"possible {label} detected in {relative}")

    return sorted(set(errors))


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("root", nargs="?", default=".")
    args = parser.parse_args()

    root = Path(args.root).resolve()
    errors = validate(root)
    if errors:
        print("CF-01 repository validation failed:", file=sys.stderr)
        for error in errors:
            print(f"- {error}", file=sys.stderr)
        return 1

    print("CF-01 repository validation passed: C1-A public-safe and governance-integrity policy satisfied.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
