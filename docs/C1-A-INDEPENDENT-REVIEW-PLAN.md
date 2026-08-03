# C1-A Independent Review Plan

**Status:** Review scope and independence criteria defined; assessor engagement and execution remain blocked.  
**Purpose:** Establish the evidence required before CF-01 can enter runtime implementation or later staging.

## 1. Independence constitution

An independent review is not satisfied by the author re-reading the same code, an automated scanner alone, a provider marketing report or a general WordPress security check. The assessor must be sufficiently independent from the implementation and operational approval being evaluated.

Potential conflicts, prior implementation work, financial dependence, scope restrictions and unavailable evidence must be disclosed. The Founder decides acceptance only after receiving qualified advice; no review may certify absolute security or legal compliance.

## 2. Required review streams

| Stream | Minimum reviewer competence | C1-A deliverable | Later execution gate |
|---|---|---|---|
| Legal/privacy applicability | qualified counsel/privacy professional for target jurisdictions | reviewed applicability register and launch restrictions | before any real-data jurisdiction is enabled |
| Clinical/professional safety | qualified clinical/professional reviewer | workflow, record integrity, prescription/follow-up and emergency-boundary review | before C1-D and clinical UAT |
| Architecture/threat model | senior application/security architect | data-flow, trust-boundary, key/storage and abuse-case review | before C1-B implementation |
| Application/API security | independent penetration tester | authenticated/unauthenticated object, field, function and workflow testing | before staging acceptance |
| Cryptography/key management | qualified cryptography/cloud-security reviewer | key hierarchy, rotation, recovery, separation and failure analysis | before production key activation |
| Privacy engineering | independent privacy/security reviewer | minimization, consent/guardian, rights, logging and provider-flow assessment | before patient portal/export |
| Accessibility/RTL usability | qualified accessibility tester and representative users | WCAG-oriented keyboard/screen-reader/zoom/RTL findings | before staging acceptance |
| Resilience/recovery | independent reliability/recovery reviewer | backup, restore, deletion reconciliation, ransomware and provider-exit assessment | before production release |

## 3. Evidence package supplied to reviewers

Public-safe documents:

- governing CF-01 master plan reference;
- change-control record;
- C1-A foundation, data-flow and threat model;
- cross-file contract baseline;
- legal/professional applicability register;
- retention/legal-hold matrix;
- cryptography/storage/attachment architecture;
- operational ownership and escalation constitution;
- requirements traceability matrix;
- automated repository-safety tests and CI evidence.

Private evidence, when it exists:

- provider contracts and regions;
- detailed diagrams, configurations and secrets-handling procedures;
- source code, dependency/SBOM and deployment manifest;
- test identities and synthetic fixtures;
- vulnerability reports, incident/recovery runbooks and legal opinions;
- staging endpoints and authorized credentials.

Real patient data is not required for independent testing and must not be supplied unless separately lawful, necessary and approved. Synthetic/adversarial fixtures are the default.

## 4. Mandatory threat scenarios

Independent testing must include, as applicable:

- IDOR/BOLA across patients, clinics, guardians and practitioners;
- field-level leakage through DTOs, errors, logs, exports and notifications;
- stale role, suspension, consent, guardian and treating-relationship state;
- wrong-patient link/merge and identifier collision;
- CSRF, replay, duplicate submission and optimistic-concurrency loss;
- clinician/support/admin privilege confusion;
- break-glass abuse, repeated use and no-review paths;
- attachment path, MIME, polyglot, parser, metadata, malware and signed-link attacks;
- key outage, rotation, revocation, recovery and provider compromise;
- cache, search, analytics and backup resurrection after deletion/revocation;
- export enumeration, expiry bypass and recipient confusion;
- race conditions around sign/addendum/prescription supersession;
- degraded dependency and permissive fallback;
- mobile/shared-device/browser-storage leakage;
- RTL/accessibility paths that conceal warnings or block safe action;
- resource exhaustion, queue duplication and restore divergence.

## 5. Finding schema

Every finding must include:

- stable finding ID;
- severity and exploitability/impact rationale;
- affected requirement, component, environment and version/SHA;
- preconditions and reproducible steps;
- evidence location without unnecessary clinical content;
- recommended mitigation and compensating control;
- owner and target date;
- retest result, reviewer and date;
- residual risk and approval/closure status.

Severity cannot be reduced solely because the repository is small or the feature is not yet public.

## 6. Review-and-fix law

For every review stream:

1. reviewer records findings;
2. owner corrects every accepted defect;
3. automated and manual regression tests run;
4. the reviewer or an appropriately independent reviewer retests;
5. a fresh/adversarial second pass searches for bypasses and regressions;
6. evidence package and traceability are updated;
7. release remains blocked while unresolved critical/high findings exist, unless a lawful, time-bound Founder risk acceptance is recorded after qualified advice.

## 7. Rules of engagement for later penetration testing

The engagement must define:

- authorized repository, commit, environments and dates;
- permitted accounts/roles and synthetic data;
- prohibited destructive actions and emergency contacts;
- rate/resource limits and backup readiness;
- evidence encryption and retention/destruction;
- immediate escalation for active exposure;
- no testing against live patients or unrelated infrastructure;
- final report, retest and disclosure handling.

## 8. Acceptance outputs

C1-A independent-review planning is accepted when:

- reviewer competencies and independence criteria are approved;
- scope maps to CF01-A requirements and threat register;
- private/public evidence locations are established;
- rules of engagement and finding schema are approved;
- assessor(s), dates and funding are assigned;
- Founder records review-plan approval.

Actual security/privacy/clinical acceptance requires completed reports and retests; this plan alone is not evidence that controls work.

## 9. Current decision

The review plan is defined, but no independent assessor, engagement date or completed report exists. Independent acceptance and C1-B promotion remain blocked.
