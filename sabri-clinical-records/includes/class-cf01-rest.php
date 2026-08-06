<?php
defined('ABSPATH') || exit;

final class CF01_REST {
    private const NS = 'clinical/v1';

    public static function register_routes(): void {
        $routes = array(
            array('/me', 'GET', 'my_record'),
            array('/patients', 'POST', 'create_patient'),
            array('/patients/(?P<patient>[a-f0-9-]{36})', 'GET', 'patient'),
            array('/patients/(?P<patient>[a-f0-9-]{36})/relationships', 'POST', 'propose_relationship'),
            array('/patients/(?P<patient>[a-f0-9-]{36})/consents', 'POST', 'record_consent'),
            array('/patients/(?P<patient>[a-f0-9-]{36})/timeline', 'GET', 'timeline'),
            array('/patients/(?P<patient>[a-f0-9-]{36})/rights', 'POST', 'request_right'),
            array('/encounters', 'POST', 'create_encounter'),
            array('/encounters/(?P<id>[a-f0-9-]{36})', 'GET', 'encounter'),
            array('/encounters/(?P<id>[a-f0-9-]{36})', 'PATCH', 'update_encounter'),
            array('/encounters/(?P<id>[a-f0-9-]{36})/sign', 'POST', 'sign_encounter'),
            array('/encounters/(?P<id>[a-f0-9-]{36})/addenda', 'POST', 'addendum'),
            array('/encounters/(?P<id>[a-f0-9-]{36})/attachments', 'POST', 'attach'),
            array('/attachments/(?P<id>[a-f0-9-]{36})/delivery', 'POST', 'attachment_delivery'),
            array('/prescriptions', 'POST', 'create_prescription'),
            array('/prescriptions/(?P<id>[a-f0-9-]{36})', 'GET', 'prescription'),
            array('/prescriptions/(?P<id>[a-f0-9-]{36})/sign', 'POST', 'sign_prescription'),
            array('/prescriptions/(?P<id>[a-f0-9-]{36})/discontinue', 'POST', 'discontinue_prescription'),
            array('/followups', 'POST', 'plan_followup'),
            array('/followups/(?P<id>[a-f0-9-]{36})', 'GET', 'followup'),
            array('/followups/(?P<id>[a-f0-9-]{36})/outcomes', 'POST', 'submit_outcome'),
            array('/outcomes/(?P<id>[a-f0-9-]{36})/review', 'POST', 'review_outcome'),
            array('/break-glass', 'POST', 'break_glass'),
            array('/break-glass/(?P<id>[a-f0-9-]{36})', 'GET', 'break_glass_assertion'),
            array('/rights/(?P<id>[a-f0-9-]{36})/decision', 'POST', 'decide_right'),
            array('/rights/(?P<id>[a-f0-9-]{36})/export', 'POST', 'prepare_export'),
            array('/rights/(?P<id>[a-f0-9-]{36})/download', 'POST', 'consume_export'),
            array('/health', 'GET', 'health'),
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
        if (!CF01_DB::is_enabled() && $request->get_route() !== '/' . self::NS . '/health') {
            return new WP_Error('cf01_disabled', __('Clinical records are not available.', 'sabri-clinical-records'), array('status' => 503));
        }
        return true;
    }

    public static function my_record(WP_REST_Request $request): WP_REST_Response {
        return self::respond(function () use ($request): array {
            $actor = get_current_user_id();
            $context = CF01_Authorization::actor($actor, 'view_own_clinical_record');
            $platform_uuid = (string) ($context['membership']['platform_uuid'] ?? '');
            $patient = CF01_Patients::for_platform_subject($platform_uuid);
            $patient_uuid = (string) $patient['clinical_uuid'];
            $fields = CF01_Authorization::fields(
                'patient',
                'clinical_care',
                self::requested_fields($request, array('summary', 'encounters', 'prescriptions', 'followups', 'consents', 'access_history')),
                array('patient_uuid' => $patient_uuid)
            );
            $record = self::project_patient($patient_uuid, $fields);
            CF01_Audit::access($actor, $patient_uuid, 'OwnClinicalRecordViewed', 'clinical_patient', $patient_uuid, 'clinical_care', 'success');
            return $record;
        });
    }

    public static function create_patient(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'CreateClinicalPatient', function () use ($request): array {
            $data = self::json($request);
            $membership = CF01_Contracts::membership(get_current_user_id());
            if (empty($membership['valid'])) {
                throw new RuntimeException('Membership assertion is unavailable.');
            }
            return CF01_Patients::create(
                get_current_user_id(),
                (string) $membership['platform_uuid'],
                (array) ($data['demographics'] ?? array()),
                sanitize_text_field((string) ($data['jurisdiction'] ?? ''))
            );
        });
    }

