<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/wp/');
define('ARRAY_A', 'ARRAY_A');
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
define('CF01_VERSION', '1.0.0');
define('CF01_SCHEMA_VERSION', '1.0.0');
define('CF01_CONTRACT_VERSION', '1.0.0');
define('CF01_DIR', dirname(__DIR__) . '/sabri-clinical-records/');
define('CF01_URL', 'https://example.test/wp-content/plugins/sabri-clinical-records/');
define('CF01_MASTER_KEY', 'test-only-key-material-that-is-at-least-thirty-two-bytes-long');

$GLOBALS['cf01_options'] = array('cf01_activation_state' => 'enabled', 'cf01_schema_version' => '1.0.0', 'cf01_schema_status' => 'installed');
$GLOBALS['cf01_filters'] = array();
$GLOBALS['cf01_caps'] = array('*' => true);
$GLOBALS['cf01_current_user'] = 1;
$GLOBALS['cf01_transients'] = array();
$GLOBALS['cf01_schedules'] = array();

function wp_json_encode($value, int $flags = 0) { return json_encode($value, $flags | JSON_THROW_ON_ERROR); }
function sanitize_key($value): string { return trim(preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)) ?? ''); }
function sanitize_text_field($value): string { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value): string { return trim(strip_tags((string) $value)); }
function esc_url_raw($value): string { return filter_var((string) $value, FILTER_SANITIZE_URL) ?: ''; }
function esc_html__($value, $domain = ''): string { return (string) $value; }
function __($value, $domain = ''): string { return (string) $value; }
function esc_attr($value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function esc_html($value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function add_filter($tag, $callback, $priority = 10, $accepted_args = 1): bool { $GLOBALS['cf01_filters'][$tag][] = $callback; return true; }
function has_filter($tag): bool { return !empty($GLOBALS['cf01_filters'][$tag]); }
function apply_filters($tag, $value, ...$args) { foreach ($GLOBALS['cf01_filters'][$tag] ?? array() as $callback) { $value = $callback($value, ...$args); } return $value; }
function do_action($tag, ...$args): void { foreach ($GLOBALS['cf01_filters'][$tag] ?? array() as $callback) { $callback(...$args); } }
function add_action($tag, $callback, $priority = 10, $accepted_args = 1): bool { return add_filter($tag, $callback, $priority, $accepted_args); }
function current_user_can($capability): bool { return !empty($GLOBALS['cf01_caps']['*']) || !empty($GLOBALS['cf01_caps'][$capability]); }
function get_current_user_id(): int { return (int) $GLOBALS['cf01_current_user']; }
function is_user_logged_in(): bool { return get_current_user_id() > 0; }
function get_option($key, $default = false) { return array_key_exists($key, $GLOBALS['cf01_options']) ? $GLOBALS['cf01_options'][$key] : $default; }
function update_option($key, $value, $autoload = null): bool { $GLOBALS['cf01_options'][$key] = $value; return true; }
function set_transient($key, $value, $expiration): bool { $GLOBALS['cf01_transients'][$key] = $value; return true; }
function get_transient($key) { return $GLOBALS['cf01_transients'][$key] ?? false; }
function delete_transient($key): bool { unset($GLOBALS['cf01_transients'][$key]); return true; }
function wp_next_scheduled($hook) { return $GLOBALS['cf01_schedules'][$hook] ?? false; }
function wp_schedule_event($timestamp, $recurrence, $hook): bool { $GLOBALS['cf01_schedules'][$hook] = $timestamp; return true; }
function wp_clear_scheduled_hook($hook): int { unset($GLOBALS['cf01_schedules'][$hook]); return 1; }
function wp_generate_uuid4(): string { static $n = 0; $n++; return sprintf('00000000-0000-4000-8000-%012d', $n); }
function plugin_dir_path($file): string { return dirname($file) . '/'; }
function plugin_dir_url($file): string { return 'https://example.test/plugin/'; }
function register_rest_route($namespace, $route, $args): bool { return true; }
function register_activation_hook($file, $callback): void {}
function register_deactivation_hook($file, $callback): void {}
function load_plugin_textdomain($domain, $deprecated = false, $path = ''): bool { return true; }
function plugin_basename($file): string { return basename($file); }
function add_rewrite_rule($regex, $query, $after = 'bottom'): void {}
function add_rewrite_tag($tag, $regex): void {}
function add_shortcode($tag, $callback): void {}
function get_query_var($key, $default = '') { return $default; }
function is_admin(): bool { return false; }
function auth_redirect(): void {}
function nocache_headers(): void {}
function wp_enqueue_style(...$args): void {}
function wp_enqueue_script(...$args): void {}
function wp_localize_script(...$args): void {}
function wp_create_nonce($action): string { return 'nonce'; }
function determine_locale(): string { return 'en_US'; }
function rest_url($path = ''): string { return 'https://example.test/wp-json/' . ltrim($path, '/'); }
function home_url($path = ''): string { return 'https://example.test/' . ltrim((string) $path, '/'); }
function wp_parse_url($url) { return parse_url((string) $url); }
function do_shortcode($value): string { return (string) $value; }
function get_header(): void {}
function get_footer(): void {}
function flush_rewrite_rules($hard = true): void {}
function dbDelta($sql): array { return array(); }

class WP_Error {
    public function __construct(public string $code = '', public string $message = '', public array $data = array()) {}
}
class WP_REST_Request implements ArrayAccess {
    private array $params;
    private array $headers;
    private string $body;
    private string $route;
    public function __construct(array $params = array(), array $headers = array(), string $body = '', string $route = '') { $this->params = $params; $this->headers = array_change_key_case($headers, CASE_LOWER); $this->body = $body; $this->route = $route; }
    public function get_json_params(): array { return $this->body !== '' ? (json_decode($this->body, true) ?: array()) : $this->params; }
    public function get_header($name): string { return (string) ($this->headers[strtolower((string) $name)] ?? ''); }
    public function get_body(): string { return $this->body; }
    public function get_route(): string { return $this->route; }
    public function get_param($name) { return $this->params[$name] ?? null; }
    public function offsetExists(mixed $offset): bool { return array_key_exists($offset, $this->params); }
    public function offsetGet(mixed $offset): mixed { return $this->params[$offset] ?? null; }
    public function offsetSet(mixed $offset, mixed $value): void { $this->params[$offset] = $value; }
    public function offsetUnset(mixed $offset): void { unset($this->params[$offset]); }
}
class WP_REST_Response {
    public function __construct(public $data = null, public int $status = 200, public array $headers = array()) {}
}

final class CF01_Fake_WPDB {
    public string $prefix = 'wp_';
    public int $insert_id = 0;
    public array $tables = array();
    private array $auto = array();

    public function query(string $sql) { return true; }

    public function insert(string $table, array $data, $formats = null) {
        $uniqueMap = array(
            'wp_cf01_clinical_patients' => array('clinical_uuid','platform_subject_hash'),
            'wp_cf01_care_relationships' => array('relationship_uuid'),
            'wp_cf01_clinical_consents' => array('consent_uuid'),
            'wp_cf01_encounters' => array('encounter_uuid'),
            'wp_cf01_observations' => array('observation_uuid'),
            'wp_cf01_attachments' => array('attachment_uuid'),
            'wp_cf01_assessments' => array('assessment_uuid'),
            'wp_cf01_prescriptions' => array('prescription_uuid'),
            'wp_cf01_followups' => array('followup_uuid'),
            'wp_cf01_patient_outcomes' => array('outcome_uuid'),
            'wp_cf01_access_events' => array('event_uuid'),
            'wp_cf01_rights_cases' => array('case_uuid'),
            'wp_cf01_break_glass' => array('grant_uuid'),
            'wp_cf01_audit_ledger' => array('event_uuid','chain_hash'),
            'wp_cf01_outbox' => array('event_uuid'),
            'wp_cf01_retention_ledger' => array('retention_uuid'),
            'wp_cf01_migrations' => array('migration_uuid'),
            'wp_cf01_command_receipts' => array('receipt_uuid','key_hash'),
        );
        $unique = $uniqueMap[$table] ?? array();
        foreach ($this->tables[$table] ?? array() as $row) {
            foreach ($unique as $field) {
                if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '' && array_key_exists($field, $row) && (string) $row[$field] === (string) $data[$field]) {
                    return false;
                }
            }
        }
        $this->auto[$table] = ($this->auto[$table] ?? 0) + 1;
        $data['id'] = $data['id'] ?? $this->auto[$table];
        $this->insert_id = (int) $data['id'];
        $this->tables[$table][] = $data;
        return 1;
    }

    public function update(string $table, array $data, array $where) {
        $count = 0;
        if (!isset($this->tables[$table])) {
            return 0;
        }
        foreach ($this->tables[$table] as &$row) {
            if ($this->matches($row, $where)) {
                $row = array_merge($row, $data);
                $count++;
            }
        }
        unset($row);
        return $count;
    }

    public function prepare(string $query, $args = null): string {
        $values = is_array($args) ? array_values($args) : array_slice(func_get_args(), 1);
        $i = 0;
        return preg_replace_callback('/%(?:\d+\$)?([sdf])/', function (array $m) use (&$i, $values): string {
            $value = $values[$i++] ?? null;
            if ($m[1] === 'd') return (string) (int) $value;
            if ($m[1] === 'f') return (string) (float) $value;
            return "'" . str_replace("'", "''", (string) $value) . "'";
        }, $query) ?? $query;
    }

    public function get_row(string $sql, $format = ARRAY_A) {
        $rows = $this->select($sql);
        return $rows[0] ?? null;
    }

    public function get_results(string $sql, $format = ARRAY_A): array { return $this->select($sql); }

    private function select(string $sql): array {
        if (!preg_match('/\bFROM\s+([A-Za-z0-9_]+)/i', $sql, $match)) return array();
        $table = $match[1];
        $rows = array_values($this->tables[$table] ?? array());
        if (preg_match('/\bWHERE\s+(.+?)(?:\bORDER\s+BY\b|\bGROUP\s+BY\b|\bLIMIT\b|$)/is', $sql, $whereMatch)) {
            $where = trim($whereMatch[1]);
            $rows = array_values(array_filter($rows, fn(array $row): bool => $this->matchesSql($row, $where)));
        }
        if (preg_match('/ORDER\s+BY\s+(\w+)\s+(ASC|DESC)/i', $sql, $order)) {
            usort($rows, function (array $a, array $b) use ($order): int {
                $cmp = ($a[$order[1]] ?? null) <=> ($b[$order[1]] ?? null);
                return strtoupper($order[2]) === 'DESC' ? -$cmp : $cmp;
            });
        }
        if (preg_match('/GROUP\s+BY\s+(\w+)/i', $sql, $group)) {
            $grouped = array();
            foreach ($rows as $row) $grouped[(string) ($row[$group[1]] ?? '')] = ($grouped[(string) ($row[$group[1]] ?? '')] ?? 0) + 1;
            return array_map(fn($key, $total): array => array($group[1] => $key, 'total' => $total), array_keys($grouped), array_values($grouped));
        }
        if (preg_match('/SELECT\s+COUNT\(\*\)\s+AS\s+(\w+)/i', $sql, $count)) return array(array($count[1] => count($rows)));
        if (preg_match('/LIMIT\s+(\d+)/i', $sql, $limit)) $rows = array_slice($rows, 0, (int) $limit[1]);
        return $rows;
    }

    private function matches(array $row, array $where): bool {
        foreach ($where as $key => $value) {
            if (!array_key_exists($key, $row) || (string) $row[$key] !== (string) $value) return false;
        }
        return true;
    }

    private function matchesSql(array $row, string $where): bool {
        $clauses = preg_split('/\s+AND\s+/i', trim($where)) ?: array();
        foreach ($clauses as $clause) {
            $clause = trim($clause, " ()\t\n\r\0\x0B");
            if (preg_match('/^(\w+)\s+IN\s*\((.+)\)$/i', $clause, $m)) {
                $values = array_map(fn($v): string => trim($v, " '\""), explode(',', $m[2]));
                if (!in_array((string) ($row[$m[1]] ?? ''), $values, true)) return false;
                continue;
            }
            if (preg_match('/^(\w+)\s+IS\s+(NOT\s+)?NULL$/i', $clause, $m)) {
                $isNull = !isset($row[$m[1]]) || $row[$m[1]] === null;
                if (!empty($m[2]) ? $isNull : !$isNull) return false;
                continue;
            }
            if (preg_match('/^(\w+)\s*(=|<>|<=|>=|<|>)\s*(?:\'((?:\'\'|[^\'])*)\'|(-?\d+(?:\.\d+)?))$/', $clause, $m)) {
                $left = $row[$m[1]] ?? null;
                $right = $m[3] !== '' ? str_replace("''", "'", $m[3]) : $m[4];
                $ok = match ($m[2]) { '=' => (string) $left === (string) $right, '<>' => (string) $left !== (string) $right, '<=' => $left <= $right, '>=' => $left >= $right, '<' => $left < $right, '>' => $left > $right };
                if (!$ok) return false;
                continue;
            }
        }
        return true;
    }
}

$GLOBALS['wpdb'] = new CF01_Fake_WPDB();

function smc_membership_assertions(int $user_id): array {
    return array('contract_version' => '1.1.2', 'platform_uuid' => 'platform-user-' . $user_id, 'approved' => true, 'suspended' => false, 'account_class' => $user_id === 2 ? 'doctor' : 'member', 'guardian_context' => array('status' => 'not_required'));
}
function sauth_cf01_recent_authentication_assertion(int $user_id, string $purpose): array {
    return array('contract_version' => '1.0.0', 'recent_auth' => true, 'step_up' => true, 'expires_at' => gmdate('Y-m-d H:i:s', time() + 300), 'subject_uuid' => 'platform-user-' . $user_id);
}
function sgd_cf01_professional_eligibility_assertion(int $user_id, string $purpose): array {
    return array('contract_version' => '1.0.0', 'eligible' => $user_id === 2, 'verified' => $user_id === 2, 'suspended' => false, 'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600), 'professional_uuid' => 'doctor-' . $user_id, 'jurisdictions' => array('PK'), 'scopes' => array('clinical_care'));
}
function swc_cf01_care_context_assertion(string $reference, int $actor_id): array {
    return array('contract_version' => '1.0.0', 'patient_platform_uuid' => 'platform-user-1', 'practitioner_platform_uuid' => 'platform-user-2', 'appointment_reference' => $reference, 'actor_platform_uuid' => 'platform-user-' . $actor_id);
}
function sn_cf01_communication_context_assertion(string $reference, int $actor_id): array {
    return array('contract_version' => '1.0.0', 'reference_uuid' => $reference, 'conversation_reference' => 'conversation-opaque', 'purpose' => 'clinical_care', 'expires_at' => gmdate('Y-m-d H:i:s', time() + 300), 'revoked' => false);
}
function sun_cf01_request_notification(array $request): array { return array('accepted' => true, 'suppressed' => false, 'retryable' => false, 'reference' => 'notification-opaque', 'code' => 'accepted'); }

add_filter('cf01_allow_service_actor', fn($allowed, int $current, int $asserted, string $action): bool => true, 10, 4);

add_filter('cf01_secure_media_operation', function ($result, string $operation, array $request): array {
    return array('contract_version' => '1.0.0', 'accepted' => true, 'asset_reference' => (string) ($request['asset_reference'] ?? 'asset-opaque'), 'privacy_class' => 'C5', 'delivery_grant' => 'delivery-grant-opaque');
}, 10, 3);
add_filter('cf01_shell_route_registration', fn($result, array $manifest): array => array('contract_version' => '1.0.0', 'registered' => true, 'private' => true, 'no_store' => true), 10, 2);
add_filter('cf01_visual_component_registration', fn($result, array $manifest): array => array('contract_version' => '1.0.0', 'accepted' => true, 'rtl' => true, 'accessibility' => true), 10, 2);
add_filter('cf01_assurance_manifest_registration', fn($result, array $manifest): array => array('contract_version' => '1.0.0', 'registered' => true, 'native_enforcement_preserved' => true), 10, 2);
add_filter('cf01_prescription_safety_review', fn($result, array $request, array $order): array => array('contract_version' => '1.0.0', 'passed' => true, 'blocking' => false, 'warnings' => array(), 'evidence_reference' => 'safety-evidence-opaque'), 10, 3);


add_filter('cf01_subject_identity_assertion', function ($result, string $platform_uuid, int $actor_id, string $purpose): array {
    return array(
        'contract_version' => '1.0.0', 'subject_platform_uuid' => $platform_uuid,
        'active' => true, 'revoked' => false, 'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
        'evidence_reference' => 'identity-evidence-' . $actor_id,
    );
}, 10, 4);
add_filter('cf01_relationship_source_assertion', function ($result, string $reference, int $actor_id, string $patient_uuid, int $doctor_user_id, string $purpose, array $scope): array {
    $reference = $reference !== '' ? $reference : 'relationship-source-test-' . $patient_uuid;
    return array(
        'contract_version' => '1.0.0', 'accepted' => true, 'revoked' => false,
        'source_reference' => $reference, 'patient_uuid' => $patient_uuid,
        'doctor_user_id' => $doctor_user_id, 'purpose' => $purpose, 'scope' => $scope,
        'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
    );
}, 10, 7);
add_filter('cf01_guardian_authority_assertion', function ($result, int $actor_id, string $patient_uuid, string $purpose, array $context): array {
    $reference = (string) ($context['reference'] ?? $context['guardian_reference'] ?? 'guardian-evidence-' . $actor_id);
    return array(
        'valid' => true, 'contract_version' => '1.0.0', 'accepted' => true,
        'revoked' => false, 'suspended' => false, 'actor_user_id' => $actor_id,
        'actor_platform_uuid' => 'platform-user-' . $actor_id, 'patient_uuid' => $patient_uuid,
        'guardian_reference' => $reference, 'scopes' => array('clinical_care', 'clinical_rights', 'images', 'teleconsultation', 'recording', 'transfer', 'research', 'educational_reuse'),
        'authority_version' => 1, 'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
    );
}, 10, 5);
add_filter('cf01_clinical_template_assertion', function ($result, string $key, string $version, string $context): array {
    return array(
        'contract_version' => '1.0.0', 'accepted' => true, 'revoked' => false,
        'template_key' => $key, 'template_version' => $version, 'context' => $context,
        'effective_at' => '2026-08-01 00:00:00', 'historical_rendering_supported' => true,
    );
}, 10, 4);
add_filter('cf01_terminology_mapping_assertion', function ($result, array $mapping): array {
    return array(
        'contract_version' => '1.0.0', 'accepted' => true, 'profile_version' => 'test-1',
        'terminology_version' => 'test-1', 'mappings' => $mapping, 'round_trip_preserved' => true,
    );
}, 10, 2);
add_filter('cf01_emergency_policy_request', function ($result, array $request): array {
    return array(
        'contract_version' => '1.0.0', 'accepted' => true,
        'guidance' => 'Use the approved local emergency pathway.', 'alerted' => true,
        'alert_reference' => 'emergency-alert-test', 'local_emergency_direction' => true,
    );
}, 10, 2);
add_filter('cf01_care_team_assertion', function ($result, int $actor_id, string $patient_uuid, string $purpose, string $role): array {
    return array(
        'valid' => true, 'accepted' => true, 'revoked' => false, 'suspended' => false,
        'contract_version' => '1.0.0', 'assignment_reference' => 'care-team-test',
        'assignment_version' => 1, 'actor_user_id' => $actor_id, 'patient_uuid' => $patient_uuid,
        'purpose' => $purpose, 'role' => $role, 'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
    );
}, 10, 6);
add_filter('cf01_clinical_oversight_assertion', function ($result, int $actor_id, string $patient_uuid, string $purpose, string $role): array {
    return array(
        'valid' => true, 'accepted' => true, 'revoked' => false, 'suspended' => false,
        'contract_version' => '1.0.0', 'assignment_reference' => 'oversight-test',
        'assignment_version' => 1, 'actor_user_id' => $actor_id, 'patient_uuid' => $patient_uuid,
        'purpose' => $purpose, 'role' => $role, 'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
    );
}, 10, 6);

$files = array(
    'class-cf01-db.php','class-cf01-crypto.php','class-cf01-contracts.php','class-cf01-authorization.php',
    'class-cf01-patients.php','class-cf01-role-context.php','class-cf01-relationships.php','class-cf01-consents.php','class-cf01-encounters.php',
    'class-cf01-attachments.php','class-cf01-prescriptions.php','class-cf01-followups.php','class-cf01-rights.php',
    'class-cf01-break-glass.php','class-cf01-audit-outbox.php','class-cf01-retention.php','class-cf01-rest.php',
    'class-cf01-ui-health.php','class-cf01-migrations.php',
);
foreach ($files as $file) require_once CF01_DIR . 'includes/' . $file;
CF01_DB::register_tables();

function cf01_assert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function cf01_expect_exception(callable $callback, string $contains = ''): void {
    try { $callback(); } catch (Throwable $error) { if ($contains !== '' && !str_contains($error->getMessage(), $contains)) throw new RuntimeException('Unexpected exception: ' . $error->getMessage()); return; }
    throw new RuntimeException('Expected exception was not raised.');
}
function cf01_reset(): void {
    $GLOBALS['wpdb'] = new CF01_Fake_WPDB();
    $GLOBALS['cf01_options'] = array('cf01_activation_state' => 'enabled', 'cf01_schema_version' => '1.0.0', 'cf01_schema_status' => 'installed');
    $GLOBALS['cf01_current_user'] = 1;
    $GLOBALS['cf01_caps'] = array('*' => true);
    CF01_DB::register_tables();
}
