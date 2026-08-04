# CF-01 Security and Privacy Architecture

## Classification and ownership

Clinical records are C5 restricted data. CF-01 is the sole clinical mutation owner; identity, professional verification, scheduling, messages, notifications, shell, visual presentation, assurance and media delivery remain with their native modules.

## Enforcement

- Every protected action checks current membership, suspension, capability and object context.
- Clinician actions additionally check current professional eligibility, scope, expiry and active treating relationship.
- High-risk actions require recent, subject-bound, unexpired step-up authentication.
- Consent is purpose-specific and may be withdrawn; no bundled or coerced consent is accepted.
- Fields are role/purpose allowlisted. Cache, UI, notifications and availability detection never grant access.
- Signed encounters, assessments and prescriptions use canonical snapshots and cannot be silently overwritten.
- Mutations require idempotency keys and optimistic record versions.

## Cryptography and storage

Sensitive fields use AES-256-GCM envelopes with purpose-bound AAD. Key material comes only from managed configuration or an approved provider filter; it is never stored in ordinary options, logs, repository files or browser storage. Equality links use keyed blind indexes. Audit and outbox payloads are minimized and encrypted.

## Public repository prohibitions

No real/synthetic patient fixture resembling a person, clinical narrative, identity document, credentials, tokens, keys, private URLs, provider configuration, incident exploit detail or backup location may enter this repository.

## Browser boundary

Clinical routes are authenticated, `noindex`, `no-store`, no-referrer and frame-denied. The JavaScript client uses no `localStorage`, `sessionStorage`, IndexedDB, service worker or offline clinical cache. Hidden/page-exit events abort requests and clear rendered data.

## Failure policy

Unknown/stale contract versions, unavailable keys, scanner/provider failures, audit persistence failures and authorization ambiguity fail closed. Public reading elsewhere on the platform may continue, but clinical writes and reads do not silently degrade into permissive access.
