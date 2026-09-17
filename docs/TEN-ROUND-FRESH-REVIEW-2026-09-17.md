# CF-01 Fresh Ten-Round Review — 17 September 2026

## Governing method

This ledger records a new ten-round source review performed after the earlier 16 September review. It is repository evidence only; it does not prove staging or live deployment.

Every numbered round was **audited to completion first**. During the audit phase no correction for that round was started. The complete defect set was frozen before the correction phase. Only after all confirmed defects from that round were corrected and the required verification gate passed did the next numbered round begin. No numbered next round began before the preceding round's correction/verification phase had completed.

Starting repository head for this fresh sequence: `8ad206bf14f91f8afcc7378881fe75ad4f356f8f`.

The governing comparison basis was the CF-01 Conditional Complete Master Plan 2026 with the Future24 amendment, the platform central governing plan, canonical repository contracts, runtime source and exact-head automated evidence.

## Round 1 — Package, source, syntax and repository hygiene

Audit scope: retained exact-head release artifact, plugin manifest, package structure, PHP syntax, source/runtime placement, forbidden secret/runtime patterns and general repository hygiene.

Frozen defect set: **none**.

Correction phase: none required.

Verification: exact artifact structure and manifest verified; PHP source syntax was clean; no unresolved TODO/FIXME/HACK or prohibited executable/runtime artifact was established as a defect.

## Round 2 — Authorization, abuse throttling, replay and mutation perimeter

Audit scope: action-time authorization, capability narrowing, Future24 route perimeter, request bounds, idempotency/replay behavior, rights/export and break-glass throttling.

Frozen defect set: **one confirmed defect class**.

Defect: `CF01_Authorization::enforce_rate_limit()` used a transient read-then-write counter without serialization. Concurrent requests could read the same counter value and lose increments, weakening the intended bounded abuse control under race conditions.

Correction phase: production rate limiting was serialized with a bounded MySQL advisory lock and made fail-closed when locking or counter persistence is unavailable. Regression coverage was added. The lightweight PHP test database was then taught the same advisory-lock semantics so verification exercised the production contract instead of weakening it.

Correction commits included `199a76e303006da9421129d2adfda0038ca4412b`, `160fcf4c6a4dc5ef4072b9810a4011baf52c8174` and the final test-harness compatibility correction `fc16570b7b5a850ca5b35ab93d88de6a74781d7f`.

Verification gate before Round 3: exact-head workflow run `35181999501` completed successfully across Python 3.11/3.12, PHP 8.1/8.3, the forty-round runner and deterministic release-bundle gates.

## Round 3 — Database transactions, idempotency and optimistic concurrency

Audit scope: nested transactions/savepoints, rollback failure behavior, versioned writes, idempotency receipt uniqueness, duplicate races, failed command replay and stale-version conflict handling.

Frozen defect set: **none**.

Correction phase: none required.

Verification: transaction/savepoint and command receipt controls remained fail-closed; unique idempotency key hashes and request hashes prevent unsafe key reuse; stale or failed commands require reconciliation rather than automatic duplicate execution.

## Round 4 — Cryptography, secrets and clinical attachments

Audit scope: clinical encryption envelopes, authenticated encryption parameters, key/version context, blind indexes/signatures, attachment quarantine/scanning, MIME/hash evidence, consent revalidation and secure delivery.

Frozen defect set: **none**.

Correction phase: none required.

Verification: AES-256-GCM envelope validation, AAD/purpose binding, bounded legacy context, clinical attachment quarantine, detected/declared type checks, current purpose-specific consent and non-public expiring delivery controls were present and covered by regression evidence.

## Round 5 — Schema, migrations, activation, rollback and restore

Audit scope: schema inventory, columns/indexes, migration locking, activation evidence, native-owner contract binding, schedules, compensation, File 08 migration controls, rollback, backup/restore and resurrection prevention.

Frozen defect set: **none**.

Correction phase: none required.

Verification: schema verification is derived from canonical create statements and checks columns/indexes; migration locking is database-atomic; activation is evidence-bound and compensated on abandonment/failure; migration/rollback writes and ledgers are transactional; restore verification checks counts/integrity/holds and no-deleted-record resurrection before reopening.

## Round 6 — Core clinical workflows, consent propagation and follow-up state machines

Audit scope: encounter and prescription state changes, signed immutability, addenda, current consent propagation, patient-reported outcomes, clinician review, reschedule/overdue/close paths and treatment-autonomy boundaries.

Frozen defect set: **none**.

Correction phase: none required.

Verification: current care/teleconsultation consent is rechecked on protected future mutations; signed clinical cores remain immutable; follow-up transitions cannot bypass contact resolution; patient-reported outcomes remain labeled and cannot automatically mutate treatment.

## Round 7 — Rights, break-glass, audit/outbox and retention/deletion

Audit scope: patient rights requests/exports/corrections, separation of duties, one-time export delivery, break-glass TTL/review/abuse controls, audit hash-chain/outbox behavior, retention holds and provider purge lifecycle.

