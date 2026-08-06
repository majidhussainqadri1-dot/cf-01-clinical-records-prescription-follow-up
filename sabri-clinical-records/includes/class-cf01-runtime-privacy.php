<?php
defined('ABSPATH') || exit;

/**
 * Cross-cutting privacy and transport hardening for every CF-01 surface.
 *
 * Domain classes remain responsible for object and field authorization. This
 * class guarantees that an upstream WordPress theme, proxy or REST error path
 * cannot accidentally make clinical responses cacheable, frameable or richly
 * diagnostic to an unauthorised client.
 */
final class CF01_Runtime_Privacy {
    private const REST_PREFIX = '/clinical/v1/';

    public static function register(): void {
        add_action('send_headers', array(__CLASS__, 'send_private_headers'), 0);
        add_filter('rest_post_dispatch', array(__CLASS__, 'harden_rest_response'), 999, 3);
        add_filter('rest_pre_serve_request', array(__CLASS__, 'send_rest_headers'), 0, 4);
    }

    public static function send_private_headers(): void {
        if (!CF01_UI::is_clinical_request()) {
            return;
        }
        self::emit_headers();
    }

    public static function send_rest_headers(bool $served, $result, WP_REST_Request $request, WP_REST_Server $server): bool {
        unset($result, $server);
        if (self::is_clinical_rest_request($request)) {
            self::emit_headers();
        }
        return $served;
    }

    public static function harden_rest_response($response, WP_REST_Server $server, WP_REST_Request $request) {
        unset($server);
        if (!self::is_clinical_rest_request($request)) {
            return $response;
        }

        $response = rest_ensure_response($response);
        foreach (self::headers() as $name => $value) {
            $response->header($name, $value);
        }

        $status = (int) $response->get_status();
        if ($status >= 500 && !self::may_expose_diagnostics()) {
            $data = $response->get_data();
            $code = is_array($data) && isset($data['code'])
                ? sanitize_key((string) $data['code'])
                : 'cf01_internal_error';
            $response->set_data(array(
                'code' => $code ?: 'cf01_internal_error',
                'message' => __('The clinical service could not complete this request.', 'sabri-clinical-records'),
                'data' => array('status' => $status),
            ));
        }

        return $response;
    }

    private static function is_clinical_rest_request(WP_REST_Request $request): bool {
        $route = '/' . ltrim((string) $request->get_route(), '/');
        return str_starts_with($route, self::REST_PREFIX);
    }

    private static function may_expose_diagnostics(): bool {
        if (!defined('WP_DEBUG') || WP_DEBUG !== true) {
            return false;
        }
        return current_user_can('cf01_view_clinical_health')
            && (bool) apply_filters('cf01_allow_runtime_diagnostics', false);
    }

    private static function emit_headers(): void {
        if (headers_sent()) {
            return;
        }
        foreach (self::headers() as $name => $value) {
            header($name . ': ' . $value, true);
        }
    }

    private static function headers(): array {
        return array(
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        );
    }
}
