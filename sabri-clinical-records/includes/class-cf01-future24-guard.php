<?php
defined('ABSPATH') || exit;

/** Route-level authorization, abuse and replay perimeter for Future24. */
final class CF01_Future24_Guard {
    private const PREFIX = '/clinical/v1/future/';
    private const MAX_BODY_BYTES = 262144;

    public static function register(): void {
        add_filter('rest_request_before_callbacks', array(__CLASS__, 'before_callbacks'), 8, 3);
        add_filter('rest_request_after_callbacks', array(__CLASS__, 'after_callbacks'), PHP_INT_MAX, 3);
        CF01_Future24_Response_Guard::register();
    }

    public static function before_callbacks($response, array $handler, WP_REST_Request $request) {
        unset($handler);
        if ($response !== null) {
            return $response;
        }
        $route = '/' . ltrim((string) $request->get_route(), '/');
        if (!str_starts_with($route, self::PREFIX)) {
            return $response;
        }
        if (!is_user_logged_in()) {
            return self::error('cf01_authentication_required', 'Authentication is required.', 401);
        }
        try {
            self::core_gate($route);
            self::request_size_gate($request);
            self::rate_limit($route, $request);
            self::route_authorization($route, $request);
            self::mutation_transport_gate($request);
            $replay = self::reserve_mutation($route, $request);
            return $replay ?? $response;
        } catch (InvalidArgumentException $error) {
            return self::error('cf01_future24_invalid_request', $error->getMessage(), 400);
        } catch (RuntimeException $error) {
            return self::error('cf01_future24_forbidden', $error->getMessage(), 403);
        } catch (Throwable $error) {
            return self::error('cf01_future24_guard_failed', 'Future clinical authorization failed closed.', 503);
        }
    }

    public static function after_callbacks($response, array $handler, WP_REST_Request $request) {
        unset($handler);
        $route = '/' . ltrim((string) $request->get_route(), '/');
        if (!str_starts_with($route, self::PREFIX) || strtoupper((string) $request->get_method()) === 'GET') {
            return $response;
        }
        $receipt_uuid = trim((string) $request->get_param('_cf01_future24_receipt_uuid'));
        if ($receipt_uuid === '') {
            return $response;
        }
        try {
            if (is_wp_error($response)) {
                self::fail_receipt($receipt_uuid, sanitize_key((string) $response->get_error_code()));
                return $response;
            }
            $rest = rest_ensure_response($response);
            $status = (int) $rest->get_status();
            if ($status < 200 || $status >= 300) {
                self::fail_receipt($receipt_uuid, 'http_' . $status);
                return $response;
            }
            $stored = array('status' => $status, 'data' => $rest->get_data());
            if (!CF01_DB::update_versioned('commands', array(
                'status' => 'completed',
                'response_cipher' => CF01_Crypto::encrypt($stored, 'command-response'),
                'error_code' => null,
            ), array('receipt_uuid' => $receipt_uuid), 1)) {
                throw new RuntimeException('Future24 idempotency receipt changed concurrently.');
            }
            return $response;
        } catch (Throwable $error) {
            do_action('cf01_exception', $error, CF01_DB::uuid());
            return self::error('cf01_future24_receipt_failed', 'Future clinical mutation requires reconciliation before retry.', 503);
        }
    }

    private static function core_gate(string $route): void {
        if (CF01_DB::is_enabled()) {
            return;
        }
        $metadata = $route === '/clinical/v1/future/features'
            || str_starts_with($route, '/clinical/v1/future/features/')
            || $route === '/clinical/v1/future/sidecar-assurance';
        if ($metadata && current_user_can('cf01_view_clinical_health')) {
            return;
        }
        throw new RuntimeException('Clinical records are disabled pending activation acceptance.');
    }

    private static function request_size_gate(WP_REST_Request $request): void {
        if (strlen((string) $request->get_body()) > self::MAX_BODY_BYTES) {
            throw new InvalidArgumentException('The protected Future24 request is too large.');
        }
    }

    private static function rate_limit(string $route, WP_REST_Request $request): void {
        $mutation = strtoupper((string) $request->get_method()) !== 'GET';
        $operation = 'future24_' . substr(hash('sha256', $route), 0, 20);
        $subject = substr(hash('sha256', get_current_user_id() . '|' . $route), 0, 32);
        CF01_Authorization::enforce_rate_limit(get_current_user_id(), $operation, $subject, $mutation ? 60 : 240, 60);
    }

    private static function mutation_transport_gate(WP_REST_Request $request): void {
        if (strtoupper((string) $request->get_method()) === 'GET') {
            return;
        }
        $key = trim((string) $request->get_header('Idempotency-Key'));
        if (!preg_match('/^[A-Za-z0-9._:-]{16,128}$/', $key)) {
            throw new InvalidArgumentException('A bounded Idempotency-Key is required for Future24 mutations.');
        }
    }

