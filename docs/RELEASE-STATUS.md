# CF-01 Release Status

## Current source stage

- **Specified:** complete under the Definitive Master Plan, CF-01 conditional plan and merged C1-A governance baseline.
- **Coded:** complete disabled-by-default runtime candidate for `CF01-FR-001` through `CF01-FR-032`.
- **Governance integration:** the reviewed C1-A foundation is merged to canonical `main`; the runtime branch has been reconciled with that baseline.
- **Packaged:** pending exact-head deterministic double-build receipt.
- **Automated-QA Green:** pending exact-head PHP 8.1/8.3, governance, runtime, forty-round, policy/package and pull-request merge-ref evidence.
- **Staging-Accepted: pending**
- **Live-Deployed: pending**
- **Operational: pending**

## Runtime safety state

The clinical runtime remains `disabled` by default. Source merge must not install or enable a real clinical schema, migrate File 08 production records, accept real patient data, expose real clinical attachments or activate provider credentials.

Missing, stale, revoked, wrong-environment or wrong-release native-owner contracts fail closed. Availability, UI state, cache, event, notification, analytics or projection is never authorization.

## External acceptance gates

Real patient data and activation remain prohibited until all of the following are recorded and accepted:

1. qualified Pakistan and target-jurisdiction legal/professional review;
2. independent security assessment and corrective retest;
3. accepted retention, legal-hold, patient-rights and breach rules;
4. frozen compatible native-owner contracts;
5. approved provider, region, storage, encryption-key and recovery decisions;
6. Hostinger-equivalent isolated staging with synthetic data only;
7. fresh install, upgrade, migration, reconciliation, backup/restore and rollback rehearsal;
8. representative browser, device, RTL, accessibility, weak-connection, load and failure acceptance;
9. named clinical, records, privacy, security, support and release owners with runbooks;
10. explicit Founder production approval supported by immutable evidence.

## Evidence boundary

Exact commit SHA, workflow run, deterministic package checksum and final assertion totals are pull-request-owned immutable evidence and are recorded only after the last source commit passes every gate. A ZIP, checksum or green CI run is not production completion.
