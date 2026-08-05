# CF-01 Three-Plan Correction Matrix

## Governing baselines

This corrective branch is reviewed against three concurrent governing sources:

1. `01-Sabri-Social-Homeopathy-Platform-Definitive-Master-Plan-2026-v3.0` — parent product constitution and truthful completion law.
2. `Sabri-Platform-All-Chats-Recovered-Directives-Final-5-8-2026-Updated-v2.1` — later Founder-approved consolidated directives, including green identity, meaningful icons, RTL-first layout, Back/Home controls and global harmonization.
3. `CF-01-Clinical-Records-Prescription-Follow-Up-Conditional-Complete-Master-Plan-2026-v1.0` — conditional clinical system-of-record specification.

The newer explicit Founder directive supersedes older conflicting visual rules. CF-01 remains conditional, disabled by default and prohibited from handling real patient data until every external activation gate is independently accepted.

## Truthful status law

`Specified`, `Coded`, `Packaged`, `Automated-QA Green`, `Staging-Accepted`, `Live-Deployed` and `Operational` are separate states. This branch may establish corrected source and automated evidence only. It does not establish legal approval, independent penetration-test acceptance, Hostinger staging acceptance, live deployment or operations.

## Correction register

| ID | Governing requirement | Prior defect | R1 correction evidence | Current status |
|---|---|---|---|---|
| TPC-001 | Green is the primary platform identity; other colors remain semantic | CF-01 hard-coded an orange primary token | `assets/css/clinical.css` now consumes shared Sabri tokens with approved green fallback and no orange primary token | Corrected in source; visual staging pending |
| TPC-002 | Important actions use meaningful icons with accessible labels | Back/Home, retry and record actions had no icon system | Inline SVG icon factory plus visible bilingual labels; no external icon dependency | Corrected in source; screen-reader acceptance pending |
| TPC-003 | Internal pages expose shared RTL-aware Back and Home controls | Clinical views had no common navigation controls | Safe same-origin Back behavior, deterministic clinical fallback and canonical Home control | Corrected in source; File 20 component contract pending |
| TPC-004 | CF-01 must provide real protected patient/doctor views, not a placeholder-only shell | Every non-governance route displayed the same placeholder | Route-aware own-record, patient, encounter, prescription and follow-up renderers | Partially corrected; mutation/editor journeys remain |
| TPC-005 | Patient can securely view own eligible chart | `/my-health-record/` had no server contract resolving the current patient | `GET /clinical/v1/me` plus blind-index canonical subject resolver and access audit | Corrected in source; integration staging pending |
| TPC-006 | Prescription and follow-up routes require object, purpose and current relationship authorization | UI routes existed without corresponding read APIs | Protected GET contracts with patient-owner/treating-doctor checks, field policy and audit | Corrected in source; guardian/supervisor roles pending |
| TPC-007 | No clinical data persists in browser storage | Placeholder code was safe but regression was not tied to the later plan | Permanent test blocks localStorage, sessionStorage, IndexedDB, service worker and cookies | Corrected and regression-gated |
| TPC-008 | Clinical UI must avoid unsafe HTML injection | No permanent plan-specific assertion | DOM-only rendering and permanent `innerHTML` prohibition test | Corrected and regression-gated |
| TPC-009 | All later-plan corrections must remain in exact-head CI | Existing suites did not explicitly enforce newer green/icon/navigation requirements | `tests/three-plan-corrections.php` runs on PHP 8.1, PHP 8.3, forty-round and package-policy jobs | Corrected in CI definition |
| TPC-010 | Complete role journeys: patient, guardian, treating doctor, assistant, supervisor, records officer, auditor | Current read helper covers patient and treating doctor only | No false completion claim | Open — next correction batch |
| TPC-011 | Complete clinical lifecycle UI and REST: relationships, consent withdrawal, observations, assessments, supersession, follow-up transitions, rights, retention and break-glass review | Domain methods exist unevenly; public/internal route coverage is incomplete | No false completion claim | Open — next correction batch |
| TPC-012 | Activation evidence must be structured, immutable, exact-head and independently verifiable | Existing activation gate accepts non-empty evidence fields | No false completion claim | Open — next correction batch |
| TPC-013 | File 00/02/08/09/17/19/20/24/25 and secure-media contracts must be accepted and frozen | Current code has fail-closed adapters but no accepted native-owner evidence | No false completion claim | External blocker |
| TPC-014 | Hostinger-equivalent staging, browsers, RTL, accessibility, restore, rollback and penetration testing | Automated/synthetic evidence only | No false completion claim | External blocker |

## Release boundary after R1

R1 corrects the first visual and protected-read defects. It does **not** make CF-01 production complete. Real patient data remains prohibited. Runtime activation remains disabled by default. The branch must stay draft until all open Critical/High items and external activation gates are resolved.
