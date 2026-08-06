# CF-01 Three-Plan Correction Matrix

## Governing baselines

CF-01 is reviewed against three concurrent governing sources:

1. `01-Sabri-Social-Homeopathy-Platform-Definitive-Master-Plan-2026-v3.0` — parent product constitution, canonical ownership and truthful completion law.
2. `Sabri-Platform-All-Chats-Recovered-Directives-Final-5-8-2026-Updated-v2.1` — later Founder-approved consolidated directives, including green identity, meaningful icons, RTL-first layout, Back/Home controls and post-GitHub harmonization.
3. `CF-01-Clinical-Records-Prescription-Follow-Up-Conditional-Complete-Master-Plan-2026-v1.0` — conditional clinical system-of-record specification and `CF01-FR-001` through `CF01-FR-032`.

The newer explicit Founder directive supersedes older conflicting visual rules. CF-01 remains conditional, disabled by default and prohibited from handling real patient data until every external activation gate is independently accepted.

## Immutable reviewed implementation release baseline

The evidence below identifies the implementation/package release examined before the later three-plan clinical hardening. It is deliberately not described as the forever-current `main` tip: later correction and documentation commits create new Git commit IDs.

- Source branch at prior implementation-release review: `main`
- Exact prior implementation head: `5ad4319927713b6c4ce4b8ad459df35fe267ba9c`
- Runtime / schema / contract: `1.0.0 / 1.0.0 / 1.0.0`
- Exact-head GitHub Actions run: `31042006210` — successful
- Retained release artifact: `8944933830`
- Artifact name: `cf-01-release-5ad4319927713b6c4ce4b8ad459df35fe267ba9c`
- Artifact archive digest: `sha256:47122c85cfb0472ff2c3f3958291dea7c65677253f4f063ef7cbf0d429964218`

The exact head, workflow, package and artifact for the later hardening candidate belong to its GitHub Actions and pull-request evidence. A committed document must not make the self-referential claim that it contains the immutable SHA of its own future merge commit.

## Truthful status law

