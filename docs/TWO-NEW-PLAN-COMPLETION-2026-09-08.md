# CF-01 Two-New-Plan Source Completion — 2026-09-08

## Governing sources

This source-completion batch is governed by the two newly supplied plans:

1. `Sabri-Social-Homeopathy-Platform-Three-Central-Plans-Consolidated-Governing-Master-Plan-2026` — the current consolidated platform constitution and release law.
2. `CF-01-Clinical-Records-Prescription-Follow-Up-Conditional-Complete-Master-Plan-2026-v1.0` — the current CF-01 owner, functional, security, privacy, lifecycle, migration and Definition-of-Done specification.

The batch began from exact repository `main` head `9e58cf82dbb84f1a49804aa714a823878364f1f7`. It does not treat repository state as deployed/live state.

## Review → ledger freeze → correction doctrine

A fresh source review was completed before correction. The defect ledger was then frozen and corrected as one integrated source batch. The corrections are intentionally limited to repository-correctable defects; legal/professional approval, independent penetration testing, Hostinger-equivalent staging acceptance, real provider acceptance, production deployment and operational staffing cannot be manufactured by source code.

## Frozen defect ledger and corrections

| ID | Defect proven in exact baseline | Correction |
|---|---|---|
| NPC-001 | Idempotent REST command receipts did not make the clinical mutation + audit/outbox + completed receipt one atomic transaction. A failure after mutation could leave reconciliation ambiguity. | `CF01_DB::idempotent()` now commits the mutation and completed response receipt atomically, rolls clinical writes back on failure, and retains only the durable failed/processing receipt outside the clinical transaction. |
| NPC-002 | Audit-chain head selection was not locked while appending, allowing a concurrent chain-fork risk. | Audit append now uses a transaction plus `SELECT ... FOR UPDATE` on the chain head before insert, with rollback on audit persistence failure. |
| NPC-003 | Outbox stored canonical CamelCase event names through `sanitize_key()`, lowercasing them; later case-sensitive BreakGlass/FollowUp/Prescription classification could silently fall back to generic delivery policy. | Canonical event names are strictly validated and case-preserved; routing/policy classification is deliberately case-insensitive, including old lower-case retained rows. |
| NPC-004 | Treating-relationship termination queried `prescriptions.relationship_uuid`, a column not present in the canonical prescription schema. | Termination reconciliation now joins prescription → encounter → relationship using canonical foreign keys. |
| NPC-005 | Relationship termination could skip reconciliation entirely when no open encounter/prescription existed and did not enumerate open follow-ups or prove transfer/retention closure. | Every termination now requires a bounded structured reconciliation contract covering open encounters, prescriptions, follow-ups, continuity instructions, transfer and retention before access is ended. |
| NPC-006 | Main longitudinal timeline cursor encoded timestamp + UUID but decoded only the timestamp, so equal-timestamp rows could repeat across pages. | Cursor filtering now uses the strict `(timestamp, UUID)` tuple for every timeline source and access event, with patient-bound HMAC validation. |
| NPC-007 | `/me` requested `access_history` by default but the projection silently omitted it. | Own-record projection now returns a privacy-minimal access history with actor category and break-glass indicator, without staff/secret identifiers. |
| NPC-008 | Runtime conflicts such as stale versions and concurrent/state-transition conflicts were returned as generic 403 responses. | Both REST surfaces now return privacy-safe HTTP 409 `clinical_conflict` responses while preserving non-enumerating 403 behavior for ordinary protected failures. |
| NPC-009 | Records-role authorized view treated the retention ledger as if it contained `patient_uuid` and used a nonexistent `ledger_uuid`. | Retention projection now resolves the patient-owned clinical object UUID set, queries the canonical `object_uuid`, returns the real `retention_uuid`, and stays bounded. |

## Permanent regression gate

`tests/test_two_new_plan_completion.py` permanently guards the nine correction classes above. It is discovered by the repository Python governance test run and is therefore part of exact-head CI.

## Truth boundary

This batch may establish only source-level `Coded`, `Packaged` and `Automated-QA Green` after exact-head CI succeeds. It does not establish `Staging-Accepted`, `Live-Deployed` or `Operational`.

External acceptance remains controlled by the current plans and activation evidence gates. Real patient data, File 08 production extraction, production schema installation and live operation remain prohibited until those gates are independently accepted and Founder-approved.

## Live-First status

- Repository baseline reviewed: `9e58cf82dbb84f1a49804aa714a823878364f1f7`
- Corrective branch: `review/cf01-two-new-plans-completion-2026-09-08`
- Deployed version: unverified
- Production DB version: unverified
- Production migration state: unverified
- Live verification: not performed in this source-correction batch

Exact deployed code remains unverified; repository evidence must not be represented as live deployment evidence.
