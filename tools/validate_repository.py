#!/usr/bin/env python3
"""Unified public-repository safety and governance gate for CF-01.

The C1-A governance baseline is merged and Founder Change-Control
CF01-CCR-2026-08-06-003 authorizes reviewable source implementation while
activation and real patient data remain prohibited. This validator therefore
allows the declared WordPress runtime surface, but continues to reject secrets,
patient-data artifacts, unreviewed binaries, supply-chain indirection, runtime
outside approved roots, incomplete traceability and weakened workflow evidence.
"""

from __future__ import annotations

import argparse
import re
import sys
from collections import Counter
from pathlib import Path
from typing import Iterator

PHASE_TRACEABILITY_PATH = "docs/C1-B-TO-C1-H-IMPLEMENTATION-TRACEABILITY.md"
RUNTIME_TRACEABILITY_PATH = "docs/REQUIREMENTS-TRACEABILITY.md"
WORKFLOW_PATH = ".github/workflows/governance.yml"
AUTHORIZATION_ID = "CF01-CCR-2026-08-06-003"

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
    "docs/C1-A-CROSS-REPOSITORY-CONTRACT-TRACKING.md",
    "docs/C1-A-LEGAL-PROFESSIONAL-APPLICABILITY-REGISTER.md",
    "docs/C1-A-RETENTION-LEGAL-HOLD-MATRIX.md",
    "docs/C1-A-CRYPTOGRAPHY-STORAGE-ATTACHMENT-SECURITY.md",
    "docs/C1-A-OPERATIONAL-OWNERSHIP-AND-ESCALATION.md",
    "docs/C1-A-INDEPENDENT-REVIEW-PLAN.md",
    "docs/C1-A-REQUIREMENTS-TRACEABILITY.md",
    "docs/C1-A-EVIDENCE-MANIFEST.md",
    "docs/C1-A-FINAL-REVIEW-CORRECTION-REGISTER.md",
    PHASE_TRACEABILITY_PATH,
    RUNTIME_TRACEABILITY_PATH,
    "docs/SECURITY-PRIVACY-ARCHITECTURE.md",
    "docs/MIGRATION-ROLLBACK.md",
    "docs/RELEASE-STATUS.md",
    "docs/REVIEWS-40-CORRECTION-REGISTER.md",
    "sabri-clinical-records/sabri-clinical-records.php",
    "sabri-clinical-records/includes/class-cf01-authorization.php",
    "sabri-clinical-records/includes/class-cf01-migrations.php",
    "tests/test_generate_sbom.py",
    "tests/test_validate_repository.py",
    "tests/unit.php",
    "tests/runtime-adversarial.php",
    "tests/static-audit.php",
    "tests/migration-review.php",
    "tests/fresh-review.php",
    "tests/security-corrections.php",
    "tools/generate_sbom.py",
    "tools/validate_repository.py",
    "tools/validate_runtime.py",
    "tools/run_40_reviews.py",
    "tools/package.sh",
}

REQUIRED_MARKERS = {
    "README.md": (
        "Current status:",
        "C1-A governance package",
        "Runtime candidate",
        "Activation law",
        "Truthful completion statuses",
    ),
    "CHANGE_CONTROL.md": (
        "CF01-CCR-2026-08-03-001",
        "CF01-CCR-2026-08-05-002",
        AUTHORIZATION_ID,
        "Source authority",
        "External activation gates",
    ),
    "docs/C1-A-CROSS-FILE-CONTRACTS.md": (
        "Contract constitution",
        "Canonical ownership matrix",
        "Freeze gate",
    ),
    "docs/C1-A-EVIDENCE-MANIFEST.md": (
        "Evidence law",
        "Truthful status",
        "Phase-exit decision",
    ),
    PHASE_TRACEABILITY_PATH: (
        "Phase constitution",
        "C1-B",
        "C1-H",
        "Definition of Done",
        "Current status",
    ),
    RUNTIME_TRACEABILITY_PATH: (
        "CF-01 Requirements Traceability",
        "CF01-FR-001",
        "CF01-FR-032",
    ),
    "docs/SECURITY-PRIVACY-ARCHITECTURE.md": (
        "Security",
        "Privacy",
    ),
    "docs/MIGRATION-ROLLBACK.md": (
        "Migration",
        "Rollback",
    ),
    "docs/RELEASE-STATUS.md": (
        "disabled",
        "patient data",
    ),
}

