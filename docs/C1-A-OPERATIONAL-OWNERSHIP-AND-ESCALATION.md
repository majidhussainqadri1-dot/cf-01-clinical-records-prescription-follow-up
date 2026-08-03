# C1-A Operational Ownership and Escalation Constitution

**Status:** Responsibilities and separation-of-duties model defined; operational assignees remain incomplete.  
**Phase law:** No clinical runtime may begin until mandatory roles, deputies, coverage and escalation acceptance are recorded.

## 1. Governance objective

CF-01 must not depend on an unnamed “administrator.” Clinical, privacy, security, records, infrastructure and support decisions require distinct accountable owners. Founder authority governs strategic approval but does not silently replace clinical confidentiality, least privilege, audit or separation of duties.

## 2. Mandatory roles

| Role | Accountable duties | Must not do alone | Primary assignee | Deputy | Status |
|---|---|---|---|---|---|
| Founder / Executive Sponsor | scope, jurisdictions, funding, risk acceptance, phase and release approval | routine unrestricted clinical access, conceal known defect | Dr. Allamah Majid Hussain Sabri | TBD | Partially assigned |
| Clinical Product Owner | requirements, canonical ownership, workflow acceptance and change control | approve own high-risk exception without review | TBD | TBD | Blocked |
| Treating-Doctor Lead | clinical workflow, record quality, prescription/follow-up safety and UAT | override identity/privacy/security controls | TBD | TBD | Blocked |
| Clinical Safety Officer | red flags, emergency boundaries, adverse/unexpected-event escalation and safety review | provide ordinary patient care through incident role | TBD | TBD | Blocked |
| Medical Records Custodian | retention, legal/professional holds, correction, export, transfer and disposal | grant unrestricted clinical access or destroy held records alone | TBD | TBD | Blocked |
| Privacy Officer | purpose, consent/rights, minors/guardians, data minimization and provider privacy review | act as sole incident commander and evidence approver | TBD | TBD | Blocked |
| Security Lead | threat model, access control, keys, logging, vulnerability and incident security | approve and execute own unrestricted break-glass/key recovery | TBD | TBD | Blocked |
| Infrastructure / Reliability Owner | environments, storage, backup, restore, monitoring, provider health and exit | access clinical content beyond operationally necessary fields | TBD | TBD | Blocked |
| QA / Release Lead | traceability, automated/adversarial/staging evidence and release gate | waive failed critical/high tests unilaterally | TBD | TBD | Blocked |
| Clinical Support Lead | patient-facing support, access/correction routing and continuity communication | impersonate user, view blanket chart or issue clinical decision | TBD | TBD | Blocked |
| Independent Reviewer / Assessor | independent security/privacy and control assessment | implement the controls being independently certified | TBD/external | TBD | Blocked |

## 3. RACI decision matrix

Legend: `A` accountable, `R` responsible, `C` consulted, `I` informed. Named people replace role labels before operational approval.

| Decision / action | Founder | Clinical Product | Treating Lead | Safety | Records Custodian | Privacy | Security | Infrastructure | QA/Release |
|---|---|---|---|---|---|---|---|---|---|
| Enter C1-B | A | R | C | C | C | C | C | C | R |
| Add launch jurisdiction/service mode | A | R | C | C | C | A/C | C | C | C |
| Approve clinical workflow | I | A/R | A/R | C | C | C | C | I | C |
| Change prescription/follow-up rule | I | A | R | A/C | C | C | C | I | C |
| Approve retention category/duration | I | C | C | C | A/R | A/C | C | C | C |
| Apply/release high-risk legal hold | I | I | C | C | A/R | A/C | C | I | I |
| Approve provider/region | A | C | I | I | C | A/C | A/C | R | C |
| Key recovery/rotation | I/A for exceptional case | I | I | I | C | C | A | R | C |
| Break-glass policy | I | A | R | A | C | A/C | A/C | I | C |
| Review individual break-glass use | I | C | C | A/R | C | A/C | A/C | I | I |
| Declare clinical safety incident | I | C | R | A | C | C | C | I | I |
| Declare security/privacy incident | I | I | C | C | C | A/C | A/R | R | I |
| Restore production clinical data | I | C | C | C | A/C | C | A/C | R | R/C |
| Approve production release | A | A/C | C | C | C | C | C | C | R |
| Accept residual critical/high risk | A only with documented advice | C | C | C | C | C | C | C | C |

## 4. Separation-of-duties rules

The following combinations require distinct people or explicit dual approval:

