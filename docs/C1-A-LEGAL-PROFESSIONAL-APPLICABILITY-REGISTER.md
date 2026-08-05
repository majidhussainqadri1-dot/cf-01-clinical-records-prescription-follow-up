# C1-A Legal and Professional Applicability Register

**Status:** Register structure established; substantive legal/professional conclusions remain blocked pending qualified review.  
**Important:** This document is not legal advice, a compliance certification or permission to process real clinical data.

## 1. Governing rule

CF-01 may not enter C1-B until qualified reviewers determine, for Pakistan and every intended launch jurisdiction, the applicable requirements for clinical records, teleconsultation, consent, minors/guardians, clinician authority, prescriptions, patient rights, retention, breach duties, cross-border processing and provider use.

No duration, lawful basis, professional privilege, minor-consent exception, breach deadline or prescribing authority may be invented from generic knowledge or copied from another jurisdiction.

## 2. Review roles

| Review role | Required decision scope | Current assignee | Status |
|---|---|---|---|
| Qualified legal counsel | privacy/data protection, contracts, patient rights, breach and cross-border obligations | Unassigned | Blocked |
| Qualified professional/clinical reviewer | record integrity, consultation, prescription, supervision and continuity standards | Unassigned | Blocked |
| Medical Records Custodian | record categories, retention, legal holds, correction/export and disposal operation | Unassigned | Blocked |
| Privacy Officer | purpose, minimization, consent/other basis, rights and processor governance | Unassigned | Blocked |
| Security Lead | security safeguards, incident evidence and independent assessment | Unassigned | Blocked |
| Founder | launch jurisdictions, risk acceptance and final phase authorization | Dr. Allamah Majid Hussain Sabri | Pending evidence |

## 3. Jurisdiction register

Each launch jurisdiction requires a separately approved row. `Unknown` or blank values block processing in that jurisdiction.

| Field | Required entry |
|---|---|
| Jurisdiction ID | Stable code and official name |
| Intended service | Education only, appointment, teleconsultation, record keeping, prescription, follow-up, transfer/export |
| Patient location rule | How location is determined and revalidated |
| Practitioner location and authority | Required status and scope |
| Responsible legal entity/controller | Named entity and address |
| Clinical-record rule source | Exact instrument/professional standard and effective date |
| Data-protection/privacy rule source | Exact instrument, scope and effective date |
| Minor/guardian rule | Age, guardian proof, assent, exceptions and revocation |
| Telehealth/remote-care rule | Permitted scope, disclosures, limitations and emergency direction |
| Prescription rule | Who may prescribe, what may be recorded/transmitted, signatures and restrictions |
| Consent/other legal basis | Per purpose: care, images, recording, transfer, research, educational publication |
| Patient rights | Access, correction/addendum, export, restriction, objection and deletion/anonymization limits |
| Retention source | Category-specific rule, start event, duration, extension and review date |
| Breach/security duty | Responsible party, assessment, notification and evidence requirements |
| Cross-border/provider rule | Regions, contracts, transfer safeguards and exit obligations |
| Audit/record integrity | Signature, amendment, provenance, access logging and admissibility expectations |
| Required notices | Patient, guardian, practitioner and public claims |
| Prohibited claims/features | Certification claims, automated diagnosis/prescription, emergency replacement or other limits |
| Reviewer names/qualifications | Verifiable details |
| Decision date and expiry/review date | Dates and re-review trigger |
| Evidence location/hash | Private approved evidence reference; no sensitive evidence in public repository |
| Launch decision | `Allowed`, `Allowed with conditions`, `Education-only`, `Blocked` |

## 4. Applicability questions

Qualified review must answer at minimum:

1. Which entity is responsible for each processing purpose and provider relationship?
2. What professional status is required to create, sign, prescribe, amend, supervise and transfer a record?
3. Does an appointment create any clinical relationship, or is a separate treating-relationship act required?
4. Which consent purposes must be separate, and when may consent be withdrawn without rewriting historical truth?
5. How are legal minors, guardians, assent, confidential-care exceptions and guardian changes handled?
6. Which record categories require immutable retention, addendum rather than deletion, or legal/professional hold?
7. Which patient rights apply, who decides exceptions, and what response evidence is required?
8. What is required for secure export, transfer to another practitioner and recipient confirmation?
9. Which data may be hosted or accessed outside the jurisdiction, under what provider contracts and safeguards?
10. What security, incident, breach-assessment and notification duties apply?
11. What limitations apply to remote consultation, emergency presentation and patient location uncertainty?
12. What prescription data may be recorded or communicated, and what is explicitly outside CF-01 scope?
13. Which records may be used for education/research/public cases, and what independent consent/anonymization review is required?
14. Which marketing or compliance claims are prohibited without independent evidence?
15. Which changes in law, provider, service scope or jurisdiction force re-review?

## 5. Service-mode gates

| Service mode | Default C1-A decision | Evidence needed to change decision |
|---|---|---|
| Public educational reading | Outside clinical-record runtime; governed by public content owners | Existing platform publication rules |
| Appointment scheduling | File 08 owner; not CF-01 clinical authority | Accepted File 08 contract |
| Secure intake before consultation | Blocked | Jurisdiction, consent, identity, retention and clinician workflow approval |
| Teleconsultation charting | Blocked | Practitioner/jurisdiction authority and professional workflow approval |
| Clinician-entered prescription record | Blocked | Qualified prescribing/recording review and clinician-only controls |
| Patient follow-up questionnaire | Blocked | Care relationship, consent, red-flag and response-duty approval |
| Record export/transfer | Blocked | Rights, recipient, encryption, expiry and audit approval |
| Break-glass access | Blocked | Lawful/professional basis, strict reason list and retrospective review approval |
| Research/education reuse | Blocked by default | Separate purpose, consent/other basis, minimization and ethics/publication approval |
| Cross-border provider processing | Blocked | Provider/region/contract/transfer and exit approval |

## 6. Evidence handling

Legal opinions, identity evidence, contracts, incident procedures and other sensitive material must remain in an approved private evidence location. The public repository stores only:

- evidence identifier and hash;
- reviewer role/qualification summary;
- decision, scope and effective/review dates;
- public-safe conditions and blockers;
- Founder approval reference.

## 7. Re-review triggers

Re-review is mandatory when any of these changes:

- launch country/region or patient/practitioner location model;
- legal entity or provider/subprocessor;
- service from education to consultation/prescription/follow-up;
- record category, retention or export method;
- minors/guardian workflow;
- AI, automated classification or decision support scope;
- encryption/storage region or breach process;
- material law/professional standard or qualified-review expiry.

## 8. Current decision

No jurisdiction has yet been entered as `Allowed` or `Allowed with conditions`. Therefore:

- clinical runtime remains unauthorized;
- no real patient data may be processed;
- no compliance/certification claim may be made;
- C1-B remains blocked pending qualified evidence and Founder approval.
