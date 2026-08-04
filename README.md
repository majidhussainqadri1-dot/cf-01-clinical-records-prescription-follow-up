# CF-01 — Clinical Records, Prescription and Follow-Up

Conditional, disabled-by-default WordPress clinical system-of-record candidate for the Sabri Social Homeopathy Platform.

## Truthful status

- Plan scope: CF01-FR-001–CF01-FR-032.
- Runtime candidate: 1.0.0.
- Activation: disabled by default.
- Real patient data: prohibited until legal/professional, independent security, staging, backup/restore, rollback, operational staffing and Founder gates are accepted.
- This public repository contains no patient data, identity evidence, keys, provider secrets or private incident runbooks.

## Canonical ownership

CF-01 owns clinical patients, treating relationships, purpose-specific clinical consent, encounters, observations, assessments, prescriptions, follow-ups, outcomes, access history, rights cases, break-glass and clinical retention. It does not own authentication, doctor verification, appointments, general messages, notifications, global shell, public UI, security governance, general search, payments or media infrastructure.

## Development

```bash
find sabri-clinical-records tests -name '*.php' -type f -print0 | xargs -0 -n1 php -l
node --check sabri-clinical-records/assets/js/clinical.js
php tests/unit.php
php tests/runtime-adversarial.php
php tests/static-audit.php
php tests/migration-review.php
php tests/fresh-review.php
python3 tools/validate_runtime.py .
bash tools/package.sh
```

The release workflow repeats these gates on PHP 8.1 and PHP 8.3 and builds the installable ZIP twice for byte-for-byte comparison.
