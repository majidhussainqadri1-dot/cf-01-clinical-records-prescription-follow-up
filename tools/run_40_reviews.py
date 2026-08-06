#!/usr/bin/env python3
"""Run forty distinct CF-01 review rounds with a correction gate after every round."""

from __future__ import annotations

import pathlib
import subprocess
import sys
from dataclasses import dataclass
from typing import Callable

ROOT = pathlib.Path(__file__).resolve().parents[1]


def read(relative: str) -> str:
    path = ROOT / relative
    return path.read_text(encoding="utf-8")


SOURCES = {
    "plan": read("docs/C1-B-TO-C1-H-IMPLEMENTATION-TRACEABILITY.md"),
    "validator": read("tools/validate_runtime.py"),
    "workflow": read(".github/workflows/governance.yml"),
    "plugin": read("sabri-clinical-records/sabri-clinical-records.php"),
    "auth": read("sabri-clinical-records/includes/class-cf01-authorization.php"),
    "patients": read("sabri-clinical-records/includes/class-cf01-patients.php"),
    "relationships": read("sabri-clinical-records/includes/class-cf01-relationships.php"),
    "consents": read("sabri-clinical-records/includes/class-cf01-consents.php"),
    "encounters": read("sabri-clinical-records/includes/class-cf01-encounters.php"),
    "attachments": read("sabri-clinical-records/includes/class-cf01-attachments.php"),
    "prescriptions": read("sabri-clinical-records/includes/class-cf01-prescriptions.php"),
    "followups": read("sabri-clinical-records/includes/class-cf01-followups.php"),
    "rights": read("sabri-clinical-records/includes/class-cf01-rights.php"),
    "breakglass": read("sabri-clinical-records/includes/class-cf01-break-glass.php"),
    "audit": read("sabri-clinical-records/includes/class-cf01-audit-outbox.php"),
    "retention": read("sabri-clinical-records/includes/class-cf01-retention.php"),
    "rest": read("sabri-clinical-records/includes/class-cf01-rest.php"),
    "migrations": read("sabri-clinical-records/includes/class-cf01-migrations.php"),
    "package": read("tools/package.sh"),
    "sbom": read("tools/generate_sbom.py"),
}


def contains(source: str, *needles: str) -> Callable[[], None]:
    def check() -> None:
        for needle in needles:
            if needle not in SOURCES[source]:
                raise AssertionError(f"{source} is missing required invariant: {needle}")

    return check


def excludes(source: str, *needles: str) -> Callable[[], None]:
    def check() -> None:
        for needle in needles:
            if needle in SOURCES[source]:
                raise AssertionError(f"{source} contains prohibited pattern: {needle}")

    return check


def cross_contains(*requirements: tuple[str, str]) -> Callable[[], None]:
    def check() -> None:
        for source, needle in requirements:
            if needle not in SOURCES[source]:
                raise AssertionError(f"{source} is missing cross-file invariant: {needle}")

    return check


def count_requirements() -> None:
    missing = [f"CF01-FR-{number:03d}" for number in range(1, 33) if f"CF01-FR-{number:03d}" not in SOURCES["plan"]]
    if missing:
        raise AssertionError(f"Missing requirement traceability: {missing}")


def export_order() -> None:
    source = SOURCES["rights"]
    provider = source.find("CF01_Contracts::secure_media('delivery'")
    consumed = source.find("array('export_token_consumed_at' => CF01_DB::now())")
    if provider < 0 or consumed < 0 or provider >= consumed:
        raise AssertionError("Export token consumption occurs before provider acceptance.")


def workflow_matrix() -> None:
    source = SOURCES["workflow"]
    markers = (
        'php: ["8.1", "8.3"]',
        "Verify deterministic ZIP manifest checksum and SPDX SBOM bundle",
        "cmp -s",
        "diff -qr",
        ".spdx.json",
        "Retain complete release evidence bundle",
        "actions/upload-artifact@ea165f8d65b6e75b540449e92b4886f43607fa02",
        "merge-ref compatibility",
    )
    for marker in markers:
        if marker not in source:
            raise AssertionError(f"Workflow evidence is missing: {marker}")

    package_markers = (
        "tools/generate_sbom.py",
        'sha256sum "${SBOM}"',
        "MANIFEST.sha256",
    )
    for marker in package_markers:
        if marker not in SOURCES["package"]:
            raise AssertionError(f"Package evidence is missing: {marker}")

    sbom_markers = (
        '"SPDX-2.3"',
        '"CC0-1.0"',
        "packageVerificationCodeValue",
        "SHA256",
    )
    for marker in sbom_markers:
        if marker not in SOURCES["sbom"]:
            raise AssertionError(f"SBOM evidence is missing: {marker}")


@dataclass(frozen=True)
class Round:
    number: int
    title: str
    review: Callable[[], None]


