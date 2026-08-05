#!/usr/bin/env python3
from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(sys.argv[1] if len(sys.argv) > 1 else ".").resolve()
EXCLUDED = {".git", "build", ".pytest_cache", "__pycache__", "node_modules", "vendor"}
TEXT_EXTENSIONS = {".php", ".py", ".js", ".css", ".md", ".txt", ".yml", ".yaml", ".json", ".xml", ".pot", ".sh"}
FORBIDDEN_SUFFIXES = {".zip", ".tar", ".gz", ".7z", ".rar", ".sql", ".sqlite", ".db", ".doc", ".docx", ".pdf", ".pem", ".key", ".p12", ".pfx"}
FORBIDDEN_PATH_PARTS = {"runtime-payload", "private-runbook", "patient-data", "identity-documents", "secrets"}
SECRET_PATTERNS = {
    "private key": re.compile(r"-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----"),
    "AWS access key": re.compile(r"\bAKIA[0-9A-Z]{16}\b"),
    "GitHub token": re.compile(r"\bgh[opurs]_[A-Za-z0-9]{30,}\b"),
    "JWT": re.compile(r"\beyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\b"),
    "bearer token": re.compile(r"Authorization\s*:\s*Bearer\s+[A-Za-z0-9._~-]{16,}", re.I),
}

errors: list[str] = []
files: list[Path] = []
for path in ROOT.rglob("*"):
    if any(part in EXCLUDED for part in path.relative_to(ROOT).parts):
        continue
    if path.is_symlink():
        errors.append(f"Symlink prohibited: {path.relative_to(ROOT)}")
        continue
    if path.is_file():
        files.append(path)

for path in files:
    rel = path.relative_to(ROOT)
    lower_parts = {part.lower() for part in rel.parts}
    if lower_parts & FORBIDDEN_PATH_PARTS:
        errors.append(f"Sensitive/generated path prohibited: {rel}")
    if path.suffix.lower() in FORBIDDEN_SUFFIXES:
        errors.append(f"Archive/binary/private artifact prohibited: {rel}")
    if path.name.endswith(".payload") or path.name.startswith(".cf01-runtime-source"):
        errors.append(f"Runtime payload indirection prohibited: {rel}")
    if path.stat().st_size > 1_000_000:
        errors.append(f"Unexpected oversized public source file: {rel}")
    if path.suffix.lower() not in TEXT_EXTENSIONS and path.name not in {"LICENSE", ".gitignore"}:
        errors.append(f"Unapproved file type: {rel}")
        continue
    try:
        text = path.read_text(encoding="utf-8")
    except UnicodeDecodeError:
        errors.append(f"Non-UTF-8 file prohibited: {rel}")
        continue
    for label, pattern in SECRET_PATTERNS.items():
        if pattern.search(text):
            errors.append(f"Potential {label} in {rel}")
    if re.search(r"\b(?:patient|guardian)[ _-]?(?:name|phone|email|national[ _-]?id)\s*[:=]\s*['\"][^'\"]+", text, re.I):
        errors.append(f"Possible clinical identity fixture in {rel}")

required = [
    "README.md", "docs/REQUIREMENTS-TRACEABILITY.md", "docs/SECURITY-PRIVACY-ARCHITECTURE.md",
    "docs/MIGRATION-ROLLBACK.md", "docs/RELEASE-STATUS.md", "docs/REVIEWS-40-CORRECTION-REGISTER.md",
    "docs/THREE-PLAN-CORRECTION-MATRIX.md", "docs/REVIEW-CORRECTION-R1.md",
    "tools/package.sh", "tools/run_40_reviews.py",
    "sabri-clinical-records/sabri-clinical-records.php",
    "sabri-clinical-records/includes/class-cf01-authorization.php",
    "sabri-clinical-records/includes/class-cf01-migrations.php",
    "tests/unit.php", "tests/runtime-adversarial.php", "tests/static-audit.php",
    "tests/migration-review.php", "tests/fresh-review.php", "tests/security-corrections.php",
    "tests/three-plan-corrections.php",
]
for rel in required:
    if not (ROOT / rel).is_file():
        errors.append(f"Required release surface missing: {rel}")

trace = (ROOT / "docs/REQUIREMENTS-TRACEABILITY.md").read_text(encoding="utf-8") if (ROOT / "docs/REQUIREMENTS-TRACEABILITY.md").is_file() else ""
for number in range(1, 33):
    req = f"CF01-FR-{number:03d}"
    if trace.count(req) != 1:
        errors.append(f"{req} must appear exactly once in structured traceability")

php_files = sorted((ROOT / "sabri-clinical-records").rglob("*.php")) + sorted((ROOT / "tests").glob("*.php"))
if len(php_files) != 31:
    errors.append(f"Expected 31 permanent PHP files, found {len(php_files)}")

plugin_php = sorted((ROOT / 'sabri-clinical-records').rglob('*.php'))
source = '\n'.join(path.read_text(encoding='utf-8') for path in plugin_php)
for token in ["eval(", "shell_exec(", "passthru(", "permission_callback' => '__return_true'", "_smc_totp_secret", "_smc_identity_verified", "_smc_doctor_verified"]:
    if token in source:
        errors.append(f"Forbidden source token: {token}")
for token in ["CF01-FR-001", "CF01-FR-032"]:
    if token not in trace:
        errors.append(f"Traceability boundary missing: {token}")

if errors:
    print("CF-01 runtime policy validation FAILED", file=sys.stderr)
    for error in errors:
        print(f"- {error}", file=sys.stderr)
    raise SystemExit(1)

print(f"CF-01 runtime policy validation PASS: {len(files)} public-safe files, {len(php_files)} PHP files, 32/32 requirements traced")