    private static function reserve_mutation(string $route, WP_REST_Request $request): ?WP_REST_Response {
        if (strtoupper((string) $request->get_method()) === 'GET') {
            return null;
        }
        $actor = get_current_user_id();
        $key = trim((string) $request->get_header('Idempotency-Key'));
        $command = 'future24_' . substr(hash('sha256', $route), 0, 24);
        $key_hash = CF01_Crypto::blind_index($actor . '|' . $command . '|' . $key, 'command-idempotency');
        $request_hash = hash('sha256', CF01_Crypto::canonical_json(array(
            'method' => strtoupper((string) $request->get_method()),
            'route' => $route,
            'url' => $request->get_url_params(),
            'query' => $request->get_query_params(),
            'body' => self::json($request),
        )));
        $existing = CF01_DB::row('SELECT * FROM ' . CF01_DB::table('commands') . ' WHERE key_hash = %s LIMIT 1', array($key_hash));
        if ($existing) {
            if (!hash_equals((string) $existing['request_hash'], $request_hash)) {
                throw new RuntimeException('Idempotency key was reused with a different Future24 request.');
            }
            if (($existing['status'] ?? '') === 'completed' && !empty($existing['response_cipher'])) {
                $stored = CF01_Crypto::decrypt((string) $existing['response_cipher'], 'command-response');
                if (!is_array($stored) || !array_key_exists('data', $stored)) {
                    throw new RuntimeException('Stored Future24 idempotency result is invalid.');
                }
                return new WP_REST_Response($stored['data'], max(200, min(299, (int) ($stored['status'] ?? 200))));
            }
            throw new RuntimeException('The Future24 idempotent command is already processing, failed or requires reconciliation.');
        }

        $receipt_uuid = CF01_DB::uuid();
        try {
            CF01_DB::insert('commands', array(
                'receipt_uuid' => $receipt_uuid,
                'actor_pseudonym' => hash_hmac('sha256', (string) $actor, CF01_Crypto::key() ?? str_repeat("\0", 32)),
                'command_name' => sanitize_key($command),
                'key_hash' => $key_hash,
                'request_hash' => $request_hash,
                'status' => 'processing',
                'response_cipher' => null,
                'error_code' => null,
                'row_version' => 1,
                'created_at' => CF01_DB::now(),
                'updated_at' => CF01_DB::now(),
            ));
        } catch (Throwable $error) {
            $raced = CF01_DB::row('SELECT * FROM ' . CF01_DB::table('commands') . ' WHERE key_hash = %s LIMIT 1', array($key_hash));
            if ($raced && hash_equals((string) $raced['request_hash'], $request_hash) && ($raced['status'] ?? '') === 'completed' && !empty($raced['response_cipher'])) {
                $stored = CF01_Crypto::decrypt((string) $raced['response_cipher'], 'command-response');
                if (is_array($stored) && array_key_exists('data', $stored)) {
                    return new WP_REST_Response($stored['data'], max(200, min(299, (int) ($stored['status'] ?? 200))));
                }
            }
            throw $error;
        }
        $request->set_param('_cf01_future24_receipt_uuid', $receipt_uuid);
        return null;
    }

    private static function fail_receipt(string $receipt_uuid, string $error_code): void {
        try {
            CF01_DB::update_versioned('commands', array(
                'status' => 'failed',
                'error_code' => sanitize_key($error_code ?: 'command_failed'),
            ), array('receipt_uuid' => $receipt_uuid), 1);
        } catch (Throwable $ignored) {
            // Reconciliation has precedence over unsafe automatic replay.
        }
    }

