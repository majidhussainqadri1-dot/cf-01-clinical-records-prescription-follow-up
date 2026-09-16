<?php
/**
 * Plugin Name: Sabri Clinical Records
 * Description: Conditional, disabled-by-default clinical records, prescription and follow-up system of record for the Sabri Social Homeopathy Platform.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Dr. Allamah Majid Hussain Sabri Muhaddith Mursheed
 * Text Domain: sabri-clinical-records
 */

defined('ABSPATH') || exit;

define('CF01_VERSION', '1.0.0');
define('CF01_SCHEMA_VERSION', '1.0.0');
define('CF01_CONTRACT_VERSION', '1.0.0');
define('CF01_FILE', __FILE__);
define('CF01_DIR', plugin_dir_path(__FILE__));
define('CF01_URL', plugin_dir_url(__FILE__));

$cf01_files = array(
    'class-cf01-db.php',
    'class-cf01-crypto.php',
    'class-cf01-contracts.php',
    'class-cf01-authorization.php',
    'class-cf01-patients.php',
    'class-cf01-role-context.php',
    'class-cf01-relationships.php',
    'class-cf01-consents.php',
    'class-cf01-encounters.php',
    'class-cf01-attachments.php',
    'class-cf01-prescriptions.php',
    'class-cf01-followups.php',
    'class-cf01-rights.php',
    'class-cf01-break-glass.php',
    'class-cf01-audit-outbox.php',
    'class-cf01-retention.php',
    'class-cf01-activation-evidence.php',
    'class-cf01-release-orchestrator.php',
    'class-cf01-future24-governance.php',
    'class-cf01-future24-guard.php',
    'class-cf01-future24.php',
    'class-cf01-rest.php',
    'class-cf01-lifecycle-rest.php',
    'class-cf01-ui-health.php',
    'class-cf01-migrations.php',
);

foreach ($cf01_files as $cf01_file) {
    require_once CF01_DIR . 'includes/' . $cf01_file;
}
unset($cf01_files, $cf01_file);

final class CF01_Plugin {
    private const REST_PREFIX = '/clinical/v1/';
    private const FUTURE_PREFIX = '/clinical/v1/future/';
    private const MAX_FUTURE_QUERY_PARAMS = 20;
    private const MAX_FUTURE_QUERY_VALUE_BYTES = 512;
    private const MAX_FUTURE_RESPONSE_BYTES = 524288;

    public static function boot(): void {
        CF01_Activation_Evidence::register();
        CF01_Release_Orchestrator::register();
        CF01_Future24_Governance::register();
        CF01_Future24_Guard::register();
        add_action('plugins_loaded', array(__CLASS__, 'plugins_loaded'));
        add_action('init', array('CF01_UI', 'register'));
        add_action('rest_api_init', array('CF01_REST', 'register_routes'));
        add_action('rest_api_init', array('CF01_Lifecycle_REST', 'register_routes'));
        add_action('rest_api_init', array('CF01_Future24', 'register_routes'));
        add_action('cf01_process_outbox', array('CF01_Outbox', 'process'));
        add_action('cf01_retention_reconcile', array('CF01_Retention', 'reconcile'));
        add_action('cf01_followup_reconcile', array('CF01_Followups', 'reconcile_due'));
        add_action('send_headers', array(__CLASS__, 'send_private_headers'), 0);
        add_filter('rest_request_before_callbacks', array(__CLASS__, 'validate_future24_query'), 9, 3);
        add_filter('rest_post_dispatch', array(__CLASS__, 'harden_rest_response'), 999, 3);
        add_filter('rest_pre_serve_request', array(__CLASS__, 'send_rest_headers'), 0, 4);
        add_filter('wp_robots', array(__CLASS__, 'private_robots'));
    }

    public static function plugins_loaded(): void {
        load_plugin_textdomain('sabri-clinical-records', false, dirname(plugin_basename(CF01_FILE)) . '/languages');
        CF01_DB::register_tables();
    }

    public static function activate(): void {
        update_option('cf01_version', CF01_VERSION, false);
        update_option('cf01_activation_state', 'disabled', false);
        update_option('cf01_schema_status', 'not_installed', false);
        if (defined('CF01_SCHEMA_INSTALL_APPROVED') && CF01_SCHEMA_INSTALL_APPROVED === true) {
            CF01_Migrations::install_schema();
        }
        CF01_UI::register();
        flush_rewrite_rules(false);
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook('cf01_process_outbox');
        wp_clear_scheduled_hook('cf01_retention_reconcile');
        wp_clear_scheduled_hook('cf01_followup_reconcile');
        flush_rewrite_rules(false);
    }

    public static function private_robots(array $robots): array {
        if (CF01_UI::is_clinical_request()) {
            $robots['noindex'] = true;
            $robots['nofollow'] = true;
            $robots['noarchive'] = true;
            $robots['nosnippet'] = true;
            $robots['noimageindex'] = true;
        }
        return $robots;
    }

    public static function send_private_headers(): void {
        if (CF01_UI::is_clinical_request()) {
            self::emit_private_headers();
        }
    }

    public static function send_rest_headers(bool $served, $result, WP_REST_Request $request, WP_REST_Server $server): bool {
        unset($result, $server);
        if (self::is_clinical_rest_request($request)) {
            self::emit_private_headers();
        }
        return $served;
    }

