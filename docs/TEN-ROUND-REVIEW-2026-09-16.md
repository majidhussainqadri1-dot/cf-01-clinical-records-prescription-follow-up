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
Defects found:
1. A future provider adapter result was trusted after route authorization without an independent minimum-necessary/authorization assertion in the response contract.
2. Autonomous clinical-action rejection checked only selected top-level decision-support fields, so nested `dose`/`potency`/automatic-action fields could evade the check.
3. Donor/payment priority rejection in transparency was likewise top-level only.
4. No common last-line rejection existed for raw secrets/provider payloads, and simulation output did not have an independent recursive real-subject check.

Correction batch after Round 4 audit:
- Added `CF01_Future24_Response_Guard` as the final provider-response filter.
- Required explicit provider assertions for authorization, minimum-necessary disclosure, CF-01 canonical ownership and contract version.
- Added recursive rejection for autonomous diagnosis/prescription/dose/potency/treatment mutation signals.
- Added recursive donor/payment/rank-bias rejection and global secret/raw-payload rejection.
- Added independent synthetic/de-identified simulation-output enforcement and regression tests.

Status after correction: Round 4 source clinical-safety/data-minimization defects corrected; no live/staging claim.
