# CF-01 Three-Plan Clinical Hardening — R5

**Date:** 06 August 2026  
**Branch:** `codex/cf01-three-plan-complete-hardening-1.0.1`  
**Governing scope:** Definitive Master Plan v3.0, All-Chats Recovered Directives v2.1 and CF-01 Conditional Complete Master Plan v1.0.

## Purpose

This round records the fresh source-level completion work performed after the earlier R1–R4 candidate. The review did not treat a prior green workflow, traceability table or package as proof that every clinical trust boundary was complete. Each material requirement was re-read against its executable domain path, negative path and recovery behavior.

## Corrected source domains

1. **Canonical relationship authority:** relationship creation now requires a current versioned native-owner source assertion; the provider may safely discover and return the opaque source reference, while absence or rejection fails closed.
2. **Object-bound authorization:** encounter, prescription, follow-up and attachment actions remain bound to the exact patient, treating relationship, purpose and permitted scope rather than a broad active relationship.
3. **Minor and guardian governance:** patient identity, age evidence, guardian authority, scope, expiry and revocation are revalidated; missing or invalid age evidence does not authorize protected processing.
4. **Clinical attachments:** delivery and relink require patient-scoped role, field policy, scan/quarantine state and actor-bound secure-media authorization; relink is restricted to records authority.
5. **Template and terminology governance:** clinical template version, historical rendering and terminology mapping/round-trip evidence are owner-asserted and fail closed.
6. **Emergency boundaries:** encounter and patient-reported red flags invoke a versioned approved emergency-policy contract and clinician alert evidence without autonomous diagnosis or prescription.
7. **Follow-up safety:** reminder opt-in, quiet hours, time zone, deterministic due state and notification truth are enforced before outbox work.
8. **Export and break-glass controls:** generation and consumption are bounded and patient-scoped; attachment manifests are privacy-minimal; emergency access has actor and patient-target misuse controls.
9. **Integrity and recovery:** signed encounters and prescriptions include UTC/local-time provenance and are cryptographically reverified during restore acceptance.
10. **Versioned encryption-key rotation:** encrypted envelopes record their key version, pre-rotation envelopes remain readable, historical key absence fails closed, rotation is capability-controlled, recent-step-up governed, reasoned and audited. Blind indexes and signed provenance continue to use the stable root key so encryption rotation does not silently invalidate identity or signatures.

## Regression corrections found during execution

Fresh CI execution exposed and corrected stale or over-broad test assumptions:

- guardian consent tests were aligned with context-bound action-time eligibility;
- prescription fixtures now include required language provenance;
- prescription safety denial is tested at the authoritative signature gate;
- legal-hold testing verifies the exact held policy state rather than assuming no other retention item can be processed;
- relationship activation denial removes records-manager authority before testing an unassigned clinician;
- permanent three-plan tests now include encryption envelope/key-version and key-rotation authorization checks.

These are test corrections only where the former assertion no longer represented the strengthened clinical law; source controls were not weakened to satisfy historical fixtures.

## Evidence law

The final exact commit, workflow run, deterministic ZIP/SBOM checksums, assertion totals and merge-ref result belong to GitHub Actions and pull-request evidence after the final content commit. This document does not predict its own future merge SHA.

## Truthful boundary

This round can establish corrected source, permanent automated regressions, deterministic packaging and exact-head/merge-ref QA when all jobs pass. It cannot establish qualified legal/professional approval, accepted native-owner operational contracts, independent penetration-test acceptance, Hostinger staging, representative browser/RTL/accessibility/load acceptance, real backup/restore rehearsal, live deployment or operational readiness. CF-01 remains disabled by default and real patient data remains prohibited until those external gates are accepted.
