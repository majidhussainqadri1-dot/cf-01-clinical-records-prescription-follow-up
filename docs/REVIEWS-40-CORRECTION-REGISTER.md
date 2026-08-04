# CF-01 — Forty-Round Review and Correction Register

## Governing status

This register records forty separate review scopes against the reviewable source on `codex/cf-01-complete-runtime-1.0.0`. Every round ends with an automated correction/regression gate. A round that finds no new defect records **No new defect** rather than inventing a correction. Runtime activation remains disabled by default; this register does not claim legal approval, staging acceptance, live deployment, or operational completion.

## Review doctrine

1. Review the corrected exact head, not an earlier package.
2. Identify a concrete invariant, threat, concurrency risk, privacy boundary, or release gate.
3. Correct every confirmed defect in the same review cycle.
4. Re-run `tests/security-corrections.php` and `tests/fresh-review.php` after every round.
5. At rounds 10, 20, 30, and 40, re-run unit, runtime-adversarial, static, and migration suites.
6. Round 40 additionally re-runs repository policy tests and the runtime validator.

## Forty rounds

| Round | Review scope | Finding | Correction / final state |
|---:|---|---|---|
| 01 | Plan-to-runtime traceability | No new defect after source materialization | CF01-FR-001 through CF01-FR-032 remain explicitly traced. |
| 02 | Conditional activation | No new defect | Module remains disabled by default and real patient data remains prohibited before acceptance. |
| 03 | Clinical identity separation | No new defect | Clinical UUID and encrypted/blind-indexed platform linkage remain separate from public identity. |
| 04 | Explicit actor identity | No new defect | Asserted actor mismatch fails closed except an explicit service-actor contract. |
| 05 | Membership eligibility | No new defect | Current approved, non-suspended membership is rechecked on protected actions. |
| 06 | Professional eligibility | No new defect | Verified, eligible, non-suspended, unexpired practitioner assertion remains mandatory. |
| 07 | Explicit-user capabilities | **Defect found:** isolated checks relied on current-user capability semantics | Production now prefers `user_can($user_id, …)` with a test-harness-only fallback. |
| 08 | Relationship temporal validity | **Defect found:** active status did not reject a future `starts_at` | Start and end timestamps are now timezone-aware and enforced at action time. |
| 09 | Relationship actor authorization | **Defect found:** transition lacked actor authorization | Activation and transition now authorize the assigned doctor or an authorized records manager. |
| 10 | Assigned practitioner validation | **Defect found:** manager activation impersonated the target doctor | Target practitioner eligibility is checked directly without asserting the manager as that doctor. |
| 11 | Latest-consent supersession | **Critical defect found:** older grant could survive a later decline/withdrawal lookup | Latest consent is selected regardless of status; only the latest granted, unexpired record authorizes. |
| 12 | Consent expiry/timezone | **Defect found:** permissive timestamp parsing | Consent and assertion times now use `DateTimeImmutable` with UTC normalization. |
| 13 | Guardian actor binding | **High defect found:** guardian reference could be treated as authority | Guardian action now requires current verified platform identity binding. |
| 14 | Reference-only impersonation | **High defect found:** disclosed reference could authorize an unrelated actor | Reference is consistency evidence only; it never substitutes for actor identity. |
| 15 | Minor/guardian governance | **Defect found:** legal majority was hard-coded | Majority age is now bounded and jurisdiction-policy configurable; assent remains required where applicable. |
| 16 | Field-level authorization | No new defect; hardening applied | Requested and policy fields are normalized, deduplicated, and intersected fail-closed. |
| 17 | Patient ownership | No new defect | Ownership remains blind-indexed against the current canonical membership UUID. |
| 18 | Encounter context | No new defect; time parsing hardened | Mode and start time are validated with timezone-aware normalization. |
| 19 | Teleconsultation consent | **Defect found:** teleconsultation used only general clinical consent | Teleconsultation now requires both clinical-care and teleconsultation purpose consent. |
| 20 | Draft concurrency | No new defect | Optimistic row versions continue to reject stale writes. |
| 21 | Signed encounter immutability | No new defect | Signed, addended, and entered-in-error encounter content remains immutable. |
| 22 | Addendum provenance | **Defect found:** parent stayed `signed` and was not versioned when addendum was added | Parent is atomically versioned to `addended`; addendum snapshot records parent version and signer professional UUID. |
| 23 | Entered-in-error scope | **High defect found:** generic clinician capability could mark an unrelated encounter | Treating relationship and valid state transition are now mandatory. |
| 24 | Observation correction atomicity | **Defect found:** replacement could survive if supersession lost a race | Replacement insert, old-row supersession, and audit now execute in one transaction. |
| 25 | Assessment lifecycle | **Defect found:** assessment could be created or signed against a closed/tombstoned encounter | Creation and signing now require an open associated encounter. |
| 26 | Assessment consent/signature | No new defect; hardening applied | Current consent is rechecked at assessment signing and signer provenance remains cryptographic. |
| 27 | Prescription clinician ownership | No new defect | Practitioner, treating relationship, consent, and clinician-entered order rules remain enforced. |
| 28 | Prescription signed-state integrity | No new defect | Signed order snapshot/signature and explicit supersession/discontinuation lifecycle remain intact. |
| 29 | Follow-up/outcome scoping | No new defect | Patient ownership, treating relationship, due-state, and clinician review boundaries remain in force. |
| 30 | Break-glass request validity | **Defect found:** grant did not first prove the patient record existed | Patient existence and an authorized minimum field set are required before grant creation. |
| 31 | Break-glass current eligibility | **High defect found:** grant assertion did not recheck practitioner eligibility | Every emergency read now revalidates current membership and professional eligibility. |
| 32 | Break-glass field ceiling | **High defect found:** later assertion could request fields not named in the grant | Read fields are now the intersection of current request, original grant, and emergency policy. |
| 33 | Break-glass expiry race | **Defect found:** expiry events could emit after a lost update | Audit/outbox events emit only after the versioned expiry transition succeeds. |
| 34 | Rights request eligibility | **Defect found:** patient-owner path skipped the current actor gate | Every rights request now rechecks current actor eligibility and patient existence. |
| 35 | Representative identity | **High defect found:** representative reference alone could pass | Explicit requesting actor must match verified guardian platform identity; reference is consistency-only. |
| 36 | Export token consumption | **Defect found:** one-time token was consumed before delivery-provider acceptance | Secure delivery is obtained first; token consumption follows only after provider acceptance. |
| 37 | Correction fulfillment atomicity | **High defect found:** correction case could target another patient and case/addendum writes were non-atomic | Encounter patient must match the case; addendum and case fulfillment run transactionally. |
| 38 | Export completeness | **Defect found:** fixed limits could silently truncate rights exports | Synchronous export fails explicitly above bounds and directs the workflow to an approved paginated job. |
| 39 | PHP/package reproducibility | **Infrastructure defect corrected:** prior evidence was tied to payload materialization rather than the reviewable tree | Exact-head PHP 8.1/8.3 suites and byte-identical double-build ZIP checks are permanent CI gates. |
| 40 | Integrated independent final review | No new defect after corrections | Forty review scopes, forty correction gates, full milestone suites, repository policy tests, and runtime validation are required to pass together. |

