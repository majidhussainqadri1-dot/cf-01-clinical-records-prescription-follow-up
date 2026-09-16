<?php
defined('ABSPATH') || exit;

/**
 * Future Clinical Intelligence 24 foundation.
 *
 * This class intentionally provides a fail-closed, disabled-by-default contract
 * surface only. It does not create a second clinical source of truth and it does
 * not make any Future24 capability production-ready merely because source code
 * exists. Native CF-01 records remain canonical and provider adapters must pass
 * explicit governance/staging gates before a capability can become effective.
 */
final class CF01_Future24 {
    private const NS = 'clinical/v1';
    private const STATES = array('disabled', 'shadow', 'enabled');
    private const EXTRA_DATA_GOVERNANCE = array('CF01-FUT-020', 'CF01-FUT-021', 'CF01-FUT-022', 'CF01-FUT-023');

    public static function register_routes(): void {
        register_rest_route(self::NS, '/future/features', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'features'),
            'permission_callback' => array(__CLASS__, 'permission'),
        ));
        register_rest_route(self::NS, '/future/features/(?P<id>CF01-FUT-[0-9]{3})/state', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'feature_state'),
            'permission_callback' => array(__CLASS__, 'permission'),
        ));
        register_rest_route(self::NS, '/future/sidecar-assurance', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'sidecar_assurance'),
            'permission_callback' => array(__CLASS__, 'permission'),
        ));
        register_rest_route(self::NS, '/future/patients/(?P<patient>[a-f0-9-]{36})/timeline', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'patient_timeline'),
            'permission_callback' => array(__CLASS__, 'permission'),
        ));
        register_rest_route(self::NS, '/future/patients/(?P<patient>[a-f0-9-]{36})/features/(?P<id>CF01-FUT-[0-9]{3})', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'patient_feature'),
            'permission_callback' => array(__CLASS__, 'permission'),
        ));
        register_rest_route(self::NS, '/future/patients/(?P<patient>[a-f0-9-]{36})/features/(?P<id>CF01-FUT-[0-9]{3})/facts', array(
            array(
                'methods' => 'GET',
                'callback' => array(__CLASS__, 'patient_feature_facts'),
                'permission_callback' => array(__CLASS__, 'permission'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array(__CLASS__, 'write_patient_feature_fact'),
                'permission_callback' => array(__CLASS__, 'permission'),
            ),
        ));
        register_rest_route(self::NS, '/future/decision-support', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'decision_support'),
            'permission_callback' => array(__CLASS__, 'permission'),
        ));
        register_rest_route(self::NS, '/future/patient-reported-outcomes/(?P<followup>[a-f0-9-]{36})', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'patient_reported_outcomes'),
            'permission_callback' => array(__CLASS__, 'permission'),
        ));
        register_rest_route(self::NS, '/future/patients/(?P<patient>[a-f0-9-]{36})/research-consents', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'research_consents'),
            'permission_callback' => array(__CLASS__, 'permission'),
        ));
        register_rest_route(self::NS, '/future/institutional/webhooks/(?P<event>[a-z0-9._-]{1,80})', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'institutional_webhook'),
            'permission_callback' => array(__CLASS__, 'permission'),
        ));
        register_rest_route(self::NS, '/future/simulation', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'simulation'),
            'permission_callback' => array(__CLASS__, 'permission'),
        ));
        register_rest_route(self::NS, '/future/transparency/(?P<decision>[a-z0-9._-]{1,80})', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'transparency'),
            'permission_callback' => array(__CLASS__, 'permission'),
        ));
    }

    public static function permission(WP_REST_Request $request) {
        if (!is_user_logged_in()) {
            return new WP_Error('cf01_authentication_required', __('Authentication is required.', 'sabri-clinical-records'), array('status' => 401));
        }

        $route = '/' . ltrim((string) $request->get_route(), '/');
        $metadata_only = str_ends_with($route, '/future/features')
            || str_contains($route, '/future/features/')
            || str_ends_with($route, '/future/sidecar-assurance');

        if (!CF01_DB::is_enabled()) {
            if ($metadata_only && current_user_can('cf01_view_clinical_health')) {
                return true;
            }
            return new WP_Error('cf01_disabled', __('Clinical records are not available.', 'sabri-clinical-records'), array('status' => 503));
        }
        return true;
    }

    public static function manifest(): array {
        return array(
            'CF01-FUT-001' => array('key' => 'sidecar_assurance', 'label' => 'Clinical Sidecar & Membership Assurance', 'privacy' => 'C3'),
            'CF01-FUT-002' => array('key' => 'longitudinal_timeline', 'label' => 'Longitudinal Clinical Timeline', 'privacy' => 'C5'),
            'CF01-FUT-003' => array('key' => 'problem_diagnosis_tracking', 'label' => 'Problem List & Diagnosis Tracking', 'privacy' => 'C5'),
            'CF01-FUT-004' => array('key' => 'allergy_intolerance', 'label' => 'Allergy & Intolerance Management', 'privacy' => 'C5'),
            'CF01-FUT-005' => array('key' => 'medication_therapy', 'label' => 'Medication & Therapy Management', 'privacy' => 'C5'),
            'CF01-FUT-006' => array('key' => 'lab_results_trends', 'label' => 'Lab Results & Trends', 'privacy' => 'C5'),
            'CF01-FUT-007' => array('key' => 'imaging_diagnostics', 'label' => 'Imaging & Diagnostic Reports', 'privacy' => 'C5'),
            'CF01-FUT-008' => array('key' => 'clinical_documents', 'label' => 'Clinical Documents & Attachments', 'privacy' => 'C5'),
            'CF01-FUT-009' => array('key' => 'vitals_measurements', 'label' => 'Vitals & Measurements', 'privacy' => 'C5'),
            'CF01-FUT-010' => array('key' => 'immunization_preventive', 'label' => 'Immunization & Preventive Care', 'privacy' => 'C5'),
            'CF01-FUT-011' => array('key' => 'care_plans_goals', 'label' => 'Care Plans & Goals', 'privacy' => 'C5'),
            'CF01-FUT-012' => array('key' => 'encounters_notes', 'label' => 'Encounters & Clinical Notes', 'privacy' => 'C5'),
            'CF01-FUT-013' => array('key' => 'referrals_coordination', 'label' => 'Referrals & Care Coordination', 'privacy' => 'C5'),
            'CF01-FUT-014' => array('key' => 'orders_results_routing', 'label' => 'Orders & Results Routing', 'privacy' => 'C5'),
            'CF01-FUT-015' => array('key' => 'clinical_decision_support', 'label' => 'Clinical Decision Support', 'privacy' => 'C5'),
            'CF01-FUT-016' => array('key' => 'patient_reported_outcomes', 'label' => 'Patient-Reported Outcomes', 'privacy' => 'C5'),
            'CF01-FUT-017' => array('key' => 'family_social_history', 'label' => 'Family & Social History', 'privacy' => 'C5'),
            'CF01-FUT-018' => array('key' => 'reproductive_maternal', 'label' => 'Reproductive & Maternal Health', 'privacy' => 'C5'),
            'CF01-FUT-019' => array('key' => 'behavioral_mental_health', 'label' => 'Behavioral & Mental Health', 'privacy' => 'C5'),
            'CF01-FUT-020' => array('key' => 'genomics_precision', 'label' => 'Genomics & Precision Medicine', 'privacy' => 'C5'),
            'CF01-FUT-021' => array('key' => 'research_consent_registry', 'label' => 'Research & Consent Registry', 'privacy' => 'C5'),
            'CF01-FUT-022' => array('key' => 'institutional_api_webhooks', 'label' => 'Institutional Support API + Webhooks', 'privacy' => 'C5'),
            'CF01-FUT-023' => array('key' => 'training_simulation', 'label' => 'Agent Training & Simulation Lab', 'privacy' => 'Synthetic only'),
            'CF01-FUT-024' => array('key' => 'transparency_fairness', 'label' => 'Transparency & Fairness Center', 'privacy' => 'Minimum necessary'),
        );
    }

    public static function features(WP_REST_Request $request): WP_REST_Response {
        unset($request);
        $features = array();
        foreach (self::manifest() as $id => $meta) {
            $features[] = self::feature_metadata($id, $meta);
        }
        return rest_ensure_response(array('features' => $features, 'count' => count($features)));
    }

    public static function feature_state(WP_REST_Request $request): WP_REST_Response {
        $id = self::validated_id((string) $request['id']);
        return rest_ensure_response(self::feature_metadata($id, self::manifest()[$id]));
    }

    public static function sidecar_assurance(WP_REST_Request $request): WP_REST_Response {
        unset($request);
        $membership = CF01_Contracts::membership(get_current_user_id());
        return rest_ensure_response(array(
            'runtime_version' => CF01_VERSION,
            'schema_version' => CF01_SCHEMA_VERSION,
            'contract_version' => CF01_CONTRACT_VERSION,
            'activation_state' => (string) get_option('cf01_activation_state', 'disabled'),
            'schema_status' => (string) get_option('cf01_schema_status', 'not_installed'),
            'membership_assertion_valid' => !empty($membership['valid']),
            'membership_approved' => !empty($membership['approved']),
            'membership_suspended' => !empty($membership['suspended']),
            'future24_total' => count(self::manifest()),
            'future24_effectively_enabled' => count(array_filter(array_keys(self::manifest()), fn($id) => self::effective_state($id) === 'enabled')),
            'identity_source_of_truth' => 'external_membership_contract',
        ));
    }

    public static function patient_timeline(WP_REST_Request $request) {
        return self::patient_provider($request, 'CF01-FUT-002', 'timeline');
    }

    public static function patient_feature(WP_REST_Request $request) {
        return self::patient_provider($request, self::validated_id((string) $request['id']), 'feature');
    }

    public static function patient_feature_facts(WP_REST_Request $request) {
        return self::patient_provider($request, self::validated_id((string) $request['id']), 'facts_read');
    }

    public static function write_patient_feature_fact(WP_REST_Request $request) {
        self::require_mutation_guards($request, true);
        return self::patient_provider($request, self::validated_id((string) $request['id']), 'facts_write', true);
    }

    public static function decision_support(WP_REST_Request $request) {
        self::require_mutation_guards($request, true);
        $data = self::json($request);
        $patient = self::clinical_uuid((string) ($data['patient_uuid'] ?? ''));
        self::require_enabled('CF01-FUT-015');
        $context = CF01_Authorization::patient_context(get_current_user_id(), $patient, 'clinical_care', 'doctor');
        $result = self::provider('CF01-FUT-015', 'decision_support', array(
            'actor_user_id' => get_current_user_id(),
            'patient_uuid' => $patient,
            'role_context' => $context,
            'request' => $data,
        ));
        if (is_wp_error($result)) {
            return $result;
        }
        foreach (array('dose', 'dosage', 'potency', 'automatic_prescription', 'automatic_treatment_change', 'diagnosis_autonomous', 'prescription_automatic') as $forbidden) {
            if (!empty($result[$forbidden])) {
                return self::error('cf01_future24_unsafe_advice', 'Decision-support provider attempted an autonomous clinical action.', 422);
            }
        }
        $result['advisory'] = true;
        $result['clinician_review_required'] = true;
        $result['diagnosis_autonomous'] = false;
        $result['prescription_automatic'] = false;
        $result['automatic_treatment_change'] = false;
        return rest_ensure_response($result);
    }

    public static function patient_reported_outcomes(WP_REST_Request $request) {
        self::require_enabled('CF01-FUT-016');
        $followup = trim((string) $request['followup']);
        $result = self::provider('CF01-FUT-016', 'patient_reported_outcomes', array(
            'actor_user_id' => get_current_user_id(),
            'followup_uuid' => $followup,
        ));
        return is_wp_error($result) ? $result : rest_ensure_response($result + array('automatic_treatment_change' => false));
    }

    public static function research_consents(WP_REST_Request $request) {
        return self::patient_provider($request, 'CF01-FUT-021', 'research_consents');
    }

    public static function institutional_webhook(WP_REST_Request $request) {
        self::require_mutation_guards($request, false);
        self::require_enabled('CF01-FUT-022');
        if (!self::governance_ready('CF01-FUT-022')) {
            return self::error('cf01_future24_governance_missing', 'Institutional integration governance is incomplete.', 503);
        }
        $event = sanitize_key((string) $request['event']);
        $result = self::provider('CF01-FUT-022', 'institutional_webhook', array(
            'actor_user_id' => get_current_user_id(),
            'event' => $event,
            'request' => self::json($request),
        ));
        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public static function simulation(WP_REST_Request $request) {
        self::require_mutation_guards($request, false);
        self::require_enabled('CF01-FUT-023');
        $data = self::json($request);
        if (self::contains_real_subject_identifier($data)) {
            return self::error('cf01_future24_simulation_subject_rejected', 'Simulation accepts synthetic or de-identified fixtures only.', 422);
        }
        $result = self::provider('CF01-FUT-023', 'simulation', array(
            'actor_user_id' => get_current_user_id(),
            'request' => $data,
        ));
        if (is_wp_error($result)) {
            return $result;
        }
        $result['only_simulation'] = true;
        $result['not_for_patient_care'] = true;
        return rest_ensure_response($result);
    }

    public static function transparency(WP_REST_Request $request) {
        self::require_enabled('CF01-FUT-024');
        $result = self::provider('CF01-FUT-024', 'transparency', array(
            'actor_user_id' => get_current_user_id(),
            'decision' => sanitize_key((string) $request['decision']),
        ));
        if (is_wp_error($result)) {
            return $result;
        }
        foreach (array('donor_priority', 'payment_priority', 'paid_rank', 'donation_rank') as $forbidden) {
            if (!empty($result[$forbidden])) {
                return self::error('cf01_future24_financial_bias_rejected', 'Financial or donor preference is forbidden in clinical transparency.', 422);
            }
        }
        return rest_ensure_response($result);
    }

    public static function requested_state(string $id): string {
        $id = self::validated_id($id);
        $states = get_option('cf01_future24_feature_states', array());
        $state = sanitize_key((string) (is_array($states) ? ($states[$id] ?? 'disabled') : 'disabled'));
        return in_array($state, self::STATES, true) ? $state : 'disabled';
    }

    public static function effective_state(string $id): string {
        $requested = self::requested_state($id);
        if ($requested === 'disabled' || !self::governance_ready($id)) {
            return 'disabled';
        }
        return $requested;
    }

    public static function governance_ready(string $id): bool {
        $id = self::validated_id($id);
        $all = get_option('cf01_future24_governance_evidence', array());
        $evidence = is_array($all) && isset($all[$id]) && is_array($all[$id]) ? $all[$id] : array();
        $required = array('founder_approved', 'privacy_reviewed', 'clinical_safety_reviewed', 'security_reviewed', 'staging_accepted', 'rollback_ready');
        if (in_array($id, self::EXTRA_DATA_GOVERNANCE, true)) {
            $required[] = 'data_governance_approved';
        }
        foreach ($required as $key) {
            if (($evidence[$key] ?? false) !== true) {
                return false;
            }
        }
        return true;
    }

    private static function patient_provider(WP_REST_Request $request, string $id, string $operation, bool $mutation = false) {
        self::require_enabled($id);
        $patient = self::clinical_uuid((string) $request['patient']);
        $context = CF01_Authorization::patient_context(get_current_user_id(), $patient, 'clinical_care');
        $payload = array(
            'actor_user_id' => get_current_user_id(),
            'patient_uuid' => $patient,
            'role_context' => $context,
            'query' => $request->get_query_params(),
        );
        if ($mutation) {
            $payload['request'] = self::json($request);
            $payload['expected_version'] = self::expected_version_header($request);
            $payload['idempotency_key_hash'] = hash('sha256', (string) $request->get_header('Idempotency-Key'));
        }
        $result = self::provider($id, $operation, $payload);
        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    private static function require_enabled(string $id): void {
        if (self::effective_state($id) !== 'enabled') {
            throw new RuntimeException('Future clinical capability is disabled pending accepted governance evidence.');
        }
    }

    private static function provider(string $id, string $operation, array $context) {
        try {
            $result = apply_filters('cf01_future24_provider_response', null, $id, $operation, $context);
        } catch (Throwable $error) {
            return self::error('cf01_future24_provider_failed', 'Future clinical provider failed closed.', 503);
        }
        if ($result === null || $result === false) {
            return self::error('cf01_future24_provider_unavailable', 'Future clinical provider is unavailable.', 503);
        }
        if ($result instanceof WP_Error) {
            return $result;
        }
        if (!is_array($result)) {
            return self::error('cf01_future24_provider_invalid', 'Future clinical provider returned an invalid response.', 502);
        }
        return $result;
    }

    private static function require_mutation_guards(WP_REST_Request $request, bool $require_version): void {
        $key = trim((string) $request->get_header('Idempotency-Key'));
        if ($key === '' || strlen($key) < 16 || strlen($key) > 200) {
            throw new InvalidArgumentException('A bounded Idempotency-Key is required.');
        }
        if ($require_version) {
            self::expected_version_header($request);
        }
    }

    private static function expected_version_header(WP_REST_Request $request): int {
        $value = trim((string) $request->get_header('If-Match'));
        if ($value === '') {
            $value = trim((string) $request->get_header('X-CF01-Expected-Version'));
        }
        $value = trim($value, "\"W/ ");
        if ($value === '' || !ctype_digit($value) || (int) $value < 1) {
            throw new InvalidArgumentException('A positive expected record version is required.');
        }
        return (int) $value;
    }

    private static function feature_metadata(string $id, array $meta): array {
        return array(
            'id' => $id,
            'key' => (string) $meta['key'],
            'label' => (string) $meta['label'],
            'privacy_class' => (string) $meta['privacy'],
            'requested_state' => self::requested_state($id),
            'effective_state' => self::effective_state($id),
            'governance_ready' => self::governance_ready($id),
            'source_presence_is_not_activation' => true,
        );
    }

    private static function validated_id(string $id): string {
        $id = strtoupper(trim($id));
        $manifest = self::manifest();
        if (!isset($manifest[$id])) {
            throw new InvalidArgumentException('Unknown Future24 capability identifier.');
        }
        return $id;
    }

    private static function clinical_uuid(string $value): string {
        $value = strtolower(trim($value));
        if (!preg_match('/^[a-f0-9-]{36}$/', $value)) {
            throw new InvalidArgumentException('A valid clinical patient identifier is required.');
        }
        return $value;
    }

    private static function json(WP_REST_Request $request): array {
        $data = $request->get_json_params();
        return is_array($data) ? $data : array();
    }

    private static function contains_real_subject_identifier(array $data): bool {
        $forbidden = array('patient_uuid', 'platform_uuid', 'user_id', 'email', 'phone', 'mrn', 'clinical_uuid');
        $walk = function ($value) use (&$walk, $forbidden): bool {
            if (!is_array($value)) {
                return false;
            }
            foreach ($value as $key => $child) {
                if (in_array(sanitize_key((string) $key), $forbidden, true) && trim((string) $child) !== '') {
                    return true;
                }
                if (is_array($child) && $walk($child)) {
                    return true;
                }
            }
            return false;
        };
        return $walk($data);
    }

    private static function error(string $code, string $message, int $status): WP_Error {
        return new WP_Error($code, __($message, 'sabri-clinical-records'), array(
            'status' => $status,
            'trace_id' => function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : bin2hex(random_bytes(12)),
        ));
    }
}
