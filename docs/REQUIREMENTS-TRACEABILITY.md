# CF-01 Requirements Traceability

Every requirement below is a release-blocking Must requirement. “Implemented” means code and permanent tests exist; it does not mean staging, legal or production acceptance.

| Requirement | Implementation surfaces | Permanent evidence |
|---|---|---|
| CF01-FR-001 Clinical identity compartment | `CF01_Patients`, encrypted platform link, duplicate quarantine | unit/static/adversarial |
| CF01-FR-002 Treating relationship authority | `CF01_Relationships`, action-time `CF01_Authorization::relationship` | unit/adversarial |
| CF01-FR-003 Minor/guardian controls | patient guardian context, scoped consent/rights representative checks | unit/adversarial |
| CF01-FR-004 Purpose-specific consent | `CF01_Consents`, separate purpose/status/withdrawal/expiry | unit/adversarial |
| CF01-FR-005 Emergency/red-flag boundary | outcome red flags, generic emergency disclaimer, no triage replacement | static/fresh |
| CF01-FR-006 Structured intake and narrative | encounter structured fields plus narrative/provenance | unit/static |
| CF01-FR-007 Homeopathic totality | totality, mental/physical generals, particulars, temperament, miasmatic assessment | static/unit |
| CF01-FR-008 Encounter lifecycle | draft/in-progress/ready/signed/addendum/error; immutable signed core | unit/adversarial |
| CF01-FR-009 Observation and attachment provenance | observation correction chain; C5 secure-media quarantine/scan | unit/adversarial |
| CF01-FR-010 Clinical templates | template key/version on each encounter; addendum template | static/unit |
| CF01-FR-011 Clinical coding/interoperability mapping | owner-controlled structured fields and provenance; no false FHIR compliance claim | static/fresh |
| CF01-FR-012 Clinician-only prescription | professional assertion + active relationship + signed encounter | adversarial |
| CF01-FR-013 Prescription signature | recent step-up, canonical snapshot hash and HMAC signature | unit/adversarial |
| CF01-FR-014 Supersession/discontinuation | explicit immutable replacement and encrypted reason | unit/adversarial |
| CF01-FR-015 Interaction/allergy/safety checks | required warnings/safety status and red-flag boundary; no autonomous decision | unit/static |
| CF01-FR-016 Instructions and language | clinician-entered instructions/language fields | unit/static |
| CF01-FR-017 Follow-up plan | due date, objectives, questionnaire, reminders, expected outcomes | unit |
| CF01-FR-018 Patient-reported outcome | encrypted responses, adherence/change/aggravation/new symptom/adverse event | unit/adversarial |
| CF01-FR-019 Clinician review | pending review; no automatic assessment/prescription change | adversarial/static |
| CF01-FR-020 Longitudinal timeline | federated bounded clinical timeline query | static/fresh |
| CF01-FR-021 Care gaps and reminders | due/overdue reconciliation and privacy-minimal outbox | unit/adversarial |
| CF01-FR-022 Field-level access | role/purpose allowlist and action-time checks | unit/adversarial |
| CF01-FR-023 Access history | append-only access events and patient-scoped query | unit/static |
| CF01-FR-024 Correction and amendment | signed addenda, observation correction and rights-case workflow | unit/adversarial |
| CF01-FR-025 Secure export/transfer | approved rights case, recent auth, one-time short token, opaque provider reference | adversarial |
| CF01-FR-026 Retention and deletion | policy key, holds, verified native purge receipt and backup-expiry evidence hook | migration/adversarial |
| CF01-FR-027 Break-glass governance | verified clinician, explicit reason, step-up, 15-minute minimum view, no export, independent review | adversarial |
| CF01-FR-028 Optimistic concurrency | row versions, If-Match, versioned updates and idempotency receipts | unit/adversarial |
| CF01-FR-029 Offline and weak connection | no local/session/IndexedDB clinical storage; abort/retry; no-store | static/fresh |
| CF01-FR-030 Provider and scanner failure | fail-closed secure media, notification retry/dead-letter, health states | adversarial/fresh |
| CF01-FR-031 Clinical data quality | required fields, provenance, timestamps, signatures, reconciliation | unit/migration |
| CF01-FR-032 Continuity and disaster recovery | disabled-by-default activation, migration lock/cursor, reconciliation, rollback and restore gates | migration/fresh |
