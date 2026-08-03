# C1-A Retention, Legal-Hold and Disposal Matrix

**Status:** Record-category framework established; durations and jurisdictional rules remain unapproved.  
**Release law:** No blanket retention period is authorized. Any `TBD` value blocks C1-B processing for that category.

## 1. Retention constitution

Retention is determined by record category, jurisdiction, patient age/guardian context, care relationship, professional duty, legal/complaint/appeal hold, provider copy and backup lifecycle. A single duration for all clinical data is prohibited.

The retention engine must eventually calculate a review/purge boundary from an approved policy version. It must never silently delete signed clinical truth, silently retain data forever or treat account deletion as automatic destruction of every clinical record.

## 2. Mandatory policy fields

Each category requires:

- stable category ID and canonical owner;
- data class and field scope;
- jurisdiction and policy version;
- retention start event;
- approved duration or decision formula;
- minor-to-adult transition rule;
- relationship-termination effect;
- correction/addendum and entered-in-error behavior;
- legal/professional/safety hold conditions;
- export/transfer consequences;
- canonical, derivative, cache, index, provider and backup disposal rule;
- deletion/anonymization method and verification evidence;
- owner, approver, effective date, review date and rollback/exception route.

## 3. Record-category matrix

| Category ID | Record category | Canonical owner | Retention start event | Duration/formula | Hold/exception rule | Disposal target | Current status |
|---|---|---|---|---|---|---|---|
| CF01-RET-001 | Clinical patient identity link and merge history | CF-01 | relationship closure / identity merge event | TBD by jurisdiction | identity dispute, complaint, legal/professional hold | canonical minimization + mapping tombstone where justified | Blocked |
| CF01-RET-002 | Treating relationship and care-team authority | CF-01 | relationship termination | TBD | continuity, dispute, access investigation | purge/minimize after approved boundary; preserve necessary provenance | Blocked |
| CF01-RET-003 | Consent/directive and withdrawal history | CF-01 | consent expiry/withdrawal or related record closure | TBD per purpose | proof of consent/withdrawal, complaint or publication dispute | retain only justified evidence; purge derivatives | Blocked |
| CF01-RET-004 | Intake/history/totality | CF-01 | encounter/care closure | TBD | clinical continuity and legal/professional hold | secure deletion/anonymization under approved rule | Blocked |
| CF01-RET-005 | Encounter drafts | CF-01 | abandonment/expiry | Short bounded period TBD | investigation of failed signature only if approved | purge draft and transient copies | Blocked |
| CF01-RET-006 | Signed encounter notes and addenda | CF-01 | care closure or last relevant event | TBD | immutable provenance; correction by addendum, not silent overwrite | approved secure deletion/anonymization only when lawful | Blocked |
| CF01-RET-007 | Observations and clinician assessments | CF-01 | linked encounter/care closure | TBD | clinical/legal/professional hold | purge/anonymize under approved policy | Blocked |
| CF01-RET-008 | Prescription/order history, supersession and discontinuation | CF-01 | prescription closure / care closure | TBD | safety, complaint, professional review | approved secure disposal; preserve required provenance | Blocked |
| CF01-RET-009 | Follow-up plans, questionnaires and outcomes | CF-01 | follow-up/care closure | TBD | unresolved safety event or complaint | purge/anonymize under approved policy | Blocked |
| CF01-RET-010 | Clinical attachments and quarantine copies | CF-01/private storage | upload rejection, supersession or care closure | shortest justified category-specific period TBD | consent, clinical necessity, legal hold, malware investigation | canonical + quarantine + thumbnail/derivative/provider purge ledger | Blocked |
| CF01-RET-011 | Correction, access, restriction and export requests | CF-01/privacy workflow | request closure | TBD | appeal, legal hold, proof of fulfillment | minimize package copies; retain justified decision evidence | Blocked |
| CF01-RET-012 | Export packages and signed delivery links | CF-01/private delivery | package creation/download/expiry | very short expiry TBD | active transfer issue only | cryptographic expiry + provider purge + access audit | Blocked |
| CF01-RET-013 | Break-glass grant and review record | CF-01 | grant closure/review completion | TBD security/legal schedule | abuse investigation, complaint or incident hold | retain minimized audit evidence only as approved | Blocked |
| CF01-RET-014 | Clinical audit events | CF-01 | event occurrence | TBD security/legal schedule | incident/legal/professional hold | tamper-evident retention then approved purge/minimization | Blocked |
| CF01-RET-015 | Notification projection/delivery metadata | File 19 | delivery/failure/expiry | File 19 approved bounded schedule | incident/complaint hold | provider and local purge; no clinical narrative | Blocked |
| CF01-RET-016 | Search/cache/projection data | None/general indexing prohibited | source change/revocation | immediate/bounded reconciliation target TBD | no independent hold unless approved evidence | tombstone, cache invalidation and reconciliation proof | Blocked |
| CF01-RET-017 | Backups and restore copies | infrastructure owner | backup creation | approved rotating schedule TBD | legal hold does not justify indefinite full backup by default | cryptographic expiry, media/provider deletion and restore-aware ledger | Blocked |
| CF01-RET-018 | De-identified research/quality dataset | separately approved owner | dataset approval/closure | separate ethics/privacy schedule TBD | research integrity/ethics hold | verified anonymization or dataset purge | Blocked |