- requester, reviewer and final approver of a professional/clinical privilege;
- key-recovery requester, approver and executor;
- legal-hold requester and destructive disposal executor;
- break-glass user and retrospective reviewer;
- code author and final independent security assessor;
- backup operator and restore acceptance approver;
- incident subject and incident evidence reviewer;
- export requester and unrestricted bulk-export approver.

Support, analytics, ordinary administrator and infrastructure roles receive no blanket clinical chart access.

## 5. Access provisioning lifecycle

Future lifecycle:

`Requested → Manager/Owner Review → Identity and Eligibility Verified → Training Confirmed → Least-Privilege Grant → Step-Up Enrolled → Active → Periodic Review → Suspended/Revoked → Evidence Retained`

Every grant requires:

- named person and employment/contract context;
- exact capabilities and data fields;
- purpose, clinic/jurisdiction and expiry;
- training and confidentiality acceptance;
- recent authentication/MFA requirement;
- approver and version;
- audit and periodic review date.

Role change, suspension, relationship end or training expiry must affect the next protected action.

## 6. Coverage and escalation data

Each operational role must record privately:

- primary and deputy contacts;
- coverage hours/time zone;
- maximum acknowledgment/escalation times by severity;
- emergency fallback and authority limit;
- secure communication channel;
- conflict-of-interest declaration;
- handover and absence procedure;
- training and review expiry.

The public repository may hold only role names, status and public-safe escalation rules—not personal contact details or private incident channels.

## 7. Severity model

| Severity | Representative condition | Immediate action | Required leadership |
|---|---|---|---|
| SEV-1 | cross-patient exposure, key compromise, destructive corruption, unsafe prescription propagation, widespread unavailable care record | contain/disable affected capability, preserve evidence, invoke incident and continuity plans | Security/Privacy/Clinical Safety + Founder informed |
| SEV-2 | bounded unauthorized access, failed break-glass control, material attachment leak, restore/deletion divergence | restrict affected function, investigate, reconcile, notify duties assessed | domain owner + Security/Privacy/Records |
| SEV-3 | degraded provider, delayed follow-up notification, bounded data-quality or accessibility defect without known harm | explicit degraded state, queue/retry, corrective release | product/infrastructure/QA owner |
| SEV-4 | documentation, cosmetic or low-risk operational defect | normal tracked correction | relevant owner |

Severity is based on impact and evidence, not reputation management. Known critical/high defects block release unless Founder accepts a lawful, time-bound, fully documented residual risk after qualified advice.

## 8. Escalation paths

### 8.1 Clinical safety

Treating clinician/operator → Treating-Doctor Lead → Clinical Safety Officer → Clinical Product Owner → Founder where strategic/risk decision is required.

No automated system may close an emergency/safety escalation or issue autonomous treatment.

### 8.2 Privacy and patient rights

Support/intake → Privacy Officer and Records Custodian → qualified legal/professional reviewer where needed → Founder for unresolved strategic risk.

Support must not promise deletion, disclosure or correction before the native decision is confirmed.

### 8.3 Security

Detector/operator → Security Lead → Privacy Officer/Clinical Safety Officer according to impact → Infrastructure Owner → Founder and qualified notification decision-makers.

### 8.4 Availability and recovery

Monitoring/operator → Infrastructure Owner → Security/Records/Clinical Product/QA acceptance roles → Founder for prolonged outage or production rollback decision.

## 9. Training gates

Before access, each role must complete approved training appropriate to duties:

- confidentiality and minimum necessary access;
- patient identity and wrong-patient prevention;
- consent, guardian and jurisdiction context;
- immutable notes, addenda and entered-in-error handling;
- prescription/follow-up safety boundaries;
- attachments, phishing/malware and secure export;
- break-glass and incident reporting;
- support non-impersonation and no-secret/no-OTP handling;
- backup/restore and deletion reconciliation where applicable.

Training evidence must have version and expiry/review date.

## 10. Operational readiness checklist

C1-A exit requires:

- [ ] all mandatory primary assignees named and accepted;
- [ ] deputies and coverage recorded;
- [ ] conflicts and separation-of-duties checked;
- [ ] access request/review/revocation workflow approved;
- [ ] severity and escalation times approved;
- [ ] private contact/runbook location established;
- [ ] training curriculum and evidence process approved;
- [ ] incident, break-glass, key recovery, export and restore tabletop exercises scheduled;
- [ ] Founder records final C1-A responsibility acceptance.

## 11. Current decision

Only the Founder role is presently named. Operational roles, deputies and coverage are not yet established; CF01-A-018 and C1-B remain blocked.
