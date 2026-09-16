# CF-01 ten-round review — 2026-09-16

Review baseline: `9e58cf82dbb84f1a49804aa714a823878364f1f7`.

Process law: each numbered round is audited to completion first; only after that round closes are its collected defects corrected. The next round begins only after the correction batch is complete. A green repository/CI state is not staging acceptance, deployment or live verification.

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
Defects found: stale plugin/combined PHP inventory counts and a stale pre-amendment green-color assertion caused exact-head workflow failures although the new Future24 Python regressions themselves passed.
Correction batch: updated plugin inventory to 26, combined permanent PHP inventory to 37 and the visual regression gate to Sabri Green `#087A4E`; no substantive gate was bypassed.
Status: exact-head GitHub Actions run `35051481951` completed successfully after correction; no staging/live claim.

## Round 6 — exact-release activation, governance evidence and state-transition review
Audit completed before correction.
Defects found:
1. Future24 governance evidence was unbound boolean state rather than exact-release evidence.
2. Feature-state/evidence options lacked dedicated transition guards.
3. The core activation receipt lacked direct exact-head/package identity needed for Future24 traceability.
4. Required acceptance records lacked immutable document hashes, including extra data-governance evidence for 020–023.
Correction batch: added `CF01_Future24_Governance`; bound evidence to activation fingerprint/generation, exact HEAD, package SHA-256, environment and runtime/schema/contract versions; enriched activation receipt; required evidence hashes; prohibited silent active-state/evidence replacement; added regression gates.
Status: correction subsequently reverified green on exact-head CI; no staging/live claim.

## Round 7 — replay, abuse controls and institutional webhook integrity
Audit completed before correction.
Defects found:
1. Future24 mutations required an Idempotency-Key header but did not yet have durable command-receipt replay semantics comparable to the canonical CF-01 mutation path.
2. Request bodies and route traffic lacked a dedicated Future24 size/rate perimeter.
3. Institutional webhook acceptance lacked a mandatory timestamp/nonce/signature verification contract with a bounded replay window.
4. Provider contract assertions did not yet freeze the current contract identity strongly enough in the new perimeter.
5. The first correction exposed a stale regression assertion that still looked for the earlier idempotency implementation instead of the hardened bounded form.
Correction batch: added command-receipt reservation/replay/completion, request-size and rate gates, bounded 16–128 idempotency keys, signed webhook timestamp/nonce/body-hash/key-id/current-contract verification, exact provider contract checks and updated permanent regression tests.
Status: exact-head run `35053467364` passed Python 3.11/3.12, PHP 8.1/8.3, policy/package and forty-round jobs; no staging/live claim.

## Round 8 — API disclosure bounds, query bounds and auditability
Audit completed before correction.
Defects found:
1. Future24 query/filter input had no common bounded-query perimeter despite the API constitution requiring bounded pagination and safe filters.
2. Provider governance metadata (`_cf01`) could remain in the client response after it had served its internal validation purpose.
3. Provider response size had no common disclosure ceiling.
4. Successful Future24 access did not have a common fail-closed audit persistence layer at the final response boundary.
Correction batch: added maximum query count/value/limit controls, internal metadata redaction, a 512 KiB protected response ceiling, successful Future24 access/audit recording and regression tests.
Status: exact-head run `35053697989` passed all applicable jobs; no staging/live claim.

## Round 9 — pre-storage safety, stale-context control, safe errors and denial audit
Audit completed before correction.
Defects found:
1. Round-8 response redaction/size enforcement occurred at post-dispatch, which was after the durable idempotency receipt completion hook; an oversized/raw provider response could therefore be encrypted into the replay receipt before final disclosure rejection.
2. Future24 4xx envelopes could preserve lower-layer/runtime details and 403/404 distinctions that were unnecessary for the client.
3. Future24 route identifiers were not independently normalized to canonical RFC-style UUID shape at the common perimeter.
4. Advisory decision-support required an expected version header but did not independently compare that version with the authorized patient row before computation.
5. Denied Future24 requests lacked a common denial-audit hook; generic 5xx redaction also lacked a trace identifier.
Correction batch: added a pre-receipt callback-response hardening hook at `PHP_INT_MAX - 1`, stripped internal metadata and enforced the response ceiling before receipt completion, normalized 4xx envelopes with 403/404 existence indistinguishability, added canonical UUID checks, compared decision-support expected version with the patient row, audited denied requests when runtime is enabled and added trace IDs plus permanent regressions.
Status: exact-head run `35053829202` completed successfully; 72 Python tests passed, repository/runtime validation passed, PHP 8.1/8.3 and forty-round gates passed, and deterministic package/SBOM evidence was retained; no staging/live claim.

## Round 10 — independent final release-evidence and documentation consistency review
Audit completed before correction.
Defects found:
1. This ten-round ledger stopped at Round 6 and therefore did not record the completed Round 7–9 audits/corrections.
2. `docs/RELEASE-STATUS.md` still identified an obsolete implementation head/run/artifact from before Future24 hardening and described governance as only R1–R4.
3. Workflow display names still said `R1-R4` although the canonical workflow now executes the expanded repository/runtime/Future24 regression surface, making release evidence nomenclature stale and potentially misleading.
4. Release-status traceability did not distinguish the original 32 base functional requirements from the 24 stable Future24 capability IDs.
Correction batch: complete this ledger, refresh immutable implementation-release evidence to the latest fully green implementation head, distinguish 32/32 base requirements plus 24/24 Future24 IDs, and rename workflow evidence labels without weakening any gate. A final exact-head workflow run is required after this correction batch.
Status: correction batch committed; final exact-head CI result is the closing evidence for the repository round. No staging/live claim is implied.

## Defect-round summary
Defects were found in **Rounds 1, 2, 3, 4, 5, 6, 7, 8, 9 and 10**. In every round, the audit was completed and its defect set frozen before that round's correction batch began. No numbered next round began before the preceding correction batch and its required source/CI verification had closed.

## Release-boundary statement
Repository source completion and automated QA do not establish staging or production reality. `Staging-Accepted`, `Live-Deployed` and `Operational` remain separate evidence states and must not be inferred from this review ledger.