    private static function route_authorization(string $route, WP_REST_Request $request): void {
        $user_id = get_current_user_id();
        if ($route === '/clinical/v1/future/features' || str_starts_with($route, '/clinical/v1/future/features/')) {
            CF01_Authorization::actor($user_id, 'view_clinical_health', array('purpose' => 'clinical_governance'));
            return;
        }
        if ($route === '/clinical/v1/future/sidecar-assurance') {
            CF01_Authorization::actor($user_id, 'view_clinical_health', array('purpose' => 'clinical_governance'));
            return;
        }
        $patient = self::route_patient_uuid($request);
        if ($patient !== '') {
            $method = strtoupper((string) $request->get_method());
            if ($method === 'POST' && str_contains($route, '/features/') && str_ends_with($route, '/facts')) {
                CF01_Authorization::patient_context($user_id, $patient, 'clinical_care', 'doctor');
                self::recent_auth($user_id, 'future24_clinical_fact_write');
            } else {
                CF01_Authorization::patient_context($user_id, $patient, 'clinical_care');
            }
            return;
        }
        if ($route === '/clinical/v1/future/decision-support') {
            $body = self::json($request);
            $patient_uuid = self::clinical_uuid((string) ($body['patient_uuid'] ?? ''));
            CF01_Authorization::patient_context($user_id, $patient_uuid, 'clinical_care', 'doctor');
            self::recent_auth($user_id, 'future24_decision_support');
            return;
        }
        if (str_starts_with($route, '/clinical/v1/future/patient-reported-outcomes/')) {
            $followup = CF01_Followups::get(trim((string) $request['followup']));
            CF01_Authorization::patient_context($user_id, (string) $followup['patient_uuid'], 'clinical_care');
            return;
        }
        if (str_starts_with($route, '/clinical/v1/future/institutional/webhooks/')) {
            self::require_capability($user_id, 'cf01_manage_clinical_records');
            self::require_capability($user_id, 'cf01_manage_clinical_keys');
            self::recent_auth($user_id, 'future24_institutional_webhook');
            self::verify_institutional_signature($request);
            if (!(bool) apply_filters('cf01_future24_institutional_authorized', false, $user_id, $request)) {
                throw new RuntimeException('An accepted institutional integration authorization is required.');
            }
            return;
        }
        if ($route === '/clinical/v1/future/simulation') {
            if (!current_user_can('cf01_audit_clinical') && !current_user_can('cf01_manage_clinical_records')) {
                throw new RuntimeException('Clinical simulation capability is required.');
            }
            self::recent_auth($user_id, 'future24_simulation');
            return;
        }
        if (str_starts_with($route, '/clinical/v1/future/transparency/')) {
            CF01_Authorization::actor($user_id, 'view_own_clinical_record', array('purpose' => 'clinical_transparency'));
            if (!(bool) apply_filters('cf01_future24_transparency_authorized', false, $user_id, (string) $request['decision'], $request)) {
                throw new RuntimeException('A patient-scoped transparency authorization is required.');
            }
            return;
        }
        throw new RuntimeException('Future clinical route is not covered by an explicit authorization rule.');
    }

    private static function verify_institutional_signature(WP_REST_Request $request): void {
        $timestamp = trim((string) $request->get_header('X-CF01-Webhook-Timestamp'));
        $nonce = trim((string) $request->get_header('X-CF01-Webhook-Nonce'));
        $signature = trim((string) $request->get_header('X-CF01-Webhook-Signature'));
        $idempotency = trim((string) $request->get_header('Idempotency-Key'));
        if (!ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300) {
            throw new RuntimeException('Institutional webhook timestamp is missing or outside the accepted replay window.');
        }
        if (!preg_match('/^[A-Za-z0-9._:-]{16,128}$/', $nonce) || !hash_equals($nonce, $idempotency)) {
            throw new RuntimeException('Institutional webhook nonce must be bounded and identical to the idempotency key.');
        }
        if (strlen($signature) < 32 || strlen($signature) > 512) {
            throw new RuntimeException('Institutional webhook signature is missing or malformed.');
        }
        $body_hash = hash('sha256', (string) $request->get_body());
        $verification = apply_filters('cf01_future24_institutional_signature_verification', null, array(
            'actor_user_id' => get_current_user_id(),
            'timestamp' => (int) $timestamp,
            'nonce' => $nonce,
            'signature' => $signature,
            'body_sha256' => $body_hash,
            'event' => sanitize_key((string) $request['event']),
            'contract_version' => CF01_CONTRACT_VERSION,
        ), $request);
        if (!is_array($verification)
            || ($verification['valid'] ?? false) !== true
            || !hash_equals($body_hash, strtolower(trim((string) ($verification['body_sha256'] ?? ''))))
            || !hash_equals($nonce, (string) ($verification['nonce'] ?? ''))
            || (int) ($verification['timestamp'] ?? 0) !== (int) $timestamp
            || trim((string) ($verification['key_id'] ?? '')) === ''
            || !hash_equals(CF01_CONTRACT_VERSION, (string) ($verification['contract_version'] ?? ''))
        ) {
            throw new RuntimeException('Institutional webhook signature verification failed closed.');
        }
    }

    private static function route_patient_uuid(WP_REST_Request $request): string {
        $value = trim((string) $request['patient']);
        return $value === '' ? '' : self::clinical_uuid($value);
    }

    private static function recent_auth(int $user_id, string $action): void {
        $membership = CF01_Contracts::membership($user_id);
        $recent = CF01_Contracts::recent_auth($user_id, $action);
        if (empty($membership['valid']) || empty($membership['platform_uuid'])
            || empty($recent['valid']) || empty($recent['recent_auth']) || empty($recent['step_up'])
            || empty($recent['expires_at']) || !CF01_Authorization::not_expired((string) $recent['expires_at'])
            || empty($recent['subject_uuid']) || !hash_equals((string) $membership['platform_uuid'], (string) $recent['subject_uuid'])) {
            throw new RuntimeException('Current recent step-up authentication is required.');
        }
    }

