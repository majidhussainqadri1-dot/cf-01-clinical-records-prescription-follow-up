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

Defects found:
1. `tests/fresh-review.php` still froze the pre-Future24 plugin PHP inventory at 24 files although two governed Future24 PHP files had been added.
2. `tools/validate_runtime.py` still froze the combined permanent PHP inventory at 35 rather than 37.
3. `tests/three-plan-corrections.php` still asserted the superseded green fallback `#0b6b3a`, contradicting the current Sabri Green `#087A4E` requirement.
4. Because these regression gates were stale, all exact-head workflow families failed or stopped early even though the newly added Future24 Python regression tests themselves passed.

Correction batch after Round 5 audit:
- Updated the plugin PHP inventory gate to 26 and the combined permanent PHP inventory gate to 37.
- Updated the three-plan visual regression gate to require current Sabri Green `#087A4E` case-insensitively.
- Preserved all substantive clinical/security/runtime gates; no failing test was removed or bypassed.

Status after correction: source/test-gate defects corrected; exact-head CI re-verification is required before Round 5 can be considered QA-green. No staging/live claim.
