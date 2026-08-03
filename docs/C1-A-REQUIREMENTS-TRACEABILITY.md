# C1-A Requirements Traceability Matrix

Status values: `Proposed`, `In Review`, `Accepted`, `Blocked`, `Superseded`.

| ID | Requirement | Owner | Acceptance evidence | Current status |
|---|---|---|---|---|
| CF01-A-001 | Preserve CF-01 as a conditional identifier; do not assert permanent file numbering | Founder / Architecture Owner | change-control record and repository wording audit | Accepted |
| CF01-A-002 | No real patient data, clinical attachments, secrets or private runbooks in the public repository | Security Lead / Privacy Officer | automated repository validation + manual review | In Review |
| CF01-A-003 | Define one canonical clinical owner without duplicating appointment, message, profile, AI, payment, search or media truth | Architecture Owner | ownership matrix reviewed by affected module owners | In Review |
| CF01-A-004 | Define complete clinical data flow and trust boundaries | Architecture Owner / Security Lead | approved diagram, entry/exit points and data classes | In Review |
| CF01-A-005 | Complete clinical threat model including IDOR/BOLA, wrong-patient merge, staff abuse, guardian abuse, attachment leakage, key loss, break-glass misuse and restore resurrection | Security Lead / Clinical Safety Officer | threat register with mitigations, tests and residual risk | In Review |
| CF01-A-006 | Establish server-side actor/object/field/purpose/relationship/consent/guardian/version authorization constitution | Security Lead / Clinical Product Owner | authorization decision table and negative test design | In Review |
| CF01-A-007 | Obtain qualified legal/professional applicability review for Pakistan and intended launch jurisdictions | Founder / Qualified Counsel / Records Custodian | signed applicability register with dates and launch gates | Blocked |
| CF01-A-008 | Define retention, legal hold, correction, export, deletion/anonymization and backup-expiry rules by category and jurisdiction | Records Custodian / Privacy Officer | reviewed retention schedule; no invented blanket duration | Blocked |
| CF01-A-009 | Freeze File 00/02 identity, guardian, suspension, recent-auth and capability assertion contract | File 00/02 Owners | versioned schema and consumer contract tests | Blocked |
| CF01-A-010 | Freeze File 08 appointment/care-context extraction boundary | File 08 Owner / Clinical Product Owner | entity inventory, ownership map and extraction criteria | Blocked |
| CF01-A-011 | Freeze doctor professional-verification contract with Files 03/07/09 | Relevant Module Owners | current verification assertions and action-time recheck contract | Blocked |
| CF01-A-012 | Define File 17 clinical-context link without treating message bodies as chart records | File 17 Owner / Clinical Product Owner | versioned reference contract and privacy test cases | Blocked |
| CF01-A-013 | Define File 19 minimal notification contract excluding diagnosis, symptoms, remedy and patient-identifying content | File 19 Owner / Privacy Officer | template schema and negative disclosure tests | Blocked |
| CF01-A-014 | Define File 20/25 private shell, noindex/no-store, RTL and accessibility contracts | File 20/25 Owners | route placement and component contract acceptance | Blocked |
| CF01-A-015 | Define File 24 assurance contract while preserving native CF-01 enforcement | File 24 Owner / Security Lead | assurance manifest and fail-independent test design | Blocked |
| CF01-A-016 | Define encryption, key management, secure object storage, quarantine, scanning, signed delivery and provider-exit architecture | Security Lead / Infrastructure Owner | architecture decision records and independent review | Blocked |
| CF01-A-017 | Define backup, restore, deletion reconciliation, RPO/RTO and ransomware recovery | Infrastructure Owner / Records Custodian | BIA, restore test plan and reconciliation design | Blocked |
| CF01-A-018 | Name operational owners and escalation chain | Founder | accepted responsibility matrix | Blocked |
| CF01-A-019 | Link every C1-B+ requirement to owner, test, evidence and release gate before clinical runtime coding | Product Owner / QA Lead | complete RTM and test catalogue | Proposed |
| CF01-A-020 | Record Founder C1-A exit approval before entering C1-B | Founder | dated approval with evidence package version/SHA | Blocked |

## Release rule

A blocked requirement is not a defect when it represents an external C1-A gate; however, it blocks promotion to C1-B. No blocked item may be silently marked complete from assumptions, generic legal knowledge, a UI mockup or code presence.
