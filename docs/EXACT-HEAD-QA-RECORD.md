# CF-01 Exact-Head QA Record

## Reviewed implementation-release identity

- Runtime: `Sabri Clinical Records 1.0.0`
- Source branch at implementation review: `main`
- Exact reviewed implementation head: `5ad4319927713b6c4ce4b8ad459df35fe267ba9c`
- Scope: `CF01-FR-001` through `CF01-FR-032` and approved source-level cross-file contracts
- Activation: disabled by default; real patient data prohibited until all external activation gates pass

This record identifies the immutable runtime/package baseline. Later documentation-only commits may move the `main` tip without changing this reviewed installable package. Their exact-head and merge-ref results are retained by GitHub Actions and pull-request evidence rather than predicted inside a self-referential committed document.

## Exact GitHub Actions evidence

- Workflow: `CF-01 Unified R1-R4 Governance and Runtime Gates`
- Implementation-release run ID: `31042006210`
- Event: implementation-release `main` push
- Conclusion: `success`

Successful jobs:

- Governance and repository review — Python 3.11
- Governance and repository review — Python 3.12
- PHP 8.1 R1–R4 clinical review — exact head
- PHP 8.3 R1–R4 clinical review — exact head
- Forty review and correction rounds — exact head
- Policy, JavaScript and deterministic release bundle — exact head

The pull-request merge-ref compatibility job is conditional on a pull-request event and was therefore correctly skipped on that `main` push. It passed in the merged pull-request evidence. Later review and documentation pull requests must independently pass both exact-head and merge-ref gates.

## Reviewed evidence totals

- Functional requirements traced: `32/32`
- Repository/public-safe inventory at final implementation-release review: `71` files
- PHP source inventory at final implementation-release review: `35` files across repository tests and installable source
- PHP 8.1 R1–R4 clinical assertions: `393` passed
- PHP 8.3 R1–R4 clinical assertions: `393` passed
- Forty review cycles: `40/40` review pass and `40/40` correction-gate pass
- Deterministic double-build comparison: byte-identical

## Retained implementation-release evidence

- Artifact ID: `8944933830`
- Artifact name: `cf-01-release-5ad4319927713b6c4ce4b8ad459df35fe267ba9c`
- Artifact size: `105463` bytes
- Artifact archive digest: `sha256:47122c85cfb0472ff2c3f3958291dea7c65677253f4f063ef7cbf0d429964218`
- Installable ZIP SHA-256: `6967242b62250b1711c9643f29d2fda5a7c5eef9a8937d28331b28619dc3b1ea`
- SPDX 2.3 SBOM SHA-256: `9564eef0e1e458bb1ce9ffb89fd988eb9ddd67e5991bf2b4ec438a5461416bd1`

The retained bundle contains the installable ZIP, detached ZIP checksum, detached package manifest, deterministic SPDX 2.3 SBOM and detached SBOM checksum. The workflow validates the complete bundle before artifact retention.

## Truthful completion boundary

This record establishes exact-head `Coded`, `Packaged` and `Automated-QA Green` evidence within the reviewed implementation scope. It does not establish:

- qualified legal or professional approval;
- accepted native-owner operational contracts;
- independent penetration-test acceptance and retest;
- Hostinger-equivalent staging;
- real provider, region, key-management or secure-storage evidence;
- representative browser, device, RTL or accessibility acceptance;
- backup/restore and rollback rehearsal on approved infrastructure;
- live deployment or operational readiness;
- authorization to process real patient data.

CF-01 therefore remains disabled by default and is not production-operational.
