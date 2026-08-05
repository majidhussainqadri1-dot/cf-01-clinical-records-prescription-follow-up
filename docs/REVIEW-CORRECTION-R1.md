# CF-01 Three-Plan Review and Correction — Round 1

## Reviewed baseline

- Source baseline: `aa9c6409b3146adf0f84cc329fca608bce58fd45`
- Corrective branch: `codex/cf-01-three-plan-correction-r1`
- Parent runtime candidate: `1.0.0`
- Activation: disabled by default
- Real patient data: prohibited

## Round-1 review scope

This round targeted defects simultaneously material under the Definitive Master Plan v3.0, the All-Chats Recovered Directive Register v2.1 and the CF-01 plan:

- superseded orange-primary implementation;
- absent meaningful icon language;
- absent common Back/Home controls;
- placeholder-only non-governance clinical interface;
- missing own-record resolution;
- missing prescription and follow-up read routes;
- lack of a permanent later-directive regression suite.

## Corrections applied

1. Replaced the CF-01 orange primary token with a shared-token-first green system and semantic secondary colors.
2. Added inline SVG icons with visible Urdu/English labels; no font, CDN or third-party icon dependency was introduced.
3. Added RTL-aware Back/Home navigation with same-origin validation and a safe records fallback.
4. Replaced the generic placeholder-only renderer with protected views for:
   - own clinical record;
   - authorized patient chart summary;
   - encounter detail;
   - prescription detail;
   - follow-up detail;
   - clinical health/governance status.
5. Added `GET /clinical/v1/me` using the File 00 membership subject and a CF-01 blind-index lookup. Merged or quarantined identities are excluded.
6. Added protected prescription and follow-up GET contracts with patient-owner or current treating-doctor authorization, field policy and durable access audit.
7. Added private response headers including `no-store`, `no-referrer` and `nosniff`.
8. Added `tests/three-plan-corrections.php` and made it release-blocking in all exact-head workflow paths.

## Fresh correction review

The new permanent gate checks:

- approved green fallback and removal of the old orange primary token;
- icon component presence;
- 44px target and RTL support;
- Back/Home bilingual controls;
- all newly supported route renderers;
- prohibition of `innerHTML` and browser persistence;
- own-record, prescription and follow-up REST routes;
- shared read authorization and access-audit hooks;
- canonical own-record resolver and excluded unsafe identity states.

## Known open defects after R1

R1 is intentionally not represented as full completion. The following remain open:

- guardian, clinical assistant, supervisor, records officer and auditor journeys;
- complete relationship and consent lifecycle routes/UI;
- observation and assessment create/correct/sign workflows;
- prescription update/ready/supersession and full safe mutation UI;
- follow-up reschedule/cancel/close and outcome form/review UI;
- rights, access-history, retention/legal-hold and break-glass review UI;
- structured signed activation-evidence registry;
- native-owner contract acceptance/freeze evidence;
- cursor pagination and large-chart performance acceptance;
- File 20 shared-component and File 25 component-contract staging acceptance;
- legal/professional review, independent security test, Hostinger staging, restore/rollback rehearsal and operations staffing.

## Truthful outcome

Round 1 establishes a materially improved, reviewable source candidate. It does not establish staging, production, legal-compliance, penetration-test or operational completion. CF-01 remains disabled and unsuitable for real patient data.
