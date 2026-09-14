<?php
defined('ABSPATH') || exit;

final class CF01_Plan_REST {
    private const NS = 'clinical/v1';

    public static function register_routes(): void {
        $routes = array(
            // Missing/explicit command catalogue.
            array('/patients/(?P<patient>[a-f0-9-]{36})/identity/link', 'POST', 'link_identity'),
            array('/patients/(?P<patient>[a-f0-9-]{36})/duplicates/resolve', 'POST', 'resolve_duplicate'),
            array('/patients/(?P<patient>[a-f0-9-]{36})/consents/present', 'POST', 'present_consent'),
            array('/consents/(?P<id>[a-f0-9-]{36})/renew', 'POST', 'renew_consent'),
            array('/attachments/(?P<id>[a-f0-9-]{36})/review', 'POST', 'review_attachment'),
            array('/break-glass/requests', 'POST', 'request_break_glass'),
            array('/break-glass/(?P<id>[a-f0-9-]{36})/grant', 'POST', 'grant_break_glass'),
            array('/break-glass/(?P<id>[a-f0-9-]{36})/close-review', 'POST', 'close_break_glass_review'),
            array('/rights/(?P<id>[a-f0-9-]{36})/appeal', 'POST', 'appeal_rights'),
            array('/rights/(?P<id>[a-f0-9-]{36})/export-fulfill', 'POST', 'fulfill_export'),
            array('/rights/(?P<id>[a-f0-9-]{36})/correction-resolve', 'POST', 'resolve_correction'),
            array('/retention', 'POST', 'schedule_retention'),
            array('/retention/(?P<id>[a-f0-9-]{36})/holds', 'POST', 'place_hold'),
            array('/retention/(?P<id>[a-f0-9-]{36})/holds/(?P<hold>[a-f0-9-]{36})/release', 'POST', 'release_hold'),
            array('/retention/(?P<id>[a-f0-9-]{36})/purge', 'POST', 'purge_retention'),

            // Explicit query catalogue.
            array('/queries/my-consents', 'GET', 'query_my_consents'),
            array('/queries/my-active-instructions', 'GET', 'query_my_active_instructions'),
            array('/queries/patients/(?P<patient>[a-f0-9-]{36})/relationship-eligibility', 'GET', 'query_relationship_eligibility'),
            array('/queries/patients/(?P<patient>[a-f0-9-]{36})/field-authorization', 'GET', 'query_field_authorization'),
            array('/queries/patients/(?P<patient>[a-f0-9-]{36})/record-version', 'GET', 'query_record_version'),
            array('/queries/patients/(?P<patient>[a-f0-9-]{36})/safety-alerts', 'GET', 'query_safety_alerts'),
            array('/queries/patients/(?P<patient>[a-f0-9-]{36})/unsigned-encounters', 'GET', 'query_unsigned_encounters'),
            array('/queries/patients/(?P<patient>[a-f0-9-]{36})/pending-followups', 'GET', 'query_pending_followups'),
            array('/queries/patients/(?P<patient>[a-f0-9-]{36})/care-transfer-status', 'GET', 'query_care_transfer_status'),
            array('/queries/patients/(?P<patient>[a-f0-9-]{36})/access-anomalies', 'GET', 'query_access_anomalies'),
            array('/queries/break-glass-review-queue', 'GET', 'query_break_glass_review_queue'),
            array('/queries/retention-due', 'GET', 'query_retention_due'),
            array('/queries/restore-reconciliation', 'GET', 'query_restore_reconciliation'),
            array('/queries/rights/(?P<id>[a-f0-9-]{36})/export-status', 'GET', 'query_export_status'),

            // Pre-activation governance commands needed by migration/restore DoD.
            array('/governance/file08-extraction', 'POST', 'file08_extraction'),
            array('/governance/migrations/(?P<id>[a-f0-9-]{36})/rollback', 'POST', 'rollback_migration'),
            array('/governance/restore/verify', 'POST', 'verify_restore'),
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
        if (str_contains($request->get_route(), '/' . self::NS . '/governance/')) {
            if (!current_user_can('cf01_run_clinical_migrations')) {
                return new WP_Error('cf01_migration_authority_required', __('Clinical migration authority is required.', 'sabri-clinical-records'), array('status' => 403));
            }
            return true;
        }
        return CF01_REST::permission($request);
    }

    public static function link_identity(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'LinkPlatformIdentity', fn(array $d): array => CF01_Patients::link_platform_identity(get_current_user_id(), (string) $request['patient'], (string) ($d['platform_uuid'] ?? ''), self::version($request, $d)));
    }