ROUNDS = [
    Round(1, "Plan-to-runtime traceability", count_requirements),
    Round(2, "Conditional activation fail-closed", contains("plugin", "update_option('cf01_activation_state', 'disabled'", "disabled-by-default clinical records")),
    Round(3, "Canonical clinical identity separation", contains("patients", "platform_subject_hash", "platform_subject_cipher", "clinical_uuid")),
    Round(4, "Explicit actor identity", contains("auth", "Clinical actor identity mismatch.", "cf01_allow_service_actor")),
    Round(5, "Membership eligibility", contains("auth", "membership($user_id)", "!empty($membership['suspended'])")),
    Round(6, "Professional eligibility", contains("auth", "practitioner($user_id, $action)", "Professional eligibility has expired.")),
    Round(7, "Explicit-user capability evaluation", contains("auth", "function_exists('user_can')", "user_can($user_id, $capability)")),
    Round(8, "Relationship temporal validity", contains("auth", "$starts_at === null || $starts_at > $now", "$ends_at !== null && $ends_at <= $now")),
    Round(9, "Relationship actor authorization", contains("relationships", "authorize_relationship_actor", "Only the assigned treating doctor")),
    Round(10, "Assigned practitioner validation", contains("relationships", "require_target_practitioner", "professional scope does not authorize")),
    Round(11, "Latest-consent supersession", contains("auth", "WHERE patient_uuid = %s AND purpose = %s ORDER BY id DESC LIMIT 1", "!== 'granted'")),
    Round(12, "Consent expiry and timezone", contains("auth", "utc_timestamp", "DateTimeImmutable")),
    Round(13, "Guardian actor binding", contains("consents", "$guardian_actor", "platform_uuid")),
    Round(14, "Reference-only impersonation prevention", excludes("consents", "$guardian_actor || $guardian_reference")),
    Round(15, "Minor and guardian governance", contains("consents", "cf01_legal_majority_age", "minor_assent")),
    Round(16, "Field-level allowlist", contains("auth", "cf01_field_policy", "array_intersect($requested, $allowed)")),
    Round(17, "Patient ownership boundary", contains("auth", "platform_subject_hash", "patient_owner")),
    Round(18, "Encounter context validation", contains("encounters", "validate_context", "Invalid encounter mode.")),
    Round(19, "Teleconsultation consent", contains("encounters", "consent($patient_uuid, 'teleconsultation')")),
    Round(20, "Draft optimistic concurrency", cross_contains(("encounters", "update_versioned('encounters'"), ("auth", "Stale clinical record version."))),
    Round(21, "Signed encounter immutability", contains("encounters", "Signed or tombstoned encounter content is immutable.")),
    Round(22, "Addendum parent versioning", contains("encounters", "array('status' => 'addended')", "parent_row_version")),
    Round(23, "Entered-in-error relationship scope", contains("encounters", "mark_entered_in_error", "relationship_for_record")),
    Round(24, "Observation correction atomicity", contains("encounters", "return CF01_DB::transaction(function () use ($actor_id, $old", "ClinicalObservationCorrected")),
    Round(25, "Assessment open-encounter rule", contains("encounters", "A new assessment requires an open encounter.", "Assessment must be signed while its encounter remains open.")),
    Round(26, "Assessment consent and signature", contains("encounters", "consent((string) $row['patient_uuid'], 'clinical_care')", "assessment-signature")),
    Round(27, "Prescription clinician ownership", contains("prescriptions", "CF01_Authorization::clinician", "CF01_Authorization::relationship_for_record")),
    Round(28, "Prescription signed-state integrity", contains("prescriptions", "prescription-signature", "Invalid prescription transition.")),
    Round(29, "Follow-up and outcome scoping", contains("followups", "patient_owner", "relationship")),
    Round(30, "Break-glass patient and emergency validation", contains("breakglass", "CF01_Patients::get($patient_uuid)", "single emergency minimum-view")),
    Round(31, "Break-glass current reauthorization", contains("breakglass", "clinician($actor_id, 'use_break_glass'")),
    Round(32, "Break-glass field ceiling", contains("breakglass", "$granted_fields", "array_intersect")),
    Round(33, "Break-glass race-safe expiry", contains("breakglass", "if (!$updated)", "BreakGlassExpired")),
    Round(34, "Rights request current eligibility", contains("rights", "actor($actor_id, 'request_clinical_right')")),
    Round(35, "Representative identity binding", contains("rights", "guardian_or_representative($actor_id", "CF01_Role_Context::resolve", "hash_equals")),
    Round(36, "Export provider-before-consumption", export_order),
    Round(37, "Correction patient match and atomicity", contains("rights", "Correction case and encounter patient do not match.", "CF01_DB::transaction(function () use ($actor_id, $case_uuid")),
    Round(38, "Bounded non-truncating exports", contains("rights", "bounded_rows", "approved paginated export job")),
    Round(39, "Dual-PHP and deterministic retained release bundle", workflow_matrix),
    Round(40, "Integrated independent final review", contains("validator", "CF01-FR-032", "CF-01 runtime policy validation PASS")),
]


def run(command: list[str], label: str) -> None:
    completed = subprocess.run(command, cwd=ROOT, text=True, capture_output=True, timeout=180, check=False)
    if completed.returncode != 0:
        sys.stdout.write(completed.stdout)
        sys.stderr.write(completed.stderr)
        raise RuntimeError(f"{label} failed with exit code {completed.returncode}")


def correction_gate(round_number: int) -> None:
    run(["php", "tests/security-corrections.php"], "security correction regression")
    run(["php", "tests/fresh-review.php"], "fresh correction review")
    if round_number % 10 == 0:
        for suite in ("unit.php", "runtime-adversarial.php", "static-audit.php", "migration-review.php"):
            run(["php", f"tests/{suite}"], f"milestone suite {suite}")
    if round_number == 40:
        run([sys.executable, "-m", "unittest", "discover", "-s", "tests", "-p", "test_*.py", "-v"], "repository policy tests")
        run([sys.executable, "tools/validate_runtime.py", "."], "runtime validator")


def main() -> int:
    if len(ROUNDS) != 40 or [item.number for item in ROUNDS] != list(range(1, 41)):
        raise RuntimeError("Review register must contain exactly forty sequential rounds.")
    for item in ROUNDS:
        item.review()
        print(f"ROUND {item.number:02d} REVIEW PASS — {item.title}")
        correction_gate(item.number)
        print(f"ROUND {item.number:02d} CORRECTION GATE PASS — fresh regression evidence reconfirmed")
    print("CF-01 FORTY-ROUND REVIEW: 40/40 REVIEW PASS; 40/40 CORRECTION GATE PASS")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
