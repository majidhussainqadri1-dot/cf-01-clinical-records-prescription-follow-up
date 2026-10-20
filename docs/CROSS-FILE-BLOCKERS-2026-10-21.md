# CF-01 — Native-Owner Dependency and Blocker Snapshot (21 October 2026)

## Immutable scope and evidence rule

This supersedes **only the historical status claims**, not the contracts or canonical owner law, of `docs/C1-A-CROSS-REPOSITORY-CONTRACT-TRACKING.md` dated 5 August 2026.

Checked repository provider/PR/issue metadata on 21 October 2026. Each repository `main` HEAD below is **source-repository evidence, not an accepted provider contract, installed plugin, staging system or deployed version**. Owner-frozen schema semantics and the qualified security/clinical/legal/Founder gates remain separate.

## Fresh native-owner source heads

| Native owner | 21-Oct repository main SHA (abbreviated) | CF-01 tracking issue | Observed issue state |
|---|---|---|---|
| File 00 Membership | `2fa7c022ee9c` | #12 | Open |
| File 02 Authentication | `224c39bcb8c2` | #3 | Open |
| File 08 Clinic/Appointments | `92e2422d6a23` | #4 | Open |
| File 09 Doctor Verification | `cfc5f781a766` | #3 | Open |
| File 17 Communication | `8ae656e51796` | #4 | Open |
| File 19 Notifications | `04078025b643` | #3 | Open |
| File 20 Shell | `8a4dbcaf4fef` | #7 | Open |
| File 25 Public UI/Visual | `347a4ff4d4c2` | #3 | Open |
| File 24 Security/Assurance | `a5b8d49968a7` | #8 | Open |

A tracking issue being open is not proof that a code implementation is missing; closure is also not proof of clinical acceptance. Provider capabilities, versions, compatibility tests and owner approvals require review at exact provider heads.

## Corrected historic PR-state assumptions

| Native module | Historically recorded PR | Current GitHub PR metadata | Consequence |
|---|---|---|---|
| File 02 Authentication | PR #4 described as open/unmerged | **Closed and merged** | Owner-source presence advanced; recheck current `main` contract schema and CF-01 consumer version/authorization semantics. Still not owner-frozen. |
| File 08 Clinic/Appointments | Base PR #3 described as open/unmerged | **Closed, not merged** | Previous extraction/relationship contract candidate is not native `main` truth; reassess newer provider source or restart approval workflow. |
| File 08 Clinic/Appointments | Stacked PR #5 described as open/unmerged | **Closed, not merged** | Do not use PR #5 as accepted provider truth. |
| File 09 Doctor Verification | Base PR #2 | **Open draft, not mergeable** | Resolve dependency chain and provider validation before contract freeze. |
| File 09 Doctor Verification | Stacked PR #4 | **Open draft; unmerged** | Base PR #2 acceptance and integration remain outstanding. |

## Blocker disposition

### Repository-correctable in CF-01

- Six audit findings `CF01-40-001` through `CF01-40-006` have code and regression corrections on PR #13.
- 40 distinct source rounds plus fresh Python/PHP and merge-ref gates succeeded at PR #13 provisional head `bf25c13296cfb47739d65a179d92934436bedddb`, GitHub Actions run `39483245207`; this is not production parity.
- Maintain the new owner-source snapshot so historical PR statuses do not silently control current decisions.

### Owner-controlled / independent acceptance, NOT removable by changing CF-01 source

1. Native owner contracts must be confirmed against exact merged versions, not historical PR snapshots.
2. File 08 live/staging relationship and patient-write inventory must be frozen before any migration diagnosis or extraction plan.
3. File 09 professional-eligibility restrictions and File 00/02 identity/step-up semantics must be independently accepted.
4. Files 17/19/20/25/24 contracts require owner/consumer conformance, signed-version and adverse-state fixtures.
5. Qualified Pakistan/target-region legal/professional/privacy rules and independent penetration review must be documented.
6. Approved provider/region/keys/retention/legal hold/clinical storage, synthetic-data staging, disaster recovery and rollback must pass.
7. Named clinical/records/privacy/security operations and explicit Founder production approval are required.

**No operational, deployed, migration-resolved or live-tested claim is made.** Any future observed live defect triggers a read-only exact-deployment/database/migration freeze before repository diagnosis or patching.