FORBIDDEN_SENSITIVE_SUFFIXES = {
    ".sql", ".sqlite", ".sqlite3", ".db", ".dump", ".bak",
    ".pem", ".key", ".p12", ".pfx", ".jks", ".keystore",
}
FORBIDDEN_BINARY_OR_ARCHIVE_SUFFIXES = {
    ".csv", ".tsv", ".xls", ".xlsx", ".ods", ".doc", ".docx", ".pdf",
    ".zip", ".7z", ".rar", ".tar", ".gz", ".tgz", ".bz2", ".xz",
    ".jpg", ".jpeg", ".png", ".webp", ".gif", ".bmp", ".tif", ".tiff",
    ".heic", ".mp3", ".wav", ".mp4", ".mov", ".avi", ".mkv", ".dcm",
}
FORBIDDEN_NAMES = {
    ".env", ".gitmodules", "wp-config.php", "credentials.json", "secrets.json",
}
FORBIDDEN_PATH_PARTS = {
    "patient-data", "clinical-data", "identity-documents", "private-runbooks",
    "secrets", "production-data", "database-dumps",
}
FORBIDDEN_NORMALIZED_PATH_TOKENS = {
    "realpatientdata", "productionpatientdata", "privateclinicaldata",
    "patientidentitydocuments", "productionclinicaldata",
}
FORBIDDEN_GENERATED_PATH_PARTS = {
    "__pycache__", ".pytest_cache", ".mypy_cache", ".venv", "venv",
    "node_modules", "vendor", "build", "dist", "artifacts",
}
IGNORED_PATH_PARTS = {".git"}
ALLOWED_TEXT_SUFFIXES = {
    "", ".md", ".txt", ".py", ".php", ".js", ".css", ".pot", ".po",
    ".yml", ".yaml", ".json", ".xml", ".ini", ".cfg", ".toml", ".sh",
}
ALLOWED_RUNTIME_ROOTS = {"sabri-clinical-records", "tests"}

PRIVATE_KEY_PATTERN = re.compile(
    "-----BEGIN " + r"(?:RSA |EC |OPENSSH )?PRIVATE KEY-----"
)
SENSITIVE_PATTERNS = {
    "private key": PRIVATE_KEY_PATTERN,
    "generic API secret": re.compile(
        r"(?i)(?:api[_-]?key|client[_-]?secret|access[_-]?token|password)\s*[:=]\s*['\"][^'\"\s]{16,}"
    ),
    "AWS access key": re.compile(r"\bAKIA[0-9A-Z]{16}\b"),
    "GitHub token": re.compile(r"\bgh[pousr]_[A-Za-z0-9_]{30,}\b"),
    "Git LFS pointer": re.compile(r"(?m)^version https://git-lfs\.github\.com/spec/v1\s*$"),
    "Git LFS filter": re.compile(r"(?m)^\s*\S+\s+filter=lfs\b"),
}
PATIENT_FIXTURE_PATTERN = re.compile(
    r"(?i)\b(?:patient|guardian)[ _-]?(?:name|phone|email|national[ _-]?id)\s*[:=]\s*['\"][^'\"]{3,}"
)
RUNTIME_SHEBANG_PATTERN = re.compile(
    r"\A#![^\n]*(?:\bphp\b|\bnode\b|\bdeno\b|\bbun\b)", re.IGNORECASE
)
MAPPED_FUNCTIONAL_REQUIREMENT_PATTERN = re.compile(
    r"(?m)^\|\s*(CF01-FR-\d{3})(?:\s+[^|]*)?\|"
)
RUNTIME_REQUIREMENT_PATTERN = re.compile(r"(?m)^\|\s*(CF01-FR-\d{3})\b")
PHASE_HEADING_PATTERN = re.compile(r"(?m)^##\s+\d+\.\s+(C1-[B-H])\b")
ACTION_USE_PATTERN = re.compile(r"(?m)^\s*(?:-\s*)?uses:\s+[^@\s]+@([^\s#]+)")
EXACT_SHA_PATTERN = re.compile(r"^[0-9a-f]{40}$")
MAX_TEXT_FILE_BYTES = 2_000_000


def normalize_path_token(part: str) -> str:
    return re.sub(r"[^a-z0-9]+", "", part.lower())


def iter_repository_paths(root: Path) -> Iterator[tuple[Path, Path]]:
    for path in root.rglob("*"):
        relative = path.relative_to(root)
        if {part.lower() for part in relative.parts} & IGNORED_PATH_PARTS:
            continue
        if path.is_symlink() or path.is_file():
            yield path, relative


def validate_requirement_set(content: str, pattern: re.Pattern[str], label: str) -> list[str]:
    values = pattern.findall(content)
    counts = Counter(values)
    found = set(values)
    errors: list[str] = []
    for requirement in sorted(EXPECTED_FUNCTIONAL_REQUIREMENTS - found):
        errors.append(f"{label} missing functional requirement: {requirement}")
    for requirement in sorted(found - EXPECTED_FUNCTIONAL_REQUIREMENTS):
        errors.append(f"{label} contains unrecognized functional requirement: {requirement}")
    for requirement, count in sorted(counts.items()):
        if count != 1:
            errors.append(f"{label} must map {requirement} exactly once; found {count}")
    return errors


def validate_phase_traceability(content: str) -> list[str]:
    errors = validate_requirement_set(
        content, MAPPED_FUNCTIONAL_REQUIREMENT_PATTERN, "future-phase traceability"
    )
    phases = PHASE_HEADING_PATTERN.findall(content)
    counts = Counter(phases)
    found = set(phases)
    for phase in sorted(EXPECTED_PHASES - found):
        errors.append(f"future-phase traceability missing phase heading: {phase}")
    for phase, count in sorted(counts.items()):
        if count != 1:
            errors.append(f"future-phase traceability must contain {phase} exactly once; found {count}")
    return errors


