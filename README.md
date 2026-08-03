# CF-01 — Clinical Records, Prescription and Follow-Up

Conditional future clinical-domain module for the **Sabri Social Homeopathy Platform**.

> **Current status:** C1-A governance and architecture foundation. This repository is not a production clinical system, does not authorize live patient data, and must not be installed on the live website.

## Canonical scope

CF-01 is intended to become the canonical owner of highly sensitive clinical records after all activation gates are satisfied. Its approved domain includes:

- clinical patient identity compartment linked to, but separate from, the public platform identity;
- verified care relationships and purpose-bound access;
- structured intake, history, observations, totality and clinician-authored assessments;
- immutable signed encounters with addenda and entered-in-error handling;
- clinician-entered prescriptions with supersession and discontinuation history;
- follow-up plans, patient-reported outcomes and longitudinal clinical timelines;
- consent, guardian context, access history, correction/export requests and retention controls;
- highly restricted break-glass access with step-up, expiry, notification and retrospective review;
- secure clinical attachments through quarantined, scanned and access-controlled storage.

## Explicit boundaries

This repository must not become the canonical owner of:

- public profiles, doctor verification or appointments;
- ordinary messages, calls or message bodies;
- public case articles or educational publishing;
- Radar/AI diagnosis, autonomous prescription or emergency replacement;
- payments, card data, billing or refund ledgers;
- public media-library objects, global search indexes or unrestricted analytics.

## Activation law

Runtime clinical coding and any handling of real patient data remain blocked until the C1-A exit gate is recorded:

1. qualified legal and professional applicability review;
2. approved data-flow map and clinical threat model;
3. canonical ownership, role, consent, guardian and retention decisions;
4. File 00/02 identity assertions and File 08 care-context contracts frozen;
5. File 20/25 private-shell and accessible-component contracts available;
6. File 24 assurance manifest and independent security acceptance defined;
7. named operational owners, deputies, coverage and escalation paths;
8. Founder Change-Control approval recorded with version, date and evidence.

## C1-A governance package

The public-safe foundation currently includes:

- `CHANGE_CONTROL.md` — phase authorization, scope and prohibitions;
- `SECURITY.md` — public-repository disclosure and sensitive-artifact policy;
- `docs/C1-A-FOUNDATION.md` — architecture, trust boundaries, roles, authorization and threat model;
- `docs/C1-A-CROSS-FILE-CONTRACTS.md` — versioned ownership and integration freeze baseline;
- `docs/C1-A-LEGAL-PROFESSIONAL-APPLICABILITY-REGISTER.md` — qualified-review register and launch gates;
- `docs/C1-A-RETENTION-LEGAL-HOLD-MATRIX.md` — category-specific retention, holds and disposal framework;
- `docs/C1-A-CRYPTOGRAPHY-STORAGE-ATTACHMENT-SECURITY.md` — key, storage, quarantine, scanning, delivery and recovery architecture;
- `docs/C1-A-OPERATIONAL-OWNERSHIP-AND-ESCALATION.md` — accountable roles, separation of duties and escalation;
- `docs/C1-A-INDEPENDENT-REVIEW-PLAN.md` — legal, clinical, security, privacy, accessibility and resilience review plan;
- `docs/C1-A-REQUIREMENTS-TRACEABILITY.md` — requirement, owner, evidence and blocker status;
- `tools/validate_repository.py` and `tests/` — automated public-safety and phase-boundary gate.

These documents establish reviewable baselines; they do not themselves constitute qualified approval, working controls or runtime completion.

## Delivery phases

| Phase | Scope | Exit gate |
|---|---|---|
| C1-A | Legal/professional applicability, architecture, data flow, threat model, owners, roles, retention and extraction approval | Qualified sign-off + Founder Change-Control |
| C1-B | Identity, care relationship, consent and field authorization | IDOR, guardian and termination tests pass |
| C1-C | Intake, encounters, observations, attachments and signatures | Integrity, concurrency, scanner and accessibility pass |
| C1-D | Prescriptions, supersession, patient instructions and safety limits | Clinician-only and adversarial approval |
| C1-E | Follow-up, outcomes, timeline and reminders | Time-zone, red-flag and no-auto-treatment tests pass |
| C1-F | Patient portal, rights, export/correction, access history and break-glass | Privacy, legal and security acceptance |
| C1-G | Controlled extraction from File 08, shadow reads and reconciliation | Zero unexplained divergence + rollback drill |
| C1-H | Load, resilience, restore, penetration testing, clinical UAT and staged rollout | Founder + clinical/privacy/security approval |

## Truthful completion statuses

`Specified`, `Coded`, `Packaged`, `Automated-QA Green`, `Staging-Accepted`, `Live-Deployed` and `Operational` are separate statuses and must never be treated as synonyms.

Current truthful status:

- governing plan: specified;
- public-safe C1-A governance package: drafted and under review;
- companion-owner contracts and qualified approvals: blocked/pending;
- clinical runtime, package, staging, live deployment and operations: not started and not claimed.

## Security and privacy

This is a public repository. Never commit patient data, clinical attachments, identity evidence, credentials, private runbooks, encryption keys, provider secrets, database dumps, production logs or unrestricted clinical content.

## Governance

Every change must preserve one canonical owner, server-side object/field/purpose authorization, immutable clinical provenance, safe failure, migration/rollback evidence and two consecutive review-and-fix rounds before release.
