# C1-A Cryptography, Storage and Attachment-Security Architecture

**Status:** Proposed architecture baseline; provider selection, implementation, independent assessment and recovery proof remain blocked.  
**Phase law:** No key, bucket, patient object, upload endpoint or clinical database is created by this document.

## 1. Security objectives

The future CF-01 storage plane must provide:

- confidentiality and integrity for C5 clinical data in transit and at rest;
- strict separation between public WordPress content and clinical data;
- per-object/field/purpose authorization before decryption or delivery;
- limited blast radius through key and storage compartmentalization;
- immutable provenance and tamper-evident audit context;
- safe quarantine and malware/content-type validation for attachments;
- time-limited, recipient-bound export delivery;
- key rotation, revocation, recovery and provider exit without silent data loss;
- backup/restore behavior that preserves deletion, hold and access-revocation truth.

Encryption is not authorization. Possession of an object URL, database identifier, storage key or decrypted cache does not grant clinical access.

## 2. Trust zones

| Zone | Permitted content | Prohibited content |
|---|---|---|
| Public repository | public-safe code, schemas, tests and architecture | patient data, keys, secrets, private runbooks, provider credentials |
| Public WordPress/application shell | route mount, generic UI and opaque references | raw clinical attachment, unrestricted chart cache, secret material |
| Clinical application service | authorized bounded clinical commands/queries | broad admin bypass, public indexing, shared permissive credentials |
| Clinical database | structured canonical clinical records and encrypted sensitive fields as approved | payment/card data, message bodies, public-content copies |
| Quarantine storage | newly uploaded encrypted objects awaiting validation | end-user delivery before all gates pass |
| Approved clinical object storage | scanned, validated and access-controlled attachments | public bucket/object listing or permanent unsigned URLs |
| Key-management service | protected key operations and metadata | plaintext master keys in code, database, logs or ordinary options |
| Audit/evidence plane | minimized actor/purpose/action/result/version evidence | clinical narrative, full identity evidence or attachment bodies |
| Backup/recovery plane | encrypted approved snapshots and reconciliation ledgers | untracked plaintext exports or indefinite orphaned copies |

## 3. Key hierarchy

A provider-neutral envelope-encryption model is required:

1. **Root/management key:** held by an approved managed key service or equivalent protected facility; never exported into application code.
2. **Environment key boundary:** development, test, staging and production use separate keys and identities. Production ciphertext must not be decrypted in lower environments.
3. **Domain/data-encryption key:** scoped to CF-01 and preferably to bounded tenant/clinic/object classes according to the approved threat model.
4. **Object/record encryption:** unique data-encryption material or cryptographically safe equivalent for attachment and sensitive-record payloads.
5. **Export-delivery key:** short-lived and independent from canonical storage keys.

Key identifiers, versions, creation/rotation state and cryptographic context may be stored; plaintext key material must not be stored in WordPress options, database rows, repository files, logs, client-side code or support tickets.

## 4. Cryptographic context and binding

Encryption/decryption operations must bind ciphertext to approved context such as:

- environment;
- domain `CF-01`;
- canonical patient/record/object UUID;
- data class and object type;
- key version;
- purpose or storage class where appropriate.

Context mismatch must fail closed. Identifiers used as context must not expose patient identity in provider consoles, object names or URLs.

## 5. Key lifecycle

Required lifecycle states:

`Proposed → Active → Rotation Pending → Retiring → Decrypt-Only → Revoked/Destroyed`

Every transition requires owner, reason, date, affected scope, rollback/recovery method and evidence. Rotation must be resumable and idempotent, with reconciliation of every affected object/record.

Emergency revocation must distinguish:

- compromised access credential;
- compromised application identity;
- suspected key compromise;
- provider outage;
- accidental deletion/disablement.

A false `healthy` state is prohibited when the key service is unavailable or verification is incomplete.

## 6. Key recovery and separation of duties

Recovery must require designated roles and dual control for high-risk actions. No single ordinary administrator, support agent, developer or clinician may both authorize and execute unrestricted key recovery.

Recovery evidence must prove:

- identity and recent step-up of approvers;
- exact key/version/scope;
- reason and incident/change reference;
- limited recovery window;
- audit and retrospective review;
- no plaintext key exposure;
- successful post-recovery reconciliation.

Recovery material and runbooks remain private; the public repository stores only public-safe policy and evidence references.

## 7. Attachment pipeline

Future attachment state machine:

`Upload Initiated → Streaming Intake → Quarantined → Type/Structure Validated → Malware Scanned → Content/Metadata Policy Checked → Available → Superseded/Rejected → Expired/Purged`

### 7.1 Intake controls

- authenticated, authorized and purpose-bound upload session;
- server-generated opaque object identifier and idempotency key;
- strict maximum size/count and bounded processing time;
- streamed upload; no trust in filename or browser MIME;
- immediate encryption before durable quarantine storage where architecture permits;
- checksum and byte count;
- no direct public/application-server execution path;
- no clinical data in object key/name, URL or provider tag.

### 7.2 Validation and scanning

Availability requires all configured gates:

- allowlisted file type and verified magic/structure;
- archive/container recursion and decompression limits if such types are later approved;
- malware scanning and scanner-version evidence;
- image/PDF/document active-content and metadata policy where applicable;
- patient/consent and attachment-purpose validation;
- duplicate/hash policy;
- reviewer requirement for sensitive images or publication handoff.

Scanner unavailable, timeout, ambiguous result or unsupported type means `Quarantined` or `Rejected`, never `Available`.

### 7.3 Delivery

Every read/download requires current server-side reauthorization for actor, object, field/type, purpose, relationship, consent/guardian and current record version. Delivery uses short-lived signed access or an authenticated streaming proxy under the approved architecture.

Required controls:

- narrow TTL and one object/scope;
- no permanent public URL;
- safe content disposition and type;
- anti-sniffing and cache restrictions;
- range-request policy where needed;
- download/access audit;
- revocation effective before expiry where feasible;
- no attachment name or patient identity in unauthorized errors.

## 8. Structured clinical storage

The implementation decision must compare and formally choose storage patterns without assuming that ordinary WordPress metadata is suitable for C5 truth.

Required properties:

- canonical owner schema and constraints;
- separate clinical UUIDs linked minimally to platform UUIDs;
- row/object version for optimistic concurrency;
- immutable signed versions and addenda;
- field-level authorization and selective encryption strategy;
- no raw clinical narrative in search, analytics or general caches;
- encrypted connections and restricted service identity;
- migration, backup, restore and provider-exit support;
- tested behavior under disk, database, key and dependency failure.

## 9. Logs, metrics and traces

Prohibited in logs/telemetry:

- clinical note, diagnosis, symptoms, remedy, potency/dose;
- patient/guardian names, contact details or identity evidence;
- attachment content or sensitive filename;
- plaintext keys, tokens, signed URLs or provider credentials;
- unrestricted query/request bodies.

Permitted only when minimized and protected:

- opaque UUID/hash;
- action/result/reason code;
- policy/contract/key version;
- timing, size bucket and provider status;
- correlation/trace ID;
- masked network/device risk context where approved.

## 10. Backup and disaster recovery

Backups must declare coverage for:

- canonical database;
- clinical object storage and quarantine state;
- key metadata and approved recovery dependency;
- audit/outbox/inbox/queue state;
- deletion/hold and provider-reconciliation ledgers;
- configuration and contract versions.

Restore acceptance requires isolated recovery, integrity checks, authorization revalidation, key availability, deletion/hold replay, downstream reconciliation and proof that revoked access/export links are not resurrected.

RPO and RTO remain `TBD` pending a business-impact analysis and operational approval.

## 11. Provider and region due diligence

Before provider approval, record:

- legal entity, service and processing/storage regions;
- security and key-management capabilities;
- access-control/audit features;
- backup/versioning/deletion behavior;
- incident/breach terms and support process;
- subcontractors and cross-border implications;
- export format, migration bandwidth and exit/deletion evidence;
- service limits, outage modes, cost and lock-in risks;
- independent review and contract approval.

No provider marketing claim alone is acceptance evidence.

## 12. Independent verification gates

Required before C1-B/C1-C implementation or staging as applicable:

- architecture and data-flow review;
- key-management and recovery tabletop;
- attachment upload/scan/delivery threat assessment;
- authorization/IDOR and signed-URL negative tests;
- path, MIME, polyglot, archive-bomb and parser-abuse tests;
- key outage/loss/rotation fault injection;
- backup/restore and deletion-reconciliation drill;
- external independent security/privacy assessment plan;
- Founder-approved residual-risk record.

## 13. Current decision

The architecture baseline is now explicit, but all provider, key, storage, region, RPO/RTO and independent-review choices remain unapproved. CF01-A-016 and CF01-A-017 therefore remain blocked from C1-B runtime use.