Frozen defect set: **one confirmed defect class**.

Defect: retention purge did not share a serialized transition boundary with legal-hold changes, and provider/finalization failure could leave the ledger looking merely `scheduled` after an external purge attempt. That created a race/ambiguity against the plan's hold protection, provider-purge evidence and universal transition/reconciliation requirements.

Correction phase: retention hold/purge operations now share a record-scoped advisory lock and locked revalidation. Purge uses explicit `purge_pending` and `purge_failed` states, fail-closed provider receipt validation, atomic transition audit records and retry/reconciliation visibility. A hold can stop a recovered pending purge, and completed purges reject new holds. Regression coverage was added in `tests/test_fresh_n14_retention_purge_reconciliation.py`.

Correction commits: `300e9dc0c6f83801313ae4ed26e64203144e0d38` and `b8deab13c8aebf6cfdfb407a47f2e0c634ea4996`.

Verification gate before Round 8: exact-head workflow run `35182433635` completed successfully across Python 3.11/3.12, PHP 8.1/8.3, policy/JavaScript/package validation and the forty-round review runner. The Python suite reported `146 PASS`; runtime validation reported `112 public-safe files, 38 PHP files, 32/32 requirements traced`. The deterministic installable ZIP SHA-256 was `5477ae58c9516e4a08e132fdfe95879fa4e671e042fb336b1e0daff5b71bf0a3` and the SPDX 2.3 SBOM SHA-256 was `f9b54e480b29ace15c2ebd9995a4c19482f21c2992f4d121a757b51693da9baa`.

## Round 8 — Future24 governance and external integration safety

Audit scope: Future24 disabled/shadow/enabled governance, exact activation/release binding, provider response assertions, patient-scoped access, institutional webhooks, simulation boundaries, transparency and clinical autonomy restrictions.

Frozen defect set: **none**.

Correction phase: none required.

Verification: Future24 source presence remains non-activation; non-disabled states require evidence bound to the current activation fingerprint/generation, exact head/package/environment and runtime/schema/contract versions. Institutional webhooks require privileged capability, recent step-up, timestamp/nonce/signature verification and explicit integration authorization. Provider response guards reject forbidden secrets, autonomous treatment actions, financial/donor clinical bias and real-subject simulation leakage.

## Round 9 — UI privacy, accessibility boundaries, workflow and deterministic packaging

Audit scope: private clinical routing/shell, authentication boundary, no-offline-storage signal, accessibility hooks, health disclosure boundary, exact-head workflow pinning, JavaScript syntax, repository/runtime validators, deterministic packaging, manifest/checksums and SPDX SBOM.

Frozen defect set: **none**.

Correction phase: none required.

Verification: protected routes remain authenticated/private; health output separates public-safe state from privileged contract/queue evidence; workflow actions are commit-pinned; exact-head CI spans PHP 8.1/8.3 and Python 3.11/3.12; the release ZIP is built twice and required to be byte-identical with checksum, manifest and SPDX validation.

## Round 10 — Plan parity, release evidence and documentation consistency

Audit scope: final CF-01 requirement parity, Future24 count, release status, historical/current review evidence separation, exact reviewed implementation identity, CI/artifact/checksum metadata and the live-vs-repository evidence boundary.

Frozen defect set: **one confirmed defect class**.

Defect: `docs/RELEASE-STATUS.md` still identified the older `91a1fbb0b6e79768f629d18df389255737a0f23a` implementation artifact even though this fresh review had produced a newer fully green runtime baseline containing the Round-2 and Round-7 corrections. The document was therefore stale as release evidence.

Correction phase: `docs/RELEASE-STATUS.md` was refreshed to the exact fully green implementation baseline `b8deab13c8aebf6cfdfb407a47f2e0c634ea4996`, workflow run `35182433635`, artifact `10480189826`, current test/runtime counts and current ZIP/SBOM digests. This fresh ten-round ledger was added so the new review is not confused with the historical 16 September ledger.

Verification requirement: the post-correction documentation/evidence HEAD must itself pass the same exact-head governance/runtime workflow before this round may be reported complete. Because the Round-10 changes are documentation/evidence-only, the retained reviewed runtime/package implementation baseline remains `b8deab13c8aebf6cfdfb407a47f2e0c634ea4996`.

## Defect-round summary

Confirmed defects were found in **Rounds 2, 7 and 10**.

Rounds **1, 3, 4, 5, 6, 8 and 9** completed without a newly confirmed defect requiring correction.

The Round-2 and Round-7 runtime corrections were verified green before the following numbered round began. Round 10 is complete only after its own post-correction exact-head workflow is green.

## Evidence boundary

This ledger proves repository review/correction status only. It does not prove staging acceptance, production deployment, production database/schema state, production migration state or live behavior. Repository HEAD, retained package, deployed artifact, database state and migration state remain separate realities until deployment parity and live re-test are independently established.