    public static function patient(WP_REST_Request $request): WP_REST_Response {
        return self::respond(function () use ($request): array {
            $patient_uuid = (string) $request['patient'];
            $actor = get_current_user_id();
            $context = self::authorize_patient_read($actor, $patient_uuid, 'view_clinical_record');
            $fields = CF01_Authorization::fields(
                (string) $context['role'],
                'clinical_care',
                self::requested_fields($request, array('summary', 'encounters', 'prescriptions', 'followups', 'consents')),
                array('patient_uuid' => $patient_uuid)
            );
            $record = self::project_patient($patient_uuid, $fields);
            CF01_Audit::access($actor, $patient_uuid, 'ClinicalRecordViewed', 'clinical_patient', $patient_uuid, 'clinical_care', 'success');
            return $record;
        });
    }

    public static function propose_relationship(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'ProposeCareRelationship', function () use ($request): array {
            $data = self::json($request);
            return CF01_Relationships::propose(get_current_user_id(), (string) $request['patient'], (int) ($data['doctor_user_id'] ?? 0), $data);
        });
    }

    public static function record_consent(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'RecordConsent', function () use ($request): array {
            $data = self::json($request);
            return CF01_Consents::record(
                get_current_user_id(),
                (string) $request['patient'],
                sanitize_key((string) ($data['purpose'] ?? '')),
                sanitize_key((string) ($data['status'] ?? '')),
                (array) ($data['evidence'] ?? array())
            );
        });
    }

    public static function timeline(WP_REST_Request $request): WP_REST_Response {
        return self::respond(fn() => self::timeline_rows(
            get_current_user_id(),
            (string) $request['patient'],
            max(1, min(100, (int) ($request['limit'] ?? 50))),
            (string) ($request['cursor'] ?? '')
        ));
    }

    public static function request_right(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'RequestClinicalRight', function () use ($request): array {
            $data = self::json($request);
            return CF01_Rights::request(get_current_user_id(), (string) $request['patient'], sanitize_key((string) ($data['type'] ?? '')), (array) ($data['request'] ?? array()));
        });
    }

    public static function create_encounter(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'CreateEncounter', function () use ($request): array {
            $data = self::json($request);
            return CF01_Encounters::create(get_current_user_id(), (string) ($data['patient_uuid'] ?? ''), $data);
        });
    }

    public static function encounter(WP_REST_Request $request): WP_REST_Response {
        return self::respond(function () use ($request): array {
            $row = CF01_Encounters::get((string) $request['id']);
            $actor = get_current_user_id();
            $context = self::authorize_patient_read($actor, (string) $row['patient_uuid'], 'view_encounter');
            CF01_Audit::access($actor, (string) $row['patient_uuid'], 'EncounterViewed', 'encounter', (string) $row['encounter_uuid'], 'clinical_care', 'success');
            return array('metadata' => self::safe_row($row, 'encounters'), 'content' => self::project_encounter_content(CF01_Encounters::content($row), (string) $context['role']));
        });
    }

    public static function update_encounter(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'UpdateEncounterDraft', function () use ($request): array {
            $data = self::json($request);
            return CF01_Encounters::update_draft(
                get_current_user_id(),
                (string) $request['id'],
                (array) ($data['content'] ?? array()),
                sanitize_key((string) ($data['status'] ?? 'draft')),
                self::version($request, $data)
            );
        });
    }

    public static function sign_encounter(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'SignEncounter', fn() => CF01_Encounters::sign(get_current_user_id(), (string) $request['id'], self::version($request, self::json($request))));
    }

    public static function addendum(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'AddEncounterAddendum', fn() => CF01_Encounters::addendum(get_current_user_id(), (string) $request['id'], self::json($request)));
    }

    public static function attach(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'AttachClinicalAsset', function () use ($request): array {
            $encounter = CF01_Encounters::get((string) $request['id']);
            return CF01_Attachments::attach(get_current_user_id(), (string) $encounter['patient_uuid'], (string) $request['id'], self::json($request));
        });
    }

    public static function attachment_delivery(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'DeliverClinicalAttachment', function () use ($request): array {
            return array('delivery_grant' => CF01_Attachments::delivery_reference(get_current_user_id(), (string) $request['id']));
        });
    }

    public static function create_prescription(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'CreatePrescription', function () use ($request): array {
            $data = self::json($request);
            return CF01_Prescriptions::create(get_current_user_id(), (string) ($data['patient_uuid'] ?? ''), (string) ($data['encounter_uuid'] ?? ''), $data);
        });
    }

    public static function prescription(WP_REST_Request $request): WP_REST_Response {
        return self::respond(function () use ($request): array {
            $row = CF01_Prescriptions::get((string) $request['id']);
            $actor = get_current_user_id();
            $context = self::authorize_patient_read($actor, (string) $row['patient_uuid'], 'view_prescription');
            $role = (string) $context['role'];
            if (!in_array('prescriptions', CF01_Authorization::fields($role, 'clinical_care', array('prescriptions'), $row), true)) {
                throw new RuntimeException('Prescription access is unavailable.');
            }
            CF01_Audit::access($actor, (string) $row['patient_uuid'], 'PrescriptionViewed', 'prescription', (string) $row['prescription_uuid'], 'clinical_care', 'success');
            return array('metadata' => self::safe_row($row, 'prescriptions'), 'order' => CF01_Prescriptions::order($row));
        });
    }

    public static function sign_prescription(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'SignPrescription', fn() => CF01_Prescriptions::sign(get_current_user_id(), (string) $request['id'], self::version($request, self::json($request))));
    }

    public static function discontinue_prescription(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'DiscontinuePrescription', function () use ($request): array {
            $data = self::json($request);
            return CF01_Prescriptions::discontinue(get_current_user_id(), (string) $request['id'], (string) ($data['reason'] ?? ''), self::version($request, $data));
        });
    }

    public static function plan_followup(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'PlanFollowUp', function () use ($request): array {
            $data = self::json($request);
            return CF01_Followups::plan(get_current_user_id(), (string) ($data['patient_uuid'] ?? ''), (string) ($data['prescription_uuid'] ?? ''), $data);
        });
    }

    public static function followup(WP_REST_Request $request): WP_REST_Response {
        return self::respond(function () use ($request): array {
            $row = CF01_Followups::get((string) $request['id']);
            $actor = get_current_user_id();
            $context = self::authorize_patient_read($actor, (string) $row['patient_uuid'], 'view_followup');
            $role = (string) $context['role'];
            if (!in_array('followups', CF01_Authorization::fields($role, 'clinical_care', array('followups'), $row), true)) {
                throw new RuntimeException('Follow-up access is unavailable.');
            }
            $questionnaire = CF01_Crypto::decrypt((string) $row['questionnaire_cipher'], 'followup-questionnaire');
            $plan = CF01_Crypto::decrypt((string) $row['plan_cipher'], 'followup-plan');
            CF01_Audit::access($actor, (string) $row['patient_uuid'], 'FollowUpViewed', 'follow_up_plan', (string) $row['followup_uuid'], 'clinical_care', 'success');
            return array(
                'metadata' => self::safe_row($row, 'followups'),
                'questionnaire' => is_array($questionnaire) ? $questionnaire : array(),
                'plan' => is_array($plan) ? $plan : array(),
            );
        });
    }

    public static function submit_outcome(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'SubmitPatientOutcome', fn() => CF01_Followups::submit_outcome(get_current_user_id(), (string) $request['id'], self::json($request), self::version($request, self::json($request))));
    }

    public static function review_outcome(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'ReviewFollowUp', fn() => CF01_Followups::review(get_current_user_id(), (string) $request['id'], self::json($request), self::version($request, self::json($request))));
    }

    public static function break_glass(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'RequestBreakGlass', function () use ($request): array {
            $data = self::json($request);
            return CF01_Break_Glass::request(get_current_user_id(), (string) ($data['patient_uuid'] ?? ''), (string) ($data['reason'] ?? ''), $data);
        });
    }

    public static function break_glass_assertion(WP_REST_Request $request): WP_REST_Response {
        return self::respond(fn() => CF01_Break_Glass::assertion(get_current_user_id(), (string) $request['id'], self::requested_fields($request)));
    }

    public static function decide_right(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'DecideClinicalRight', function () use ($request): array {
            $data = self::json($request);
            return CF01_Rights::decide(get_current_user_id(), (string) $request['id'], sanitize_key((string) ($data['decision'] ?? '')), (array) ($data['details'] ?? array()), self::version($request, $data));
        });
    }

    public static function prepare_export(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'FulfillClinicalExport', function () use ($request): array {
            $data = self::json($request);
            $case = CF01_Rights::get((string) $request['id']);
            $manifest = CF01_Rights::export_manifest_for_case(get_current_user_id(), (string) $request['id'], (array) ($data['scope'] ?? array('encounters','prescriptions','followups')));
            $reference = apply_filters('cf01_generate_clinical_export', '', $manifest, $case, get_current_user_id());
            if (!is_string($reference) || $reference === '') {
                throw new RuntimeException('Secure clinical export generation is unavailable.');
            }
            return CF01_Rights::fulfill_export(get_current_user_id(), (string) $request['id'], $reference, self::version($request, $data));
        });
    }

    public static function consume_export(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'ConsumeClinicalExport', function () use ($request): array {
            $data = self::json($request);
            return array('reference' => CF01_Rights::consume_export(get_current_user_id(), (string) $request['id'], (string) ($data['token'] ?? ''), self::version($request, $data)));
        });
    }

    public static function health(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response(CF01_Health::report(get_current_user_id()), 200, self::private_headers());
    }

    private static function mutate(WP_REST_Request $request, string $command, callable $callback): WP_REST_Response {
        $key = trim((string) $request->get_header('Idempotency-Key'));
        $raw = (string) $request->get_body();
        if (strlen($raw) > 262144) {
            return new WP_REST_Response(array('ok' => false, 'code' => 'request_too_large', 'message' => __('The protected clinical request is too large.', 'sabri-clinical-records')), 413, self::private_headers());
        }
        return self::respond(function () use ($request, $command, $callback, $key): array {
            return CF01_DB::idempotent(get_current_user_id(), $command, $key, self::json($request), $callback);
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

    private static function requested_fields(WP_REST_Request $request, array $defaults = array()): array {
        $value = $request->get_param('fields');
        if ($value === null || $value === '') {
            $value = $defaults;
        } elseif (is_string($value)) {
            $value = explode(',', $value);
        }
        return array_values(array_unique(array_filter(array_map('sanitize_key', (array) $value))));
    }

    private static function authorize_patient_read(int $actor_id, string $patient_uuid, string $doctor_action): array {
        try {
            return CF01_Role_Context::resolve($actor_id, $patient_uuid, 'clinical_care');
        } catch (Throwable $error) {
            CF01_Audit::denied($actor_id, 'ClinicalObjectReadDenied', 'clinical_patient', $patient_uuid, 'clinical_care', sanitize_key($doctor_action));
            throw $error;
        }
    }

    private static function project_patient(string $patient_uuid, array $fields): array {
        $patient = CF01_Patients::get($patient_uuid);
        $result = array('patient' => CF01_Patients::public_row($patient), 'fields' => $fields);
        if (in_array('summary', $fields, true)) {
            $result['summary'] = array('status' => $patient['status'], 'jurisdiction' => $patient['jurisdiction']);
        }
        foreach (array('encounters', 'prescriptions', 'followups', 'consents') as $key) {
            if (in_array($key, $fields, true)) {
                $rows = CF01_DB::rows('SELECT * FROM ' . CF01_DB::table($key) . ' WHERE patient_uuid = %s ORDER BY id DESC LIMIT 50', array($patient_uuid));
                $result[$key] = array_map(static fn(array $row): array => self::safe_row($row, $key), $rows);
            }
        }
        return $result;
    }

    private static function timeline_rows(int $actor_id, string $patient_uuid, int $limit, string $cursor = ''): array {
        self::authorize_patient_read($actor_id, $patient_uuid, 'view_clinical_timeline');
        $before = self::decode_timeline_cursor($cursor, $patient_uuid);
        $items = array();
        $sources = array(
            'encounters' => array('encounter_uuid', 'status', 'created_at'),
            'prescriptions' => array('prescription_uuid', 'status', 'created_at'),
            'followups' => array('followup_uuid', 'status', 'created_at'),
            'outcomes' => array('outcome_uuid', 'review_status', 'created_at'),
            'consents' => array('consent_uuid', 'status', 'created_at'),
        );
        foreach ($sources as $key => [$id_field, $status_field, $time_field]) {
            $sql = 'SELECT ' . $id_field . ', ' . $status_field . ', ' . $time_field . ', row_version FROM ' . CF01_DB::table($key) . ' WHERE patient_uuid = %s';
            $args = array($patient_uuid);
            if ($before !== '') {
                $sql .= ' AND ' . $time_field . ' <= %s';
                $args[] = $before;
            }
            $sql .= ' ORDER BY ' . $time_field . ' DESC, ' . $id_field . ' DESC LIMIT ' . ($limit + 1);
            foreach (CF01_DB::rows($sql, $args) as $row) {
                $items[] = array(
                    'type' => $key === 'consents' ? 'consent' : rtrim($key, 's'),
                    'uuid' => (string) $row[$id_field],
                    'status' => (string) ($row[$status_field] ?? ''),
                    'occurred_at' => (string) ($row[$time_field] ?? ''),
                    'row_version' => (int) ($row['row_version'] ?? 1),
                );
            }
        }
        $access_sql = 'SELECT event_uuid, action, result, occurred_at FROM ' . CF01_DB::table('access') . ' WHERE patient_uuid = %s';
        $access_args = array($patient_uuid);
        if ($before !== '') {
            $access_sql .= ' AND occurred_at <= %s';
            $access_args[] = $before;
        }
        $access_sql .= ' ORDER BY occurred_at DESC, event_uuid DESC LIMIT ' . ($limit + 1);
        foreach (CF01_DB::rows($access_sql, $access_args) as $row) {
            $items[] = array('type' => 'access_event', 'uuid' => (string) $row['event_uuid'], 'status' => (string) $row['result'], 'action' => (string) $row['action'], 'occurred_at' => (string) $row['occurred_at'], 'row_version' => 1);
        }
        usort($items, static function (array $a, array $b): int {
            $time = strcmp($b['occurred_at'], $a['occurred_at']);
            return $time !== 0 ? $time : strcmp($b['uuid'], $a['uuid']);
        });
        $has_more = count($items) > $limit;
        $items = array_slice($items, 0, $limit);
        $next_cursor = '';
        if ($has_more && $items) {
            $last = $items[count($items) - 1];
            $next_cursor = self::encode_timeline_cursor($patient_uuid, (string) $last['occurred_at'], (string) $last['uuid']);
        }
        CF01_Audit::access($actor_id, $patient_uuid, 'ClinicalTimelineViewed', 'clinical_patient', $patient_uuid, 'clinical_care', 'success');
        return array('items' => $items, 'next_cursor' => $next_cursor, 'has_more' => $has_more);
    }

    private static function encode_timeline_cursor(string $patient_uuid, string $occurred_at, string $uuid): string {
        $payload = rtrim(strtr(base64_encode(CF01_Crypto::canonical_json(array('patient_uuid' => $patient_uuid, 'occurred_at' => $occurred_at, 'uuid' => $uuid))), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $payload, CF01_Crypto::key() ?? str_repeat("\0", 32));
        return $payload . '.' . $signature;
    }

    private static function decode_timeline_cursor(string $cursor, string $patient_uuid): string {
        if ($cursor === '') {
            return '';
        }
        $parts = explode('.', $cursor, 2);
        if (count($parts) !== 2 || !hash_equals(hash_hmac('sha256', $parts[0], CF01_Crypto::key() ?? str_repeat("\0", 32)), $parts[1])) {
            throw new InvalidArgumentException('Timeline cursor is invalid.');
        }
        $encoded = strtr($parts[0], '-_', '+/');
        $encoded .= str_repeat('=', (4 - strlen($encoded) % 4) % 4);
        $decoded = base64_decode($encoded, true);
        $payload = is_string($decoded) ? json_decode($decoded, true) : null;
        if (!is_array($payload) || !hash_equals($patient_uuid, (string) ($payload['patient_uuid'] ?? '')) || empty($payload['occurred_at'])) {
            throw new InvalidArgumentException('Timeline cursor is invalid for this patient.');
        }
        return sanitize_text_field((string) $payload['occurred_at']);
    }

    private static function project_encounter_content(array $content, string $role): array {
        if (in_array($role, array('doctor', 'supervisor'), true)) {
            return $content;
        }
        $allowed = array('chief_complaints', 'onset', 'causes', 'modalities', 'concomitants', 'history', 'objective_findings', 'narrative', 'red_flags');
        return array_intersect_key($content, array_flip($allowed));
    }

    private static function safe_row(array $row, string $entity): array {
        $allow = array(
            'encounters' => array('encounter_uuid','parent_encounter_uuid','encounter_type','mode','starts_at','ends_at','status','template_key','template_version','row_version','created_at','updated_at'),
            'prescriptions' => array('prescription_uuid','encounter_uuid','parent_prescription_uuid','status','effective_from','effective_until','superseded_by_uuid','row_version','created_at','updated_at'),
            'followups' => array('followup_uuid','prescription_uuid','status','due_at','overdue_at','reviewed_at','closed_at','row_version','created_at','updated_at'),
            'consents' => array('consent_uuid','purpose','notice_version','status','granted_at','withdrawn_at','expires_at','row_version','created_at','updated_at'),
        );
        return array_intersect_key($row, array_flip($allow[$entity] ?? array()));
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
