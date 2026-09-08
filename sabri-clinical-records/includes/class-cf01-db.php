<?php
defined('ABSPATH') || exit;

final class CF01_DB {
    private const TABLES = array(
        'patients' => 'cf01_clinical_patients',
        'relationships' => 'cf01_care_relationships',
        'consents' => 'cf01_clinical_consents',
        'encounters' => 'cf01_encounters',
        'observations' => 'cf01_observations',
        'attachments' => 'cf01_attachments',
        'assessments' => 'cf01_assessments',
        'prescriptions' => 'cf01_prescriptions',
        'followups' => 'cf01_followups',
        'outcomes' => 'cf01_patient_outcomes',
        'access' => 'cf01_access_events',
        'rights' => 'cf01_rights_cases',
        'breakglass' => 'cf01_break_glass',
        'audit' => 'cf01_audit_ledger',
        'outbox' => 'cf01_outbox',
        'retention' => 'cf01_retention_ledger',
        'migrations' => 'cf01_migrations',
        'commands' => 'cf01_command_receipts',
    );

    private static array $registered = array();
    private static int $transaction_depth = 0;

    public static function register_tables(): void {
        foreach (self::TABLES as $key => $suffix) {
            self::$registered[$key] = self::prefix() . $suffix;
        }
    }

    public static function table(string $key): string {
        if (!isset(self::$registered[$key])) {
            self::register_tables();
        }
        if (!isset(self::$registered[$key])) {
            throw new InvalidArgumentException('Unknown CF-01 table key.');
        }
        return self::$registered[$key];
    }

    public static function prefix(): string {
        global $wpdb;
        return isset($wpdb->prefix) ? (string) $wpdb->prefix : 'wp_';
    }

    public static function now(): string {
        return gmdate('Y-m-d H:i:s');
    }

