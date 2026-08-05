# C1-B to C1-H Implementation Traceability and Test Catalogue

**Status:** Implementation blueprint only; every runtime phase remains blocked until C1-A exit approval.  
**Source scope:** CF01-FR-001 through CF01-FR-032, approved state machines, migrations, security/privacy, accessibility, resilience and Definition of Done.

## 1. Phase constitution

No phase may begin merely because a prior document exists. Entry requires:

- preceding phase accepted;
- no unresolved critical/high defect;
- applicable legal/professional and contract gates accepted;
- requirements, owner, data classes, migrations, tests and rollback frozen;
- branch/change-control record and exact baseline SHA;
- Founder authorization for the phase.

Every coding batch must undergo review → fix → fresh/adversarial review → fix → retest. `Specified`, `Coded`, `Packaged`, `Automated-QA Green`, `Staging-Accepted`, `Live-Deployed` and `Operational` remain separate statuses.

## 2. Global test dimensions

Every relevant requirement is tested across:

- guest, patient, verified guardian, treating doctor, assistant, supervisor, records/privacy, break-glass clinician, security/auditor and unauthorized actor;
- adult/minor, guardian change/revocation, jurisdiction and confidential-care exception where approved;
- own/cross-patient, own/cross-clinic and active/ended/suspended relationship;
- current/stale/missing contract and record version;
- duplicate/replay/concurrent mutation;
- dependency healthy/degraded/unavailable/recovered;
- web/mobile, LTR/RTL, keyboard, screen reader, zoom and low bandwidth;
- fresh install, upgrade, migration, restore and rollback;
- normal, boundary, negative, abuse and fault-injection paths.

## 3. C1-B — Identity, treating relationship, consent and authorization

### 3.1 Scope

| Requirement | Implementation outcome | Primary owner | Required automated evidence |
|---|---|---|---|
| CF01-FR-001 | separate clinical UUID linked minimally to platform UUID; guarded match/merge/quarantine | Clinical Product / Data Owner | duplicate, mismatch, merge-race and cross-patient isolation tests |
| CF01-FR-002 | versioned treating-relationship authority with purpose/scope/start/end/status | Clinical Product / File 08 owner | before/after termination, appointment-only denial, alternate-clinic denial |
| CF01-FR-003 | jurisdiction-aware guardian scope, expiry, revocation and assent context | Privacy / File 00 owner | guardian change/revoke, adult transition, cross-ward and public-leakage tests |
| CF01-FR-004 | separate versioned consent purposes and withdrawal propagation | Privacy / Records | bundled-consent rejection, notice-version and future-processing withdrawal tests |
| CF01-FR-005 | emergency/red-flag boundary with approved local direction; no autonomous diagnosis | Clinical Safety | red-flag fixtures, non-emergency false-positive review and no-auto-treatment tests |
| CF01-FR-022 foundation | field/object/purpose authorization engine and safe DTO filtering | Security / Clinical Product | role-field-state matrix, IDOR/BOLA, excessive-property and existence-leakage tests |
| Cross-file contracts | File 00/02, 03/07/09 and 08 assertions accepted and revalidated action-time | Contract owners | incompatible/missing/stale assertion and provider-outage tests |

### 3.2 State machines

- treating relationship: `Proposed → Identity/Consent Pending → Active → Restricted/Suspended → Ended → Archived`;
- consent: `Draft Notice → Presented → Granted/Declined → Withdrawn/Expired → Reconsented`;
- identity link/merge: proposed future states must include match confidence, manual review, quarantined conflict and irreversible-merge dual control.

### 3.3 C1-B acceptance gate

- zero cross-patient/cross-clinic exposure in automated and adversarial fixtures;
- guardian and suspension changes effective on next protected action;
- appointment alone never grants indefinite chart access;
- no permissive fallback on File 00/02/08 outage or unknown contract;
- no clinical runtime route accessible without authenticated private-shell contract;
- migration/rollback preserves identity and relationship truth;
- Founder, Privacy, Security and Clinical Product acceptance.

## 4. C1-C — Intake, encounters, observations, attachments and signatures

### 4.1 Scope

