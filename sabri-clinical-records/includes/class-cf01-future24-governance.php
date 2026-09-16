<?php
defined('ABSPATH') || exit;

/**
 * Exact-activation governance for Future24 feature states.
 *
 * Feature evidence is accepted only when it is bound to the currently accepted
 * core activation receipt, exact repository/package identity, environment and
 * runtime/schema/contract versions. Source presence never activates a feature.
 */
final class CF01_Future24_Governance {
    private const EVIDENCE_OPTION = 'cf01_future24_governance_evidence';
    private const STATES_OPTION = 'cf01_future24_feature_states';
    private const STATES = array('disabled', 'shadow', 'enabled');
    private const DATA_GOVERNANCE = array('CF01-FUT-020', 'CF01-FUT-021', 'CF01-FUT-022', 'CF01-FUT-023');
    private const BASE_HASHES = array(
        'founder_approval_hash',
        'privacy_review_hash',
        'clinical_safety_review_hash',
        'security_review_hash',
        'staging_acceptance_hash',
        'rollback_evidence_hash',
    );

    public static function register(): void {
        add_filter('pre_update_option_' . self::STATES_OPTION, array(__CLASS__, 'guard_states'), 10, 3);
        add_filter('pre_update_option_' . self::EVIDENCE_OPTION, array(__CLASS__, 'guard_evidence'), 10, 3);
        add_filter('option_' . self::EVIDENCE_OPTION, array(__CLASS__, 'filter_runtime_evidence'), 10, 2);
        add_action('updated_option', array(__CLASS__, 'after_option_update'), 20, 3);
    }

    public static function guard_states($new_value, $old_value, string $option) {
        unset($option);
        if (!is_array($new_value)) {
            throw new InvalidArgumentException('Future24 feature states must be a keyed array.');
        }
        $manifest = CF01_Future24::manifest();
        foreach ($new_value as $id => $state) {
            $id = strtoupper(trim((string) $id));
            $state = sanitize_key((string) $state);
            if (!isset($manifest[$id])) {
                throw new InvalidArgumentException('Unknown Future24 feature state identifier.');
            }
            if (!in_array($state, self::STATES, true)) {
                throw new InvalidArgumentException('Future24 feature state must be disabled, shadow or enabled.');
            }
            if ($state !== 'disabled') {
                if (!CF01_DB::is_enabled()) {
                    throw new RuntimeException('Future24 cannot leave disabled state while the core clinical runtime is not accepted and enabled.');
                }
                $evidence = get_option(self::EVIDENCE_OPTION, array());
                if (!is_array($evidence) || !isset($evidence[$id]) || !is_array($evidence[$id])) {
                    throw new RuntimeException('Exact-release Future24 governance evidence is required before shadow or enabled state.');
                }
            }
        }
        foreach ((array) $old_value as $id => $old_state) {
            if (!array_key_exists($id, $new_value) && in_array((string) $old_state, array('shadow', 'enabled'), true)) {
                throw new RuntimeException('Active Future24 state may not be silently removed; explicitly set it to disabled.');
            }
        }
        return $new_value;
    }

    public static function guard_evidence($new_value, $old_value, string $option) {
        unset($option);
        $states = get_option(self::STATES_OPTION, array());
        foreach ((array) $states as $state) {
            if (in_array((string) $state, array('shadow', 'enabled'), true) && $new_value !== $old_value) {
                throw new RuntimeException('Future24 governance evidence is immutable while any feature is shadow or enabled. Disable governed features before replacing evidence.');
            }
        }
        if (!is_array($new_value)) {
            throw new InvalidArgumentException('Future24 governance evidence must be a keyed array.');
        }
        $manifest = CF01_Future24::manifest();
        foreach ($new_value as $id => $evidence) {
            $id = strtoupper(trim((string) $id));
            if (!isset($manifest[$id]) || !is_array($evidence)) {
                throw new InvalidArgumentException('Future24 governance evidence contains an unknown or malformed feature entry.');
            }
            if (!self::evidence_matches_current_activation($id, $evidence)) {
                throw new RuntimeException('Future24 governance evidence is not bound to the exact accepted clinical activation/release.');
            }
        }
        return $new_value;
    }

    public static function filter_runtime_evidence($value, string $option) {
        unset($option);
        if (!is_array($value)) {
            return array();
        }
        $filtered = array();
        foreach ($value as $id => $evidence) {
            $id = strtoupper(trim((string) $id));
            if (is_array($evidence) && self::evidence_matches_current_activation($id, $evidence)) {
                $filtered[$id] = $evidence;
            }
        }
        return $filtered;
    }

