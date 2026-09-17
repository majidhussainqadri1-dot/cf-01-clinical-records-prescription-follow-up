# CF-01 Release Status

## Current status classes

- **Specified: complete for the repository-governed scope** — the CF-01 governing plans, base traceability and the 2026 Future Clinical Intelligence 24 amendment are represented in repository controls.
- **Coded: complete within the approved source scope** — the disabled-by-default runtime candidate implements the 32 base `CF01-FR-*` requirements and the 24 stable `CF01-FUT-001` through `CF01-FUT-024` source foundations. Source presence does not activate Future24.
- **Governance integration: complete within the repository candidate** — core clinical governance, Future24 hardening, atomic abuse throttling and retention purge reconciliation hardening are present on canonical `main`.
- **Packaged: complete for the retained reviewed implementation release** — deterministic double-build ZIP, embedded manifest, detached ZIP checksum, SPDX 2.3 SBOM and detached SBOM checksum were retained together.
- **Automated-QA Green: complete for the retained reviewed implementation release** — exact-head PHP 8.1/8.3, Python 3.11/3.12 governance, repository/runtime, Future24 regressions, forty-round and release-bundle gates passed.
- **Staging-Accepted: pending**
- **Live-Deployed: pending**
- **Operational: pending**

## Immutable implementation-release evidence

This section identifies the latest fully green runtime/package implementation baseline before the Round-10 documentation/evidence correction of the 17 September 2026 fresh ten-round review. Documentation or test-evidence commits after this baseline do not retroactively rename or mutate the retained runtime artifact; their own exact-head CI is additional repository evidence.

- Reviewed implementation head: `b8deab13c8aebf6cfdfb407a47f2e0c634ea4996`
- Successful implementation-release GitHub Actions run: `35182433635`
- Runtime / schema / contract: `1.0.0 / 1.0.0 / 1.0.0`
- Base functional requirements traced: `32/32`
- Stable Future24 capability IDs present: `24/24`
- Python tests in release job: `146 PASS`
- Runtime policy validation: `112 public-safe files, 38 PHP files, 32/32 base requirements traced`
- Retained artifact ID: `10480189826`
- Artifact name: `cf-01-release-b8deab13c8aebf6cfdfb407a47f2e0c634ea4996`
- Artifact size: `140318` bytes
- Artifact archive digest: `sha256:2963e80848b6aaf63b635f30a18bcd9306586ae2a33ac0204b2344d204c84243`
- Installable ZIP SHA-256: `5477ae58c9516e4a08e132fdfe95879fa4e671e042fb336b1e0daff5b71bf0a3`
- SPDX 2.3 SBOM SHA-256: `f9b54e480b29ace15c2ebd9995a4c19482f21c2992f4d121a757b51693da9baa`

The implementation-release workflow completed successfully for governance on Python 3.11 and 3.12, PHP review on PHP 8.1 and 8.3, the forty review-and-correction runner and the deterministic release-evidence bundle. The package was built twice and was byte-identical across the checked deliverables. This baseline includes the fresh-review corrections for serialized abuse rate limiting and fail-closed, observable retention purge reconciliation, together with the existing consent, follow-up, attachment, migration/schema, timeline, authorization, Future24 and privacy regressions.

## Ten-round review evidence

The current fresh review ledger is `docs/TEN-ROUND-FRESH-REVIEW-2026-09-17.md`. The historical 16 September ledger remains at `docs/TEN-ROUND-REVIEW-2026-09-16.md` and must not be used as proof that later source changes were already reviewed.

Both review sequences follow the audit-first rule: a numbered round is audited to completion and its defect set is frozen before corrections for that round begin; corrections and required verification complete before the next numbered round starts.

## Release evidence bundle law

The canonical source release bundle contains exactly these retained deliverables:

1. `cf-01-clinical-records-prescription-follow-up-1.0.0.zip`;
2. detached ZIP SHA-256 receipt;
3. detached package file manifest;
4. deterministic SPDX 2.3 JSON SBOM covering every staged plugin file;
5. detached SBOM SHA-256 receipt.

Two complete builds must be byte-identical across all five files. The workflow validates ZIP structure, internal manifest, checksums, SPDX identity, analyzed-file inventory and package relationships before retaining the complete bundle as one GitHub Actions artifact.

## Runtime safety state

The clinical runtime remains `disabled` by default. Source merge must not install or enable a real clinical schema, migrate File 08 production records, accept real patient data, expose real clinical attachments or activate provider credentials merely because repository code exists.

Future24 feature source presence is not activation. Requested `shadow`/`enabled` state remains fail-closed without accepted evidence bound to the active core activation generation/fingerprint, exact repository head, package SHA-256, environment, runtime/schema/contract versions and the required immutable acceptance hashes. Features 020–023 additionally require accepted data-governance evidence.

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

The repository establishes `Specified`, `Coded`, `Packaged` and `Automated-QA Green` only for an exactly identified reviewed implementation candidate. A ZIP, checksum, SBOM, green CI run or repository merge is not `Staging-Accepted`, `Live-Deployed` or `Operational` completion. No claim of being unhackable, universally compliant, certified or absolutely defect-free is permitted.

Repository HEAD, retained package artifact, deployed artifact, database/schema state and migration state are separate realities. Deployment parity must be proven independently before repository evidence may be treated as live-system evidence.
