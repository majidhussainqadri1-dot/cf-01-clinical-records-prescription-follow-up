<?php
defined('ABSPATH') || exit;

/**
 * Route-level authorization guard for the Future24 contract surface.
 * It runs before Future24 callbacks and therefore cannot be bypassed by a
 * provider adapter. Feature-state checks remain in CF01_Future24.
 */
final class CF01_Future24_Guard {
    private const PREFIX = '/clinical/v1/future/';

    public static function register(): void {
        add_filter('rest_request_before_callbacks', array(__CLASS__, 'before_callbacks'), 8, 3);
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
            || empty($recent['subject_uuid'])
            || !hash_equals((string) $membership['platform_uuid'], (string) $recent['subject_uuid'])
        ) {
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