## 4. Lifecycle rules

### 4.1 Correction and entered-in-error

Signed records are not silently overwritten. Correction uses an addendum, reason, author, timestamp and link to the original. Entered-in-error status restricts ordinary clinical use while preserving required provenance and showing the status in timeline/export.

### 4.2 Account deletion

A platform-account deletion request must trigger a category-by-category decision. Public profile and membership data may have different owners and rules. CF-01 must return a reasoned result for each category: delete, anonymize, restrict, retain under named exception or place on reviewed hold.

### 4.3 Minor-to-adult transition

The approved jurisdiction policy must determine whether the retention clock, access rights, guardian visibility or consent authority changes when a patient reaches legal adulthood. Transition must be deterministic, notified where lawful and tested at boundary dates.

### 4.4 Relationship termination

Ending a treating relationship revokes future access immediately but does not itself erase historical clinical records. Open prescriptions, follow-ups, safety issues, transfer/export and retention start events must be reconciled.

### 4.5 Legal/professional hold

A hold must be:

- category- and subject-specific;
- based on an approved reason code;
- authorized by a designated role;
- time-bound or periodically reviewed;
- auditable and separable from ordinary access;
- released through dual control where risk requires;
- propagated to affected provider/backup purge schedules without granting broader access.

Unbounded `retain everything` holds are prohibited.

## 5. Deletion and anonymization workflow

Future workflow:

1. determine category, jurisdiction, policy version and current record version;
2. check open care, prescription, safety, complaint, appeal and legal/professional holds;
3. create an immutable disposal decision record without copying clinical narrative;
4. delete/anonymize canonical data as approved;
5. purge or tombstone derivatives, caches, notifications, temporary exports and provider copies;
6. register backup-expiry obligation rather than rewriting immutable backups unsafely;
7. reconcile every target and retry failures;
8. record hashes/counts, not deleted clinical content;
9. notify the authorized requester with a category-level outcome;
10. retain only the minimum disposal evidence required by the approved schedule.

## 6. Backup and restore law

A restore must not resurrect:

- revoked treating access;
- withdrawn consent for future processing;
- expired export links;
- deleted provider tokens;
- purged attachments or stale search/cache entries;
- released/expired break-glass grants.

Every restore test must replay the deletion/hold ledger and reconcile downstream providers before the environment is accepted.

## 7. Acceptance tests

Required future tests include:

- boundary-date calculation and time-zone safety;
- minor-to-adult transition;
- relationship termination before/after retention start;
- legal-hold application, review, release and resumed purge;
- correction/addendum and entered-in-error export presentation;
- canonical, derivative, provider, cache and notification purge;
- backup expiry and restore reconciliation;
- provider outage/retry without false completion;
- no cross-patient deletion;
- idempotent repeated purge requests;
- audit evidence without clinical narrative leakage.

## 8. Current decision

The matrix structure is ready for qualified completion, but all actual durations/formulas remain `TBD`. Therefore CF01-A-008 and clinical processing remain blocked.
