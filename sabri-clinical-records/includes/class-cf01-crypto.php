<?php
defined('ABSPATH') || exit;

final class CF01_Crypto {
    private const CIPHER = 'aes-256-gcm';
    private const ENVELOPE_VERSION = 2;
    private const CRYPTO_CONTEXT_VERSION = '1';
    private const LEGACY_RUNTIME_VERSION = '1.0.0';

    public static function available(): bool {
        return self::encryption_key(self::current_key_version()) !== null
            && function_exists('openssl_encrypt')
            && function_exists('openssl_decrypt');
    }

    public static function key(): ?string {
        $material = null;
        if (defined('CF01_MASTER_KEY') && is_string(CF01_MASTER_KEY) && CF01_MASTER_KEY !== '') {
            $material = CF01_MASTER_KEY;
        }
        $material = apply_filters('cf01_master_key_material', $material);
        if (!is_string($material) || strlen($material) < 32) {
            return null;
        }
        return hash('sha256', $material, true);
    }

    public static function current_key_version(): int {
        $version = max(1, (int) get_option('cf01_crypto_key_version', 1));
        return max(1, (int) apply_filters('cf01_encryption_key_version', $version));
    }

    public static function encrypt($value, string $purpose): string {
        $key_version = self::current_key_version();
        $key = self::encryption_key($key_version);
        if ($key === null) {
            throw new RuntimeException('CF-01 encryption key is unavailable.');
        }
        $purpose = self::normalize_purpose($purpose);
        $plaintext = wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($plaintext)) {
            throw new RuntimeException('Clinical value serialization failed.');
        }
        $iv = random_bytes(12);
        $tag = '';
        $aad = self::aad($purpose, $key_version, self::CRYPTO_CONTEXT_VERSION);
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, $aad, 16);
        if (!is_string($ciphertext) || strlen($tag) !== 16) {
            throw new RuntimeException('Clinical encryption failed.');
        }
        $encoded = wp_json_encode(array(
            'v' => self::ENVELOPE_VERSION,
            'cv' => self::CRYPTO_CONTEXT_VERSION,
            'kv' => $key_version,
            'alg' => self::CIPHER,
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'ct' => base64_encode($ciphertext),
            'aad' => hash('sha256', $aad),
        ), JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            throw new RuntimeException('Clinical encryption envelope serialization failed.');
        }
        return base64_encode($encoded);
    }

    public static function decrypt(string $envelope, string $purpose) {
        $json = base64_decode($envelope, true);
        $payload = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($payload)) {
            throw new RuntimeException('Invalid clinical encryption envelope.');
        }

        $envelope_version = (int) ($payload['v'] ?? 0);
        if (!in_array($envelope_version, array(1, self::ENVELOPE_VERSION), true)) {
            throw new RuntimeException('Unsupported clinical encryption envelope version.');
        }
        if (!hash_equals(self::CIPHER, (string) ($payload['alg'] ?? ''))) {
            throw new RuntimeException('Unsupported clinical encryption algorithm.');
        }

        $legacy_has_no_key_version = !array_key_exists('kv', $payload);
        $key_version = max(1, (int) ($payload['kv'] ?? 1));
        $key = self::encryption_key($key_version);
        if ($key === null) {
            throw new RuntimeException('Required historical clinical encryption key is unavailable.');
        }

        $iv = base64_decode((string) ($payload['iv'] ?? ''), true);
        $tag = base64_decode((string) ($payload['tag'] ?? ''), true);
        $ciphertext = base64_decode((string) ($payload['ct'] ?? ''), true);
        if (!is_string($iv) || strlen($iv) !== 12 || !is_string($tag) || strlen($tag) !== 16 || !is_string($ciphertext) || $ciphertext === '') {
            throw new RuntimeException('Corrupt clinical encryption envelope.');
        }

        $purpose = self::normalize_purpose($purpose);
        $expected_aad = strtolower(trim((string) ($payload['aad'] ?? '')));
        if (!preg_match('/^[a-f0-9]{64}$/', $expected_aad)) {
            throw new RuntimeException('Corrupt clinical encryption authentication context.');
        }

        if ($envelope_version === self::ENVELOPE_VERSION) {
            $context_version = (string) ($payload['cv'] ?? '');
            if (!hash_equals(self::CRYPTO_CONTEXT_VERSION, $context_version)) {
                throw new RuntimeException('Unsupported clinical encryption context version.');
            }
            $aad = self::aad($purpose, $key_version, $context_version);
        } else {
            // Version-1 envelopes were created by release 1.0.0 and bound AAD
            // to that runtime value. This explicit compatibility path prevents
            // later plugin version promotion from making those records unreadable.
            $aad = $legacy_has_no_key_version
                ? 'cf01|' . self::LEGACY_RUNTIME_VERSION . '|' . $purpose
                : 'cf01|' . self::LEGACY_RUNTIME_VERSION . '|' . $purpose . '|key:' . $key_version;
        }

        if (!hash_equals(hash('sha256', $aad), $expected_aad)) {
            throw new RuntimeException('Clinical encryption purpose mismatch.');
        }
        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, $aad);
        if (!is_string($plaintext)) {
            throw new RuntimeException('Clinical decryption failed.');
        }
        try {
            return json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('Clinical decrypted value is invalid.', 0, $error);
        }
    }

    public static function rotate_key(int $actor_id, string $reason): int {
        CF01_Authorization::actor($actor_id, 'rotate_clinical_key');
        $reason = sanitize_textarea_field($reason);
        if (strlen($reason) < 8) {
            throw new InvalidArgumentException('A substantive clinical key-rotation reason is required.');
        }
        $current = self::current_key_version();
        $next = $current + 1;
        if (self::encryption_key($next) === null) {
            throw new RuntimeException('The next clinical encryption key version is unavailable.');
        }
        $previous = get_option('cf01_crypto_key_version', 1);
        if (!update_option('cf01_crypto_key_version', $next, false)) {
            throw new RuntimeException('Clinical encryption key version could not be advanced.');
        }
        try {
            CF01_Audit::record($actor_id, 'ClinicalEncryptionKeyRotated', 'clinical_key', (string) $next, 'security_operations', array(
                'previous_version' => $current,
                'new_version' => $next,
                'reason_hash' => hash('sha256', $reason),
            ));
        } catch (Throwable $error) {
            update_option('cf01_crypto_key_version', $previous, false);
            throw $error;
        }
        return $next;
    }

    public static function blind_index(string $value, string $purpose): string {
        $key = self::key();
        if ($key === null) {
            throw new RuntimeException('CF-01 encryption key is unavailable.');
        }
        return hash_hmac('sha256', strtolower(trim($value)), hash_hmac('sha256', $purpose, $key, true));
    }

    public static function sign(array $snapshot, string $purpose): string {
        $key = self::key();
        if ($key === null) {
            throw new RuntimeException('CF-01 signing key is unavailable.');
        }
        return hash_hmac('sha256', self::canonical_json($snapshot), hash_hmac('sha256', 'signature|' . $purpose, $key, true));
    }

    public static function verify(array $snapshot, string $purpose, string $signature): bool {
        return preg_match('/^[a-f0-9]{64}$/', $signature) === 1
            && hash_equals(self::sign($snapshot, $purpose), strtolower($signature));
    }

    public static function canonical_json(array $data): string {
        self::ksort_recursive($data);
        $json = wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        if (!is_string($json)) {
            throw new RuntimeException('Clinical canonical serialization failed.');
        }
        return $json;
    }

    private static function aad(string $purpose, int $key_version, string $context_version): string {
        return 'cf01|crypto:' . $context_version . '|' . $purpose . '|key:' . $key_version;
    }

    private static function normalize_purpose(string $purpose): string {
        $purpose = sanitize_key($purpose);
        if ($purpose === '' || strlen($purpose) > 96) {
            throw new InvalidArgumentException('A valid bounded clinical encryption purpose is required.');
        }
        return $purpose;
    }

    private static function encryption_key(int $version): ?string {
        if ($version < 1) {
            return null;
        }
        $root = self::key();
        if ($root === null) {
            return null;
        }
        $derived = $version === 1
            ? $root
            : hash_hmac('sha256', 'cf01-encryption-key-version|' . $version, $root, true);
        $material = apply_filters('cf01_encryption_key_material', $derived, $version);
        if (!is_string($material) || strlen($material) < 32) {
            return null;
        }
        return strlen($material) === 32 ? $material : hash('sha256', $material, true);
    }

    private static function ksort_recursive(array &$value): void {
        foreach ($value as &$item) {
            if (is_array($item)) {
                self::ksort_recursive($item);
            }
        }
        unset($item);
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
    }
}
