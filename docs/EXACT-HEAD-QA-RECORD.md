# CF-01 Exact-Head QA Record

## Candidate identity

- Runtime: `Sabri Clinical Records 1.0.0`
- Branch: `codex/cf-01-complete-runtime-1.0.0`
- Scope: CF01-FR-001 through CF01-FR-032 and the approved cross-file contracts
- Activation: disabled by default; real patient data prohibited until all external activation gates pass

## Current evidence state

The complete reviewed runtime source has been atomically materialized from a SHA-256-verified source payload. Temporary payload, diagnostic and self-modifying workflow files were removed by the materialization commit.

This record intentionally triggers exact-head GitHub Actions after materialization. The following remain pending until the resulting run is green and its evidence is recorded in Draft PR #3:

- PHP 8.1 full syntax, unit, runtime/adversarial, static and migration review;
- PHP 8.3 full syntax, unit, runtime/adversarial, static and migration review;
- inherited C1-A governance and public-safety continuity;
- JavaScript syntax and repository policy validation;
- deterministic double-build package comparison;
- embedded/detached source manifest verification;
- installable ZIP checksum and uploaded artifact identity.

No staging, live deployment, legal approval, independent penetration-test acceptance, migration rehearsal, rollback rehearsal, operational readiness or production activation is claimed by this record.
