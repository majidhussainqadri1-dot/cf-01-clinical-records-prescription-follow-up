# CF-01 Migration and Rollback

1. Inventory File 08 clinical-like fields and owner contracts.
2. Freeze mapping and synthetic fixtures only.
3. Execute read-only dry run with cursor receipts.
4. Create idempotent backfill candidate through CF-01 owner commands.
5. Run shadow reads and reconciliation without authorizing from legacy state.
6. Perform controlled cutover only after legal, security, staging and Founder acceptance.
7. Retain bounded rollback mapping; verify no stale authorization or deleted-record resurrection.

Migration uses a lock, resumable cursor, immutable encrypted receipt and explicit reconciliation. Rollback requires an owner-supplied completed and reconciled receipt. Direct legacy table mutation and deletion are prohibited.
