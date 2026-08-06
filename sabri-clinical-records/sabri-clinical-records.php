<?php
/**
 * Plugin Name: Sabri Clinical Records
 * Description: Conditional, disabled-by-default clinical records, prescription and follow-up system of record for the Sabri Social Homeopathy Platform.
 * Version: 1.0.1
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Dr. Allamah Majid Hussain Sabri Muhaddith Mursheed
 * Text Domain: sabri-clinical-records
 */

defined('ABSPATH') || exit;

define('CF01_VERSION', '1.0.1');
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
    public static function boot(): void {
        CF01_Activation_Evidence::register();
        CF01_Release_Orchestrator::register();
        add_action('plugins_loaded', array(__CLASS__, 'plugins_loaded'));
        add_action('init', array('CF01_UI', 'register'));
        add_action('rest_api_init', array('CF01_REST', 'register_routes'));
        add_action('rest_api_init', array('CF01_Lifecycle_REST', 'register_routes'));
        add_action('cf01_process_outbox', array('CF01_Outbox', 'process'));
        add_action('cf01_retention_reconcile', array('CF01_Retention', 'reconcile'));
        add_action('cf01_followup_reconcile', array('CF01_Followups', 'reconcile_due'));
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
}

register_activation_hook(__FILE__, array('CF01_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('CF01_Plugin', 'deactivate'));
CF01_Plugin::boot();

if (!function_exists('cf01_resolve_notification_destination')) {
    function cf01_resolve_notification_destination(string $reference, int $actor_id = 0): string {
        return CF01_Outbox::resolve_destination($actor_id > 0 ? $actor_id : get_current_user_id(), $reference);
    }
}
