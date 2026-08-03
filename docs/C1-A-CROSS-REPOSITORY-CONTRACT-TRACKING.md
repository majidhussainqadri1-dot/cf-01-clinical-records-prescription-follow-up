# C1-A Cross-Repository Contract Tracking Register

**Status:** Provider issues opened; no contract is accepted or frozen yet.  
**Parent exit gate:** [CF-01 issue #2](https://github.com/majidhussainqadri1-dot/cf-01-clinical-records-prescription-follow-up/issues/2)  
**Draft contract baseline:** `docs/C1-A-CROSS-FILE-CONTRACTS.md`

## 1. Contract issue register

| C1-A requirement | Native owner / repository | Tracking issue | Required outcome | Current status |
|---|---|---|---|---|
| CF01-A-009 | File 00 — Membership Core | [Issue #12](https://github.com/majidhussainqadri1-dot/00-sabri-membership-core/issues/12) | versioned membership, age/guardian, suspension, capability and identity-assurance assertion | Open — not accepted |
| CF01-A-009 | File 02 — Authentication | [Issue #3](https://github.com/majidhussainqadri1-dot/02-sabri-authentication/issues/3) | recent-authentication, step-up and session-assurance assertion | Open — not accepted |
| CF01-A-010 | File 08 — Clinic/Appointments | [Issue #4](https://github.com/majidhussainqadri1-dot/08-worldwide-clinic-and-appointments-foundation/issues/4) | care-context assertion, current clinical-data inventory and controlled extraction/cutover boundary | Open — not accepted |
| CF01-A-011 | File 09 coordinating Files 03/07/09 | [Issue #3](https://github.com/majidhussainqadri1-dot/09-global-doctor-onboarding-and-verification-completion/issues/3) | practitioner eligibility, scope, restrictions and action-time verification assertion | Open — not accepted |
| CF01-A-012 | File 17 — Network/Messages | [Issue #4](https://github.com/majidhussainqadri1-dot/17-sabri-network/issues/4) | opaque clinical-context reference with no automatic message-body/call/attachment copying | Open — not accepted |
| CF01-A-013 | File 19 — Notifications | [Issue #3](https://github.com/majidhussainqadri1-dot/19-sabri-unified-notifications/issues/3) | privacy-minimal notification request with no clinical narrative or bearer authorization | Open — not accepted |
| CF01-A-014 | File 20 — Unified Shell | [Issue #7](https://github.com/majidhussainqadri1-dot/20-sabri-unified-application-shell/issues/7) | private route mounting, noindex/no-store, safe links and degraded states | Open — not accepted |
| CF01-A-014 | File 25 — Visual Components | [Issue #3](https://github.com/majidhussainqadri1-dot/25-sabri-public-ui-profile-timeline-visual-experience/issues/3) | private clinical component, RTL, accessibility and visual-state contract | Open — not accepted |
| CF01-A-015 | File 24 — Security/Privacy Assurance | [Issue #8](https://github.com/majidhussainqadri1-dot/24-sabri-platform-security-privacy-compliance-and-resilience-center/issues/8) | privacy-minimal assurance manifest with native CF-01 enforcement preserved | Open — not accepted |

## 2. Freeze criteria for every contract

A tracking issue is not complete merely because a schema or code exists. Closure requires:

1. canonical owner and named responsible reviewer;
2. versioned schema/DTO/assertion/query/event contract;
3. mandatory/optional fields, semantics and privacy classification;
4. current-state, record-version, issued-at/expiry and safe reason codes;
5. unknown/incompatible/stale/dependency-outage fail behavior;
6. producer and CF-01 consumer fixtures;
7. negative authorization, leakage, replay, concurrency and outage tests;
8. migration, compatibility/deprecation window and rollback behavior;
9. privacy/security and relevant clinical/professional review;
10. Founder-approved change-control reference.

## 3. Cross-contract invariants

- Availability never grants authorization.
- UI role/badge/cache/index/projection never overrides native truth.
- Every protected action revalidates current identity, suspension, guardian, practitioner, relationship, consent, purpose, field and record version.
- Events are past-tense facts, not commands or credentials.
- No raw clinical narrative, attachment, identity evidence or bearer credential enters cross-module events/notifications/assurance.
- A provider outage produces explicit `unknown`/degraded state and never permissive fallback.
- Companion contracts do not create a second clinical system of record.

## 4. Review cadence

On each contract change:

1. update the native-owner issue;
2. update this register and CF01-A traceability;
3. run producer and consumer contract tests;
4. perform review → fix → fresh/adversarial review → fix → retest;
5. attach public-safe evidence and private evidence references;
6. keep CF01-A-020 blocked until every mandatory provider contract is accepted.

## 5. Current decision

All nine provider issues are open. Therefore Files 00/02, 03/07/09, 08, 17, 19, 20/25 and 24 contracts remain **drafted but not frozen**, and C1-B runtime remains unauthorized.