    private static function require_capability(int $user_id, string $capability): void {
        if (!user_can($user_id, $capability)) {
            throw new RuntimeException('Required clinical integration capability is missing.');
        }
    }

    private static function clinical_uuid(string $value): string {
        $value = strtolower(trim($value));
        if (!preg_match('/^[a-f0-9-]{36}$/', $value)) {
            throw new InvalidArgumentException('A valid clinical patient identifier is required.');
        }
        return $value;
    }

    private static function json(WP_REST_Request $request): array {
        $body = $request->get_json_params();
        return is_array($body) ? $body : array();
    }

    private static function error(string $code, string $message, int $status): WP_Error {
        return new WP_Error($code, __($message, 'sabri-clinical-records'), array('status' => $status));
    }
}

/** Last-line provider response validator for Future24 adapters. */
final class CF01_Future24_Response_Guard {
    private const GLOBAL_FORBIDDEN = array(
        'password', 'otp', 'cvv', 'pan', 'private_key', 'provider_secret', 'raw_token',
        'raw_payload', 'raw_clinical_payload', 'full_card_number', 'bank_password'
    );
    private const CLINICAL_AUTONOMY = array(
        'dose', 'dosage', 'potency', 'automatic_prescription', 'prescription_automatic',
        'diagnosis_autonomous', 'automatic_treatment_change', 'autonomous_treatment'
    );
    private const FINANCIAL_BIAS = array(
        'donor_priority', 'donation_priority', 'payment_priority', 'paid_rank',
        'donation_rank', 'donor_advantage', 'paid_clinical_priority'
    );
    private const REAL_SUBJECT = array('patient_uuid', 'clinical_uuid', 'platform_uuid', 'email', 'phone', 'mrn');

    public static function register(): void {
        add_filter('cf01_future24_provider_response', array(__CLASS__, 'validate'), PHP_INT_MAX, 4);
    }

    public static function validate($result, string $id, string $operation, array $context) {
        unset($operation, $context);
        if ($result === null || $result === false || $result instanceof WP_Error) {
            return $result;
        }
        if (!is_array($result)) {
            return self::error('cf01_future24_provider_invalid', 'Future clinical provider returned an invalid response.', 502);
        }
        $meta = $result['_cf01'] ?? null;
        if (!is_array($meta)
            || ($meta['authorization_checked'] ?? false) !== true
            || ($meta['minimum_necessary'] ?? false) !== true
            || sanitize_key((string) ($meta['canonical_owner'] ?? '')) !== 'cf01'
            || !hash_equals(CF01_CONTRACT_VERSION, (string) ($meta['contract_version'] ?? ''))) {
            return self::error('cf01_future24_provider_contract_missing', 'Future clinical provider response lacks current governance assertions.', 502);
        }
        if (self::contains_nonempty_key($result, self::GLOBAL_FORBIDDEN)) {
            return self::error('cf01_future24_sensitive_payload_rejected', 'Future clinical provider attempted to expose a forbidden sensitive payload.', 422);
        }
        if ($id === 'CF01-FUT-015' && self::contains_nonempty_key($result, self::CLINICAL_AUTONOMY)) {
            return self::error('cf01_future24_unsafe_advice', 'Decision-support provider attempted an autonomous clinical action.', 422);
        }
        if ($id === 'CF01-FUT-024' && self::contains_nonempty_key($result, self::FINANCIAL_BIAS)) {
            return self::error('cf01_future24_financial_bias_rejected', 'Financial or donor preference is forbidden in clinical decisions.', 422);
        }
        if ($id === 'CF01-FUT-023' && self::contains_nonempty_key($result, self::REAL_SUBJECT)) {
            return self::error('cf01_future24_simulation_subject_rejected', 'Simulation output must remain synthetic or de-identified.', 422);
        }
        return $result;
    }

    private static function contains_nonempty_key(array $value, array $forbidden): bool {
        foreach ($value as $key => $child) {
            if (in_array(sanitize_key((string) $key), $forbidden, true)) {
                if (is_array($child) ? $child !== array() : (is_scalar($child) && trim((string) $child) !== '')) {
                    return true;
                }
            }
            if (is_array($child) && self::contains_nonempty_key($child, $forbidden)) {
                return true;
            }
        }
        return false;
    }

    private static function error(string $code, string $message, int $status): WP_Error {
        return new WP_Error($code, __($message, 'sabri-clinical-records'), array('status' => $status));
    }
}
