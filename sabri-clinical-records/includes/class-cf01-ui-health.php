<?php
defined('ABSPATH') || exit;

final class CF01_UI {
    private const ROUTES = array(
        '^clinic/records/?$' => 'records',
        '^clinic/patients/([a-f0-9-]{36})/?$' => 'patient',
        '^my-health-record/?$' => 'my_record',
        '^clinic/encounters/([a-f0-9-]{36})/?$' => 'encounter',
        '^clinic/prescriptions/([a-f0-9-]{36})/?$' => 'prescription',
        '^clinic/follow-ups/([a-f0-9-]{36})/?$' => 'followup',
        '^admin/clinical-governance/?$' => 'governance',
    );

    public static function register(): void {
        foreach (self::ROUTES as $pattern => $view) {
            add_rewrite_rule($pattern, 'index.php?cf01_view=' . $view . '&cf01_object=$matches[1]', 'top');
        }
        add_rewrite_tag('%cf01_view%', '([^&]+)');
        add_rewrite_tag('%cf01_object%', '([a-f0-9-]{36})');
        add_filter('template_include', array(__CLASS__, 'template'));
        add_action('send_headers', array(__CLASS__, 'headers'));
        add_shortcode('sabri_clinical_records', array(__CLASS__, 'shortcode'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'assets'));
    }

    public static function is_clinical_request(): bool {
        return get_query_var('cf01_view') !== '' || is_admin() && isset($_GET['page']) && str_starts_with(sanitize_key((string) $_GET['page']), 'cf01');
    }

    public static function template(string $template): string {
        if (!self::is_clinical_request()) {
            return $template;
        }
        if (!is_user_logged_in()) {
            auth_redirect();
            exit;
        }
        return CF01_DIR . 'templates/clinical-shell.php';
    }

    public static function headers(): void {
        if (!self::is_clinical_request()) {
            return;
        }
        nocache_headers();
        header('Cache-Control: no-store, private, max-age=0, must-revalidate', true);
        header('Pragma: no-cache', true);
        header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex', true);
        header("Referrer-Policy: no-referrer", true);
        header("X-Frame-Options: DENY", true);
        header("X-Content-Type-Options: nosniff", true);
        header("Permissions-Policy: camera=(), microphone=(), geolocation=()", true);
    }

    public static function assets(): void {
        if (!self::is_clinical_request()) {
            return;
        }
        wp_enqueue_style('cf01-clinical', CF01_URL . 'assets/css/clinical.css', array(), CF01_VERSION);
        wp_enqueue_script('cf01-clinical', CF01_URL . 'assets/js/clinical.js', array(), CF01_VERSION, true);
        wp_localize_script('cf01-clinical', 'CF01_APP', array(
            'restRoot' => esc_url_raw(rest_url('clinical/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'locale' => determine_locale(),
            'activation' => CF01_DB::activation_state(),
            'noOfflineStorage' => true,
        ));
    }

    public static function shortcode(array $atts = array()): string {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('Sign in to access protected clinical records.', 'sabri-clinical-records') . '</p>';
        }
        if (!CF01_DB::is_enabled()) {
            return '<div class="cf01-state cf01-state--disabled" role="status"><h2>' . esc_html__('Clinical records are not active.', 'sabri-clinical-records') . '</h2><p>' . esc_html__('This conditional module remains disabled until all legal, security, staging and operational gates are accepted.', 'sabri-clinical-records') . '</p></div>';
        }
        $view = sanitize_key((string) get_query_var('cf01_view', 'records'));
        $object = sanitize_text_field((string) get_query_var('cf01_object', ''));
        return '<main class="cf01-app" data-view="' . esc_attr($view) . '" data-object="' . esc_attr($object) . '" aria-live="polite"><a class="cf01-skip" href="#cf01-content">' . esc_html__('Skip to clinical content', 'sabri-clinical-records') . '</a><header><h1>' . esc_html__('Protected Clinical Records', 'sabri-clinical-records') . '</h1><p>' . esc_html__('Private, no-store clinical workspace. Emergency care is not provided through this website.', 'sabri-clinical-records') . '</p></header><section id="cf01-content" tabindex="-1"><div class="cf01-loading" role="status">' . esc_html__('Loading protected clinical content…', 'sabri-clinical-records') . '</div></section></main>';
    }
}

final class CF01_Health {
    public static function report(int $actor_id = 0): array {
        $private = false;
        if ($actor_id > 0) {
            try {
                CF01_Authorization::actor($actor_id, 'view_clinical_health');
                $private = true;
            } catch (Throwable $error) {
                $private = false;
            }
        }
        $schema = (string) get_option('cf01_schema_version', 'not_installed');
        $base = array(
            'module' => 'CF-01 Clinical Records, Prescription and Follow-Up',
            'runtime_version' => CF01_VERSION,
            'schema_version' => $schema,
            'activation_state' => CF01_DB::activation_state(),
            'encryption_available' => CF01_Crypto::available(),
            'native_enforcement_preserved' => true,
            'real_patient_data_allowed' => CF01_DB::is_enabled(),
            'status' => CF01_DB::is_enabled() && CF01_Crypto::available() ? 'available' : 'inactive_or_unavailable',
        );
        if ($private) {
            $base['contracts'] = CF01_Contracts::dependency_report();
            $base['queues'] = self::queue_counts();
            $base['assurance_manifest'] = CF01_Contracts::assurance_manifest();
        }
        return $base;
    }

    private static function queue_counts(): array {
        $rows = CF01_DB::rows('SELECT status, COUNT(*) AS total FROM ' . CF01_DB::table('outbox') . ' GROUP BY status');
        $counts = array();
        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }
        return $counts;
    }
}
