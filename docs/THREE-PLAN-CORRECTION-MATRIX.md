# CF-01 Three-Plan Correction Matrix

## Governing baselines

CF-01 is reviewed against three concurrent governing sources:

1. `01-Sabri-Social-Homeopathy-Platform-Definitive-Master-Plan-2026-v3.0` — parent product constitution, canonical ownership and truthful completion law.
2. `Sabri-Platform-All-Chats-Recovered-Directives-Final-5-8-2026-Updated-v2.1` — later Founder-approved consolidated directives, including green identity, meaningful icons, RTL-first layout, Back/Home controls and post-GitHub harmonization.
3. `CF-01-Clinical-Records-Prescription-Follow-Up-Conditional-Complete-Master-Plan-2026-v1.0` — conditional clinical system-of-record specification and `CF01-FR-001` through `CF01-FR-032`.

The newer explicit Founder directive supersedes older conflicting visual rules. CF-01 remains conditional, disabled by default and prohibited from handling real patient data until every external activation gate is independently accepted.

## Immutable reviewed implementation release baseline

The evidence below identifies the implementation/package release that was examined during the fresh four-round review. It is deliberately not described as the forever-current `main` tip: later documentation-only merges create new Git commit IDs without changing the reviewed installable runtime.

- Source branch at implementation-release review: `main`
- Exact reviewed implementation head: `5ad4319927713b6c4ce4b8ad459df35fe267ba9c`
- Runtime / schema / contract: `1.0.0 / 1.0.0 / 1.0.0`
- Exact-head GitHub Actions run: `31042006210` — successful
- Retained release artifact: `8944933830`
- Artifact name: `cf-01-release-5ad4319927713b6c4ce4b8ad459df35fe267ba9c`
- Artifact archive digest: `sha256:47122c85cfb0472ff2c3f3958291dea7c65677253f4f063ef7cbf0d429964218`

The exact head and successful workflow for each later review/documentation commit belong to GitHub Actions and pull-request evidence. A committed document must not make the self-referential claim that it contains the immutable SHA of its own future merge commit.

## Truthful status law

`Specified`, `Coded`, `Packaged`, `Automated-QA Green`, `Staging-Accepted`, `Live-Deployed` and `Operational` are separate states. The immutable implementation-release evidence establishes the first four states within the reviewed source scope. It does not establish legal approval, independent penetration-test acceptance, Hostinger staging acceptance, live deployment or operational acceptance.

## Consolidated correction register

| ID | Governing requirement | Defect found during review | Correction evidence | Current status |
|---|---|---|---|---|
| TPC-001 | Green is the primary platform identity; other colors remain semantic | CF-01 previously hard-coded an orange primary token | `assets/css/clinical.css` consumes shared Sabri tokens with approved green fallback and no orange primary token | Corrected; visual staging acceptance pending |
| TPC-002 | Important actions use meaningful icons with accessible labels | Back/Home, retry and record actions lacked a governed icon system | Inline SVG icon factory plus visible bilingual labels; no external icon dependency | Corrected; representative screen-reader acceptance pending |
| TPC-003 | Internal pages expose shared RTL-aware Back and Home controls | Clinical views lacked common navigation controls | Safe same-origin Back behavior, deterministic clinical fallback and canonical Home control | Corrected in source; native File 20 staging contract pending |
| TPC-004 | CF-01 must provide protected patient/doctor views, not a placeholder-only shell | Every non-governance route previously displayed the same placeholder | Route-aware own-record, patient, encounter, prescription and follow-up renderers plus lifecycle actions | Corrected in source |
| TPC-005 | Patient can securely view own eligible chart | `/my-health-record/` lacked a current-patient resolution contract | `GET /clinical/v1/me`, blind-index subject resolver and access audit | Corrected in source; integration staging pending |
| TPC-006 | Prescription and follow-up routes require object, purpose and current relationship authorization | UI routes lacked complete protected read contracts | Protected REST contracts with patient-owner/treating-clinician checks, field policy and audit | Corrected in source |
| TPC-007 | No clinical data persists in browser storage | Regression protection was not bound to the later plan | Permanent tests prohibit `localStorage`, `sessionStorage`, IndexedDB, service workers and cookies for clinical data | Corrected and regression-gated |
| TPC-008 | Clinical UI must avoid unsafe HTML injection | No permanent plan-specific assertion | DOM-only rendering and permanent `innerHTML` prohibition test | Corrected and regression-gated |
| TPC-009 | Later-plan corrections must remain in exact-head CI | Earlier suites did not explicitly enforce green/icon/navigation requirements | `tests/three-plan-corrections.php` runs in PHP 8.1, PHP 8.3, forty-round and package-policy jobs | Corrected in CI |
| TPC-010 | Complete role journeys: patient, guardian, treating doctor, assistant, supervisor, records officer and auditor | R1 covered patient and treating doctor only | R2 role-context, guardian, assistant, supervisor, records and auditor workflows plus adversarial tests | Corrected in source; representative human staging pending |
| TPC-011 | Complete clinical lifecycle: relationships, consent withdrawal, observations, assessments, supersession, follow-up transitions, rights, retention and break-glass review | Initial runtime coverage was uneven | R2 lifecycle REST/domain completion and R3/R4 adversarial, compensation and rollback hardening | Corrected in source |
| TPC-012 | Activation evidence must be structured, immutable, exact-head and independently verifiable | Earlier gate accepted merely non-empty evidence fields | R3 release/site/environment-bound evidence, replay denial, expiry, unique IDs and immutable enabled-state evidence | Corrected in source; independent external verification pending |
| TPC-013 | File 00/02/08/09/17/19/20/24/25 and secure-media contracts must be accepted and frozen | Local fail-closed adapters cannot prove native-owner operational acceptance | Structured provider assertions and fail-closed validation implemented; native repositories must supply accepted evidence | External release blocker |
| TPC-014 | Hostinger-equivalent staging, browser/RTL/accessibility, restore/rollback and penetration testing | Automated and synthetic evidence cannot establish real-environment acceptance | No false completion claim; activation remains disabled and real data prohibited | External release blocker |
| TPC-015 | Repository status documents must reflect accepted evidence without impossible self-reference | Matrix, release-status and exact-head records retained obsolete open/pending statements; initial correction wording could be mistaken for the forever-current main tip | Updated in the 06 August 2026 four-round review and finalized as immutable release-baseline evidence | Corrected in documentation |

## Current verdict

The three-plan source implementation is complete for all 32 Must requirements, deterministically packaged and exact-head automated-QA green. No known unresolved Critical or High defect remains in the presently reviewable source scope.

This is not a claim of absolute infallibility and not a production-completion claim. CF-01 remains disabled by default. Real patient data, production schema installation, File 08 production extraction, provider credentials, Hostinger staging and live operation remain prohibited until the external release blockers and complete Definition of Done are independently accepted.
