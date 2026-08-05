# CF-01 Correction Round 4 — Integration Contracts, Activation Compensation and Rollback

**Date:** 05 August 2026  
**Branch:** `codex/cf-01-three-plan-correction-r1`  
**Scope:** source-level integration, activation, disabled-runtime migration and rollback controls.  
**Truth boundary:** this record is automated source evidence only; it is not legal, clinical, penetration-test, Hostinger-staging, backup/restore or production acceptance.

The exact immutable final head, workflow run and package checksum are recorded in the pull-request evidence after the final content commit. This committed document deliberately avoids claiming its own commit SHA.

## 1. Governing baselines

This round continues reconciliation against:

1. Definitive Integrated Master Plan v3.0;
2. All-Chats Recovered Directive Register v2.1;
3. CF-01 Clinical Records, Prescription and Follow-Up plan v1.0.

## 2. Confirmed defects and corrections

### R4-D01 — Dependency presence was weaker than contract acceptance

A PHP function or WordPress filter being present did not prove that a native-owner contract was accepted, current, release-compatible or environment-bound.

**Correction:** activation now requires structured, non-revoked, non-expired acceptance evidence for File 00, File 20, File 24 and File 25. Every proof is bound to the exact release head, target environment and site fingerprint. Live membership/recent-auth, private-shell, visual-accessibility and assurance registrations are re-probed at activation time.

### R4-D02 — Partial scheduler failure could leave activation side effects

A failure after one or two recurring jobs were scheduled could leave orphan schedules or changed evidence while activation failed.

**Correction:** mandatory schedules are created and verified before state promotion. Any partial failure clears the clinical hooks, restores the previous activation evidence, removes pending transition locks/fingerprints and records a compensation receipt.

### R4-D03 — Post-state-update receipt failure could leave runtime enabled

An activation state update could succeed while its orchestration completion receipt was missing or invalid.

**Correction:** the post-transition guard now compensates to `disabled`, clears schedules, restores previous evidence and records the final disabled state. Compensation bypasses normal activation guards only while performing that controlled reversal.

### R4-D04 — Disable could change state before schedule cleanup was verified

The earlier flow changed the runtime state and then attempted to clear schedules.

**Correction:** the release orchestrator clears and verifies all clinical schedules before allowing an enabled, activating or degraded state to become disabled. The completion receipt is written only after the transition.

### R4-D05 — File 08 write migration could not safely express `dry_run=false`

The legacy extractor used an `empty()` gate for `dry_run`; an explicit boolean `false` was therefore treated as missing. Its authorization path also conflicted with the required disabled runtime.

**Correction:** `CF01_Release_Orchestrator::extract_file08_batch()` is now the canonical write-migration entrypoint. It uses independent governance authorization, requires the runtime to be explicitly disabled, accepts an explicit boolean `dry_run`, requires a versioned File 08 contract, exact source-snapshot hash, structured reconciliation/rollback plans, bounded cursors and an immutable migration ID.

### R4-D06 — Migration replay and altered-batch reuse were not separated

A repeated batch needed deterministic idempotency, while reuse of the same batch identity with altered evidence needed rejection.

**Correction:** each migration ID and cursor produce a request hash and claim. Exact replay returns the prior public-safe receipt without duplicate writes. Reuse with a changed snapshot, plan or mode is rejected.

### R4-D07 — Public receipt and durable migration ledger were not explicitly linked

The migration receipt did not expose the actual durable migration-row identifier.

**Correction:** every canonical receipt now includes `ledger_migration_uuid`; the encrypted durable receipt contains the same identifier, and permanent regression tests resolve it back to the exact ledger row.

### R4-D08 — Rollback receipt validation and database commit were separable

The legacy rollback path did not require a successful optimistic-concurrency update before returning the provider receipt.

**Correction:** the canonical rollback validates rollback identity, migration identity, matching integrity roots, expected/post counts, and explicit no-orphan/no-authorization-drift assertions. The migration ledger is then changed transactionally with an expected row version; concurrent or repeated rollback fails closed. Legacy rollback cannot run while the clinical runtime is active.

### R4-D09 — Provider downgrade/outage needed executable regression evidence

Documentary acceptance alone could not protect against a live downgraded or unavailable File 20, File 24 or File 25 provider.

**Correction:** Round-4 adversarial suites inject downgrade, wrong-head and live provider-failure conditions and require activation to fail.

### R4-D10 — Test inventory and expected-error drift

The first fresh run exposed a stale plugin PHP inventory. A subsequent run showed that the File 20 outage test expected a differently cased token than the actual fail-closed error.

**Correction:** inventory was reconciled to the actual source tree, the assertion was aligned with the canonical `file20_shell` error code, and all suites were rerun from a fresh exact head.

## 3. Permanent executable evidence

- `sabri-clinical-records/includes/class-cf01-release-orchestrator.php`
- `tests/adversarial-round4.php`
- `tests/adversarial-round4-compensation.php`
- `.github/workflows/governance.yml`
- `tools/validate_runtime.py`

Round 4 executes on PHP 8.1, PHP 8.3, the policy/package job and the forty-round correction gate.

## 4. Required final automated evidence

The final exact head must pass:

- PHP 8.1 and PHP 8.3 clinical review jobs;
- forty review and correction rounds;
- policy, JavaScript and deterministic-package review;
- all ten PHP suites, including both Round-4 adversarial suites;
- repository-policy tests and runtime validator;
- 32/32 functional requirement traceability;
- two byte-identical package builds with one recorded SHA-256.

## 5. Canonical operational entrypoints

- Activation/disable state changes remain governed by `CF01_Migrations`, `CF01_Activation_Evidence` and the registered release-orchestration hooks.
- Real File 08 write batches must use `CF01_Release_Orchestrator::extract_file08_batch()`.
- Controlled rollback must use `CF01_Release_Orchestrator::rollback_migration()`.
- Native owner modules remain the canonical owners of membership, shell, assurance and visual contracts; CF-01 only consumes and validates their accepted assertions.

## 6. Residual external gates

The following remain unavailable and are not claimed by this source round:

- qualified legal and professional acceptance;
- independently executed penetration test and retest;
- Hostinger staging with representative browser, RTL and accessibility journeys;
- real provider credentials, storage region and key-management evidence;
- independent backup/restore and rollback rehearsal on approved infrastructure;
- accepted and frozen native-owner contracts in all companion repositories;
- named operational staffing and Founder production-release approval;
- any authorization to process real patient data.

## 7. Round decision

Round 4 may be marked source-complete only after the final content head passes every required gate. Passing those gates does not promote the module to Staging-Accepted, Live-Deployed or Operational.