`Specified`, `Coded`, `Packaged`, `Automated-QA Green`, `Staging-Accepted`, `Live-Deployed` and `Operational` are separate states. Source corrections do not establish legal approval, independent penetration-test acceptance, Hostinger staging acceptance, live deployment or operational acceptance.

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
| TPC-016 | Every clinical object action must be bound to an exact current treating relationship, purpose and scope | Encounter, prescription, follow-up and attachment paths could rely on a broad active relationship rather than the record-bound relationship | `CF01_Authorization::relationship_for_record()` and scope validation are enforced by the affected domain services | Corrected and regression-gated |
| TPC-017 | Treating relationship authority must come from its canonical native owner | Relationship creation accepted locally supplied source/context without a current owner assertion | `CF01_Contracts::relationship_source()` validates versioned, current, non-revoked source evidence before persistence | Corrected; native-provider staging acceptance pending |
| TPC-018 | Guardian/minor authority must be current at every protected action | Guardian context and consent could rely on stale or locally supplied evidence; missing date of birth could fail open | Subject identity and guardian authority contracts are revalidated; missing/invalid age evidence fails closed | Corrected and regression-gated |
| TPC-019 | Clinical attachments are C5 objects and require object/field/purpose authorization | Attachment delivery could be requested without patient-scoped role/field authorization; relink authority was too broad | Delivery now validates exact patient context, allowed fields, scan state and actor-bound secure-media grant; relink is records-only | Corrected and regression-gated |
| TPC-020 | Relationship termination must revoke future access and reconcile continuity work | Ending a relationship did not prove reconciliation of open prescriptions, follow-ups, tasks and transfer/retention obligations | Termination requires a structured reconciliation result before final transition | Corrected in source; human transfer workflow staging pending |
| TPC-021 | Clinical templates and terminology mappings must be versioned and reproducible | Encounter template keys and coding mappings were accepted without native-owner/version/round-trip proof | Versioned template and terminology contracts are required; historical rendering and narrative preservation remain explicit | Corrected and regression-gated |
| TPC-022 | Signed clinical facts require UTC and local-time provenance plus restore verification | Signed snapshots lacked complete local-time provenance and restore relied mainly on external aggregate evidence | Encounter/prescription snapshots record UTC, local timestamp, time zone and offset; internal signature verification is executed during restore validation | Corrected and regression-gated |
| TPC-023 | Red-flag input must invoke approved emergency guidance without autonomous diagnosis | Encounter and patient-outcome red flags lacked a canonical emergency-policy call | Versioned emergency-policy contract supplies approved local guidance and clinician alert evidence; no autonomous diagnosis is created | Corrected; real policy/provider acceptance pending |
| TPC-024 | Follow-up reminders must respect consent, quiet hours and time-zone rules | Reminder reconciliation could enqueue notifications without complete opt-in/quiet-hour normalization | Reminder preferences are normalized and opt-in, quiet hours and time-zone conditions are enforced before outbox creation | Corrected and regression-gated |
| TPC-025 | Secure export must be bounded, patient-scoped, attachment-aware and replay resistant | Export generation/consumption lacked explicit bounded rate controls and a sanitized attachment inventory | Actor/patient rate limits, recent-auth consumption, one-time token handling and a privacy-minimal attachment manifest are enforced | Corrected and regression-gated |
| TPC-026 | Break-glass requires misuse controls in addition to short-lived minimum access | Repeated actor/patient emergency requests lacked bounded abuse handling | Actor and patient-target rate limits, repeated-use alert evidence and suspension/security escalation hooks are enforced | Corrected; operational alert handling pending |
| TPC-027 | High-risk actions must uniformly require recent step-up authentication | Attachment relink/review, export consumption and other sensitive transitions were absent from the high-risk catalogue | The high-risk action constitution now covers all identified sensitive clinical, export, attachment, retention, break-glass and release operations | Corrected and regression-gated |
| TPC-028 | Prescriptions must reject ambiguous instructions and preserve language provenance | Ambiguous abbreviations and invalid/missing language tags could pass validation | Ambiguous-abbreviation rejection and language-tag validation are enforced before prescription persistence/signature | Corrected and regression-gated |
| TPC-029 | Role context must never be guessed from broad WordPress capabilities | Assistant, records and auditor roles could be inferred without an explicit current patient-scoped assertion | Care-team and oversight roles require accepted, current, patient/purpose/role-bound native assertions | Corrected and regression-gated |
| TPC-030 | Patient-facing projections and timelines must be field-minimal and cursor-safe | Broad row projection risked internal metadata exposure; timeline completeness and pagination proof were insufficient | Explicit projections replace heuristic row filtering; timeline includes authorized lifecycle objects and uses signed patient-bound cursors | Corrected and regression-gated |
| TPC-031 | Permanent tests must prevent recurrence of the complete hardening set | Earlier three-plan test covered mostly visual/public-read corrections | The permanent three-plan regression suite now checks native assertions, relationship binding, guardian safety, attachment security, templates, emergency policy, reminders, exports, restore signatures and abuse controls | Corrected in CI |
| TPC-032 | Test fixtures must exercise fail-closed native-owner contracts without weakening production code | New fail-closed contracts required deterministic synthetic providers for unit/adversarial execution | `tests/bootstrap.php` supplies synthetic, versioned native-owner assertions only inside the test harness | Corrected; no production bypass added |

## Current verdict

All locally demonstrated defects discovered in the present three-plan source-hardening cycle have been corrected and bound to permanent regression checks. Exact-head, merge-ref and deterministic-package acceptance for this corrective candidate is established only by its successful GitHub Actions and pull-request evidence.

This is not a claim of absolute infallibility and not a production-completion claim. CF-01 remains disabled by default. Real patient data, production schema installation, File 08 production extraction, provider credentials, Hostinger staging and live operation remain prohibited until the external release blockers and complete Definition of Done are independently accepted.
