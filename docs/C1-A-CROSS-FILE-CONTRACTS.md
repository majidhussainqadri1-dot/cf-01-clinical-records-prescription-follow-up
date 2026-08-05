# C1-A Cross-File Contract Freeze Baseline

**Status:** Drafted for owner review; not yet frozen or accepted by companion-module owners.  
**Phase law:** This document defines contract boundaries only. It does not authorize clinical runtime, patient tables, routes, real-data processing or installation.

## 1. Contract constitution

Every CF-01 integration must use a versioned command, query, assertion or past-tense event. Direct reads or writes to another module's tables, post meta, options, files or provider records are prohibited.

Every contract response must carry, where applicable:

- `contract_version` and producer version;
- canonical subject/object UUID and source owner;
- current state and record version;
- actor, purpose and relationship context;
- consent/guardian/verification/suspension assertions;
- issued-at, expiry and correlation/trace identifiers;
- explicit `allow`, `deny` or `unknown` result with safe reason code;
- data-class and field allowlist;
- deprecation and compatibility window.

Availability detection is not authorization. Unknown version, missing mandatory field, stale assertion, failed dependency or ambiguous ownership must fail closed for sensitive writes and protected reads.

## 2. Canonical ownership matrix

| File / domain | Native owner remains responsible for | CF-01 may consume | CF-01 must never do |
|---|---|---|---|
| File 00 — Membership Core | membership legitimacy, account class, age/guardian, suspension, capabilities and identity assurance | current versioned assertions | create alternate membership truth, infer authority from role label or cache stale claims |
| File 02 — Authentication | login, recovery, provider linking and session-entry surfaces | recent-authentication/step-up result and session assurance | treat login as clinical authorization or store provider credentials |
| Files 03/07/09 — Profiles, doctors and verification | public/professional identity, licensing/verification and eligibility decisions | verified practitioner assertion and current professional restrictions | approve doctors, publish credentials or maintain duplicate professional records |
| File 08 — Clinic and appointments | appointment, clinic, location and scheduling truth | bounded care-context reference and extraction inventory | treat appointment alone as indefinite chart access or duplicate scheduling |
| File 17 — Communication Network | messages, calls, threads, reports and communication evidence | opaque clinical-context reference where expressly approved | copy message bodies into chart automatically or use chat membership as care authority |
| File 19 — Notifications | delivery, preferences, retries, templates and device/channel state | privacy-minimal notification request and delivery status | include symptoms, diagnosis, remedy, full patient identity or clinical attachment in transport payload |
| File 20 — Unified Shell | global shell, route mounting and navigation | private route placement and degraded-state shell contract | create a second shell, header, navigation or permissive fallback |
| File 25 — Public UI / components | visual components, RTL, accessibility and responsive presentation | private clinical component contract only | expose chart data publicly, own clinical state or create profile timeline copies |
| File 24 — Security Assurance | cross-platform assurance, evidence registry and posture | assurance manifest, findings and evidence receipt | replace CF-01 native authorization, audit, encryption, break-glass or retention enforcement |
| File 21 — Public content | public case/educational publication and corrections | separately consented, anonymized publication handoff reference | convert chart records into public content or infer publication consent from treatment consent |
| File 15 — Radar / research | repertory study and research objects | explicit clinician-selected reference | convert saved study into patient chart or prescription automatically |
| File 16 — AI | educational/retrieval assistance within approved limits | source-linked suggestion reference where approved | autonomous diagnosis, prescription, potency/dose or emergency replacement |
| CF-03 / payment owner | billing, refund and financial ledger | bounded payment-status reference if later approved | own card/payment data or make unconfirmed financial decisions |

## 3. Required assertion contracts

### 3.1 Membership and authentication assertion

Producer: Files 00/02. Minimum fields:

- subject platform UUID;
- account class and active/suspended state;
- verified age/jurisdiction context;
- guardian status, relationship, scope and expiry where applicable;
- current capabilities relevant to the requested clinical action;
- identity assurance level;
- recent-authentication/step-up timestamp and method class;
- contract and record versions.

Acceptance rule: every protected CF-01 action re-fetches this assertion server-side. A cached UI state never grants access.

### 3.2 Practitioner eligibility assertion

Producer: Files 03/07/09. Minimum fields:

- practitioner UUID;
- current verified status and effective dates;
- permitted professional scope/context;
- restrictions, suspension or expiry;
- source decision identifier and version;
- action-time validity.

Acceptance rule: practitioner verification is revalidated for signing, prescribing, break-glass and export approval.

### 3.3 Care-context assertion

Producer: File 08 or later approved care-relationship owner. Minimum fields:

