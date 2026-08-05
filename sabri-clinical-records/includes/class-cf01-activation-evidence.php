<?php
defined('ABSPATH') || exit;

/**
 * Enforces the separation between source completion and clinical activation.
 * Evidence is exact-release, environment-bound, time-bounded and single-use.
 */
final class CF01_Activation_Evidence {
    private const MAX_ACTIVATION_WINDOW = DAY_IN_SECONDS;
    private const GATES = array(
        'founder_approval',
        'legal_professional_acceptance',
        'independent_security_acceptance',
        'staging_acceptance',
        'backup_restore_acceptance',
        'rollback_rehearsal',
        'operations_staffing',
    );

    public static function register(): void {
        add_filter('pre_update_option_cf01_activation_state', array(__CLASS__, 'guard_state'), 10, 3);
        add_filter('pre_update_option_cf01_activation_evidence', array(__CLASS__, 'guard_evidence'), 10, 3);
        add_action('updated_option', array(__CLASS__, 'after_option_update'), 10, 3);
    }

    public static function guard_evidence($new_value, $old_value, string $option) {
        if ((string) get_option('cf01_activation_state', 'disabled') === 'enabled' && $new_value !== $old_value) {
            throw new RuntimeException('Activation evidence is immutable while the clinical runtime is enabled.');
        }
        return $new_value;
    }

    public static function guard_state($new_value, $old_value, string $option) {
        if ((string) $new_value !== 'enabled') {
            return $new_value;
        }
        if ((string) $old_value === 'enabled') {
            return $new_value;
        }

        $cipher = get_option('cf01_activation_evidence', '');
        $evidence = is_string($cipher) && $cipher !== '' ? CF01_Crypto::decrypt($cipher, 'activation-evidence') : null;
        $validation = self::validate(is_array($evidence) ? $evidence : array());
        $fingerprint = (string) $validation['fingerprint'];
        $last = (string) get_option('cf01_last_activation_fingerprint', '');
        if ($last !== '' && hash_equals($last, $fingerprint)) {
            throw new RuntimeException('Activation evidence replay is prohibited. A fresh approval package is required.');
        }

        self::acquire_transition_lock($fingerprint);
        update_option('cf01_pending_activation_fingerprint', $fingerprint, false);
        return $new_value;
    }

    public static function after_option_update(string $option, $old_value, $new_value): void {
        if ($option !== 'cf01_activation_state') {
            return;
        }

        if ((string) $new_value === 'enabled') {
            $fingerprint = (string) get_option('cf01_pending_activation_fingerprint', '');
            if (!self::sha256($fingerprint)) {
                self::release_transition_lock();
                throw new RuntimeException('Activation transition fingerprint is unavailable.');
            }
            $generation = (int) get_option('cf01_activation_generation', 0) + 1;
            update_option('cf01_last_activation_fingerprint', $fingerprint, false);
            update_option('cf01_activation_receipt', array(
                'fingerprint' => $fingerprint,
                'activated_at' => CF01_DB::now(),
                'generation' => $generation,
            ), false);
            update_option('cf01_activation_generation', $generation, false);
        } elseif ((string) $old_value === 'enabled') {
            update_option('cf01_disable_receipt', array(
                'disabled_at' => CF01_DB::now(),
                'generation' => (int) get_option('cf01_activation_generation', 0),
            ), false);
        }

        delete_option('cf01_pending_activation_fingerprint');
        self::release_transition_lock();
    }

