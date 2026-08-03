# C1-A Evidence Manifest

**Manifest version:** 1.0  
**Repository:** `majidhussainqadri1-dot/cf-01-clinical-records-prescription-follow-up`  
**Branch:** `codex/cf-01-c1-a-foundation`  
**Validated baseline head:** `7f2e7a76ea4a2b05be2e9a4bde8ed904c5899342`  
**Validated pull-request merge ref:** `b0d8ab9f166f030b8ea374ed41bbe05fdd69ba2f`  
**Validation run:** GitHub Actions `30816797726`  
**Validation job:** `91696432332`  
**Result:** 14/14 unit tests passed; public C1-A repository validation passed.

## 1. Truthful status

| Status class | Current evidence-backed state |
|---|---|
| Specified | Governing CF-01 master plan and public C1-A architecture/governance package exist |
| C1-A documentation/governance code | Present on controlled branch and reviewed in repeated correction cycles |
| Automated QA | Green for the public-repository and traceability scope stated in this manifest |
| Qualified legal/professional accepted | No |
| Companion contracts frozen | No |
| Clinical runtime coded | No; prohibited before C1-A exit |
| Installable package | No |
| Staging-Accepted | No |
| Live-Deployed | No |
| Operational | No |

Automated QA proves only the defined public-safe repository controls and document-traceability checks. It does not prove legal compliance, clinical correctness, production security or runtime completion.

## 2. Governing and control evidence

| Evidence | Purpose | Current status |
|---|---|---|
| `README.md` | canonical scope, boundaries, activation law, phases and truthful state | Reviewed baseline |
| `CHANGE_CONTROL.md` | C1-A authorization, prohibited runtime and change law | Reviewed baseline |
| `SECURITY.md` | public-repository security/disclosure and sensitive-artifact rules | Reviewed baseline |
| `.github/workflows/governance.yml` | automated test and repository-policy execution | Green on validated baseline |
| `.gitignore` | generated/local sensitive-pattern exclusions | Present |

## 3. Architecture and governance evidence

| Evidence | Coverage | Current status |
|---|---|---|
| `docs/C1-A-FOUNDATION.md` | architecture, trust boundaries, data flow, roles, authorization, states and threat model | In review; external acceptance pending |
| `docs/C1-A-CROSS-FILE-CONTRACTS.md` | owner boundaries and versioned assertion/event/query/command baseline | Drafted; companion-owner acceptance blocked |
| `docs/C1-A-LEGAL-PROFESSIONAL-APPLICABILITY-REGISTER.md` | jurisdiction/service-mode qualified-review structure | Structure complete; qualified decisions blocked |
| `docs/C1-A-RETENTION-LEGAL-HOLD-MATRIX.md` | category lifecycle, hold, disposal, provider and backup reconciliation | Structure complete; durations/formulas blocked |
| `docs/C1-A-CRYPTOGRAPHY-STORAGE-ATTACHMENT-SECURITY.md` | keys, encryption, quarantine, scanning, delivery, recovery and provider exit | Architecture proposed; provider/proof blocked |
| `docs/C1-A-OPERATIONAL-OWNERSHIP-AND-ESCALATION.md` | RACI, separation of duties, severity, coverage and escalation | Structure complete; assignees/deputies blocked |
| `docs/C1-A-INDEPENDENT-REVIEW-PLAN.md` | legal, clinical, security, privacy, accessibility and resilience assessment | Plan drafted; assessors/reports blocked |
| `docs/C1-A-REQUIREMENTS-TRACEABILITY.md` | C1-A requirements, owners, evidence and blocker state | Reconciled |
| `docs/C1-B-TO-C1-H-IMPLEMENTATION-TRACEABILITY.md` | CF01-FR-001–032 mapping, tests, migration, rollout and DoD | Internally specified; all runtime phases blocked |

## 4. Automated control evidence

| Evidence | Control purpose | Validated result |
|---|---|---|
| `tools/validate_repository.py` | blocks sensitive/runtime/binary/indirect artifacts; requires governance package and semantic/phase coverage | Passed |
| `tests/test_validate_repository.py` | adversarial fixtures for policy and traceability failures | 14/14 passed |

The validator currently proves, within its stated scope:

- required governance files and semantic markers are present;
- CF01-FR-001 through CF01-FR-032 are mapped in future-phase tables;
- all C1-B through C1-H phase headings exist exactly once;
- secrets/private-key patterns, database dumps and prohibited binary/data artifacts are rejected;
- PHP/browser runtime files and extensionless PHP/Node-style runtime are rejected during C1-A;
- symlinks, Git LFS indirection and submodules are rejected;
- sensitive path variants and composite names are rejected;
- Python is limited to governance tools/tests;
- repository source validates without generated-bytecode false positives.

## 5. Review-and-correction lineage

### Foundation review cycle

- corrected secret-scanner self-detection;
- blocked nested runtime, binary/office/archive/media artifacts and symlinks;
- corrected generated Python bytecode ordering behavior.

### Governance batch review cycle

- blocked extensionless runtime and punctuation/composite sensitive-path variants;
- removed bearer deep-link authorization ambiguity;
- prohibited durable plaintext quarantine;
- blocked Git LFS and submodule indirection.

### Future-phase traceability review cycle

- corrected weak requirement coverage based on mere identifier presence;
- required every CF01-FR-001–032 identifier to appear as a phase-table mapping;
- required exactly one C1-B through C1-H phase heading;
- added negative tests for missing requirement mapping and missing phase heading.

## 6. Remaining C1-A exit blockers

The following are deliberately not claimed complete:

1. qualified Pakistan and target-jurisdiction legal/professional decisions;
2. approved category-specific retention durations/formulas and hold rules;
3. File 00/02, 03/07/09, 08, 17, 19, 20/25 and 24 owner-frozen contracts and tests;
4. provider/region/key/storage selection and private architecture evidence;
5. BIA-approved RPO/RTO, key recovery, backup/restore and ransomware exercises;
6. named operational owners, deputies, coverage, training and conflicts review;
7. named independent assessors, engagement execution, reports and retests;
8. executable C1-B–C1-H test IDs and runtime implementation evidence;
9. Founder approval of the final versioned C1-A package and explicit authorization to enter C1-B.

## 7. Phase-exit decision

**C1-A exit status:** `BLOCKED`  
**C1-B runtime authorization:** `NOT GRANTED`  
**Reason:** external qualified evidence, companion-owner acceptance, operational assignments and Founder phase-exit approval remain incomplete.

## 8. Founder approval record

| Field | Value |
|---|---|
| Evidence package version | Pending final C1-A package approval |
| Approved exact SHA | Pending |
| Decision | Pending |
| Conditions / time-bound risk acceptance | Pending |
| Approval date/time | Pending |
| Founder signature/reference | Pending |

No blank field in this section may be interpreted as approval.
