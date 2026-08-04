<?php
defined('ABSPATH') || exit;

final class CF01_Crypto {
    private const CIPHER = 'aes-256-gcm';

    public static function available(): bool {
        return self::key() !== null && function_exists('openssl_encrypt') && function_exists('openssl_decrypt');
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

    public static function encrypt($value, string $purpose): string {
        $key = self::key();
        if ($key === null) {
            throw new RuntimeException('CF-01 encryption key is unavailable.');
        }
        $plaintext = wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($plaintext)) {
            throw new RuntimeException('Clinical value serialization failed.');
        }
        $iv = random_bytes(12);
        $tag = '';
        $aad = 'cf01|' . CF01_VERSION . '|' . $purpose;
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, $aad, 16);
        if (!is_string($ciphertext)) {
            throw new RuntimeException('Clinical encryption failed.');
        }
        return base64_encode(wp_json_encode(array(
            'v' => 1,
            'alg' => self::CIPHER,
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'ct' => base64_encode($ciphertext),
            'aad' => hash('sha256', $aad),
        ), JSON_UNESCAPED_SLASHES));
    }

    public static function decrypt(string $envelope, string $purpose) {
        $key = self::key();
        if ($key === null) {
            throw new RuntimeException('CF-01 encryption key is unavailable.');
        }
        $json = base64_decode($envelope, true);
        $payload = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($payload) || (int) ($payload['v'] ?? 0) !== 1) {
            throw new RuntimeException('Invalid clinical encryption envelope.');
        }
        $iv = base64_decode((string) ($payload['iv'] ?? ''), true);
        $tag = base64_decode((string) ($payload['tag'] ?? ''), true);
        $ciphertext = base64_decode((string) ($payload['ct'] ?? ''), true);
        if (!is_string($iv) || !is_string($tag) || !is_string($ciphertext)) {
            throw new RuntimeException('Corrupt clinical encryption envelope.');
        }
        $aad = 'cf01|' . CF01_VERSION . '|' . $purpose;
        if (!hash_equals(hash('sha256', $aad), (string) ($payload['aad'] ?? ''))) {
            throw new RuntimeException('Clinical encryption purpose mismatch.');
        }
        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, $aad);
        if (!is_string($plaintext)) {
            throw new RuntimeException('Clinical decryption failed.');
        }
        return json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);
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
        return hash_equals(self::sign($snapshot, $purpose), $signature);
    }

    public static function canonical_json(array $data): string {
        self::ksort_recursive($data);
        $json = wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        if (!is_string($json)) {
            throw new RuntimeException('Clinical canonical serialization failed.');
        }
        return $json;
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
