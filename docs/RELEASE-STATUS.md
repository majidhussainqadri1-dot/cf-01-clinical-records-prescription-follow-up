# CF-01 Release Status

## Current canonical source stage

- **Specified: complete** — Definitive Master Plan, All-Chats Directive Register v2.1, CF-01 conditional plan and merged C1-A governance baseline are traced.
- **Coded: complete within the approved source scope** — disabled-by-default runtime candidate implements `CF01-FR-001` through `CF01-FR-032`.
- **Governance integration: complete within the repository** — C1-A foundation, runtime and R1–R4 hardening are merged to canonical `main`.
- **Packaged: complete for the current candidate** — deterministic double-build ZIP, embedded manifest, detached ZIP checksum, SPDX 2.3 SBOM and detached SBOM checksum are retained together.
- **Automated-QA Green: complete for the exact reviewed head** — exact-head PHP 8.1/8.3, Python governance, runtime, forty-round and release-bundle gates passed.
- **Staging-Accepted: pending**
- **Live-Deployed: pending**
- **Operational: pending**

## Exact canonical evidence

- Main head: `5ad4319927713b6c4ce4b8ad459df35fe267ba9c`
- Successful main GitHub Actions run: `31042006210`
- Runtime / schema / contract: `1.0.0 / 1.0.0 / 1.0.0`
- Functional requirements traced: `32/32`
- Retained artifact ID: `8944933830`
- Artifact name: `cf-01-release-5ad4319927713b6c4ce4b8ad459df35fe267ba9c`
- Artifact size: `105463` bytes
- Artifact archive digest: `sha256:47122c85cfb0472ff2c3f3958291dea7c65677253f4f063ef7cbf0d429964218`
- Installable ZIP SHA-256: `6967242b62250b1711c9643f29d2fda5a7c5eef9a8937d28331b28619dc3b1ea`
- SPDX 2.3 SBOM SHA-256: `9564eef0e1e458bb1ce9ffb89fd988eb9ddd67e5991bf2b4ec438a5461416bd1`

The exact main workflow completed successfully for governance on Python 3.11 and 3.12, PHP R1–R4 review on PHP 8.1 and 8.3, forty review-and-correction rounds and the deterministic release-evidence bundle. The pull-request merge-ref job is intentionally applicable to pull-request events and was skipped on the final `main` push.

## Release evidence bundle law

The canonical source release bundle contains exactly these retained deliverables:

1. `cf-01-clinical-records-prescription-follow-up-1.0.0.zip`;
2. detached ZIP SHA-256 receipt;
3. detached package file manifest;
4. deterministic SPDX 2.3 JSON SBOM covering every staged plugin file;
5. detached SBOM SHA-256 receipt.

Two complete builds must be byte-identical across all five files. The workflow validates ZIP structure, internal manifest, checksums, SPDX identity, analyzed-file inventory and package relationships before retaining the complete bundle as one GitHub Actions artifact.

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

The present repository establishes `Specified`, `Coded`, `Packaged` and `Automated-QA Green` for the reviewed candidate. A ZIP, checksum, SBOM, green CI run or repository merge is not `Staging-Accepted`, `Live-Deployed` or `Operational` completion. No claim of being unhackable, universally compliant, certified or absolutely defect-free is permitted.