    public static function uuid(): string {
        if (function_exists('wp_generate_uuid4')) {
            return wp_generate_uuid4();
        }
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function transaction(callable $callback) {
        global $wpdb;

        $depth = self::$transaction_depth;
        $savepoint = 'cf01_sp_' . $depth;
        $begin_sql = $depth === 0 ? 'START TRANSACTION' : 'SAVEPOINT ' . $savepoint;
        if ($wpdb->query($begin_sql) === false) {
            throw new RuntimeException($depth === 0
                ? 'Clinical database transaction could not be started.'
                : 'Clinical database savepoint could not be created.');
        }

        self::$transaction_depth = $depth + 1;
        try {
            $result = $callback();
            self::$transaction_depth = $depth;

            $finish_sql = $depth === 0 ? 'COMMIT' : 'RELEASE SAVEPOINT ' . $savepoint;
            if ($wpdb->query($finish_sql) === false) {
                throw new RuntimeException($depth === 0
                    ? 'Clinical database transaction could not be committed.'
                    : 'Clinical database savepoint could not be released.');
            }
            return $result;
        } catch (Throwable $error) {
            self::$transaction_depth = $depth;
            $rollback_ok = $wpdb->query($depth === 0 ? 'ROLLBACK' : 'ROLLBACK TO SAVEPOINT ' . $savepoint) !== false;
            if ($depth > 0 && $rollback_ok) {
                $rollback_ok = $wpdb->query('RELEASE SAVEPOINT ' . $savepoint) !== false;
            }
            if (!$rollback_ok) {
                throw new RuntimeException('Clinical database rollback failed.', 0, $error);
            }
            throw $error;
        }
    }

    public static function row(string $sql, array $args = array()): ?array {
        global $wpdb;
        $prepared = $args ? $wpdb->prepare($sql, $args) : $sql;
        $row = $wpdb->get_row($prepared, ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public static function rows(string $sql, array $args = array()): array {
        global $wpdb;
        $prepared = $args ? $wpdb->prepare($sql, $args) : $sql;
        $rows = $wpdb->get_results($prepared, ARRAY_A);
        return is_array($rows) ? $rows : array();
    }

    public static function insert(string $key, array $data, array $formats = array()): int {
        global $wpdb;
        $ok = $wpdb->insert(self::table($key), $data, $formats ?: null);
        if ($ok === false) {
            throw new RuntimeException('Clinical database insert failed.');
        }
        return (int) $wpdb->insert_id;
    }

    public static function update_versioned(string $key, array $data, array $where, int $expected_version): bool {
        global $wpdb;
        $where['row_version'] = $expected_version;
        $data['row_version'] = $expected_version + 1;
        $data['updated_at'] = self::now();
        $updated = $wpdb->update(self::table($key), $data, $where);
        if ($updated === false) {
            throw new RuntimeException('Clinical database update failed.');
        }
        return $updated === 1;
    }

    /**
     * Execute a mutating command exactly once for a given actor/command/key/request.
     *
     * The durable processing receipt is intentionally written before the transaction so a
     * process crash cannot permit a blind replay. The clinical mutation, local audit/outbox
     * writes and the completed response receipt are then committed atomically. If any part
     * fails, the clinical transaction is rolled back and only a failed command receipt is
     * retained for reconciliation.
     */
    public static function idempotent(int $actor_id, string $command, string $key, array $request, callable $operation): array {
        if (!preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $key)) {
            throw new InvalidArgumentException('A valid idempotency key is required.');
        }
        $key_hash = CF01_Crypto::blind_index($actor_id . '|' . $command . '|' . $key, 'command-idempotency');
        $request_hash = hash('sha256', CF01_Crypto::canonical_json($request));
        $existing = self::row('SELECT * FROM ' . self::table('commands') . ' WHERE key_hash = %s LIMIT 1', array($key_hash));
        if ($existing) {
            if (!hash_equals((string) $existing['request_hash'], $request_hash)) {
                throw new RuntimeException('Idempotency key was reused with a different request.');
            }
            if (($existing['status'] ?? '') === 'completed' && !empty($existing['response_cipher'])) {
                $response = CF01_Crypto::decrypt((string) $existing['response_cipher'], 'command-response');
                return is_array($response) ? $response : array();
            }
            throw new RuntimeException('The idempotent command is already processing or requires reconciliation.');
        }
        $receipt_uuid = self::uuid();
        try {
            self::insert('commands', array(
                'receipt_uuid' => $receipt_uuid,
                'actor_pseudonym' => hash_hmac('sha256', (string) $actor_id, CF01_Crypto::key() ?? str_repeat("\0", 32)),
                'command_name' => sanitize_key($command),
                'key_hash' => $key_hash,
                'request_hash' => $request_hash,
                'status' => 'processing',
                'response_cipher' => null,
                'error_code' => null,
                'row_version' => 1,
                'created_at' => self::now(),
                'updated_at' => self::now(),
            ));
        } catch (Throwable $error) {
            $existing = self::row('SELECT * FROM ' . self::table('commands') . ' WHERE key_hash = %s LIMIT 1', array($key_hash));
            if ($existing && hash_equals((string) $existing['request_hash'], $request_hash) && ($existing['status'] ?? '') === 'completed') {
                $response = CF01_Crypto::decrypt((string) $existing['response_cipher'], 'command-response');
                return is_array($response) ? $response : array();
            }
            throw $error;
        }

        try {
            return self::transaction(function () use ($operation, $receipt_uuid): array {
                $response = $operation();
                if (!is_array($response)) {
                    $response = array('result' => $response);
                }
                $ok = self::update_versioned('commands', array(
                    'status' => 'completed',
                    'response_cipher' => CF01_Crypto::encrypt($response, 'command-response'),
                ), array('receipt_uuid' => $receipt_uuid), 1);
                if (!$ok) {
                    throw new RuntimeException('Idempotency receipt changed concurrently.');
                }
                return $response;
            });
        } catch (Throwable $error) {
            try {
                self::update_versioned('commands', array(
                    'status' => 'failed',
                    'error_code' => sanitize_key($error instanceof InvalidArgumentException ? 'invalid_request' : 'command_failed'),
                ), array('receipt_uuid' => $receipt_uuid), 1);
            } catch (Throwable $ignored) {
                // Original clinical error has precedence; reconciliation will inspect the processing receipt.
            }
            throw $error;
        }
    }

    public static function activation_state(): string {
        return (string) get_option('cf01_activation_state', 'disabled');
    }

    public static function is_enabled(): bool {
        return self::activation_state() === 'enabled';
    }
}
