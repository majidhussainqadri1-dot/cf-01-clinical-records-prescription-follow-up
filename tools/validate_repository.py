#!/usr/bin/env python3
"""Public-repository safety gate for CF-01 C1-A.

This validator intentionally blocks clinical runtime code and common sensitive
artifacts until a later change-control record authorizes C1-B.
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

REQUIRED_FILES = {
    "README.md",
    "CHANGE_CONTROL.md",
    "SECURITY.md",
    "docs/C1-A-FOUNDATION.md",
    "docs/C1-A-REQUIREMENTS-TRACEABILITY.md",
}

FORBIDDEN_SUFFIXES = {
    ".sql",
    ".sqlite",
    ".sqlite3",
    ".db",
    ".dump",
    ".bak",
    ".pem",
    ".key",
    ".p12",
    ".pfx",
    ".jks",
    ".keystore",
}

FORBIDDEN_NAMES = {
    ".env",
    "wp-config.php",
    "credentials.json",
    "secrets.json",
}

FORBIDDEN_PATH_PARTS = {
    "patient-data",
    "clinical-data",
    "attachments",
    "exports",
    "backups",
    "private-runbooks",
    "secrets",
}

# Clinical runtime is deliberately blocked during C1-A. Documentation, tests and
# repository-governance tools are permitted.
FORBIDDEN_RUNTIME_ROOTS = {
    "src",
    "includes",
    "plugin",
    "mu-plugins",
    "wordpress",
}

SENSITIVE_PATTERNS = {
    "private key": re.compile(r"-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----"),
    "generic API secret": re.compile(
        r"(?i)(?:api[_-]?key|client[_-]?secret|access[_-]?token|password)\s*[:=]\s*['\"][^'\"\s]{12,}"
    ),
    "AWS access key": re.compile(r"\bAKIA[0-9A-Z]{16}\b"),
    "GitHub token": re.compile(r"\bgh[pousr]_[A-Za-z0-9_]{30,}\b"),
}

TEXT_SUFFIXES = {
    ".md",
    ".txt",
    ".py",
    ".yml",
    ".yaml",
    ".json",
    ".xml",
    ".ini",
    ".cfg",
    ".toml",
    ".sh",
}


def iter_files(root: Path):
    for path in root.rglob("*"):
        if not path.is_file():
            continue
        relative = path.relative_to(root)
        if relative.parts and relative.parts[0] == ".git":
            continue
        yield path, relative


def validate(root: Path) -> list[str]:
    errors: list[str] = []
    existing = {str(relative).replace("\\", "/") for _, relative in iter_files(root)}

    for required in sorted(REQUIRED_FILES - existing):
        errors.append(f"missing required governance file: {required}")

    for path, relative in iter_files(root):
        normalized_parts = {part.lower() for part in relative.parts}
        name_lower = path.name.lower()
        suffix_lower = path.suffix.lower()

        if name_lower in FORBIDDEN_NAMES:
            errors.append(f"forbidden sensitive file: {relative}")

        if suffix_lower in FORBIDDEN_SUFFIXES:
            errors.append(f"forbidden sensitive artifact: {relative}")

        if normalized_parts & FORBIDDEN_PATH_PARTS:
            errors.append(f"forbidden sensitive path: {relative}")

        if relative.parts and relative.parts[0].lower() in FORBIDDEN_RUNTIME_ROOTS:
            errors.append(
                f"clinical runtime path is not authorized during C1-A: {relative}"
            )

        # Root-level PHP files would form an installable WordPress runtime.
        if len(relative.parts) == 1 and suffix_lower == ".php":
            errors.append(
                f"root PHP runtime is not authorized during C1-A: {relative}"
            )

        if suffix_lower not in TEXT_SUFFIXES or path.stat().st_size > 2_000_000:
            continue

        try:
            content = path.read_text(encoding="utf-8")
        except UnicodeDecodeError:
            errors.append(f"non-UTF-8 text-like file requires review: {relative}")
            continue

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

    print("CF-01 repository validation passed: C1-A public-safe policy satisfied.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
