# CF-01 — Clinical Records, Prescription and Follow-Up

Conditional, disabled-by-default clinical system-of-record source candidate for the **Sabri Social Homeopathy Platform**.

> **Current status:** the C1-A public-safe governance baseline is merged. Founder Change-Control `CF01-CCR-2026-08-06-003` authorizes source implementation and canonical repository integration only. Runtime activation, real patient data, Hostinger staging and production use remain prohibited until every external acceptance gate passes.

## Canonical scope

CF-01 is the planned canonical owner of the following clinical domain after activation approval:

- a separate clinical patient-identity compartment linked to platform identity through protected references;
- verified treating relationships, care-team scope and purpose-bound clinical consent;
- structured intake, history, observations, homeopathic totality and clinician-authored assessments;
- immutable signed encounters with addenda and entered-in-error handling;
- clinician-entered prescriptions with signature, supersession, discontinuation and safety history;
- follow-up plans, patient-reported outcomes, clinician review and bounded longitudinal timelines;
- access history, correction/export requests, retention/legal holds and disposal reconciliation;
- restricted break-glass access with step-up, minimum fields, expiry, notification and independent retrospective review;
- secure clinical attachments through owner-approved quarantine, scanning and expiring-delivery contracts.

## Explicit boundaries

CF-01 does not own authentication, membership identity, doctor verification, appointments, ordinary messages/calls, notification transport, global shell/navigation, public profiles/timelines, security governance, general search, payments or shared-media infrastructure. It never turns Radar/AI output into diagnosis or prescription and never treats a public successful-case article as a clinical chart.

Every companion capability remains with its canonical owner. Availability or cached state is not authorization; object, field, purpose, relationship, consent, guardian, suspension, recent-authentication and record-version checks are repeated server-side at action time.

## C1-A governance package

The merged public-safe foundation includes:

- `CHANGE_CONTROL.md` — phase authorization, scope, prohibitions and rollback law;
- `SECURITY.md` — public-repository disclosure and sensitive-artifact policy;
- `docs/C1-A-FOUNDATION.md` — architecture, trust boundaries, roles and threat model;
- `docs/C1-A-CROSS-FILE-CONTRACTS.md` and `docs/C1-A-CROSS-REPOSITORY-CONTRACT-TRACKING.md` — owner-contract and freeze evidence;
- legal/professional, retention/legal-hold, cryptography/storage, operational-ownership and independent-review registers;
- C1-A and C1-B–C1-H requirements traceability and evidence manifests;
- hardened repository-policy tests and exact-head/merge-ref workflow controls.

These documents establish governance and source boundaries; they are not qualified legal, clinical, privacy or security acceptance.

## Runtime candidate

The `sabri-clinical-records/` plugin source implements the approved source-level candidate for `CF01-FR-001` through `CF01-FR-032`:

- clinical patient compartment and duplicate/quarantine controls;
- relationship, consent and guardian lifecycle;
- encounter, observation, assessment and immutable correction workflows;
- clinician-only prescription and follow-up/outcome workflows;
- field-level authorization, access ledger and patient rights;
- break-glass, retention, audit/outbox, migration, rollback and continuity controls;
- protected REST/UI surfaces, private headers and no-browser-storage rules;
- native-owner contract validation and activation compensation;
- R5 object-bound authorization, provider assertions, attachment hardening, emergency/reminder controls, signed-record restore verification and versioned encryption-key rotation.

Runtime, schema and contract candidate versions are `1.0.1 / 1.0.0 / 1.0.0`. Activation remains disabled by default. Real patient data is prohibited.

## Development and review

```bash
find sabri-clinical-records tests -name '*.php' -type f -print0 | xargs -0 -n1 php -l
node --check sabri-clinical-records/assets/js/clinical.js
python3 -m unittest discover -s tests -p 'test_*.py' -v
python3 tools/validate_repository.py .
python3 tools/validate_runtime.py .
php tests/unit.php
php tests/runtime-adversarial.php
php tests/static-audit.php
php tests/migration-review.php
php tests/fresh-review.php
php tests/security-corrections.php
python3 tools/run_40_reviews.py
bash tools/package.sh
```

The release workflow runs governance and runtime gates on exact pull-request heads, separately tests the pull-request merge ref, reviews PHP 8.1 and 8.3, and builds the installable ZIP twice for byte-for-byte comparison.

## Activation law

Source merge does not authorize operation. Before any activation or real-data use, all of the following remain mandatory:

1. qualified Pakistan and target-jurisdiction legal/professional review;
2. accepted clinical threat model, data-flow map, retention formulas and patient-rights rules;
3. frozen, compatible native-owner contracts for identity, verification, appointments, communications, notifications, shell/public components, assurance and secure delivery;
4. approved provider, region, storage, encryption-key and recovery decisions;
5. independent penetration testing and corrective retest;
6. Hostinger-equivalent isolated staging with synthetic data only;
7. fresh install, upgrade, migration, reconciliation, backup/restore and rollback drills;
8. representative browser, device, RTL, accessibility, weak-connection, load and failure acceptance;
9. named clinical, records, privacy, security, support and release owners with runbooks;
10. explicit Founder production approval supported by immutable evidence.

## Truthful completion statuses

`Specified`, `Coded`, `Packaged`, `Automated-QA Green`, `Staging-Accepted`, `Live-Deployed` and `Operational` are separate states.

The repository may establish a **Coded / Packaged / Automated-QA Green candidate**. It does not establish Staging-Accepted, Live-Deployed or Operational status. No claim of being unhackable, fully compliant, certified or absolutely defect-free is permitted.

## Security and privacy

This is a public repository. Never commit patient data, clinical attachments, identity evidence, credentials, private runbooks, encryption keys, provider secrets, database dumps, production logs or unrestricted clinical content. Synthetic fixtures must remain non-identifying.

## Governance

Every change must preserve one canonical owner, server-side object/field/purpose authorization, immutable clinical provenance, fail-closed integration, reversible migration and the review doctrine:

**plan → implement → review → correct → fresh/adversarial review → correct → exact-head test → merge-ref test → truthful status.**