| Requirement | Implementation outcome | Primary owner | Required automated evidence |
|---|---|---|---|
| CF01-FR-006 | structured intake/history with narrative, provenance and incomplete-draft state | Treating Lead | conditional validation, provenance and unsigned-draft tests |
| CF01-FR-007 | clinician-entered homeopathic totality; no automatic prescription | Treating Lead | author/version visibility and no-auto-prescription tests |
| CF01-FR-008 | encounter lifecycle, immutable signed core, addendum and entered-in-error | Clinical Product / Records | sign/addendum/error tombstone, edit-after-sign denial and restore verification |
| CF01-FR-009 | observation and attachment provenance through encrypted quarantine/scanning | Security / Infrastructure | MIME/magic/polyglot/malware/wrong-patient/relink tests |
| CF01-FR-010 | versioned templates without silent wording replacement | Clinical Product | historical rendering and deprecated-template tests |
| CF01-FR-011 | optional interoperability mappings with local canonical meaning | Data/Interop owner | unknown-code, terminology-version and declared-profile round-trip tests |
| CF01-FR-028 | optimistic concurrency on every clinical write | Data/QA | two-actor race, stale version and incompatible merge tests |
| CF01-FR-029 | no C5 offline cache by default; safe network-loss recovery | Security / UX | shared-device, browser storage, logout/revoke and interrupted-draft tests |
| CF01-FR-030 attachment part | scanner/key/storage/provider failure states and quarantine retry | Infrastructure / Security | fault injection with no unscanned delivery or false success |
| CF01-FR-031 | validation, duplicate/impossible-date detection and correction provenance | Data Quality owner | seeded-error and authorized correction tests |

### 4.2 State machines

- encounter: `Draft → In Progress → Ready to Sign → Signed → Addended / Entered in Error`;
- attachment: `Upload Initiated → Streaming Intake → Quarantined → Validated → Scanned → Available → Superseded/Rejected → Expired/Purged`;
- every transition carries actor, source/target state, expected version, reason, policy/template version, idempotency and audit.

### 4.3 C1-C acceptance gate

- signed core cannot be edited in place;
- no attachment becomes available before every configured gate;
- durable plaintext quarantine prohibited;
- concurrent writes never lose clinical text/observation;
- no C5 content in browser cache, public media library, general search or logs;
- keyboard, screen-reader, RTL, zoom, weak-network and session-expiry acceptance;
- key/scanner/storage failure and recovery retested;
- Clinical, Security, Privacy, Records, Accessibility and QA acceptance.

## 5. C1-D — Prescriptions, supersession, instructions and safety limits

### 5.1 Scope

| Requirement | Implementation outcome | Primary owner | Required automated evidence |
|---|---|---|---|
| CF01-FR-012 | verified assigned clinician-only remedy/potency/form/dose/frequency/instruction entry | Treating Lead / Security | patient/support/AI/Radar denial; suspended/expired clinician denial |
| CF01-FR-013 | recent-auth, identity/credential, record-version and immutable prescription signature | Treating Lead / Security | replay, stale/concurrent signature and restore-validation tests |
| CF01-FR-014 | new version supersedes/discontinues prior; deterministic active state | Clinical Product | old instruction inactive, effective-time and deep-link status tests |
| CF01-FR-015 | versioned warning/safety checks that inform but do not autonomously decide | Clinical Safety | unavailable-source, override-reason, stale-rule and no-hidden-change tests |
| CF01-FR-016 | human-reviewed accessible multilingual patient instructions | Clinical / Localization | Urdu/English/RTL, ambiguous abbreviation and meaning-preservation tests |

### 5.2 State machine

`Draft → Validated → Signed/Active → Superseded/Discontinued/Expired → Archived`

Only an authorized clinician may move prescription states. No edit-in-place after signature. AI/Radar suggestions, if later approved, remain labeled references and cannot execute a transition.

### 5.3 C1-D acceptance gate

- clinician-only authority proven at action time;
- prescription signature remains verifiable after backup restore;
- exactly one deterministic current active version per policy;
- old/superseded instructions cannot be presented as current;
- warning failure never yields a false “safe” claim;
- no autonomous diagnosis, remedy, potency/dose or emergency replacement;
- clinical safety and adversarial professional review accepted.

## 6. C1-E — Follow-up, patient-reported outcomes and longitudinal timeline

### 6.1 Scope

