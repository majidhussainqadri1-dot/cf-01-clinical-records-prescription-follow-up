<?php
defined('ABSPATH') || exit;

/**
 * Lifecycle and role-specific REST surface.
 * Every mutation delegates to the canonical clinical domain owner classes.
 */
final class CF01_Lifecycle_REST {
    private const NS = 'clinical/v1';

    public static function register_routes(): void {
        $routes = array(
            array('/patients/(?P<patient>[a-f0-9-]{36})/authorized-view', 'GET', 'authorized_view'),
            array('/patients/(?P<patient>[a-f0-9-]{36})/access-history', 'GET', 'access_history'),
            array('/relationships/(?P<id>[a-f0-9-]{36})/activate', 'POST', 'activate_relationship'),
            array('/relationships/(?P<id>[a-f0-9-]{36})/transition', 'POST', 'transition_relationship'),
            array('/consents/(?P<id>[a-f0-9-]{36})/withdraw', 'POST', 'withdraw_consent'),
            array('/encounters/(?P<id>[a-f0-9-]{36})/entered-in-error', 'POST', 'encounter_error'),
            array('/encounters/(?P<id>[a-f0-9-]{36})/observations', 'POST', 'add_observation'),
            array('/observations/(?P<id>[a-f0-9-]{36})/correct', 'POST', 'correct_observation'),
            array('/encounters/(?P<id>[a-f0-9-]{36})/assessments', 'POST', 'create_assessment'),
            array('/assessments/(?P<id>[a-f0-9-]{36})/sign', 'POST', 'sign_assessment'),
            array('/prescriptions/(?P<id>[a-f0-9-]{36})', 'PATCH', 'update_prescription'),
            array('/prescriptions/(?P<id>[a-f0-9-]{36})/supersede', 'POST', 'supersede_prescription'),
            array('/followups/(?P<id>[a-f0-9-]{36})/reschedule', 'POST', 'reschedule_followup'),
            array('/followups/(?P<id>[a-f0-9-]{36})/close', 'POST', 'close_followup'),
            array('/break-glass/(?P<id>[a-f0-9-]{36})/revoke', 'POST', 'revoke_break_glass'),
            array('/break-glass/(?P<id>[a-f0-9-]{36})/review', 'POST', 'review_break_glass'),
            array('/rights/(?P<id>[a-f0-9-]{36})/correction', 'POST', 'fulfill_correction'),
            array('/governance/activation/validate', 'POST', 'validate_activation'),
            array('/governance/activation/activate', 'POST', 'activate_runtime'),
            array('/governance/activation/disable', 'POST', 'disable_runtime'),
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
        if (str_contains($request->get_route(), '/' . self::NS . '/governance/activation/')) {
            if (!current_user_can('cf01_activate_clinical')) {
                return new WP_Error('cf01_activation_authority_required', __('Clinical activation authority is required.', 'sabri-clinical-records'), array('status' => 403));
            }
            return true;
        }
        return CF01_REST::permission($request);
    }

    public static function authorized_view(WP_REST_Request $request): WP_REST_Response {
        return self::respond(function () use ($request): array {
            $patient_uuid = (string) $request['patient'];
            $purpose = sanitize_key((string) ($request->get_param('purpose') ?: 'clinical_care'));
            $context = CF01_Role_Context::resolve(
                get_current_user_id(),
                $patient_uuid,
                $purpose,
                sanitize_key((string) $request->get_param('role'))
            );
            $requested = self::requested_fields($request);
            if (!$requested) {
                $requested = self::default_fields((string) $context['role']);
            }
            $fields = CF01_Role_Context::fields($context, $requested);
            if (!$fields) {
                throw new RuntimeException('No minimum-necessary clinical fields are authorized.');
            }
            $result = self::project_authorized_view($patient_uuid, $context, $fields);
            CF01_Audit::access(
                get_current_user_id(),
                $patient_uuid,
                'RoleScopedClinicalView',
                'clinical_patient',
                $patient_uuid,
                $purpose,
                'success'
            );
            return $result;
        });
    }

    public static function access_history(WP_REST_Request $request): WP_REST_Response {
        return self::respond(function () use ($request): array {
            $patient_uuid = (string) $request['patient'];
            $context = CF01_Role_Context::resolve(
                get_current_user_id(),
                $patient_uuid,
                'privacy_transparency',
                sanitize_key((string) $request->get_param('role'))
            );
            if (!in_array((string) $context['role'], array('patient', 'guardian', 'records', 'auditor'), true)) {
                throw new RuntimeException('Access history is unavailable for this clinical role.');
            }
            $limit = max(1, min(100, (int) ($request->get_param('limit') ?: 25)));
            $page = self::access_page($patient_uuid, (string) $context['role'], (string) $request->get_param('cursor'), $limit);
            CF01_Audit::access(
                get_current_user_id(),
                $patient_uuid,
                'ClinicalAccessHistoryViewed',
                'clinical_patient',
                $patient_uuid,
                'privacy_transparency',
                'success'
            );
            return $page;
        });
    }

    public static function activate_relationship(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'ActivateCareRelationship', fn(array $data): array => CF01_Relationships::activate(get_current_user_id(), (string) $request['id'], self::version($request, $data)));
    }