    public static function validate(array $evidence): array {
        $errors = array();
        $activation_id = sanitize_text_field((string) ($evidence['activation_id'] ?? ''));
        $activation_nonce = trim((string) ($evidence['activation_nonce'] ?? ''));
        $environment = sanitize_key((string) ($evidence['environment'] ?? ''));
        $issued_at = (string) ($evidence['issued_at'] ?? '');
        $expires_at = (string) ($evidence['expires_at'] ?? '');

        if (!self::uuid($activation_id)) {
            $errors[] = 'activation_id: a valid UUID is required';
        }
        if (!preg_match('/^[A-Za-z0-9_-]{32,128}$/', $activation_nonce)) {
            $errors[] = 'activation_nonce: 32-128 URL-safe random characters are required';
        }
        if (!in_array($environment, array('staging', 'production'), true)) {
            $errors[] = 'environment: staging or production is required';
        }

        $issued = self::time($issued_at);
        $expires = self::time($expires_at);
        $now = time();
        if ($issued === null || $issued > $now || $issued < ($now - self::MAX_ACTIVATION_WINDOW)) {
            $errors[] = 'issued_at: a recent non-future UTC time is required';
        }
        if ($expires === null || $expires <= $now) {
            $errors[] = 'expires_at: a future UTC expiry is required';
        }
        if ($issued !== null && $expires !== null && $expires > ($issued + self::MAX_ACTIVATION_WINDOW)) {
            $errors[] = 'expires_at: activation evidence may not remain valid for more than 24 hours';
        }

        $expected_site_fingerprint = hash('sha256', strtolower(rtrim((string) home_url('/'), '/')) . '|' . $environment);
        $site_fingerprint = strtolower(trim((string) ($evidence['site_fingerprint'] ?? '')));
        if (!self::sha256($site_fingerprint) || !hash_equals($expected_site_fingerprint, $site_fingerprint)) {
            $errors[] = 'site_fingerprint: evidence is not bound to this exact site and environment';
        }

        $release = $evidence['release'] ?? null;
        $head_sha = '';
        if (!is_array($release)) {
            $errors[] = 'release: exact release identity is required';
            $release = array();
        } else {
            $head_sha = strtolower((string) ($release['head_sha'] ?? ''));
            if (!self::sha1($head_sha)) {
                $errors[] = 'release.head_sha: exact 40-character commit SHA is required';
            }
            if (!self::sha256((string) ($release['package_sha256'] ?? ''))) {
                $errors[] = 'release.package_sha256: exact package SHA-256 is required';
            }
            if (trim((string) ($release['package_name'] ?? '')) === '') {
                $errors[] = 'release.package_name: immutable package name is required';
            }
            if (!hash_equals(CF01_VERSION, (string) ($release['runtime_version'] ?? ''))) {
                $errors[] = 'release.runtime_version: current runtime version mismatch';
            }
            if (!hash_equals(CF01_SCHEMA_VERSION, (string) ($release['schema_version'] ?? ''))) {
                $errors[] = 'release.schema_version: current schema version mismatch';
            }
            if (!hash_equals(CF01_CONTRACT_VERSION, (string) ($release['contract_version'] ?? ''))) {
                $errors[] = 'release.contract_version: current contract version mismatch';
            }
            $built = self::time((string) ($release['built_at'] ?? ''));
            if ($built === null || $built > $now) {
                $errors[] = 'release.built_at: a valid non-future build time is required';
            }
        }

        $seen_evidence_ids = array();
        foreach (self::GATES as $gate) {
            $item = $evidence[$gate] ?? null;
            if (!is_array($item)) {
                $errors[] = $gate . ': structured evidence object is required';
                continue;
            }
            self::validate_gate($gate, $item, $head_sha, $environment, $issued, $seen_evidence_ids, $errors);
        }

        $jurisdictions = array_values(array_unique(array_filter(array_map('sanitize_text_field', (array) ($evidence['jurisdictions'] ?? array())))));
        if (!$jurisdictions) {
            $errors[] = 'jurisdictions: at least one qualified approved jurisdiction is required';
        }
        $provider_region = $evidence['provider_region'] ?? null;
        if (!is_array($provider_region) || empty($provider_region['provider']) || empty($provider_region['region']) || empty($provider_region['data_residency_decision'])) {
            $errors[] = 'provider_region: provider, region and data-residency decision are required';
        }
        $recovery = $evidence['recovery_objectives'] ?? null;
        if (!is_array($recovery)
            || !isset($recovery['rpo_minutes'], $recovery['rto_minutes'])
            || (int) $recovery['rpo_minutes'] < 0
            || (int) $recovery['rto_minutes'] < 1
            || empty($recovery['approved_by'])
        ) {
            $errors[] = 'recovery_objectives: approved numeric RPO/RTO are required';
        }

        if ($errors) {
            throw new RuntimeException('Structured activation evidence is invalid: ' . implode('; ', $errors));
        }

        $fingerprint_source = array(
            'activation_id' => $activation_id,
            'activation_nonce' => $activation_nonce,
            'environment' => $environment,
            'site_fingerprint' => $site_fingerprint,
            'issued_at' => gmdate('c', (int) $issued),
            'expires_at' => gmdate('c', (int) $expires),
            'release' => $release,
            'gate_documents' => array_map(static fn(string $gate): array => array(
                'evidence_id' => (string) $evidence[$gate]['evidence_id'],
                'document_hash' => strtolower((string) $evidence[$gate]['document_hash']),
                'tested_head' => strtolower((string) $evidence[$gate]['tested_head']),
            ), self::GATES),
        );

        return array(
            'valid' => true,
            'gate_count' => count(self::GATES),
            'jurisdictions' => $jurisdictions,
            'head_sha' => $head_sha,
            'package_sha256' => strtolower((string) $release['package_sha256']),
            'activation_id' => $activation_id,
            'environment' => $environment,
            'fingerprint' => hash('sha256', self::canonical_json($fingerprint_source)),
            'validated_at' => CF01_DB::now(),
        );
    }