    /**
     * Enrich the core immutable activation receipt after its own validator has
     * accepted the exact release. Future24 subsequently binds to this receipt.
     */
    public static function after_option_update(string $option, $old_value, $new_value): void {
        unset($old_value);
        if ($option !== 'cf01_activation_state' || (string) $new_value !== 'enabled') {
            return;
        }
        $receipt = get_option('cf01_activation_receipt', array());
        $cipher = get_option('cf01_activation_evidence', '');
        if (!is_array($receipt) || !is_string($cipher) || $cipher === '') {
            return;
        }
        try {
            $evidence = CF01_Crypto::decrypt($cipher, 'activation-evidence');
        } catch (Throwable $error) {
            return;
        }
        if (!is_array($evidence) || !is_array($evidence['release'] ?? null)) {
            return;
        }
        $release = $evidence['release'];
        $receipt['head_sha'] = strtolower(trim((string) ($release['head_sha'] ?? '')));
        $receipt['package_sha256'] = strtolower(trim((string) ($release['package_sha256'] ?? '')));
        $receipt['package_name'] = sanitize_file_name((string) ($release['package_name'] ?? ''));
        $receipt['environment'] = sanitize_key((string) ($evidence['environment'] ?? ''));
        $receipt['activation_id'] = sanitize_text_field((string) ($evidence['activation_id'] ?? ''));
        $receipt['runtime_version'] = (string) ($release['runtime_version'] ?? '');
        $receipt['schema_version'] = (string) ($release['schema_version'] ?? '');
        $receipt['contract_version'] = (string) ($release['contract_version'] ?? '');
        update_option('cf01_activation_receipt', $receipt, false);
    }

    private static function evidence_matches_current_activation(string $id, array $evidence): bool {
        if (!isset(CF01_Future24::manifest()[$id])) {
            return false;
        }
        $receipt = get_option('cf01_activation_receipt', array());
        if (!is_array($receipt) || (string) get_option('cf01_activation_state', 'disabled') !== 'enabled') {
            return false;
        }
        if (!self::sha256((string) ($receipt['fingerprint'] ?? ''))
            || (int) ($receipt['generation'] ?? 0) < 1
            || !self::sha1((string) ($receipt['head_sha'] ?? ''))
            || !self::sha256((string) ($receipt['package_sha256'] ?? ''))
            || !in_array((string) ($receipt['environment'] ?? ''), array('staging', 'production'), true)
        ) {
            return false;
        }

        $exact = array(
            'activation_fingerprint' => (string) $receipt['fingerprint'],
            'activation_generation' => (string) ((int) $receipt['generation']),
            'head_sha' => strtolower((string) $receipt['head_sha']),
            'package_sha256' => strtolower((string) $receipt['package_sha256']),
            'environment' => (string) $receipt['environment'],
            'runtime_version' => CF01_VERSION,
            'schema_version' => CF01_SCHEMA_VERSION,
            'contract_version' => CF01_CONTRACT_VERSION,
        );
        foreach ($exact as $key => $expected) {
            $actual = (string) ($evidence[$key] ?? '');
            if ($key === 'head_sha' || $key === 'package_sha256' || $key === 'activation_fingerprint') {
                $actual = strtolower($actual);
            }
            if (!hash_equals($expected, $actual)) {
                return false;
            }
        }

        foreach (array('founder_approved', 'privacy_reviewed', 'clinical_safety_reviewed', 'security_reviewed', 'staging_accepted', 'rollback_ready') as $flag) {
            if (($evidence[$flag] ?? false) !== true) {
                return false;
            }
        }
        foreach (self::BASE_HASHES as $field) {
            if (!self::sha256((string) ($evidence[$field] ?? ''))) {
                return false;
            }
        }
        if (in_array($id, self::DATA_GOVERNANCE, true)) {
            if (($evidence['data_governance_approved'] ?? false) !== true || !self::sha256((string) ($evidence['data_governance_hash'] ?? ''))) {
                return false;
            }
        }
        $approved_at = self::time((string) ($evidence['approved_at'] ?? ''));
        if ($approved_at === null || $approved_at > time()) {
            return false;
        }
        if (!empty($evidence['expires_at'])) {
            $expires_at = self::time((string) $evidence['expires_at']);
            if ($expires_at === null || $expires_at <= time()) {
                return false;
            }
        }
        return true;
    }

    private static function time(string $value): ?int {
        try {
            return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->getTimestamp();
        } catch (Throwable $error) {
            return null;
        }
    }

    private static function sha1(string $value): bool {
        return (bool) preg_match('/^[a-f0-9]{40}$/i', trim($value));
    }

    private static function sha256(string $value): bool {
        return (bool) preg_match('/^[a-f0-9]{64}$/i', trim($value));
    }
}
