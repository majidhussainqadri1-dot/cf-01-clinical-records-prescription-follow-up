<?php
defined('ABSPATH') || exit;

final class CF01_Future_REST {
    private const NS = 'clinical/v1';

    public static function register_routes(): void {
        $routes = array(
            array('/future/features', 'GET', 'features'),
            array('/future/features/(?P<id>CF01-FUT-[0-9]{3})/state', 'POST', 'configure_feature'),
            array('/future/sidecar-assurance', 'GET', 'sidecar_assurance'),
            array('/future/patients/(?P<patient>[a-f0-9-]{36})/timeline', 'GET', 'timeline'),
            array('/future/patients/(?P<patient>[a-f0-9-]{36})/features/(?P<id>CF01-FUT-[0-9]{3})', 'GET', 'feature_query'),
            array('/future/patients/(?P<patient>[a-f0-9-]{36})/features/(?P<id>CF01-FUT-[0-9]{3})/facts', 'POST', 'record_fact'),
            array('/future/decision-support', 'POST', 'decision_support'),
            array('/future/patient-reported-outcomes/(?P<followup>[a-f0-9-]{36})', 'POST', 'patient_reported_outcome'),
            array('/future/patients/(?P<patient>[a-f0-9-]{36})/research-consents', 'GET', 'research_consents'),
            array('/future/institutional/webhooks/(?P<event>[A-Za-z][A-Za-z0-9]{2,95})', 'POST', 'institutional_webhook'),
            array('/future/simulation', 'POST', 'simulation'),
            array('/future/transparency/(?P<decision>[A-Za-z0-9._:-]{1,191})', 'GET', 'transparency'),
        );
        foreach ($routes as [$route, $method, $handler]) {
            register_rest_route(self::NS, $route, array(
                'methods' => $method,
                'callback' => array(__CLASS__, $handler),
                'permission_callback' => array(__CLASS__, 'permission'),
            ));
        }
    }

    public static function permission(WP_REST_Request $request) {
        if (!is_user_logged_in()) {
            return new WP_Error('cf01_authentication_required', __('Authentication is required.', 'sabri-clinical-records'), array('status' => 401));
        }
        return CF01_REST::permission($request);
    }

    public static function features(WP_REST_Request $request): WP_REST_Response {
        unset($request);
        return self::query(fn(): array => array(
            'runtime_state' => CF01_DB::activation_state(),
            'features' => CF01_Future_Clinical_Intelligence::catalogue(),
            'default_state' => 'disabled',
            'separate_governance_acceptance_required' => true,
        ));
    }

