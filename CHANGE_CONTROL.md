# CF-01 Change-Control Register

## CF01-CCR-2026-08-03-001 — Begin C1-A Foundation

| Field | Decision |
|---|---|
| Requested by | Founder — Dr. Allamah Majid Hussain Sabri |
| Request evidence | Explicit instruction on 03 August 2026 to begin work in the newly created CF-01 repository |
| Approved scope | C1-A governance, architecture, threat modelling, data-flow definition, role/authorization model, retention applicability register, requirements traceability and public-safe automated repository controls |
| Not yet approved | Production clinical runtime, WordPress activation, clinical database tables, real patient data, real clinical attachments, live routes, provider credentials, break-glass operation, migration from File 08 or public claims of compliance/production readiness |
| Working identifier | CF-01; no permanent numbered-file identity is asserted by this record |
| Repository | `majidhussainqadri1-dot/cf-01-clinical-records-prescription-follow-up` |
| Branch | `codex/cf-01-c1-a-foundation` |
| Risk class | High — future C5 clinical data system |
| Current truthful status | Specified / C1-A in progress; not Coded as a clinical runtime, not Packaged, not Staging-Accepted, not Live-Deployed, not Operational |

## Rationale

The governing plan requires C1-A to precede runtime implementation. This phase establishes the evidence and boundaries needed to decide whether extraction from File 08 is legally, clinically, technically and operationally justified. Starting with runtime tables or patient workflows before these controls would create an unapproved clinical system of record and contradict the conditional-module law.

## Required C1-A exit evidence

1. Qualified Pakistan and target-jurisdiction legal/professional review of clinical records, consent, minors, telehealth, prescriptions, retention, breach duties and patient rights.
2. Approved data-flow diagram and threat model covering identity, care relationship, clinical write, attachments, notifications, exports, backups, deletion and restore.
3. Canonical ownership and integration contracts frozen for Files 00/02, 03/07/09, 08, 15/16, 17, 19, 20/25 and 24.
4. Role, purpose, object, field, relationship, consent, guardian, suspension, entitlement, recent-authentication and record-version authorization model approved.
5. Retention and legal-hold register reviewed by qualified professionals; no blanket or invented retention period.
6. Independent security architecture review defining encryption/key management, secure object storage, malware scanning, immutable audit, backup/restore and break-glass controls.
7. Named operational owners: Clinical Product Owner, Medical Records Custodian, Treating-Doctor Lead, Clinical Safety Officer, Privacy Officer, Security Lead and escalation chain.
8. Founder approval of the final C1-A evidence package and permission to enter C1-B.

## Safety constraints during C1-A

- No patient, doctor or guardian personal data may be committed, used as fixtures or copied into issues, pull requests, logs or screenshots.
- Test examples must be synthetic, non-identifying and explicitly marked synthetic.
- No secret, key, database dump, production log, clinical attachment or private incident runbook may enter this public repository.
- No endpoint or user interface may claim to diagnose, prescribe automatically, replace emergency care or establish a live doctor-patient relationship.
- No compliance certification or jurisdictional applicability may be claimed before qualified review.
- Any future runtime mutation must be server-side authorized and auditable; UI visibility is never authority.

## Rollback and revocation

The C1-A branch may be closed without merge if the architecture is rejected. Merged documentation may be superseded only through a dated change-control record. No clinical data migration or irreversible state is created in this phase, so rollback consists of reverting the relevant commits and preserving the rejected decision record for audit.

## Review doctrine

Every substantive batch must undergo:

1. comprehensive review, defect correction and retesting;
2. a separate fresh/adversarial review, further correction and retesting;
3. truthful recording of unresolved risks before any phase promotion.

---

## CF01-CCR-2026-08-05-002 — Final C1-A Review, Correction and Merge-Readiness Hardening

| Field | Decision |
|---|---|
| Requested by | Founder — Dr. Allamah Majid Hussain Sabri |
| Request evidence | Explicit approval on 05 August 2026 to perform the final review, correct all identified defects and complete PR #1 merge-readiness |
| Approved scope | PR #1 governance documents, evidence integrity, cross-repository status reconciliation, repository validator, tests, workflow supply-chain hardening and truthful PR metadata |
| Explicit exclusion | No clinical runtime, WordPress plugin activation, patient tables, real data, File 08 extraction, clinical attachments, provider credentials, staging or production authorization |
| Merge decision boundary | PR #1 may be marked ready for review after exact-head and merge-ref gates are green; merge records governance baseline only and does not grant C1-A phase exit or C1-B authority |
| Required evidence | Final correction register, current cross-repository status, hardened workflow, expanded negative tests, successful exact-head checks, successful merge-ref compatibility and current PR body |
| Rollback | Revert the final-hardening commits; no clinical or irreversible data state exists |

### Corrective objectives

1. Remove stale or self-referential SHA/run claims from committed evidence.
2. Require every declared C1-A governance/evidence document in automated validation.
3. Reject duplicate requirement mappings and hidden generated/dependency trees.
4. Reject split sensitive-path variants.
5. Pin GitHub Actions to exact commits, use a fixed runner and disable persisted checkout credentials.
6. Test exact pull-request head and pull-request merge ref as separate evidence surfaces.
7. Reconcile File 00 provider status after PR #13 merge without falsely declaring the contract accepted or frozen.
8. Preserve every external legal, clinical, privacy, security, retention, operational and Founder phase-exit blocker.

### Completion rule

This change-control record is complete only when the corrected final head has no known repository-correctable defect, all mandatory CI jobs pass, the PR summary matches the tested head, and PR #1 is ready for review while remaining explicitly blocked from clinical runtime authorization.