    public static function validate_future24_query($response, array $handler, WP_REST_Request $request) {
        unset($handler);
        if ($response !== null || !self::is_future24_request($request)) {
            return $response;
        }
        $query = $request->get_query_params();
        if (count($query) > self::MAX_FUTURE_QUERY_PARAMS) {
            return new WP_Error('cf01_future24_query_too_broad', __('The protected clinical query is too broad.', 'sabri-clinical-records'), array('status' => 400));
        }
        foreach ($query as $key => $value) {
            $key = (string) $key;
            if ($key === '' || sanitize_key($key) !== $key) {
                return new WP_Error('cf01_future24_query_invalid', __('The protected clinical query contains an invalid filter.', 'sabri-clinical-records'), array('status' => 400));
            }
            if ($key === 'limit') {
                if (!is_scalar($value) || !ctype_digit((string) $value) || (int) $value < 1 || (int) $value > 100) {
                    return new WP_Error('cf01_future24_limit_invalid', __('The protected clinical query limit is invalid.', 'sabri-clinical-records'), array('status' => 400));
                }
                continue;
            }
            $values = is_array($value) ? array_values($value) : array($value);
            if (count($values) > 20) {
                return new WP_Error('cf01_future24_filter_too_broad', __('The protected clinical query filter is too broad.', 'sabri-clinical-records'), array('status' => 400));
            }
            foreach ($values as $item) {
                if (!is_scalar($item) && $item !== null) {
                    return new WP_Error('cf01_future24_query_invalid', __('The protected clinical query contains an invalid filter.', 'sabri-clinical-records'), array('status' => 400));
                }
                if (strlen((string) $item) > self::MAX_FUTURE_QUERY_VALUE_BYTES) {
                    return new WP_Error('cf01_future24_query_value_too_large', __('The protected clinical query value is too large.', 'sabri-clinical-records'), array('status' => 400));
                }
            }
        }
        return $response;
    }

    public static function harden_rest_response($response, WP_REST_Server $server, WP_REST_Request $request) {
        unset($server);
        if (!self::is_clinical_rest_request($request)) {
            return $response;
        }
        $response = rest_ensure_response($response);
        foreach (self::private_headers() as $name => $value) {
            $response->header($name, $value);
        }

        if (self::is_future24_request($request)) {
            $data = $response->get_data();
            if (is_array($data) && array_key_exists('_cf01', $data)) {
                unset($data['_cf01']);
                $response->set_data($data);
            }
            $encoded = wp_json_encode($response->get_data());
            if (!is_string($encoded) || strlen($encoded) > self::MAX_FUTURE_RESPONSE_BYTES) {
                $response->set_status(502);
                $response->set_data(array(
                    'code' => 'cf01_future24_response_too_large',
                    'message' => __('The protected clinical response exceeded its safe disclosure limit.', 'sabri-clinical-records'),
                    'data' => array('status' => 502),
                ));
            } elseif ((int) $response->get_status() >= 200 && (int) $response->get_status() < 300) {
                try {
                    self::audit_future24_success($request, (int) $response->get_status());
                } catch (Throwable $error) {
                    do_action('cf01_exception', $error, CF01_DB::uuid());
                    $response->set_status(503);
                    $response->set_data(array(
                        'code' => 'cf01_future24_audit_unavailable',
                        'message' => __('The protected clinical operation could not be completed with its required audit evidence.', 'sabri-clinical-records'),
                        'data' => array('status' => 503),
                    ));
                }
            }
        }

        $status = (int) $response->get_status();
        if ($status >= 500 && !self::may_expose_diagnostics()) {
            $response->set_data(array(
                'code' => 'cf01_internal_error',
                'message' => __('The clinical service could not complete this request.', 'sabri-clinical-records'),
                'data' => array('status' => $status),
            ));
        }
        return $response;
    }

    private static function audit_future24_success(WP_REST_Request $request, int $status): void {
        $route = '/' . ltrim((string) $request->get_route(), '/');
        $patient_uuid = trim((string) $request['patient']);
        if ($patient_uuid !== '' && preg_match('/^[a-f0-9-]{36}$/i', $patient_uuid)) {
            CF01_Audit::access(
                get_current_user_id(),
                strtolower($patient_uuid),
                'Future24ClinicalAccess',
                'future24_route',
                substr(hash('sha256', $route), 0, 36),
                'clinical_care',
                'success'
            );
            return;
        }
        CF01_Audit::record(
            get_current_user_id(),
            'Future24RequestCompleted',
            'future24_route',
            substr(hash('sha256', $route), 0, 36),
            str_contains($route, '/features') || str_contains($route, '/sidecar-assurance') ? 'clinical_governance' : 'clinical_care',
            array('method' => strtoupper((string) $request->get_method()), 'http_status' => $status)
        );
    }

    private static function is_clinical_rest_request(WP_REST_Request $request): bool {
        $route = '/' . ltrim((string) $request->get_route(), '/');
        return str_starts_with($route, self::REST_PREFIX);
    }

    private static function is_future24_request(WP_REST_Request $request): bool {
        $route = '/' . ltrim((string) $request->get_route(), '/');
        return str_starts_with($route, self::FUTURE_PREFIX);
    }

    private static function may_expose_diagnostics(): bool {
        return defined('WP_DEBUG') && WP_DEBUG === true
            && current_user_can('cf01_view_clinical_health')
            && (bool) apply_filters('cf01_allow_runtime_diagnostics', false);
    }

    private static function emit_private_headers(): void {
        if (headers_sent()) {
            return;
        }
        foreach (self::private_headers() as $name => $value) {
            header($name . ': ' . $value, true);
        }
    }

    private static function private_headers(): array {
        return array(
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive, nosnippet, noimageindex',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        );
    }
}

register_activation_hook(__FILE__, array('CF01_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('CF01_Plugin', 'deactivate'));
CF01_Plugin::boot();

if (!function_exists('cf01_resolve_notification_destination')) {
    function cf01_resolve_notification_destination(string $reference, int $actor_id = 0): string {
        return CF01_Outbox::resolve_destination($actor_id > 0 ? $actor_id : get_current_user_id(), $reference);
    }
}
