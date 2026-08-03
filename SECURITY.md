# Security Policy

## Current security status

CF-01 is in the C1-A governance and architecture phase. It is not an operational clinical system and must not receive real patient data, clinical attachments, credentials, provider secrets or production logs.

## Reporting a vulnerability

Do not disclose a suspected vulnerability, patient information or sensitive evidence in a public issue or pull request. Contact the repository owner privately through an approved secure channel and provide only the minimum information needed to establish the problem. Credentials, patient records and unrestricted production exports must never be transmitted as proof.

## Public repository rules

The following material is prohibited:

- patient, guardian or clinician personal information used as clinical data;
- clinical notes, prescriptions, images, attachments, exports or database dumps;
- passwords, tokens, private keys, certificates or provider credentials;
- encryption-key recovery material;
- unrestricted logs, traces or screenshots containing sensitive fields;
- private incident-response runbooks, exploitation instructions or infrastructure maps that would materially increase attack risk.

All examples and test fixtures must be synthetic, non-identifying and explicitly marked synthetic.

## C1-A security gates

Before clinical runtime implementation begins, the project requires an approved threat model, data-flow map, authorization matrix, encryption/key architecture, secure object-storage and scanner design, immutable audit strategy, backup/restore reconciliation, break-glass controls and independent review plan.

## Mandatory runtime principles for later phases

- Authentication is not authorization.
- Every protected request must revalidate actor, object, field, purpose, relationship, consent, guardian/age, suspension, professional verification, recent authentication, record state and expected version.
- Unknown or incompatible mandatory contracts fail closed.
- Signed encounters and prescriptions are immutable; changes use addenda or supersession.
- Clinical attachments remain quarantined until validated and scanned.
- Sensitive responses are no-store, noindex and never placed in shared caches.
- Logs and events exclude raw clinical narratives, identity evidence and secrets.
- Break-glass is clinician-only, minimum-field, time-limited, reasoned, audited and retrospectively reviewed.
- Backup restoration must reconcile deletion, restriction, consent, legal holds and authorization before availability.

## Supported versions

No production version is supported at this stage. Security support begins only after an approved, tagged and Staging-Accepted release exists.
