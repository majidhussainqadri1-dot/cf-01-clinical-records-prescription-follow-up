# CF-01 Governance and Source Evidence Manifest

**Manifest version:** 2.0  
**Repository:** `majidhussainqadri1-dot/cf-01-clinical-records-prescription-follow-up`  
**Governing source authority:** `CF01-CCR-2026-08-06-003`  
**Truth boundary:** committed documents do not embed a self-referential final SHA, workflow run or package checksum. Exact immutable release evidence belongs in the final pull-request record after the last content commit.

## 1. Evidence law

1. A statement is accepted only at the evidence level actually proved.
2. Governance documents prove specification and decision boundaries, not runtime behavior.
3. Source and tests may prove `Coded` and `Automated-QA Green` within their tested scope.
4. A deterministic artifact plus checksum may prove `Packaged`.
5. None of those prove `Staging-Accepted`, `Live-Deployed` or `Operational`.
6. Exact pull-request head and pull-request merge ref are separate required test surfaces.
7. Missing, stale, revoked, wrong-environment or wrong-release native-owner evidence fails closed.
8. New legal, clinical, provider, security or architecture evidence reopens review.

## 2. Truthful status

| Status class | Evidence-backed state |
|---|---|
| Specified | Complete governing plan, C1-A governance package and 32-requirement traceability exist |
| Coded | Reviewable disabled-by-default runtime candidate exists under Founder source authority |
| Packaged | Deterministic package gate exists; exact checksum is PR-owned final evidence |
| Automated-QA Green | Claimed only for the exact head and run recorded after final CI success |
| Qualified legal/professional accepted | No |
| Native-owner contracts frozen | No; owner repositories must independently accept and merge compatible contracts |
| Hostinger staging accepted | No |
| Live deployed | No |
| Operational | No |
| Real patient-data authorized | No |

## 3. Governance evidence

- `CHANGE_CONTROL.md` records the C1-A foundation, final governance hardening and source-runtime authorization.
- `SECURITY.md` defines public-repository disclosure and sensitive-artifact law.
- `docs/C1-A-FOUNDATION.md` defines architecture, trust boundaries, roles and threat model.
- `docs/C1-A-CROSS-FILE-CONTRACTS.md` defines canonical owners, contracts and freeze criteria.
- `docs/C1-A-CROSS-REPOSITORY-CONTRACT-TRACKING.md` records native-owner implementation and acceptance state.
- legal/professional, retention/legal-hold, cryptography/storage, operational-ownership and independent-review registers preserve unresolved external decisions.
- `docs/C1-B-TO-C1-H-IMPLEMENTATION-TRACEABILITY.md` maps every `CF01-FR-001` through `CF01-FR-032` requirement to one implementation phase.

## 4. Runtime evidence surfaces

- `sabri-clinical-records/` — disabled-by-default WordPress source candidate.
- `docs/REQUIREMENTS-TRACEABILITY.md` — one structured runtime mapping for every `CF01-FR-001` through `CF01-FR-032`.
- `docs/SECURITY-PRIVACY-ARCHITECTURE.md` — native enforcement, encryption, authorization, audit and privacy boundaries.
- `docs/MIGRATION-ROLLBACK.md` — disabled-state extraction, reconciliation, compensation and rollback law.
- `docs/REVIEWS-40-CORRECTION-REGISTER.md` — review/fix lineage.
- `tools/validate_repository.py` — unified public-safety, governance, workflow and traceability gate.
- `tools/validate_runtime.py` — runtime surface and requirement gate.
- PHP unit, adversarial, static, migration, fresh and security-correction suites.
- deterministic packaging and source/package parity checks.

## 5. Source authorization boundary

Founder Change-Control authorizes repository source integration through the reviewed candidate phases. It does not authorize:

- installation or activation on a live or staging site;
- clinical schema installation against real infrastructure;
- File 08 production extraction;
- real patient, guardian or clinician data;
- real clinical attachments or provider credentials;
- claims of certification, legal compliance, clinical acceptance or production readiness.

The plugin must remain `disabled` by default. Schema installation and runtime enablement require separate explicit evidence and approval.

## 6. External phase-exit blockers

1. Qualified Pakistan and target-jurisdiction legal/professional decisions.
2. Approved retention formulas, legal holds, patient-rights and breach duties.
3. Owner-frozen File 00/02/08/09/17/19/20/25/24 and secure-delivery contracts.
4. Provider, region, storage, key-management, RPO/RTO and recovery decisions.
5. Independent clinical, privacy, security, accessibility and resilience reviews with retests.
6. Hostinger-equivalent isolated staging with synthetic data.
7. Migration, reconciliation, backup/restore and rollback drills.
8. Representative browser/device/RTL/accessibility/load/failure acceptance.
9. Named operational owners, deputies, coverage, training and runbooks.
10. Explicit Founder production approval supported by immutable evidence.

## 7. Phase-exit decision

**Repository source integration:** authorized subject to exact-head and merge-ref gates.  
**Runtime activation:** blocked.  
**Real patient data:** prohibited.  
**Staging, live and operational completion:** not claimed.
