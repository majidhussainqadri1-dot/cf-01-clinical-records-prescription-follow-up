# C1-A Cross-Repository Contract Tracking Register

**Status date:** 05 August 2026  
**Status:** File 00 provider code is merged to its native `main`; File 02, File 08 and File 09 provider candidates remain open and unmerged; five additional native-owner contracts remain unimplemented. No CF-01 contract is yet accepted or frozen.  
**Parent exit gate:** [CF-01 issue #2](https://github.com/majidhussainqadri1-dot/cf-01-clinical-records-prescription-follow-up/issues/2)  
**Draft contract baseline:** `docs/C1-A-CROSS-FILE-CONTRACTS.md`

## 1. Contract issue register

| Requirement | Native owner | Tracking issue | Current code/PR evidence | Acceptance state |
|---|---|---|---|---|
| CF01-A-009 | File 00 — Membership Core | `00-sabri-membership-core#12` | Provider PR #13 merged on 03 August 2026; merge commit `ebc66a3782ee846437fe14628dfe7b2a9bc31671`; provider candidate `1.2.7` | Issue remains open; CF-01 consumer fixtures, qualified review, owner freeze and Founder change control remain incomplete |
| CF01-A-009 | File 02 — Authentication | `02-sabri-authentication#3` | Draft PR #4 open, mergeable and unmerged; head `65775bbe2e76fe30b5775e5af94e3ad42337bf25` | Must be revalidated against merged File 00 and then accepted/frozen |
| CF01-A-010 | File 08 — Clinic/Appointments | `08-worldwide-clinic-and-appointments-foundation#4` | Stacked Draft PR #5 open and unmerged; base PR #3 also open; head `0997adc8808e112547a28aea3e337c63d2b8efd9` | Current staging-data inventory, extraction rehearsal, owner acceptance and consumer tests remain incomplete |
| CF01-A-011 | File 09 coordinating Files 03/07/09 | `09-global-doctor-onboarding-and-verification-completion#3` | Stacked Draft PR #4 open and unmerged; base PR #2 also open; head `6ec7ebcc765000a47df71d41b1676a362358de33` | Structured restriction ownership, Files 03/07 reconciliation, merged dependencies and acceptance remain incomplete |
| CF01-A-012 | File 17 — Network/Messages | `17-sabri-network#4` | Tracking issue open | Not implemented or accepted |
| CF01-A-013 | File 19 — Notifications | `19-sabri-unified-notifications#3` | Tracking issue open | Not implemented or accepted |
| CF01-A-014 | File 20 — Unified Shell | `20-sabri-unified-application-shell#7` | Tracking issue open | Not implemented or accepted |
| CF01-A-014 | File 25 — Visual Components | `25-sabri-public-ui-profile-timeline-visual-experience#3` | Tracking issue open | Not implemented or accepted |
| CF01-A-015 | File 24 — Security/Privacy Assurance | `24-sabri-platform-security-privacy-compliance-and-resilience-center#8` | Tracking issue open | Not implemented or accepted |

All nine tracking issues were rechecked on 05 August 2026 and remain open. An open or closed issue alone is not acceptance evidence; the freeze criteria below remain controlling.

## 2. File 00 merged provider evidence

- Native repository: `majidhussainqadri1-dot/00-sabri-membership-core`.
- Provider release: File 00 `1.2.7`.
- General membership contract: `1.1.2`.
- CF-01 provider contract: `smc.cf01.membership-assurance` `1.0.0`.
- Provider PR #13: merged to `main`.
- Provider head: `0434d79e65eeca336833f102ad03c1453f2205dd`.
- Merge commit: `ebc66a3782ee846437fe14628dfe7b2a9bc31671`.
- Historical exact-head GitHub Actions run: `30828349841` — success on PHP 7.4 and PHP 8.3.
- Historical deterministic package SHA-256: `2383aa9dcf79ddad9da29ec7bbbd01e62d62185ae0fe900979b955d461c8cdb9`.

### File 00 truth boundary

The merge proves that provider code exists in the native repository. It does **not** by itself prove:

- CF-01 consumer compatibility against the immutable merged version;
- native-owner acceptance of all semantics and privacy classes;
- qualified privacy/security/clinical/legal review;
- Hostinger staging, outage, migration, rollback or live acceptance;
- Founder approval to exit C1-A.

Therefore CF01-A-009 remains blocked from final acceptance.

## 3. File 02 candidate evidence

- Native repository: `majidhussainqadri1-dot/02-sabri-authentication`.
- Candidate release: `0.3.0`.
- Contracts: `sa.cf01.authentication-assurance` `1.0.0` and `sa.professional-reauthentication` `1.0.0`.
- Draft PR #4: open, mergeable and unmerged.
- Candidate head: `65775bbe2e76fe30b5775e5af94e3ad42337bf25`.
- Historical successful run: `30843867602`.

The PR body still describes File 00 merge as pending. Before acceptance, File 02 must be rebased or otherwise revalidated against the merged File 00 provider, its metadata must be reconciled, and producer/consumer fixtures must pass against immutable merged versions.

## 4. File 08 candidate evidence

- Native repository: `majidhussainqadri1-dot/08-worldwide-clinic-and-appointments-foundation`.
- Candidate release: `0.2.2`.
- Contract: `swc.cf01.care-context` `1.0.0`.
- Draft PR #5: open and unmerged.
- Base PR #3: open and unmerged.
- Candidate head: `0997adc8808e112547a28aea3e337c63d2b8efd9`.
- Historical successful run: `30830508463`.

The contract must continue to assert that appointment or consultation context is scheduling truth only and does not itself grant treating relationship, chart access, prescription authority, break-glass authority or clinical/publication consent.

## 5. File 09 candidate evidence

- Native repository: `majidhussainqadri1-dot/09-global-doctor-onboarding-and-verification-completion`.
- Candidate release: `1.1.1`.
- Contract: `gdo.cf01.practitioner-eligibility` `1.0.0`.
- Draft PR #4: open and unmerged.
- Base PR #2: open and unmerged.
- Candidate head: `6ec7ebcc765000a47df71d41b1676a362358de33`.
- Historical successful run: `30845577484`.

The assertion must never grant clinical authorization. Prescription signing and break-glass remain fail-closed until structured professional restriction ownership, merged dependency compatibility and qualified review are accepted.

## 6. Ordered dependency law

1. Treat the merged File 00 `1.2.7` provider as code presence only until immutable merged-version CF-01 consumer fixtures and owner acceptance are complete.
2. Rebase/revalidate File 02 PR #4 against merged File 00, reconcile its stale PR status text and rerun exact-head and merge-ref QA.
3. Resolve File 09 base PR #2, then rebase/revalidate File 09 PR #4 against accepted File 00/02 contracts.
4. Reconcile Files 03/07/09 professional fields and assign structured professional restriction ownership without creating an alternate verification authority.
5. Resolve File 08 base PR #3, then rebase/revalidate File 08 PR #5 against accepted File 00/File 09 contracts.
6. Implement and review the File 17, File 19, File 20, File 25 and File 24 contracts in their native repositories.
7. Add CF-01 consumer fixtures and cross-repository integration tests against immutable merged provider versions.
8. Complete File 08 current staging-data/write-path inventory, extraction dry run, reconciliation and rollback rehearsal.
9. Complete provider sandbox, privacy/security/clinical/professional review, Hostinger-equivalent staging and Founder change control.
10. Only then may CF01-A-009 through CF01-A-015 be proposed for `Accepted`.

## 7. Freeze criteria for every contract

A tracking issue or merged provider PR is not sufficient. Closure and freeze require:

1. canonical owner and named responsible reviewer;
2. versioned schema/DTO/assertion/query/event contract;
3. mandatory and optional fields, semantics and privacy classification;
4. current state, record version, issued-at/expiry and safe reason codes;
5. explicit fail behavior for unknown, incompatible, stale and dependency-outage states;
6. producer and CF-01 consumer fixtures against immutable merged versions;
7. negative authorization, leakage, replay, concurrency and outage tests;
8. migration, compatibility/deprecation window and rollback behavior;
9. privacy/security and relevant clinical/professional review;
10. Founder-approved change-control reference.

## 8. Cross-contract invariants

- Availability never grants authorization.
- Authentication assurance never grants membership, professional or clinical authorization.
- Professional eligibility never grants treating relationship, patient consent, chart/field access, prescription content or break-glass authority.
- Appointment existence, acceptance or completion never creates a treating relationship or clinical consent.
- UI roles, badges, caches, indexes, projections and events never override native truth.
- Every protected action revalidates current identity, suspension, guardian, practitioner, relationship, consent, purpose, field and record version.
- Events are past-tense facts, not commands or credentials.
- No raw clinical narrative, attachment, identity evidence or bearer credential enters cross-module events, notifications or assurance evidence.
- Provider outage yields explicit `unknown` or degraded state and never permissive fallback.
- Companion contracts do not create a second clinical system of record.

## 9. Review cadence

On every contract change:

1. update the native-owner issue and this register;
2. reconcile provider PR metadata with current merged dependencies;
3. run producer and consumer contract tests;
4. perform review → fix → fresh/adversarial review → fix → retest;
5. attach public-safe evidence and private evidence references;
6. keep CF01-A-020 blocked until every mandatory provider contract is accepted.

## 10. Current decision

One of nine provider implementations is now merged into its native repository, three additional provider candidates are implemented but unmerged, and five contracts remain unimplemented. All nine tracking issues remain open and no contract satisfies the full freeze criteria. Therefore CF01-A-009 through CF01-A-015 and CF01-A-020 remain blocked, C1-A phase exit is not accepted, and C1-B clinical runtime remains unauthorized.
