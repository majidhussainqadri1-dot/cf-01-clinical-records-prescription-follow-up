# CF-01 ten-round review — 2026-09-16

Review baseline: `9e58cf82dbb84f1a49804aa714a823878364f1f7`.

Process law: each numbered round is audited to completion first; only after that round closes are its collected defects corrected. The next round begins only after the correction batch is complete.

## Round 1 — governing-plan parity, scope and source inventory
Audit completed before correction.
Defects found: Future24 was absent from the August repository baseline; its REST family and automated parity gate were absent; the local primary fallback still used the earlier green value.
Correction batch: added the disabled-by-default 24-ID Future24 adapter/REST foundation, bootstrap registration, parity tests, and Sabri Green `#087A4E` fallback. No second clinical source of truth was introduced.
Status: corrected at source level; no staging/live claim.

## Round 2 — authorization, IDOR and privileged-operation review
Audit completed before correction.
Defects found: no route-specific pre-callback perimeter; generic future fact writes were insufficiently clinician-scoped; institutional/simulation routes lacked independent privileged gates/recent-auth; transparency lacked a patient-scoped assertion; outcome projection did not independently resolve its patient.
Correction batch: added `CF01_Future24_Guard`, doctor+step-up fact-write gating, privileged institutional/simulation rules, patient-scoped transparency contract, follow-up-to-patient authorization and regression tests.
Status: corrected at source level; no staging/live claim.

## Round 3 — failure modes, exception safety and degraded-state review
Audit completed before correction.
Defects found: callback exceptions could escape after authorization/state races; disabled feature state lacked a distinct safe-unavailable status; nested simulation scanning could cast arrays to strings; trace fallback could itself throw.
Correction batch: wrapped all Future24 callbacks in a fail-closed boundary, added sanitized 503/400/403/500 envelopes, safe nested identifier scanning, non-throwing trace fallback and regression tests.
Status: corrected at source level; no staging/live claim.

## Round 4 — clinical-safety, data-minimization and adapter-output review
Audit completed before correction.
Defects found: provider responses lacked independent minimum-necessary/authorization assertions; autonomous clinical and donor/payment-bias checks were top-level only; no common nested raw-secret/provider-payload rejection; simulation output lacked independent recursive real-subject enforcement.
Correction batch: added `CF01_Future24_Response_Guard`, mandatory provider governance assertions, recursive clinical-autonomy/financial-bias/secret rejection, synthetic simulation-output enforcement and regression tests.
Status: corrected at source level; no staging/live claim.

## Round 5 — exact-head CI, test-inventory and regression-gate review
Audit completed before correction. The entire failing exact-head workflow was inspected across PHP, Python, 40-round and policy/package jobs before any fix was applied.
Defects found: stale plugin/combined PHP inventory counts and a stale pre-amendment green-color assertion caused the exact-head workflow families to fail even though the new Future24 Python regression tests themselves passed.
Correction batch: updated plugin inventory to 26, combined permanent PHP inventory to 37 and the visual regression gate to current Sabri Green `#087A4E`; no substantive gate was bypassed.
Status: exact-head GitHub Actions run `35051481951` completed successfully after the correction batch; no staging/live claim.

## Round 6 — exact-release activation, governance evidence and state-transition review
Audit completed before correction.

Defects found:
1. `cf01_future24_governance_evidence` was only a set of unbound booleans; it was not cryptographically/structurally bound to the current accepted core activation generation, activation fingerprint, repository HEAD, package checksum, environment or runtime/schema/contract versions.
2. Future24 feature-state and governance-evidence options had no dedicated pre-update transition guards, so stale or replacement evidence could be written without a source-level state-transition policy.
3. The core activation receipt retained only fingerprint/time/generation, preventing direct Future24 exact-head/package traceability even though the source activation evidence itself had validated those values.
4. Evidence records did not require immutable hashes for Founder, privacy, clinical-safety, security, staging and rollback acceptance; data-governance-sensitive features 020–023 likewise lacked a required evidence hash.

Correction batch after Round 6 audit:
- Added `CF01_Future24_Governance` with fail-closed pre-update guards for feature states and governance evidence.
- Bound every usable Future24 evidence record to the active core activation fingerprint/generation, exact HEAD, package SHA-256, environment and current runtime/schema/contract versions.
- Enriched the accepted activation receipt with exact release identity after the already-validated core activation transition.
- Required SHA-256 evidence hashes for Founder/privacy/clinical-safety/security/staging/rollback gates and an additional data-governance hash for Future24 020–023.
- Made governance evidence immutable while any governed feature is `shadow` or `enabled`, and prohibited silent removal of active states.
- Added permanent exact-activation regression tests and updated inventory gates for the new governance class.

Status after correction: source governance defects corrected; exact-head CI re-verification required. No staging/live claim.
