# CF-01 — Forty-Round Fresh Review, Correction and Retest Register R2

## Governing basis

This fresh review starts from merged `main` commit `39fa2c689beaeeafa0f59aaed1d16bc6dcc2353b` and reviews the corrected exact head against:

1. **Sabri Social Homeopathy Platform — Definitive Integrated Master Plan v3.0**;
2. **Sabri Platform All-Chats Recovered Directive Register v2.1**;
3. **CF-01 Clinical Records, Prescription and Follow-Up Conditional Complete Master Plan v1.0**.

Every round is followed by the correction gate. A correction is recorded only when a concrete defect was found. A clean round is recorded as **No new defect**. The review does not convert source completion into staging, legal, penetration-test, production or operational acceptance.

## Final count

- **Total review rounds:** 40
- **Rounds in which defects were found and corrected:** 5
- **Rounds in which no new defect was found:** 35
- **Correction gates required:** 40
- **Unresolved known Critical/High defects after correction and retest:** 0 within the reviewed source scope

## Forty review rounds

| Round | Review scope | Result | Correction / verified final state |
|---:|---|---|---|
| 01 | Three-plan requirement traceability | No new defect | CF01-FR-001 through CF01-FR-032 remain represented in implementation traceability. |
| 02 | Conditional activation versus migration/restore governance | **Defect found** | `actor()` previously required an already-enabled runtime even for migration, rollback, key rotation and private health checks. A bounded pre-activation action allowlist now permits only those governance operations while preserving membership, capability and step-up checks. |
| 03 | Separate clinical identity and public identity | No new defect | Clinical UUID, encrypted subject link and blind index remain separate from public/member identity. |
| 04 | Explicit actor binding and service-actor exception | No new defect | Asserted actor mismatch remains fail-closed except the explicit filtered service-actor contract. |
| 05 | Membership approval and suspension recheck | No new defect | Current approved, non-suspended membership remains mandatory. |
| 06 | Practitioner verification and expiry | No new defect | Verified, eligible, non-suspended and unexpired practitioner evidence remains required. |
| 07 | Explicit-user capability evaluation | No new defect | `user_can($user_id, …)` remains preferred over ambient-current-user authorization. |
| 08 | Treating relationship start/end validity | No new defect | Future, expired and invalid relationship windows fail closed. |
| 09 | Relationship actor authorization | No new defect | Assigned doctor or specifically authorized records manager is required. |
| 10 | Target practitioner validation | No new defect | The target doctor is checked directly without manager impersonation. |
| 11 | Latest consent supersession | No new defect | A later decline, withdrawal or expiry cannot be bypassed by an older grant. |
| 12 | Consent timestamp normalization | No new defect | UTC-normalized immutable timestamps remain enforced. |
| 13 | Guardian actor identity binding | No new defect | Guardian authority remains bound to verified platform identity. |
| 14 | Reference-only impersonation prevention | No new defect | A disclosed guardian/reference token cannot substitute for actor identity. |
| 15 | Minor, assent and jurisdictional majority | No new defect | Majority age remains policy-bounded and assent/guardian rules remain enforced. |
| 16 | Field-level minimum-necessary projection | No new defect | Requested fields are normalized and intersected with role/purpose policy. |
| 17 | Patient ownership boundary | No new defect | Own-record access remains blind-indexed against current canonical membership identity. |
| 18 | Clinical shortcode and canonical-route privacy classification | **Defect found** | A page embedding `[sabri_clinical_records]` could fall outside `is_clinical_request()` and therefore outside no-store/noindex classification. Shortcode-bearing singular pages are now classified as clinical; rewrite rules no longer reference a nonexistent capture group. |
| 19 | Teleconsultation purpose consent | No new defect | Teleconsultation requires both clinical-care and teleconsultation consent. |
| 20 | Transaction start/commit/rollback and nested atomicity | **Defect found** | Transaction control results were not checked and nested operations had no savepoint semantics. Transactions now fail closed on start/commit failure, use bounded savepoints for nesting, and verify rollback/release success. |
| 21 | Signed encounter immutability | No new defect | Signed, addended and entered-in-error content remains immutable. |
| 22 | Addendum parent provenance and versioning | No new defect | Parent and addendum provenance remain atomic and versioned. |
| 23 | Entered-in-error treating scope | No new defect | Generic clinician capability cannot alter an unrelated encounter. |
| 24 | Observation correction atomicity | No new defect | Replacement, supersession and audit remain one transaction. |
| 25 | Assessment lifecycle and open encounter | No new defect | Creation/signing remains restricted to an open associated encounter. |
| 26 | Assessment consent and signature | No new defect | Current consent and cryptographic signer provenance remain enforced. |
| 27 | Prescription clinician ownership | No new defect | Practitioner, relationship, consent and clinician-entered rules remain enforced. |
| 28 | Prescription signed-state lifecycle | No new defect | Draft, signed, superseded and discontinued transitions remain explicit. |
| 29 | Follow-up and patient outcome scope | No new defect | Patient ownership and treating relationship remain rechecked. |
| 30 | Break-glass patient existence and minimum view | No new defect | Emergency grant requires a real patient and bounded minimum fields. |
| 31 | Break-glass current practitioner eligibility | No new defect | Each emergency read rechecks current membership and professional eligibility. |
| 32 | Break-glass field ceiling | No new defect | Requested fields cannot exceed original grant and emergency policy. |
| 33 | Break-glass expiry concurrency | No new defect | Expiry events emit only after successful versioned transition. |
| 34 | Rights request current eligibility | No new defect | Every rights request rechecks current actor and patient context. |
| 35 | Representative identity binding | No new defect | Representative reference remains consistency evidence, not identity authority. |
| 36 | Export provider-before-token-consumption | No new defect | One-time export token is consumed only after secure-delivery acceptance. |
| 37 | Correction patient match and atomic fulfillment | No new defect | Case patient and encounter patient must match; fulfillment remains transactional. |
| 38 | Bounded, non-truncating rights export | No new defect | Oversized synchronous exports fail explicitly into approved pagination. |
| 39 | Single authoritative private-header owner | **Defect found** | `CF01_UI::headers()` ran after the central guard and overwrote the stronger Permissions-Policy and Cache-Control values. The duplicate UI header writer was removed; the central guard is now the single header owner and includes `X-Robots-Tag`. |
| 40 | Complete 5xx diagnostic redaction and integrated final review | **Defect found** | The generic 5xx body still preserved a sanitized upstream/internal error code. All non-diagnostic 5xx responses now expose only `cf01_internal_error`; internal identifiers are not reflected. Full correction and policy suites are rerun. |

