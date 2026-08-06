# CF-01 — Final Three-Plan Code Hardening

**Date:** 06 August 2026  
**Scope:** source-level correction against the Definitive Master Plan v3.0, the All-Chats Recovered Directive Register v2.1, and the CF-01 Conditional Complete Master Plan v1.0.  
**Truth boundary:** this record concerns source and automated evidence only. It does not claim Hostinger staging, legal/professional approval, independent penetration-test acceptance, live deployment, operational staffing, or authorization for real patient data.

## Confirmed defect

### CF01-FH-001 — Encryption continuity was coupled to the plugin runtime version

The authenticated-data context for encrypted clinical values included `CF01_VERSION`. A normal future plugin version change could therefore make previously encrypted records fail authentication even when the same approved key remained available. This contradicted longitudinal-record continuity, reversible upgrades, key rotation, restore verification, and the rule that source/package promotion must not silently make clinical data unreadable.

**Severity:** Critical before any future version promotion or real-data use.

## Correction

`CF01_Crypto` now:

1. uses a stable, explicitly versioned cryptographic context independent of the plugin release number;
2. emits version-2 envelopes with an explicit crypto-context version and encryption-key version;
3. preserves a narrow, explicit compatibility path for version-1 envelopes created by release `1.0.0`;
4. validates the declared algorithm, envelope version, context version, IV length, GCM tag length, ciphertext presence and AAD digest before decryption;
5. normalizes and bounds encryption purposes;
6. converts malformed decrypted JSON into a controlled runtime failure;
7. rejects malformed signatures before constant-time verification;
8. retains stable root-key derivation for blind indexes and signed clinical provenance while allowing versioned encryption subkeys.

## Permanent regression evidence

`tests/test_final_code_hardening.py` is automatically discovered by every Python governance job and asserts:

- new envelope AAD is not bound to `CF01_VERSION`;
- legacy compatibility is explicit rather than silently dependent on the current runtime;
- algorithm/context/envelope failures are fail-closed;
- IV/tag/purpose bounds remain enforced;
- malformed signature values are rejected.

## Completion boundary

After exact-head and merge-ref CI pass, the corrected candidate may be described as source-coded, packaged and automated-QA green within the reviewed scope. CF-01 must remain disabled by default. External native-owner contract acceptance, independent legal/security review, synthetic-data Hostinger staging, browser/RTL/accessibility/load acceptance, backup/restore/rollback rehearsal, named operations and explicit Founder production approval remain mandatory before real-data activation.