    public static function configure_feature(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'ConfigureFutureClinicalFeature', fn(array $data): array => CF01_Future_Clinical_Intelligence::configure_state(
            get_current_user_id(),
            (string) $request['id'],
            (string) ($data['state'] ?? ''),
            (array) ($data['evidence'] ?? array())
        ));
    }

    public static function sidecar_assurance(WP_REST_Request $request): WP_REST_Response {
        unset($request);
        return self::query(fn(): array => CF01_Future_Clinical_Intelligence::sidecar_assurance(get_current_user_id()));
    }

    public static function timeline(WP_REST_Request $request): WP_REST_Response {
        return self::query(fn(): array => CF01_Future_Clinical_Intelligence::longitudinal_timeline(
            get_current_user_id(),
            (string) $request['patient'],
            self::limit($request)
        ));
    }

    public static function feature_query(WP_REST_Request $request): WP_REST_Response {
        return self::query(fn(): array => CF01_Future_Clinical_Intelligence::feature_query(
            get_current_user_id(),
            (string) $request['id'],
            (string) $request['patient'],
            self::limit($request)
        ));
    }

    public static function record_fact(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'RecordFutureClinicalFact:' . (string) $request['id'], fn(array $data): array => CF01_Future_Clinical_Intelligence::record_fact(
            get_current_user_id(),
            (string) $request['id'],
            (string) $request['patient'],
            (string) ($data['encounter_uuid'] ?? ''),
            (array) ($data['value'] ?? array()),
            (array) ($data['provenance'] ?? array())
        ));
    }

    public static function decision_support(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'RunClinicalDecisionSupport', fn(array $data): array => CF01_Future_Clinical_Intelligence::decision_support(
            get_current_user_id(),
            (string) ($data['patient_uuid'] ?? ''),
            (array) ($data['request'] ?? array())
        ));
    }

    public static function patient_reported_outcome(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'SubmitFuturePatientReportedOutcome', fn(array $data): array => CF01_Future_Clinical_Intelligence::patient_reported_outcome(
            get_current_user_id(),
            (string) $request['followup'],
            (array) ($data['response'] ?? array()),
            self::version($request, $data)
        ));
    }

    public static function research_consents(WP_REST_Request $request): WP_REST_Response {
        return self::query(fn(): array => CF01_Future_Clinical_Intelligence::research_consents(get_current_user_id(), (string) $request['patient']));
    }

    public static function institutional_webhook(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'DispatchInstitutionalClinicalWebhook', fn(array $data): array => CF01_Future_Clinical_Intelligence::institutional_webhook(
            get_current_user_id(),
            (string) $request['event'],
            (array) ($data['payload'] ?? array())
        ));
    }

    public static function simulation(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'RunClinicalSimulation', fn(array $data): array => CF01_Future_Clinical_Intelligence::simulation(
            get_current_user_id(),
            (array) ($data['scenario'] ?? $data)
        ));
    }

    public static function transparency(WP_REST_Request $request): WP_REST_Response {
        return self::query(fn(): array => CF01_Future_Clinical_Intelligence::transparency(get_current_user_id(), (string) $request['decision']));
    }

    private static function mutate(WP_REST_Request $request, string $command, callable $callback): WP_REST_Response {
        return self::respond(function () use ($request, $command, $callback): array {
            CF01_Authorization::require_enabled();
            $data = self::json($request);
            $key = trim((string) $request->get_header('Idempotency-Key'));
            return CF01_DB::idempotent(get_current_user_id(), $command, $key, $data, fn(): array => $callback($data));
        });
    }

    private static function query(callable $callback): WP_REST_Response {
        return self::respond($callback);
    }

    private static function respond(callable $callback): WP_REST_Response {
        try {
            return new WP_REST_Response(array('ok' => true, 'data' => $callback()), 200, self::headers());
        } catch (InvalidArgumentException $error) {
            return new WP_REST_Response(array('ok' => false, 'code' => 'invalid_request', 'message' => $error->getMessage()), 400, self::headers());
        } catch (Throwable $error) {
            $trace = CF01_DB::uuid();
            do_action('cf01_exception', $error, $trace);
            $message = strtolower($error->getMessage());
            $conflict = str_contains($message, 'concurrent') || str_contains($message, 'stale') || str_contains($message, 'already processing');
            return new WP_REST_Response(array(
                'ok' => false,
                'code' => $conflict ? 'clinical_conflict' : 'future_clinical_operation_unavailable',
                'message' => $conflict
                    ? __('The clinical record changed. Reload and retry safely.', 'sabri-clinical-records')
                    : __('The protected Future Clinical Intelligence operation could not be completed.', 'sabri-clinical-records'),
                'trace_id' => $trace,
            ), $conflict ? 409 : 403, self::headers());
        }
    }

    private static function json(WP_REST_Request $request): array {
        $raw = (string) $request->get_body();
        if (strlen($raw) > 262144) {
            throw new InvalidArgumentException('The protected clinical request is too large.');
        }
        $data = $request->get_json_params();
        return is_array($data) ? $data : array();
    }

    private static function version(WP_REST_Request $request, array $data): int {
        $header = $request->get_header('If-Match');
        $value = $header !== '' ? trim($header, '" W/') : (string) ($data['expected_version'] ?? '');
        if ($value === '' || !ctype_digit($value) || (int) $value < 1) {
            throw new InvalidArgumentException('Expected record version is required.');
        }
        return (int) $value;
    }

    private static function limit(WP_REST_Request $request): int {
        $value = $request->get_param('limit');
        if ($value === null || $value === '') {
            return 50;
        }
        if (!is_numeric($value)) {
            throw new InvalidArgumentException('Query limit must be numeric.');
        }
        return max(1, min(200, (int) $value));
    }

    private static function headers(): array {
        return array(
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
        );
    }
}