    public static function resolve_duplicate(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'MergeOrQuarantineDuplicatePatient', fn(array $d): array => CF01_Plan_Commands::merge_or_quarantine_duplicate(
            get_current_user_id(),
            (string) $request['patient'],
            (string) ($d['target_uuid'] ?? ''),
            (string) ($d['decision'] ?? ''),
            (string) ($d['reason'] ?? ''),
            self::version($request, $d),
            max(1, (int) ($d['target_expected_version'] ?? 0))
        ));
    }

    public static function present_consent(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'PresentConsent', fn(array $d): array => CF01_Consents::present(get_current_user_id(), (string) $request['patient'], sanitize_key((string) ($d['purpose'] ?? '')), (string) ($d['notice_version'] ?? ''), (array) ($d['evidence'] ?? array())));
    }

    public static function renew_consent(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'RenewConsent', fn(array $d): array => CF01_Consents::renew(get_current_user_id(), (string) $request['id'], (array) ($d['evidence'] ?? $d)));
    }

    public static function review_attachment(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'ReviewAttachment', fn(array $d): array => CF01_Attachments::review_scan(get_current_user_id(), (string) $request['id'], sanitize_key((string) ($d['status'] ?? '')), (array) ($d['scanner_evidence'] ?? array()), self::version($request, $d)));
    }

    public static function request_break_glass(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'RequestBreakGlass', fn(array $d): array => CF01_Break_Glass::begin_request(get_current_user_id(), (string) ($d['patient_uuid'] ?? ''), (string) ($d['reason'] ?? ''), $d));
    }

    public static function grant_break_glass(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'GrantBreakGlass', fn(array $d): array => CF01_Break_Glass::grant(get_current_user_id(), (string) $request['id'], self::version($request, $d)));
    }

    public static function close_break_glass_review(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'CloseBreakGlassReview', fn(array $d): array => CF01_Break_Glass::close_review(get_current_user_id(), (string) $request['id'], (string) ($d['reason'] ?? ''), self::version($request, $d)));
    }

    public static function appeal_rights(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'AppealRightsDecision', fn(array $d): array => CF01_Plan_Commands::appeal_rights_decision(get_current_user_id(), (string) $request['id'], (string) ($d['reason'] ?? ''), (array) ($d['evidence'] ?? array()), self::version($request, $d)));
    }

    public static function fulfill_export(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'FulfillClinicalExport', fn(array $d): array => CF01_Plan_Commands::fulfill_export(get_current_user_id(), (string) $request['id'], (string) ($d['export_reference'] ?? ''), self::version($request, $d)));
    }

    public static function resolve_correction(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'RequestCorrectionResolve', fn(array $d): array => CF01_Plan_Commands::resolve_correction(get_current_user_id(), (string) $request['id'], (string) ($d['encounter_uuid'] ?? ''), (array) ($d['correction'] ?? array()), self::version($request, $d)));
    }

    public static function schedule_retention(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'TriggerRetention', fn(array $d): array => CF01_Retention::schedule(get_current_user_id(), (string) ($d['object_type'] ?? ''), (string) ($d['object_uuid'] ?? ''), (string) ($d['policy_key'] ?? ''), isset($d['eligible_at']) ? (string) $d['eligible_at'] : null, (array) ($d['holds'] ?? array())));
    }

    public static function place_hold(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'PlaceClinicalHold', fn(array $d): array => CF01_Retention::place_hold(get_current_user_id(), (string) $request['id'], (array) ($d['hold'] ?? $d), self::version($request, $d)));
    }

    public static function release_hold(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'ReleaseClinicalHold', fn(array $d): array => CF01_Retention::release_hold(get_current_user_id(), (string) $request['id'], (string) $request['hold'], (string) ($d['reason'] ?? ''), self::version($request, $d)));
    }

    public static function purge_retention(WP_REST_Request $request): WP_REST_Response {
        return self::mutate($request, 'PurgeEligibleRecord', fn(array $d): array => CF01_Retention::purge(get_current_user_id(), (string) $request['id'], self::version($request, $d), false));
    }

    public static function query_my_consents(WP_REST_Request $r): WP_REST_Response { return self::query(fn(): array => CF01_Plan_Queries::my_consents(get_current_user_id())); }
    public static function query_my_active_instructions(WP_REST_Request $r): WP_REST_Response { return self::query(fn(): array => CF01_Plan_Queries::my_active_instructions(get_current_user_id())); }
    public static function query_relationship_eligibility(WP_REST_Request $r): WP_REST_Response { return self::query(fn(): array => CF01_Plan_Queries::relationship_eligibility(get_current_user_id(), (string) $r['patient'], sanitize_key((string) ($r->get_param('purpose') ?: 'clinical_care')))); }
    public static function query_field_authorization(WP_REST_Request $r): WP_REST_Response { return self::query(fn(): array => CF01_Plan_Queries::field_authorization(get_current_user_id(), (string) $r['patient'], sanitize_key((string) ($r->get_param('purpose') ?: 'clinical_care')), self::csv($r->get_param('fields')), sanitize_key((string) $r->get_param('role')))); }
    public static function query_record_version(WP_REST_Request $r): WP_REST_Response { return self::query(fn(): array => CF01_Plan_Queries::clinical_record_version(get_current_user_id(), (string) $r['patient'])); }
    public static function query_safety_alerts(WP_REST_Request $r): WP_REST_Response { return self::query(fn(): array => CF01_Plan_Queries::safety_alerts(get_current_user_id(), (string) $r['patient'])); }
    public static function query_unsigned_encounters(WP_REST_Request $r): WP_REST_Response { return self::query(fn(): array => CF01_Plan_Queries::unsigned_encounters(get_current_user_id(), (string) $r['patient'])); }
    public static function query_pending_followups(WP_REST_Request $r): WP_REST_Response { return self::query(fn(): array => CF01_Plan_Queries::pending_followups(get_current_user_id(), (string) $r['patient'])); }
    public static function query_care_transfer_status(WP_REST_Request $r): WP_REST_Response { return self::query(fn(): array => CF01_Plan_Queries::care_transfer_status(get_current_user_id(), (string) $r['patient'])); }
    public static function query_access_anomalies(WP_REST_Request $r): WP_REST_Response { return self::query(fn(): array => CF01_Plan_Queries::access_anomalies(get_current_user_id(), (string) $r['patient'])); }
    public static function query_break_glass_review_queue(WP_REST_Request $r): WP_REST_Response { return self::query(fn(): array => CF01_Plan_Queries::break_glass_review_queue(get_current_user_id())); }
    public static function query_retention_due(WP_REST_Request $r): WP_REST_Response { return self::query(fn(): array => CF01_Plan_Queries::retention_due(get_current_user_id())); }
    public static function query_restore_reconciliation(WP_REST_Request $r): WP_REST_Response { return self::query(fn(): array => CF01_Plan_Queries::restore_reconciliation(get_current_user_id())); }
    public static function query_export_status(WP_REST_Request $r): WP_REST_Response { return self::query(fn(): array => CF01_Plan_Queries::export_package_status(get_current_user_id(), (string) $r['id'])); }

    public static function file08_extraction(WP_REST_Request $request): WP_REST_Response {
        return self::governance_mutate($request, 'RunClinicalMigration', fn(array $d): array => CF01_Migrations::extract_from_file08(get_current_user_id(), (array) ($d['batch'] ?? $d), (string) ($d['cursor'] ?? '')));
    }

    public static function rollback_migration(WP_REST_Request $request): WP_REST_Response {
        return self::governance_mutate($request, 'RunClinicalRollback', fn(array $d): array => CF01_Migrations::rollback(get_current_user_id(), (string) $request['id'], (string) ($d['reason'] ?? '')));
    }

    public static function verify_restore(WP_REST_Request $request): WP_REST_Response {
        return self::governance_mutate($request, 'VerifyClinicalRestore', fn(array $d): array => CF01_Migrations::verify_restore(get_current_user_id(), (array) ($d['evidence'] ?? $d)));
    }

    private static function mutate(WP_REST_Request $request, string $command, callable $callback): WP_REST_Response {
        return self::mutation($request, $command, $callback, true);
    }

    private static function governance_mutate(WP_REST_Request $request, string $command, callable $callback): WP_REST_Response {
        return self::mutation($request, $command, $callback, false);
    }

    private static function mutation(WP_REST_Request $request, string $command, callable $callback, bool $require_enabled): WP_REST_Response {
        return self::respond(function () use ($request, $command, $callback, $require_enabled): array {
            if ($require_enabled) {
                CF01_Authorization::require_enabled();
            }
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
            $data = $callback();
            return new WP_REST_Response(array('ok' => true, 'data' => $data), 200, self::headers());
        } catch (InvalidArgumentException $e) {
            return new WP_REST_Response(array('ok' => false, 'code' => 'invalid_request', 'message' => $e->getMessage()), 400, self::headers());
        } catch (Throwable $e) {
            $trace = CF01_DB::uuid();
            do_action('cf01_exception', $e, $trace);
            $conflict = self::is_conflict($e);
            return new WP_REST_Response(array(
                'ok' => false,
                'code' => $conflict ? 'clinical_conflict' : 'clinical_record_unavailable',
                'message' => $conflict ? __('The clinical record changed. Reload and retry safely.', 'sabri-clinical-records') : __('The protected clinical operation could not be completed.', 'sabri-clinical-records'),
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

    private static function csv($value): array {
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        return array_values(array_unique(array_filter(array_map('sanitize_key', (array) $value))));
    }

    private static function is_conflict(Throwable $e): bool {
        $m = strtolower($e->getMessage());
        foreach (array('concurrently','stale clinical record version','already processing','already used','already reviewed','changed concurrently') as $needle) {
            if (str_contains($m, $needle)) {
                return true;
            }
        }
        return false;
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
