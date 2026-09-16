<?php
defined('ABSPATH') || exit;

/** Route-level authorization perimeter for Future24. */
final class CF01_Future24_Guard {
    private const PREFIX = '/clinical/v1/future/';

    public static function register(): void {
        add_filter('rest_request_before_callbacks', array(__CLASS__, 'before_callbacks'), 8, 3);
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
            self::mutation_transport_gate($request);
            self::route_authorization($route, $request);
            return $response;
        } catch (InvalidArgumentException $error) {
            return self::error('cf01_future24_invalid_request', $error->getMessage(), 400);
        } catch (RuntimeException $error) {
            return self::error('cf01_future24_forbidden', $error->getMessage(), 403);
        } catch (Throwable $error) {
            return self::error('cf01_future24_guard_failed', 'Future clinical authorization failed closed.', 503);
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

    private static function mutation_transport_gate(WP_REST_Request $request): void {
        if (strtoupper((string) $request->get_method()) === 'GET') {
            return;
        }
        $key = trim((string) $request->get_header('Idempotency-Key'));
        if ($key === '' || strlen($key) < 16 || strlen($key) > 200) {
            throw new InvalidArgumentException('A bounded Idempotency-Key is required for Future24 mutations.');
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

/**
 * Last-line response validator for Future24 provider adapters.
 * Providers remain replaceable, but no adapter may emit ungoverned/raw data or
 * autonomous clinical/financial-priority signals through this foundation.
 */
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
            || trim((string) ($meta['contract_version'] ?? '')) === '') {
            return self::error('cf01_future24_provider_contract_missing', 'Future clinical provider response lacks required governance assertions.', 502);
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