- patient and practitioner opaque UUIDs;
- clinic/location reference;
- relationship source, purpose, scope, start/end and status;
- authorizer and version;
- whether the relationship is sufficient for the requested action.

Acceptance rule: an appointment is evidence of scheduling only unless the owner explicitly asserts an active treating relationship.

### 3.4 Communication-context reference

Producer: File 17. Minimum fields:

- opaque conversation/thread reference;
- participants and relationship class without message body;
- consented link purpose;
- visibility/retention class;
- non-authorizing owner reference or destination intent resolved only after click-time authentication and authorization.

A stored reference or generated link is never proof of clinical access. Bearer authorization embedded in a URL, event or notification is prohibited unless a later narrowly scoped, independently reviewed transfer/export contract expressly authorizes it.

Acceptance rule: CF-01 may store only the reference and clinician-authored chart summary; message bodies remain with File 17.

### 3.5 Notification request

Consumer: File 19. Payload must be privacy-minimal:

- recipient UUID;
- template/event key;
- generic action category;
- opaque, non-authorizing destination reference;
- urgency and expiry;
- correlation and deduplication keys.

File 19 or the shell may construct the final same-origin route, but every click must authenticate and reauthorize against current native state. A notification payload or URL must not function as a durable bearer credential.

Prohibited payload: patient name where avoidable, diagnosis, symptom text, remedy, potency, dose, clinical note, attachment name/content, guardian detail, break-glass reason, session credential, signed attachment URL or reusable bearer token.

### 3.6 Shell and component contract

Producers: Files 20/25. Requirements:

- authenticated, private, `noindex`, `no-store` route context;
- no clinical data in page title, URL slug, browser history label or analytics payload;
- keyboard, screen-reader, 200%/400% zoom, RTL and mobile support;
- explicit loading, timeout, denied, stale, offline and dependency-degraded states;
- same-origin safe links and click-time authorization recheck.

### 3.7 Assurance manifest

Producer/consumer: File 24. Minimum evidence fields:

- CF-01 control identifier and version;
- evidence type, timestamp, environment and source commit;
- test/review result and severity;
- owner, expiry/review date and immutable evidence reference;
- no raw clinical content.

Acceptance rule: File 24 outage must not disable native CF-01 enforcement; posture becomes `unknown`, never falsely green.

## 4. Command and query boundary

CF-01 canonical commands will eventually include patient-link creation, treating-relationship activation, consent capture/withdrawal, encounter draft/sign/addendum, prescription issue/supersede/discontinue, follow-up record, correction/export request, retention hold and break-glass access. These commands remain names-only during C1-A.

Queries must return versioned, purpose-filtered DTOs rather than internal schema. Field-level denial must remove the field rather than merely hide it in the interface.

## 5. Event law

Events are past-tense facts, not commands or authorization. Representative future events:

- `ClinicalPatientLinked`;
- `TreatingRelationshipActivated` / `TreatingRelationshipEnded`;
- `ClinicalConsentGranted` / `ClinicalConsentWithdrawn`;
- `EncounterSigned` / `EncounterAddendumRecorded`;
- `PrescriptionIssued` / `PrescriptionSuperseded` / `PrescriptionDiscontinued`;
- `FollowUpRecorded`;
- `ClinicalExportPrepared` / `ClinicalExportExpired`;
- `ClinicalRetentionHoldApplied` / `ClinicalRetentionPurgeCompleted`;
- `ClinicalBreakGlassOpened` / `ClinicalBreakGlassClosed`.

Events must be idempotent, privacy-minimized, replay-safe and processed through outbox/inbox or an equivalent reliable mechanism. Raw clinical narrative, attachment content and bearer credentials are prohibited in event payloads.

## 6. Contract acceptance tests

Each owner contract must prove:

1. incompatible/missing version fails closed;
2. stale suspension, guardian, consent or professional state is rejected at action time;
3. unauthorized object existence is not leaked;
4. duplicate/replayed mutation does not duplicate clinical truth;
5. dependency outage produces explicit degraded state without permissive fallback;
6. termination/revocation immediately affects the next protected action;
7. events contain no prohibited clinical fields or bearer credentials;
8. cache/index/projection cannot override native owner truth;
9. cross-patient and cross-clinic fixtures remain isolated;
10. rollback restores compatibility without resurrecting revoked access.

## 7. Freeze gate

This baseline becomes a frozen C1-A contract package only after every affected owner records:

- accepted contract version and schema;
- named owner and compatibility window;
- consumer/provider tests;
- failure/degradation behavior;
- migration and rollback impact;
- privacy/security review;
- Founder-approved change-control reference.

Until then, all companion contracts remain **drafted but blocked from C1-B runtime use**.