| Requirement | Implementation outcome | Primary owner | Required automated evidence |
|---|---|---|---|
| CF01-FR-017 | due window, method, questionnaire, owner, goals, red flags and preference | Clinical Product | time-zone/DST/reschedule/cancel/overdue deterministic tests |
| CF01-FR-018 | patient-reported change/aggravation/new symptoms/adherence/adverse event labeled pending review | Clinical Safety | no auto-treatment change and approved urgent-escalation tests |
| CF01-FR-019 | clinician review and signed continue/change/stop/next-plan decision | Treating Lead | stale response, concurrent review and provenance tests |
| CF01-FR-020 | canonical-reference longitudinal timeline with field filtering | Clinical Product / UX | stable pagination, restricted-field and patient-view tests |
| CF01-FR-021 | deterministic, consented reminders and quiet hours without manipulation | Clinical Product / File 19 | opt-out, quiet-hours, dedupe, outage and false-delivery tests |
| Cross-reference: CF01-FR-030 notification part | applies the canonical C1-C provider-failure requirement to notification retry/reconciliation | File 19 / Infrastructure | queued/recovered/no-duplicate delivery tests |

### 6.2 State machine

`Planned → Due → Patient Submitted → Clinician Review → Completed → Rescheduled/Cancelled/Overdue`

Patient submission never mutates diagnosis/prescription automatically. Notification state is derivative and cannot delete or complete the follow-up plan.

### 6.3 C1-E acceptance gate

- time-zone/DST and boundary calculations deterministic;
- urgent/red-flag route follows approved policy without delayed AI/support substitute;
- timeline copies no canonical clinical truth;
- reminder failure is visible and does not claim delivery;
- notification payload contains no prohibited clinical narrative/bearer credential;
- low-bandwidth, mobile, RTL and accessibility journeys accepted.

## 7. C1-F — Patient portal, rights, access history, export and break-glass

### 7.1 Scope

| Requirement | Implementation outcome | Primary owner | Required automated evidence |
|---|---|---|---|
| Cross-reference: CF01-FR-022 completion | completes the canonical C1-B authorization requirement with minimum-necessary role/state/purpose views | Security / Privacy | complete matrix and API property-filter tests |
| CF01-FR-023 | privacy-approved patient access history with actor category/purpose/time/break-glass indicator | Privacy / Security | completeness/reconciliation and secret-masking tests |
| CF01-FR-024 | correction, disagreement note, addendum or reasoned refusal; original preserved | Records / Clinical | no-silent-rewrite, timeline/export and appeal-route tests |
| CF01-FR-025 | structured export/transfer, manifest/hashes, recent auth and expiring delivery | Records / Security | cross-patient, replay, bulk/rate, expiry and recipient tests |
| CF01-FR-026 | approved category/jurisdiction retention, holds and provider/backup disposal ledger | Records / Privacy | boundary, adult transition, hold release and provider-purge tests |
| CF01-FR-027 | clinician-only break-glass with allowed reason, step-up, minimum fields, TTL, no export and review | Security / Clinical Safety / Privacy | admin/support denial, abuse/repeat alerts, expiry and retrospective-review tests |

### 7.2 State machines

- rights request: `Received → Identity Verified → Scoped → Clinical/Legal Review → Fulfilled/Partly Fulfilled/Refused → Appealed → Closed`;
- break-glass: `Requested → Step-Up Verified → Granted (TTL) → Used → Expired/Revoked → Retrospective Review → Closed`;
- export: future states must cover requested, approved/refused, building, ready, downloaded, expired, revoked and purged.

### 7.3 C1-F acceptance gate

- patient sees only approved own data and access-history detail;
- support/security/admin cannot browse chart or use break-glass;
- export has no cross-patient data and expires irreversibly;
- correction/addendum/entered-in-error status reproduced in timeline/export;
- legal/professional schedules and rights decisions have qualified approval;
- break-glass abuse path alerts and suspends as approved;
- Privacy, Records, Clinical Safety, Security and legal/professional acceptance.

## 8. C1-G — Controlled extraction from File 08, dual reads and reconciliation

### 8.1 Entry conditions

- File 08 schema/entity/route inventory complete;
- canonical ownership mapping accepted for every field/object;
- no unidentified duplicate clinical store;
- migration and rollback plan approved;
- synthetic and approved staging datasets available;
- C1-B–C1-F functionality accepted before cutover.

### 8.2 Migration sequence

