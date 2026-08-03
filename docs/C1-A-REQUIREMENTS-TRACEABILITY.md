# C1-A Requirements Traceability Matrix

Status values: `Proposed`, `Drafted`, `In Review`, `Accepted`, `Blocked`, `Superseded`.

A document can be `Drafted` while the phase gate remains `Blocked`. Only qualified, named and versioned acceptance evidence may change an external gate to `Accepted`.

| ID | Requirement | Owner | Current evidence | Acceptance evidence still required | Current status |
|---|---|---|---|---|---|
| CF01-A-001 | Preserve CF-01 as a conditional identifier; do not assert permanent file numbering | Founder / Architecture Owner | `CHANGE_CONTROL.md`, repository wording and README | Founder approval remains governing evidence | Accepted |
| CF01-A-002 | No real patient data, clinical attachments, secrets or private runbooks in the public repository | Security Lead / Privacy Officer | `SECURITY.md`, validator, unit tests and CI gate | continuing manual/automated review on every change | Accepted for current repository scope |
| CF01-A-003 | Define one canonical clinical owner without duplicating appointment, message, profile, AI, payment, search or media truth | Architecture Owner | foundation and `C1-A-CROSS-FILE-CONTRACTS.md` ownership matrix | affected companion-owner acceptance | In Review |
| CF01-A-004 | Define complete clinical data flow and trust boundaries | Architecture Owner / Security Lead | `C1-A-FOUNDATION.md` and storage/security architecture | independent architecture review and approved provider flow | In Review |
| CF01-A-005 | Complete clinical threat model including IDOR/BOLA, wrong-patient merge, staff abuse, guardian abuse, attachment leakage, key loss, break-glass misuse and restore resurrection | Security Lead / Clinical Safety Officer | foundation threat register plus independent-review test scope | named owners, independent assessment and retest | In Review |
| CF01-A-006 | Establish server-side actor/object/field/purpose/relationship/consent/guardian/version authorization constitution | Security Lead / Clinical Product Owner | foundation and contract decision rules | C1-B executable decision table and negative tests | In Review |
| CF01-A-007 | Obtain qualified legal/professional applicability review for Pakistan and intended launch jurisdictions | Founder / Qualified Counsel / Records Custodian | applicability register structure | signed qualified jurisdiction decisions, dates and launch gates | Blocked |
| CF01-A-008 | Define retention, legal hold, correction, export, deletion/anonymization and backup-expiry rules by category and jurisdiction | Records Custodian / Privacy Officer | category matrix and lifecycle workflow | approved durations/formulas, jurisdiction sources and owners | Blocked |
| CF01-A-009 | Freeze File 00/02 identity, guardian, suspension, recent-auth and capability assertion contract | File 00/02 Owners | required schema drafted in cross-file baseline | owner-approved versioned schema and consumer tests | Blocked |
| CF01-A-010 | Freeze File 08 appointment/care-context extraction boundary | File 08 Owner / Clinical Product Owner | care-context assertion and ownership limits drafted | current entity inventory, owner approval and extraction criteria | Blocked |
| CF01-A-011 | Freeze doctor professional-verification contract with Files 03/07/09 | Relevant Module Owners | practitioner assertion drafted | accepted action-time schema and negative tests | Blocked |
| CF01-A-012 | Define File 17 clinical-context link without treating message bodies as chart records | File 17 Owner / Clinical Product Owner | opaque-reference contract drafted | File 17 owner approval and privacy tests | Blocked |
| CF01-A-013 | Define File 19 minimal notification contract excluding diagnosis, symptoms, remedy and patient-identifying content | File 19 Owner / Privacy Officer | privacy-minimal payload and prohibited fields drafted | File 19 template/schema approval and leakage tests | Blocked |
| CF01-A-014 | Define File 20/25 private shell, noindex/no-store, RTL and accessibility contracts | File 20/25 Owners | private route/component requirements drafted | owner acceptance and browser/accessibility contract tests | Blocked |
| CF01-A-015 | Define File 24 assurance contract while preserving native CF-01 enforcement | File 24 Owner / Security Lead | assurance manifest and fail-independent rule drafted | File 24 owner approval and outage test | Blocked |
| CF01-A-016 | Define encryption, key management, secure object storage, quarantine, scanning, signed delivery and provider-exit architecture | Security Lead / Infrastructure Owner | `C1-A-CRYPTOGRAPHY-STORAGE-ATTACHMENT-SECURITY.md` | provider/region decision, key recovery proof and independent review | Blocked |
| CF01-A-017 | Define backup, restore, deletion reconciliation, RPO/RTO and ransomware recovery | Infrastructure Owner / Records Custodian | restore/deletion constitution and coverage drafted | BIA-approved RPO/RTO, provider design and restore exercise | Blocked |
| CF01-A-018 | Name operational owners and escalation chain | Founder | role/RACI/escalation constitution drafted; Founder named | all mandatory assignees, deputies, coverage and acceptance | Blocked |
| CF01-A-019 | Link every C1-B+ requirement to owner, test, evidence and release gate before clinical runtime coding | Product Owner / QA Lead | C1-A matrix and independent-review plan | complete C1-B–C1-H RTM/test catalogue | Proposed |
| CF01-A-020 | Record Founder C1-A exit approval before entering C1-B | Founder | approval fields and evidence package requirements defined | dated approval with evidence package version and exact SHA | Blocked |
| CF01-A-021 | Define independent legal, clinical, security, privacy, accessibility and resilience review plan | Founder / Independent Reviewers | `C1-A-INDEPENDENT-REVIEW-PLAN.md` | named assessors, scope approval, dates, completed reports and retests | Drafted |
| CF01-A-022 | Enforce presence and semantic minimums of the public C1-A governance package in CI | QA / Security | repository validator and tests | green CI on current head and every subsequent change | In Review |

## Evidence-package inventory

The current public-safe C1-A package consists of:

- `README.md`;
- `CHANGE_CONTROL.md`;
- `SECURITY.md`;
- `docs/C1-A-FOUNDATION.md`;
- `docs/C1-A-CROSS-FILE-CONTRACTS.md`;
- `docs/C1-A-LEGAL-PROFESSIONAL-APPLICABILITY-REGISTER.md`;
- `docs/C1-A-RETENTION-LEGAL-HOLD-MATRIX.md`;
- `docs/C1-A-CRYPTOGRAPHY-STORAGE-ATTACHMENT-SECURITY.md`;
- `docs/C1-A-OPERATIONAL-OWNERSHIP-AND-ESCALATION.md`;
- `docs/C1-A-INDEPENDENT-REVIEW-PLAN.md`;
- this traceability matrix;
- repository validator, tests and GitHub Actions workflow.

## Release rule

A blocked requirement is not a concealed defect when it represents an explicit external C1-A gate; however, it blocks promotion to C1-B. No blocked item may be silently marked complete from assumptions, generic legal knowledge, an unaccepted draft, provider marketing, a UI mockup or code presence.
