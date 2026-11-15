# CF-01 — V12.3 source-only clinical UI and privacy audit (15 November 2026)

## Truth boundary

- Governing document: Sabri Website Master Plan V12.3, CF01-FR-001–032, CF01-CEN-01–10 and related privacy/accessibility requirements.
- Reviewed repository baseline: `c8fa142ee88d97174ad3f4514d062fe68b82a206` (`main` when the audit began).
- Branch: `codex/cf01-protected-view-lifecycle-v123-20261115`.
- Runtime/schema/contract source versions: `1.0.0 / 1.0.0 / 1.0.0`; deployment and live database versions are **not verified**.
- This is an isolated source correction. No real patient data, staging deployment or production activation is approved.

## Frozen source findings and root causes

| ID | Proven source-level symptom and root cause | Correction | Regression evidence |
|---|---|---|---|
| CF01-V123-UI-001 | `clinical.js` aborted an in-flight request on `visibilitychange=hidden` but left previously rendered clinical content in the DOM. The request generation was not invalidated on hide, and a bfcache restore did not trigger a fresh clinical read. This contradicted the repository's explicit hidden-tab clearing policy. | Clear the protected DOM immediately, abort and invalidate outstanding reads, reject stale responses even after JSON parsing, and re-fetch on visible and persisted pageshow. | `tests/test_protected_browser_lifecycle.py`: synthetic browser lifecycle with delayed response, conceal/restore and bfcache; Python unittest discovery runs in exact-head CI. |
| CF01-V123-UI-002 | The own-record UI explicitly requested a reduced `fields` list omitting `access_history`, and `renderPatient` ignored returned `consents`. Both were supported by the protected server projection but never displayed to the patient. | Ask only the already authorized own-record projection for `access_history`; show bounded consent status/purpose and minimal access activity/time/result. Do not display private actor IDs or introduce new endpoints. | Synthetic client test checks consent purpose, the own-record request and absence of a deliberately supplied private actor token in rendered output. |

## Source review and acceptance

- Do **not** activate CF-01 as part of this change.
- Do **not** modify File 00/02/08/09/17/19/20/24/25 owner contracts without independent version freeze and conformance evidence.
- Verify exact PR HEAD and PR merge-ref tests in GitHub Actions before merging.
- Existing release gate still requires PHP 8.1/8.3, Python 3.11/3.12, forty source review rounds, deterministic package, SBOM and checksum.
- Dedicated clinical RTL, accessibility, weak-network and real-browser testing is still an **external staging acceptance**, not proved by the synthetic Node fixture.
- The nine open companion contract tracking issues and ten external release conditions are not treated as repository code defects.
- The Future24 foundations remain disabled under their separate evidence-bound governance.

## Independent deployment blockers

1. Exact deployed code, WordPress runtime, active package and checksum unverified.
2. Live clinical DB/schema, `wp_options` activation/migration status and logs unverified.
3. Qualified legal/professional, retention, key/provider, privacy and independent penetration acceptance not provided.
4. Owner-frozen contracts and File 08 extraction/reconciliation approval not provided.
5. Isolated synthetic-data staging, migration/restore/rollback and full human/browser acceptance not provided.
6. Named operating owners and explicit Founder production authorization not provided.

**Outcome is source candidate only.** Successful repository tests never imply `Staging-Accepted`, `Live-Deployed`, `Operational` or permission to process real patient data. Any live incident requires an exact-deployment/database/migration freeze before a live defect diagnosis.