## Confirmed corrections introduced during these rounds

- Latest-consent semantics now prevent an older grant from bypassing a newer decline, withdrawal, or expiry.
- Guardian/representative authority is identity-bound and no longer reference-bound.
- Treating relationship changes, entered-in-error actions, and emergency reads are object- and actor-scoped.
- Addenda, observation correction, relationship activation, and rights correction use transactional/versioned state changes.
- Teleconsultation, assessment, break-glass, export delivery, and bounded export rules fail closed.
- Exact-head CI covers PHP 8.1, PHP 8.3, JavaScript, repository policy, runtime validation, and deterministic packaging.

## Evidence commands

```bash
python3 tools/run_40_reviews.py
php tests/security-corrections.php
php tests/unit.php
php tests/runtime-adversarial.php
php tests/static-audit.php
php tests/migration-review.php
php tests/fresh-review.php
python3 -m unittest discover -s tests -p 'test_*.py' -v
python3 tools/validate_runtime.py .
bash tools/package.sh
```

## Status boundary

Passing this register proves reviewed source and automated correction evidence for the exact commit. It does **not** replace qualified legal/professional approval, independent penetration testing, Hostinger-equivalent staging, backup/restore rehearsal, migration/rollback rehearsal, accessibility/browser acceptance, or Founder production activation.