1. inventory and classify legacy records without mutation;
2. map canonical IDs, owners, versions, consent/guardian/relationship and attachments;
3. dry-run with validation, rejects and reason codes;
4. idempotent backfill into disabled CF-01 target;
5. dual-read/shadow comparison with no user-visible authority change;
6. reconcile counts, hashes, signatures, relationships, retention/holds and attachment states;
7. bounded dual-write only if explicitly approved and protected against split-brain;
8. cutover reads, then writes, through feature gates;
9. monitor divergence and rollback window;
10. make legacy clinical writes read-only only after acceptance;
11. preserve redirects/references and complete decommission plan.

### 8.3 Migration test catalogue

- repeated dry-run/backfill produces no duplicates;
- wrong/ambiguous patient mappings quarantine;
- signed provenance and prescription state preserved;
- ended/suspended relationships do not regain access;
- missing/corrupt attachment remains quarantined with explicit status;
- deletion/hold/consent state survives migration and restore;
- rollback restores old authority without losing new accepted writes or creating split-brain;
- zero unexplained divergence at approved cutover threshold.

### 8.4 C1-G acceptance gate

- reconciliation report signed by Data, Records, Privacy, Clinical, Security and QA owners;
- rollback drill successful;
- no duplicate canonical owner or writable legacy clinical truth;
- Founder approves controlled cutover.

## 9. C1-H — Load, resilience, restore, penetration testing, clinical UAT and rollout

### 9.1 Scope

| Requirement/domain | Acceptance evidence |
|---|---|
| CF01-FR-032 | BIA-approved RPO/RTO; isolated full restore; counts/hashes/signatures/access/holds verified; no deleted record resurrection |
| Performance/load | approved concurrent users, chart sizes, attachments, timelines, exports and queues; p95/p99 targets by journey |
| Reliability | dependency outage, retry/backoff, dead-letter, reconciliation and explicit degraded states |
| Security | independent authenticated/unauthenticated penetration test and retest; no unresolved critical/high |
| Privacy | data-flow, logging, notification, export, provider and deletion/backup review |
| Clinical UAT | treating doctors and clinical supervisors complete representative safe workflows |
| Patient/guardian UAT | consent, questionnaire, correction/export, access history and accessibility acceptance |
| Accessibility/global | keyboard, screen reader, 200%/400%, RTL, mobile, low bandwidth and reduced motion |
| Release engineering | reproducible package, manifest, checksum, SBOM/dependencies, migration, rollback and clean-extract evidence |
| Operations | monitoring, alerting, staffing, incident/support, backup/restore cadence, training and SLO ownership |

### 9.2 Rollout law

- staging first with synthetic data and approved provider sandboxes;
- restricted pilot only after all staging gates;
- feature flags default closed and scoped by jurisdiction/clinic/service mode;
- monitored canary with explicit halt/rollback thresholds;
- no emergency, AI-autonomous or unapproved cross-border capability;
- post-deployment smoke, audit, backup and deletion-reconciliation checks;
- expanded rollout only after Founder and domain-owner sign-off.

### 9.3 C1-H acceptance gate

- independent reports and retests accepted;
- full restore and rollback rehearsals accepted;
- clinical/patient/accessibility UAT accepted;
- operational owners/deputies/coverage/training active;
- zero known unresolved critical/high defects;
- exact release artifact and production decision signed by Founder;
- post-release monitoring window and rollback authority active.

## 10. Definition of Done

CF-01 is not complete until all applicable conditions are evidenced:

1. canonical owner, boundaries and contracts frozen;
2. all CF01-FR-001–032 requirements implemented and traced;
3. identity, guardian, consent, relationship and field authorization proven;
4. signed records/prescriptions immutable with safe addenda/supersession;
5. follow-up, rights, export, retention and break-glass workflows accepted;
6. attachments encrypted, quarantined, scanned and purpose-authorized;
7. legal/professional/jurisdiction decisions approved;
8. privacy, security, key/recovery, backup/restore and deletion reconciliation proven;
9. migrations/cutover/rollback and decommissioning proven;
10. automated, contract, adversarial, accessibility, load, penetration and UAT evidence green;
11. reproducible package, exact SHA, manifest, checksum, dependency inventory and release notes available;
12. staging, live deployment and operational readiness separately accepted;
13. no unresolved critical/high defect or concealed known risk;
14. Founder final approval recorded.

## 11. Current status

This traceability catalogue completes an internal C1-A planning deliverable only. All C1-B–C1-H runtime statuses remain `Blocked — C1-A exit not approved`.