    public static function transition_relationship(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'TransitionCareRelationship', function (array $data) use ($request): array {
            return CF01_Relationships::transition(
                get_current_user_id(),
                (string) $request['id'],
                sanitize_key((string) ($data['status'] ?? '')),
                (string) ($data['reason'] ?? ''),
                self::version($request, $data)
            );
        });
    }

    public static function withdraw_consent(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'WithdrawClinicalConsent', fn(array $data): array => CF01_Consents::withdraw(get_current_user_id(), (string) $request['id'], (string) ($data['reason'] ?? ''), self::version($request, $data)));
    }

    public static function encounter_error(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'MarkEncounterEnteredInError', fn(array $data): array => CF01_Encounters::mark_entered_in_error(get_current_user_id(), (string) $request['id'], (string) ($data['reason'] ?? ''), self::version($request, $data)));
    }

    public static function add_observation(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'AddClinicalObservation', fn(array $data): array => CF01_Encounters::add_observation(get_current_user_id(), (string) $request['id'], sanitize_key((string) ($data['type'] ?? '')), $data['value'] ?? null, (array) ($data['provenance'] ?? array())));
    }

    public static function correct_observation(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'CorrectClinicalObservation', fn(array $data): array => CF01_Encounters::correct_observation(get_current_user_id(), (string) $request['id'], $data['value'] ?? null, (string) ($data['reason'] ?? ''), self::version($request, $data)));
    }

    public static function create_assessment(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'CreateClinicalAssessment', fn(array $data): array => CF01_Encounters::create_assessment(get_current_user_id(), (string) $request['id'], (array) ($data['assessment'] ?? $data)));
    }

    public static function sign_assessment(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'SignClinicalAssessment', fn(array $data): array => CF01_Encounters::sign_assessment(get_current_user_id(), (string) $request['id'], self::version($request, $data)));
    }

    public static function update_prescription(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'UpdatePrescriptionDraft', fn(array $data): array => CF01_Prescriptions::update(get_current_user_id(), (string) $request['id'], (array) ($data['order'] ?? $data), sanitize_key((string) ($data['status'] ?? 'draft')), self::version($request, $data)));
    }

    public static function supersede_prescription(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'SupersedePrescription', fn(array $data): array => CF01_Prescriptions::supersede(get_current_user_id(), (string) $request['id'], (array) ($data['replacement'] ?? array()), self::version($request, $data)));
    }

    public static function reschedule_followup(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'RescheduleFollowUp', fn(array $data): array => CF01_Followups::reschedule(get_current_user_id(), (string) $request['id'], (string) ($data['due_at'] ?? ''), (string) ($data['reason'] ?? ''), self::version($request, $data)));
    }

    public static function close_followup(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'CloseFollowUp', fn(array $data): array => CF01_Followups::close(get_current_user_id(), (string) $request['id'], (string) ($data['reason'] ?? ''), self::version($request, $data)));
    }

    public static function revoke_break_glass(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'RevokeBreakGlass', fn(array $data): array => CF01_Break_Glass::revoke(get_current_user_id(), (string) $request['id'], (string) ($data['reason'] ?? ''), self::version($request, $data)));
    }

    public static function review_break_glass(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'ReviewBreakGlass', fn(array $data): array => CF01_Break_Glass::review(get_current_user_id(), (string) $request['id'], (array) ($data['review'] ?? $data), self::version($request, $data)));
    }

    public static function fulfill_correction(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'FulfillClinicalCorrection', fn(array $data): array => CF01_Rights::correction_addendum(get_current_user_id(), (string) $request['id'], (string) ($data['encounter_uuid'] ?? ''), (array) ($data['correction'] ?? array()), self::version($request, $data)));
    }

    public static function validate_activation(WP_REST_Request $request): WP_REST_Response {
        return self::respond(fn(): array => CF01_Activation_Evidence::validate(self::json($request)));
    }

    public static function activate_runtime(WP_REST_Request $request): WP_REST_Response {
        return self::governance_mutation($request, 'ActivateClinicalRuntime', function (array $data): array {
            $validation = CF01_Activation_Evidence::validate($data);
            CF01_Migrations::activate_runtime(get_current_user_id(), $data);
            return array('activation_state' => CF01_DB::activation_state(), 'validation' => $validation);
        });
    }

    public static function disable_runtime(WP_REST_Request $request): WP_REST_Response {
        return self::governance_mutation($request, 'DisableClinicalRuntime', function (array $data): array {
            CF01_Migrations::disable_runtime(get_current_user_id(), (string) ($data['reason'] ?? ''));
            return array('activation_state' => CF01_DB::activation_state());
        });
    }

    private static function project_authorized_view(string $patient_uuid, array $context, array $fields): array {
        $result = array(
            'role' => (string) $context['role'],
            'purpose' => (string) $context['purpose'],
            'fields' => $fields,
            'patient' => CF01_Patients::public_row(CF01_Patients::get($patient_uuid)),
            'collections' => array(),
        );
        $map = array(
            'encounters' => array('encounters', 'encounter_uuid'),
            'recent_encounters' => array('encounters', 'encounter_uuid'),
            'observations' => array('observations', 'observation_uuid'),
            'assessments' => array('assessments', 'assessment_uuid'),
            'prescriptions' => array('prescriptions', 'prescription_uuid'),
            'active_prescriptions' => array('prescriptions', 'prescription_uuid'),
            'followups' => array('followups', 'followup_uuid'),
            'consents' => array('consents', 'consent_uuid'),
            'rights' => array('rights', 'case_uuid'),
            'attachments' => array('attachments', 'attachment_uuid'),
        );
        foreach ($map as $field => [$table, $id_field]) {
            if (!in_array($field, $fields, true)) {
                continue;
            }
            $where = $field === 'active_prescriptions' ? ' AND status = %s' : '';
            $args = $field === 'active_prescriptions' ? array($patient_uuid, 'signed') : array($patient_uuid);
            $rows = CF01_DB::rows(
                'SELECT * FROM ' . CF01_DB::table($table) . ' WHERE patient_uuid = %s' . $where . ' ORDER BY id DESC LIMIT 50',
                $args
            );
            $result['collections'][$field] = array_map(static fn(array $row): array => self::safe_row($row, $id_field), $rows);
        }
        if (in_array('retention', $fields, true)) {
            $result['collections']['retention'] = self::retention_rows_for_patient($patient_uuid);
        }
        if (in_array('access_history', $fields, true)) {
            $result['collections']['access_history'] = self::access_page($patient_uuid, (string) $context['role'], '', 25);
        }
        if (in_array('masked_audit', $fields, true)) {
            $result['collections']['masked_audit'] = self::access_page($patient_uuid, 'auditor', '', 50);
        }
        if (in_array('control_metadata', $fields, true)) {
            $result['control_metadata'] = self::control_metadata($patient_uuid);
        }
        if (in_array('quality', $fields, true)) {
            $result['quality'] = self::quality_summary($patient_uuid);
        }
        foreach (array('intake', 'totality', 'tasks', 'allergies', 'red_flags') as $detail_field) {
            if (in_array($detail_field, $fields, true)) {
                $result[$detail_field] = array('available_through_authorized_detail_contract' => true);
            }
        }
        return $result;
    }

    private static function retention_rows_for_patient(string $patient_uuid): array {
        $object_uuids = array($patient_uuid);
        $sources = array(
            'relationships' => 'relationship_uuid',
            'consents' => 'consent_uuid',
            'encounters' => 'encounter_uuid',
            'observations' => 'observation_uuid',
            'attachments' => 'attachment_uuid',
            'assessments' => 'assessment_uuid',
            'prescriptions' => 'prescription_uuid',
            'followups' => 'followup_uuid',
            'outcomes' => 'outcome_uuid',
            'rights' => 'case_uuid',
            'breakglass' => 'grant_uuid',
        );
        foreach ($sources as $table => $id_field) {
            $rows = CF01_DB::rows(
                'SELECT ' . $id_field . ' FROM ' . CF01_DB::table($table) . ' WHERE patient_uuid = %s ORDER BY id DESC LIMIT 501',
                array($patient_uuid)
            );
            if (count($rows) > 500) {
                throw new RuntimeException('Retention projection exceeds the bounded synchronous view; use the approved paginated governance query.');
            }
            foreach ($rows as $row) {
                if (!empty($row[$id_field])) {
                    $object_uuids[] = (string) $row[$id_field];
                }
            }
        }
        $object_uuids = array_values(array_unique($object_uuids));
        $placeholders = implode(',', array_fill(0, count($object_uuids), '%s'));
        $rows = CF01_DB::rows(
            'SELECT * FROM ' . CF01_DB::table('retention') . ' WHERE object_uuid IN (' . $placeholders . ') ORDER BY id DESC LIMIT 101',
            $object_uuids
        );
        if (count($rows) > 100) {
            throw new RuntimeException('Retention projection exceeds the bounded synchronous view; use the approved paginated governance query.');
        }
        return array_map(static function (array $row): array {
            $allowed = array(
                'retention_uuid','object_type','object_uuid','policy_key','eligible_at','hold_status','status',
                'purge_started_at','purged_at','row_version','created_at','updated_at'
            );
            return array_intersect_key($row, array_flip($allowed));
        }, $rows);
    }

    private static function access_page(string $patient_uuid, string $role, string $cursor, int $limit): array {
        $params = array($patient_uuid);
        $condition = '';
        if ($cursor !== '') {
            $decoded = self::decode_cursor($cursor, $patient_uuid);
            $condition = ' AND (occurred_at < %s OR (occurred_at = %s AND event_uuid < %s))';
            array_push($params, $decoded['occurred_at'], $decoded['occurred_at'], $decoded['event_uuid']);
        }
        $rows = CF01_DB::rows(
            'SELECT event_uuid, actor_pseudonym, action, object_type, object_uuid, purpose, result, occurred_at FROM ' . CF01_DB::table('access') . ' WHERE patient_uuid = %s' . $condition . ' ORDER BY occurred_at DESC, event_uuid DESC LIMIT ' . ($limit + 1),
            $params
        );
        $has_more = count($rows) > $limit;
        if ($has_more) {
            array_pop($rows);
        }
        $items = array_map(static function (array $row) use ($role): array {
            $item = array(
                'event_uuid' => (string) $row['event_uuid'],
                'actor_category' => self::actor_category((string) $row['action']),
                'action' => (string) $row['action'],
                'object_type' => (string) $row['object_type'],
                'purpose' => (string) $row['purpose'],
                'result' => (string) $row['result'],
                'occurred_at' => (string) $row['occurred_at'],
                'break_glass' => str_contains(strtolower((string) $row['action']), 'breakglass'),
            );
            if (in_array($role, array('records', 'auditor'), true)) {
                $item['actor_reference'] = substr((string) $row['actor_pseudonym'], 0, 16);
                $item['object_reference'] = substr(hash('sha256', (string) $row['object_uuid']), 0, 16);
            }
            return $item;
        }, $rows);
        $next = '';
        if ($has_more && $rows) {
            $last = end($rows);
            $next = self::encode_cursor($patient_uuid, (string) $last['occurred_at'], (string) $last['event_uuid']);
        }
        return array('items' => $items, 'next_cursor' => $next, 'has_more' => $has_more, 'limit' => $limit);
    }

    private static function control_metadata(string $patient_uuid): array {
        $counts = array();
        foreach (array('encounters', 'observations', 'assessments', 'prescriptions', 'followups', 'consents', 'rights', 'breakglass') as $table) {
            $row = CF01_DB::row('SELECT COUNT(*) AS total FROM ' . CF01_DB::table($table) . ' WHERE patient_uuid = %s', array($patient_uuid));
            $counts[$table] = (int) ($row['total'] ?? 0);
        }
        return array('counts' => $counts, 'generated_at' => CF01_DB::now(), 'contains_clinical_narrative' => false);
    }

    private static function quality_summary(string $patient_uuid): array {
        $unsigned = CF01_DB::row('SELECT COUNT(*) AS total FROM ' . CF01_DB::table('encounters') . ' WHERE patient_uuid = %s AND status IN (%s,%s,%s)', array($patient_uuid, 'draft', 'in_progress', 'ready_to_sign'));
        $pending = CF01_DB::row('SELECT COUNT(*) AS total FROM ' . CF01_DB::table('outcomes') . ' WHERE patient_uuid = %s AND review_status = %s', array($patient_uuid, 'pending'));
        return array(
            'open_encounters' => (int) ($unsigned['total'] ?? 0),
            'pending_outcome_reviews' => (int) ($pending['total'] ?? 0),
            'generated_at' => CF01_DB::now(),
        );
    }

    private static function safe_row(array $row, string $id_field): array {
        $allowed = array(
            $id_field, 'patient_uuid', 'encounter_uuid', 'relationship_uuid', 'parent_encounter_uuid',
            'prescription_uuid', 'followup_uuid', 'status', 'review_status', 'request_type', 'purpose',
            'observation_type', 'encounter_type', 'mode', 'starts_at', 'ends_at', 'effective_from',
            'effective_until', 'due_at', 'overdue_at', 'signed_at', 'reviewed_at', 'fulfilled_at',
            'expires_at', 'withdrawn_at', 'row_version', 'created_at', 'updated_at',
        );
        return array_intersect_key($row, array_flip($allowed));
    }

    private static function default_fields(string $role): array {
        $defaults = array(
            'patient' => array('summary', 'encounters', 'prescriptions', 'followups', 'consents', 'access_history'),
            'guardian' => array('summary', 'encounters', 'prescriptions', 'followups', 'consents'),
            'assistant' => array('summary', 'intake', 'observations', 'tasks'),
            'doctor' => array('summary', 'intake', 'totality', 'encounters', 'observations', 'attachments', 'assessments', 'prescriptions', 'followups'),
            'supervisor' => array('summary', 'encounters', 'assessments', 'prescriptions', 'followups', 'quality'),
            'records' => array('summary', 'consents', 'rights', 'retention', 'access_history'),
            'auditor' => array('control_metadata', 'masked_audit'),
        );
        return $defaults[$role] ?? array();
    }

    private static function requested_fields(WP_REST_Request $request): array {
        $value = $request->get_param('fields');
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        return array_values(array_filter(array_map('sanitize_key', (array) $value)));
    }

    private static function actor_category(string $action): string {
        $normalized = strtolower($action);
        if (str_contains($normalized, 'breakglass')) {
            return 'emergency_clinician';
        }
        if (str_contains($normalized, 'accesshistory') || str_contains($normalized, 'rights')) {
            return 'privacy_or_records_staff';
        }
        if (str_contains($normalized, 'patient') || str_contains($normalized, 'outcome')) {
            return 'patient_or_representative';
        }
        return 'authorized_clinical_user';
    }

    private static function encode_cursor(string $patient_uuid, string $occurred_at, string $event_uuid): string {
        $payload = rtrim(strtr(base64_encode(wp_json_encode(array('p' => $patient_uuid, 't' => $occurred_at, 'e' => $event_uuid))), '+/', '-_'), '=');
        $key = CF01_Crypto::key();
        if (!is_string($key) || $key === '') {
            throw new RuntimeException('Clinical cursor signing is unavailable.');
        }
        return $payload . '.' . hash_hmac('sha256', $payload, $key);
    }

    private static function decode_cursor(string $cursor, string $patient_uuid): array {
        $parts = explode('.', $cursor, 2);
        $key = CF01_Crypto::key();
        if (count($parts) !== 2 || !is_string($key) || $key === '' || !hash_equals(hash_hmac('sha256', $parts[0], $key), $parts[1])) {
            throw new InvalidArgumentException('Invalid access-history cursor.');
        }
        $encoded = strtr($parts[0], '-_', '+/');
        $encoded .= str_repeat('=', (4 - strlen($encoded) % 4) % 4);
        $raw = base64_decode($encoded, true);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded)
            || !hash_equals($patient_uuid, (string) ($decoded['p'] ?? ''))
            || empty($decoded['t'])
            || !preg_match('/^[a-f0-9-]{36}$/', (string) ($decoded['e'] ?? ''))
        ) {
            throw new InvalidArgumentException('Invalid access-history cursor context.');
        }
        return array('occurred_at' => sanitize_text_field((string) $decoded['t']), 'event_uuid' => (string) $decoded['e']);
    }

    private static function mutate(WP_REST_Request $request, string $command, callable $callback): WP_REST_Response {
        return self::mutation($request, $command, $callback, true);
    }

    private static function governance_mutation(WP_REST_Request $request, string $command, callable $callback): WP_REST_Response {
        return self::mutation($request, $command, $callback, false);
    }

    private static function mutation(WP_REST_Request $request, string $command, callable $callback, bool $require_enabled): WP_REST_Response {
        $raw = (string) $request->get_body();
        if (strlen($raw) > 262144) {
            return new WP_REST_Response(array('ok' => false, 'code' => 'request_too_large', 'message' => __('The protected clinical request is too large.', 'sabri-clinical-records')), 413, self::private_headers());
        }
        return self::respond(function () use ($request, $command, $callback, $require_enabled): array {
            if ($require_enabled) {
                CF01_Authorization::require_enabled();
            }
            $data = self::json($request);
            $key = trim((string) $request->get_header('Idempotency-Key'));
            return CF01_DB::idempotent(get_current_user_id(), $command, $key, $data, fn(): array => $callback($data));
        });
    }

    private static function respond(callable $callback): WP_REST_Response {
        try {
            $data = $callback();
            $headers = self::private_headers();
            if (is_array($data) && isset($data['row_version'])) {
                $headers['ETag'] = '"' . (int) $data['row_version'] . '"';
            }
            return new WP_REST_Response(array('ok' => true, 'data' => $data), 200, $headers);
        } catch (InvalidArgumentException $error) {
            return new WP_REST_Response(array('ok' => false, 'code' => 'invalid_request', 'message' => $error->getMessage()), 400, self::private_headers());
        } catch (Throwable $error) {
            $trace = CF01_DB::uuid();
            do_action('cf01_exception', $error, $trace);
            if (self::is_conflict($error)) {
                return new WP_REST_Response(array(
                    'ok' => false,
                    'code' => 'clinical_conflict',
                    'message' => __('The clinical record changed or the requested state transition is no longer current. Reload and retry safely.', 'sabri-clinical-records'),
                    'trace_id' => $trace,
                ), 409, self::private_headers());
            }
            return new WP_REST_Response(array('ok' => false, 'code' => 'clinical_record_unavailable', 'message' => __('The protected clinical operation could not be completed.', 'sabri-clinical-records'), 'trace_id' => $trace), 403, self::private_headers());
        }
    }

    private static function json(WP_REST_Request $request): array {
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

    private static function is_conflict(Throwable $error): bool {
        $message = strtolower($error->getMessage());
        foreach (array('concurrently', 'stale clinical record version', 'invalid care relationship transition', 'invalid encounter transition', 'invalid prescription transition', 'already processing', 'already used', 'already reviewed', 'already changed') as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }
        return false;
    }

    private static function private_headers(): array {
        return array(
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
        );
    }
}