## Corrections introduced in this R2 batch

1. Bounded pre-activation governance actions without opening clinical record access.
2. Fail-closed transaction start, nested savepoints, commit verification and rollback verification.
3. Shortcode-aware private request classification and capture-safe rewrite construction.
4. Removal of the weaker duplicate UI header writer; central header ownership preserved.
5. Fully generic 5xx error identifiers and explicit `X-Robots-Tag` protection.
6. Permanent Python and forty-round regression checks for all five findings.

## Required evidence commands

```bash
python3 tools/run_40_reviews.py
python3 -m unittest discover -s tests -p 'test_*.py' -v
php tests/unit.php
php tests/runtime-adversarial.php
php tests/static-audit.php
php tests/migration-review.php
php tests/fresh-review.php
php tests/security-corrections.php
php tests/three-plan-corrections.php
php tests/adversarial-round3.php
php tests/adversarial-round4.php
php tests/adversarial-round4-compensation.php
python3 tools/validate_repository.py .
python3 tools/validate_runtime.py .
bash tools/package.sh
```

## Truthful status boundary

This register establishes fresh source review and automated correction evidence only. CF-01 remains conditional and disabled by default. Real patient data, legal/professional approval, independent penetration testing, Hostinger-equivalent staging, backup/restore and rollback rehearsal, accessibility/browser acceptance and Founder production activation remain separate gates.

**Date:** 06 August 2026 — Asia/Karachi
