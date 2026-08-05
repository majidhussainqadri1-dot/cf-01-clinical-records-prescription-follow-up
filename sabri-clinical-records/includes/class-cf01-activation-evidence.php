<?php
defined('ABSPATH') || exit;

/**
 * Enforces the separation between source completion and clinical activation.
 * No scalar/non-empty placeholder can satisfy a high-risk activation gate.
 */
final class CF01_Activation_Evidence {
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
    }

    public static function guard_state($new_value, $old_value, string $option) {
        if ((string) $new_value !== 'enabled') {
            return $new_value;
        }
        $cipher = get_option('cf01_activation_evidence', '');
        $evidence = is_string($cipher) && $cipher !== '' ? CF01_Crypto::decrypt($cipher, 'activation-evidence') : null;
        self::validate(is_array($evidence) ? $evidence : array());
        return $new_value;
    }

    public static function validate(array $evidence): array {
        $errors = array();
        foreach (self::GATES as $gate) {
            $item = $evidence[$gate] ?? null;
            if (!is_array($item)) {
                $errors[] = $gate . ': structured evidence object is required';
                continue;
            }
            self::validate_gate($gate, $item, $errors);
        }

        $release = $evidence['release'] ?? null;
        if (!is_array($release)) {
            $errors[] = 'release: exact release identity is required';
        } else {
            if (!self::sha1((string) ($release['head_sha'] ?? ''))) {
                $errors[] = 'release.head_sha: exact 40-character commit SHA is required';
            }
            if (!self::sha256((string) ($release['package_sha256'] ?? ''))) {
                $errors[] = 'release.package_sha256: exact package SHA-256 is required';
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
        }

        $jurisdictions = array_values(array_filter(array_map('sanitize_text_field', (array) ($evidence['jurisdictions'] ?? array()))));
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
        return array(
            'valid' => true,
            'gate_count' => count(self::GATES),
            'jurisdictions' => $jurisdictions,
            'head_sha' => (string) $release['head_sha'],
            'package_sha256' => (string) $release['package_sha256'],
            'validated_at' => CF01_DB::now(),
        );
    }

    private static function validate_gate(string $gate, array $item, array &$errors): void {
        foreach (array('evidence_id', 'document_hash', 'approved_by', 'approved_at', 'scope', 'status', 'tested_head') as $field) {
            if (!isset($item[$field]) || trim((string) $item[$field]) === '') {
                $errors[] = $gate . '.' . $field . ': required';
            }
        }
        if (($item['status'] ?? '') !== 'accepted') {
            $errors[] = $gate . '.status: accepted is required';
        }
        if (!self::sha256((string) ($item['document_hash'] ?? ''))) {
            $errors[] = $gate . '.document_hash: SHA-256 is required';
        }
        if (!self::sha1((string) ($item['tested_head'] ?? ''))) {
            $errors[] = $gate . '.tested_head: exact commit SHA is required';
        }
        if (!self::valid_past_time((string) ($item['approved_at'] ?? ''))) {
            $errors[] = $gate . '.approved_at: valid non-future UTC time is required';
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
            $roles = array_values(array_filter(array_map('sanitize_key', (array) ($item['assigned_roles'] ?? array()))));
            foreach (array('treating_doctor', 'clinical_supervisor', 'privacy_officer', 'records_custodian', 'incident_escalation', 'patient_support') as $required_role) {
                if (!in_array($required_role, $roles, true)) {
                    $errors[] = 'operations_staffing.assigned_roles: missing ' . $required_role;
                }
            }
        }
    }

    private static function valid_past_time(string $value): bool {
        try {
            $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
            return $date->getTimestamp() <= time();
        } catch (Throwable $error) {
            return false;
        }
    }

    private static function sha1(string $value): bool {
        return (bool) preg_match('/^[a-f0-9]{40}$/i', $value);
    }

    private static function sha256(string $value): bool {
        return (bool) preg_match('/^[a-f0-9]{64}$/i', $value);
    }
}