def validate_workflow(content: str) -> list[str]:
    errors: list[str] = []
    action_refs = ACTION_USE_PATTERN.findall(content)
    required_tokens = {
        "fixed runner": "runs-on: ubuntu-24.04",
        "non-persisted checkout": "persist-credentials: false",
        "exact-head checkout": "github.event.pull_request.head.sha || github.sha",
        "merge-ref job": "merge-ref compatibility",
        "PHP 8.1": 'php: ["8.1", "8.3"]',
        "deterministic package": "cmp -s",
        "SPDX SBOM": ".spdx.json",
        "retained artifact upload": "actions/upload-artifact@",
        "repository validator": "tools/validate_repository.py",
        "runtime validator": "tools/validate_runtime.py",
    }
    if "pull_request_target" in content:
        errors.append("workflow must not use pull_request_target")
    if "ubuntu-latest" in content:
        errors.append("workflow runner must be fixed, not ubuntu-latest")
    for label, token in required_tokens.items():
        if token not in content:
            errors.append(f"workflow missing {label}")
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
        errors.append(f"missing required repository file: {required}")

    for path, relative in paths:
        relative_string = str(relative).replace("\\", "/")
        normalized_parts = {part.lower() for part in relative.parts}
        compact_relative = normalize_path_token(relative_string)
        name_lower = path.name.lower()
        suffix_lower = path.suffix.lower()
        first_part = relative.parts[0].lower() if relative.parts else ""

        if path.is_symlink():
            errors.append(f"symbolic links are prohibited: {relative}")
            continue
        if normalized_parts & FORBIDDEN_GENERATED_PATH_PARTS:
            errors.append(f"generated/dependency path is prohibited: {relative}")
        if name_lower in FORBIDDEN_NAMES:
            errors.append(f"forbidden sensitive or indirect file: {relative}")
        if suffix_lower in FORBIDDEN_SENSITIVE_SUFFIXES:
            errors.append(f"forbidden sensitive artifact: {relative}")
        if suffix_lower in FORBIDDEN_BINARY_OR_ARCHIVE_SUFFIXES:
            errors.append(f"binary/archive artifact is prohibited: {relative}")
        if normalized_parts & FORBIDDEN_PATH_PARTS:
            errors.append(f"forbidden sensitive path: {relative}")
        if any(token in compact_relative for token in FORBIDDEN_NORMALIZED_PATH_TOKENS):
            errors.append(f"forbidden sensitive path variant: {relative}")
        if suffix_lower not in ALLOWED_TEXT_SUFFIXES and path.name not in {"LICENSE", ".gitignore"}:
            errors.append(f"unreviewed file type is prohibited: {relative}")
            continue
        if suffix_lower in {".php", ".js", ".css"} and first_part not in ALLOWED_RUNTIME_ROOTS:
            errors.append(f"runtime file outside approved roots: {relative}")
        if suffix_lower in {".js", ".css"} and first_part != "sabri-clinical-records":
            errors.append(f"browser runtime outside plugin root: {relative}")
        if suffix_lower == ".py" and first_part not in {"tools", "tests"}:
            errors.append(f"Python outside tools/tests is prohibited: {relative}")
        if suffix_lower == ".sh" and first_part != "tools":
            errors.append(f"shell scripts outside tools are prohibited: {relative}")
        if path.stat().st_size > MAX_TEXT_FILE_BYTES:
            errors.append(f"text file exceeds review limit: {relative}")
            continue

        try:
            content = path.read_text(encoding="utf-8")
        except UnicodeDecodeError:
            errors.append(f"non-UTF-8 file is prohibited: {relative}")
            continue

        for marker in REQUIRED_MARKERS.get(relative_string, ()):
            if marker not in content:
                errors.append(f"required marker missing from {relative_string}: {marker}")

        if relative_string == PHASE_TRACEABILITY_PATH:
            errors.extend(validate_phase_traceability(content))
        elif relative_string == RUNTIME_TRACEABILITY_PATH:
            errors.extend(
                validate_requirement_set(content, RUNTIME_REQUIREMENT_PATTERN, "runtime traceability")
            )
        elif relative_string == WORKFLOW_PATH:
            errors.extend(validate_workflow(content))

        if RUNTIME_SHEBANG_PATTERN.search(content) and first_part not in {"tools", "tests"}:
            errors.append(f"runtime shebang outside approved tools/tests: {relative}")
        if PATIENT_FIXTURE_PATTERN.search(content):
            errors.append(f"possible identifying clinical fixture detected: {relative}")
        for label, pattern in SENSITIVE_PATTERNS.items():
            if pattern.search(content):
                errors.append(f"possible {label} detected in {relative}")

    return sorted(set(errors))


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("root", nargs="?", default=".")
    args = parser.parse_args()
    errors = validate(Path(args.root).resolve())
    if errors:
        print("CF-01 unified repository validation failed:", file=sys.stderr)
        for error in errors:
            print(f"- {error}", file=sys.stderr)
        return 1
    print("CF-01 unified repository validation passed: governance and authorized source-runtime policy satisfied.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