    private static function validate_gate(string $gate, array $item, string $release_head, string $environment, ?int $issued_at, array &$seen_ids, array &$errors): void {
        foreach (array('evidence_id', 'document_hash', 'approved_by', 'approved_at', 'scope', 'status', 'tested_head', 'environment') as $field) {
            if (!isset($item[$field]) || trim((string) $item[$field]) === '') {
                $errors[] = $gate . '.' . $field . ': required';
            }
        }
        $evidence_id = sanitize_text_field((string) ($item['evidence_id'] ?? ''));
        if ($evidence_id !== '' && in_array($evidence_id, $seen_ids, true)) {
            $errors[] = $gate . '.evidence_id: evidence identifiers must be unique per gate';
        }
        $seen_ids[] = $evidence_id;

        if (($item['status'] ?? '') !== 'accepted') {
            $errors[] = $gate . '.status: accepted is required';
        }
        if (!self::sha256((string) ($item['document_hash'] ?? ''))) {
            $errors[] = $gate . '.document_hash: SHA-256 is required';
        }
        $tested_head = strtolower((string) ($item['tested_head'] ?? ''));
        if (!self::sha1($tested_head)) {
            $errors[] = $gate . '.tested_head: exact commit SHA is required';
        } elseif ($release_head !== '' && !hash_equals($release_head, $tested_head)) {
            $errors[] = $gate . '.tested_head: every gate must accept the exact release head';
        }
        if (!hash_equals($environment, sanitize_key((string) ($item['environment'] ?? '')))) {
            $errors[] = $gate . '.environment: gate environment must match the activation target';
        }
        $approved = self::time((string) ($item['approved_at'] ?? ''));
        if ($approved === null || $approved > time()) {
            $errors[] = $gate . '.approved_at: valid non-future UTC time is required';
        } elseif ($issued_at !== null && $approved > $issued_at) {
            $errors[] = $gate . '.approved_at: approval cannot post-date the activation package';
        }
        if (!empty($item['expires_at']) && !CF01_Authorization::not_expired((string) $item['expires_at'])) {
            $errors[] = $gate . '.expires_at: evidence has expired';
        }
        if ($gate === 'founder_approval' && empty($item['founder_authority'])) {
            $errors[] = 'founder_approval.founder_authority: explicit Founder authority is required';
        }
        if ($gate === 'independent_security_acceptance' && empty($item['independent_reviewer'])) {
            $errors[] = 'independent_security_acceptance.independent_reviewer: required';
        }
        if ($gate === 'operations_staffing') {
            $roles = array_values(array_unique(array_filter(array_map('sanitize_key', (array) ($item['assigned_roles'] ?? array())))));
            foreach (array('treating_doctor', 'clinical_supervisor', 'privacy_officer', 'records_custodian', 'incident_escalation', 'patient_support') as $required_role) {
                if (!in_array($required_role, $roles, true)) {
                    $errors[] = 'operations_staffing.assigned_roles: missing ' . $required_role;
                }
            }
        }
    }

    private static function acquire_transition_lock(string $fingerprint): void {
        $lock = get_option('cf01_activation_transition_lock', null);
        if (is_array($lock) && (int) ($lock['expires_at'] ?? 0) <= time()) {
            delete_option('cf01_activation_transition_lock');
            $lock = null;
        }
        if ($lock !== null && $lock !== false) {
            throw new RuntimeException('Another clinical activation transition is already in progress.');
        }
        $value = array('fingerprint' => $fingerprint, 'expires_at' => time() + 300);
        if (function_exists('add_option')) {
            if (!add_option('cf01_activation_transition_lock', $value, '', false)) {
                throw new RuntimeException('Another clinical activation transition is already in progress.');
            }
            return;
        }
        update_option('cf01_activation_transition_lock', $value, false);
    }

    private static function release_transition_lock(): void {
        delete_option('cf01_activation_transition_lock');
    }

    private static function canonical_json(array $value): string {
        $sort = static function (&$item) use (&$sort): void {
            if (!is_array($item)) {
                return;
            }
            foreach ($item as &$child) {
                $sort($child);
            }
            unset($child);
            if (!array_is_list($item)) {
                ksort($item, SORT_STRING);
            }
        };
        $sort($value);
        return wp_json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function time(string $value): ?int {
        try {
            return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->getTimestamp();
        } catch (Throwable $error) {
            return null;
        }
    }

    private static function uuid(string $value): bool {
        return (bool) preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $value);
    }

    private static function sha1(string $value): bool {
        return (bool) preg_match('/^[a-f0-9]{40}$/i', $value);
    }

    private static function sha256(string $value): bool {
        return (bool) preg_match('/^[a-f0-9]{64}$/i', $value);
    }
}
