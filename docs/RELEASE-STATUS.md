# CF-01 Release Status

## Current source stage

- **Specified:** complete under the Definitive Master Plan, CF-01 conditional plan and merged C1-A governance baseline.
- **Coded:** complete disabled-by-default runtime candidate for `CF01-FR-001` through `CF01-FR-032`.
- **Governance integration:** the reviewed C1-A foundation, runtime and R1–R4 hardening are merged to canonical `main`.
- **Packaged:** requires an exact-head deterministic double-build receipt for the ZIP, embedded file manifest, detached ZIP checksum, SPDX 2.3 SBOM and detached SBOM checksum, retained together as one GitHub Actions artifact.
- **Automated-QA Green:** requires exact-head PHP 8.1/8.3, governance, runtime, forty-round, release-bundle and pull-request merge-ref evidence.
- **Staging-Accepted: pending**
- **Live-Deployed: pending**
- **Operational: pending**

## Release evidence bundle law

The canonical source release bundle contains exactly these retained deliverables:

1. `cf-01-clinical-records-prescription-follow-up-1.0.0.zip`;
2. detached ZIP SHA-256 receipt;
3. detached package file manifest;
4. deterministic SPDX 2.3 JSON SBOM covering every staged plugin file;
5. detached SBOM SHA-256 receipt.

Two complete builds must be byte-identical across all five files. The workflow validates the ZIP structure, internal manifest, checksums, SPDX identity, analyzed-file inventory and package relationships before uploading the complete bundle as a retained GitHub Actions artifact. Exact immutable IDs and checksums belong in the final pull-request evidence after the last source commit.

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

Exact commit SHA, workflow run, artifact ID, deterministic ZIP/SBOM checksums and final assertion totals are pull-request-owned immutable evidence and are recorded only after the last source commit passes every gate. A ZIP, checksum, SBOM or green CI run is not production completion.
