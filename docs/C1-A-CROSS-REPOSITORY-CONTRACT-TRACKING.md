# C1-A Cross-Repository Contract Tracking Register

**Status:** File 00 membership, File 02 authentication/reauthentication, File 08 care-context and File 09 practitioner-eligibility candidates are implemented and exact-head CI-green; no contract is accepted, frozen or merged yet.  
**Parent exit gate:** [CF-01 issue #2](https://github.com/majidhussainqadri1-dot/cf-01-clinical-records-prescription-follow-up/issues/2)  
**Draft contract baseline:** `docs/C1-A-CROSS-FILE-CONTRACTS.md`

## 1. Contract issue register

| C1-A requirement | Native owner / repository | Tracking issue | Required outcome | Current status |
|---|---|---|---|---|
| CF01-A-009 | File 00 — Membership Core | [Issue #12](https://github.com/majidhussainqadri1-dot/00-sabri-membership-core/issues/12) | versioned membership, age/guardian, suspension, capability and identity-assurance assertion | Candidate implemented and CI-green in stacked Draft PR #13; unmerged and not accepted |
| CF01-A-009 | File 02 — Authentication | [Issue #3](https://github.com/majidhussainqadri1-dot/02-sabri-authentication/issues/3) | recent-authentication, step-up, session-assurance and File 09 professional-reauthentication assertions | Both authentication contracts implemented and CI-green in Draft PR #4; depend on File 00 PR #13; unmerged and not accepted |
| CF01-A-010 | File 08 — Clinic/Appointments | [Issue #4](https://github.com/majidhussainqadri1-dot/08-worldwide-clinic-and-appointments-foundation/issues/4) | care-context assertion, current clinical-data inventory and controlled extraction/cutover boundary | Candidate implemented and CI-green in stacked Draft PR #5; base PR #3 and dependencies unmerged; not accepted |
| CF01-A-011 | File 09 coordinating Files 03/07/09 | [Issue #3](https://github.com/majidhussainqadri1-dot/09-global-doctor-onboarding-and-verification-completion/issues/3) | practitioner eligibility, evidence currency, scope, restrictions, jurisdiction and action-time verification assertion | Candidate implemented and CI-green in stacked Draft PR #4; base PR #2 and File 00/02 dependencies unmerged; structured restriction ownership and acceptance pending |
| CF01-A-012 | File 17 — Network/Messages | [Issue #4](https://github.com/majidhussainqadri1-dot/17-sabri-network/issues/4) | opaque clinical-context reference with no automatic message-body/call/attachment copying | Open — not implemented or accepted |
| CF01-A-013 | File 19 — Notifications | [Issue #3](https://github.com/majidhussainqadri1-dot/19-sabri-unified-notifications/issues/3) | privacy-minimal notification request with no clinical narrative or bearer authorization | Open — not implemented or accepted |
| CF01-A-014 | File 20 — Unified Shell | [Issue #7](https://github.com/majidhussainqadri1-dot/20-sabri-unified-application-shell/issues/7) | private route mounting, noindex/no-store, safe links and degraded states | Open — not implemented or accepted |
| CF01-A-014 | File 25 — Visual Components | [Issue #3](https://github.com/majidhussainqadri1-dot/25-sabri-public-ui-profile-timeline-visual-experience/issues/3) | private clinical component, RTL, accessibility and visual-state contract | Open — not implemented or accepted |
| CF01-A-015 | File 24 — Security/Privacy Assurance | [Issue #8](https://github.com/majidhussainqadri1-dot/24-sabri-platform-security-privacy-compliance-and-resilience-center/issues/8) | privacy-minimal assurance manifest with native CF-01 enforcement preserved | Open — not implemented or accepted |

## 2. File 00 provider evidence

- Native repository: `majidhussainqadri1-dot/00-sabri-membership-core`.
- Candidate release: File 00 `1.2.7`.
- Contracts consumed by this chain: general membership contract `1.1.2` and `smc.cf01.membership-assurance` `1.0.0`.
- Draft PR: [File 00 PR #13](https://github.com/majidhussainqadri1-dot/00-sabri-membership-core/pull/13).
- Base dependency: File 00 Draft PR #11 / branch `codex/file00-ilhami-cycle-1.2.6-final`.
- Exact candidate head: `0434d79e65eeca336833f102ad03c1453f2205dd`.
- GitHub Actions run: `30828349841` — success on PHP 7.4 and PHP 8.3.
- Provider checks: 16 static + 13 runtime, zero failures.
- Master-plan traceability: 24/24.
- Deterministic package: `00-sabri-membership-core-1.2.7.zip`.
- Package SHA-256: `2383aa9dcf79ddad9da29ec7bbbd01e62d62185ae0fe900979b955d461c8cdb9`.
- Package verification: 16 entries; zero unsafe entries, symlinks, manifest mismatches or CRC failures.
- Truthful status: implementation and automated QA evidence exist; provider-owner acceptance, merge and staging validation do not.

## 3. File 02 authentication and professional-reauthentication evidence

- Native repository: `majidhussainqadri1-dot/02-sabri-authentication`.
- Candidate release: File 02 `0.3.0`.
- Contracts: `sa.cf01.authentication-assurance` `1.0.0` and `sa.professional-reauthentication` `1.0.0`.
- Draft PR: [File 02 PR #4](https://github.com/majidhussainqadri1-dot/02-sabri-authentication/pull/4).
- Provider dependency: File 00 `1.2.7` / PR #13.
- Exact candidate head: `65775bbe2e76fe30b5775e5af94e3ad42337bf25`.
- GitHub Actions run: `30843867602` — success on PHP 7.4 and PHP 8.3.
- PHP lint coverage: 26 files.
- CF-01 authentication-assurance checks: 14/14.
- Professional-reauthentication checks: 12/12.
- Professional receipt law: current password verified only inside File 02; receipt bound to subject, session token, client fingerprint and opaque File 09 review scope; underlying AAL2 subject/scope revalidated on every read.
- Architecture and private-File-00-metadata/TOTP prohibition: passed.
- Authentication assurance explicitly does not grant professional or clinical authorization.
- Truthful status: implementation and automated QA evidence exist; File 00 merge, consumer acceptance, Google sandbox, Hostinger staging and privacy/security acceptance do not.

## 4. File 08 care-context and extraction evidence

- Native repository: `majidhussainqadri1-dot/08-worldwide-clinic-and-appointments-foundation`.
- Candidate release: File 08 `0.2.2`.
- Contract: `swc.cf01.care-context` `1.0.0`.
- Draft PR: [File 08 PR #5](https://github.com/majidhussainqadri1-dot/08-worldwide-clinic-and-appointments-foundation/pull/5).
- Base dependency: File 08 Draft PR #3 / branch `fix/file-08-corrective-completion`.
- Provider dependencies: File 00 `1.2.7` / PR #13 and File 09 practitioner contract PR #4.
- Exact candidate head: `0997adc8808e112547a28aea3e337c63d2b8efd9`.
- GitHub Actions run: `30830508463` — success on PHP 7.4 and PHP 8.3.
- Inherited checks: 29 corrective + 15 doctor-authority + 20 public-projection; zero failures.
- CF-01 care-context checks: 22 static + 22 runtime/adversarial; zero failures.
- Prohibited clinical/contact leakage: zero.
- Contract law: appointment is scheduling-only and never asserts treating relationship, clinical read/write, prescription, break-glass or clinical/publication consent.
- Current mixed/clinical-like fields inventoried: `_swc_reason`, `_swc_concern_duration`, `_swc_doctor_private_note`, `_swc_patient_message` and narrative audit fields.
- No migration, target chart, signed encounter or prescription creation is performed.
- Deterministic package candidate: 16 governed runtime files, 179,815 bytes.
- Inner plugin ZIP SHA-256: `6a942d18946199c298fb46f76dd804df9e82a36ba8ff397856bef64058d47f36`.
- Workflow artifact ID: `8862715111`; artifact SHA-256: `2ec362ab7e1eff6e2980ebcd554a2575096056fbeeec567c091f19784ceb4730`.
- Manifest truth: staging accepted `false`; production accepted `false`.
- Truthful status: implementation, inventory and automated QA evidence exist; base/dependency merge, native-owner acceptance, current staging-data inventory, extraction rehearsal and staging validation do not.

## 5. File 09 practitioner-eligibility evidence

- Native repository: `majidhussainqadri1-dot/09-global-doctor-onboarding-and-verification-completion`.
- Candidate release: File 09 `1.1.1`.
- Contract: `gdo.cf01.practitioner-eligibility` `1.0.0`.
- Draft PR: [File 09 PR #4](https://github.com/majidhussainqadri1-dot/09-global-doctor-onboarding-and-verification-completion/pull/4).
- Base dependency: File 09 corrective Draft PR #2 / branch `audit/file-09-source-review`.
- Provider dependencies: File 00 general contract `1.1.2`, File 00 CF-01 contract `1.0.0`, and File 02 professional-reauthentication `1.0.0`.
- Exact candidate head: `6ec7ebcc765000a47df71d41b1676a362358de33`.
- GitHub Actions run: `30845577484` — success on PHP 7.4 and PHP 8.3.
- PHP lint coverage: 25 files.
- Inherited security and architecture/canonical-owner guards: passed.
- Non-circular File 00 membership-adapter checks: 13/13.
- CF-01 static practitioner-contract checks: 35/35.
- CF-01 runtime/adversarial practitioner checks: 21/21.
- Private File 00 metadata, TOTP and recovery reads inside File 09: zero.
- File 09-local password verification: zero; authentication delegated to File 02.
- Decision evidence includes current application generation, mutable `row_version`, `checked_at`, verification validity and independently recomputed approved-snapshot fingerprint.
- Identity, qualification and license evidence must be accepted/current; license requires an explicit future validity date.
- Country names and supported ISO aliases are normalized before jurisdiction comparison.
- Assertion excludes license number, credential documents, evidence HMACs, contact data, reviewer notes and clinical data.
- Contract always states `grants_clinical_authorization: false`; appointment/badge never creates clinical authority.
- `prescription_sign` and `break_glass` return `unknown` until structured professional restriction ownership is independently modeled and accepted.
- Truthful status: implementation and automated QA evidence exist; base/dependency merge, Files 03/07 field reconciliation, structured restriction ownership, native-owner acceptance, merged-version consumer tests, legal/professional/privacy/security and staging validation do not.

## 6. Ordered dependency law

1. Resolve and merge the File 00 base PR #11 after its own acceptance gates.
2. Rebase or retarget File 00 PR #13 to `main`, rerun exact-head QA, then obtain native-owner acceptance for both general `1.1.2` and CF-01 `1.0.0` contracts.
3. Revalidate File 02 PR #4 against the merged File 00 contracts and obtain native-owner acceptance for both authentication contracts.
4. Resolve and merge File 09 base PR #2, then rebase/retarget File 09 PR #4 against the accepted File 00/02 contracts and rerun exact-head QA.
5. Reconcile Files 03/07 public/profile/directory fields with File 09 without creating alternate professional authority; assign and freeze structured professional restriction ownership.
6. Resolve and merge File 08 base PR #3, then rebase/retarget File 08 PR #5 against accepted File 00/File 09 contracts and rerun exact-head QA.
7. Add CF-01 consumer fixtures and cross-repository integration tests against immutable merged File 00/02/08/09 versions.
8. Complete File 08 current staging-data/write-path inventory, extraction dry run, reconciliation, rollback rehearsal and Hostinger-equivalent staging.
9. Perform provider sandbox, privacy/security/clinical/professional review and Founder change control.
10. Only then may CF01-A-009, CF01-A-010 or CF01-A-011 be proposed for `Accepted`; successful isolated CI alone is insufficient.

## 7. Freeze criteria for every contract

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

## 8. Cross-contract invariants

- Availability never grants authorization.
- UI role/badge/cache/index/projection never overrides native truth.
- Authentication assurance never grants clinical object, field, purpose, relationship, consent, guardian, practitioner or record-version authority.
- Professional eligibility never grants authentication, treating relationship, patient consent, chart/field access, prescription content or break-glass authority.
- Appointment existence, acceptance or completion never creates a treating relationship or clinical consent.
- File 09 may verify a professional applicant without requiring a pre-existing professional-verification flag from File 00; otherwise the dependency becomes circular.
- Every protected action revalidates current identity, suspension, guardian, practitioner, relationship, consent, purpose, field and record version.
- Events are past-tense facts, not commands or credentials.
- No raw clinical narrative, attachment, identity evidence or bearer credential enters cross-module events/notifications/assurance.
- A provider outage produces explicit `unknown`/degraded state and never permissive fallback.
- Companion contracts do not create a second clinical system of record.

## 9. Review cadence

On each contract change:

1. update the native-owner issue;
2. update this register and CF01-A traceability;
3. run producer and consumer contract tests;
4. perform review → fix → fresh/adversarial review → fix → retest;
5. attach public-safe evidence and private evidence references;
6. keep CF01-A-020 blocked until every mandatory provider contract is accepted.

## 10. Current decision

Four of nine native-owner contract candidates are implemented and automated-QA green, but all remain unmerged and unaccepted. The remaining five contracts are still open and unimplemented. Therefore CF01-A-009 through CF01-A-015 and CF01-A-020 remain blocked from final acceptance, and C1-B clinical runtime remains unauthorized.
