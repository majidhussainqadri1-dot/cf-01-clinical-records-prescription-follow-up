<?php
defined('ABSPATH') || exit;

final class CF01_Authorization {
    private const HIGH_RISK = array(
        'sign_encounter', 'sign_prescription', 'add_encounter_addendum', 'mark_encounter_entered_in_error',
        'export_record', 'consume_clinical_export', 'grant_break_glass', 'release_hold', 'place_hold',
        'purge_record', 'merge_patient', 'link_platform_identity', 'update_guardian_context',
        'activate_module', 'review_break_glass', 'revoke_break_glass', 'decide_clinical_right',
        'fulfill_clinical_export', 'review_attachment', 'relink_attachment',
        'run_clinical_migration', 'run_clinical_rollback', 'disable_module'
    );

    public static function require_enabled(): void {
        if (!CF01_DB::is_enabled()) {
            throw new RuntimeException('CF-01 is disabled pending activation acceptance.');
        }
    }

    public static function actor(int $user_id, string $action, array $context = array()): array {
        if ($user_id <= 0) {
            throw new RuntimeException('Authentication required.');
        }
        $current_user_id = get_current_user_id();
        if ($current_user_id > 0 && $current_user_id !== $user_id && !apply_filters('cf01_allow_service_actor', false, $current_user_id, $user_id, $action)) {
            throw new RuntimeException('Clinical actor identity mismatch.');
        }
        self::require_enabled();
        $membership = CF01_Contracts::membership($user_id);
        if (empty($membership['valid']) || empty($membership['approved']) || !empty($membership['suspended'])) {
            throw new RuntimeException('Current membership is not eligible for clinical access.');
        }
        if (!self::capability_allowed($user_id, $action, $context)) {
            throw new RuntimeException('Current capability does not authorize this clinical action.');
        }
        if (in_array($action, self::HIGH_RISK, true)) {
            $recent = CF01_Contracts::recent_auth($user_id, $action);
            if (empty($recent['valid']) || empty($recent['recent_auth']) || empty($recent['step_up']) || !self::not_expired((string) ($recent['expires_at'] ?? ''))) {
                throw new RuntimeException('Current recent step-up authentication is required.');
            }
            if (!hash_equals((string) $membership['platform_uuid'], (string) ($recent['subject_uuid'] ?? ''))) {
                throw new RuntimeException('Recent authentication subject mismatch.');
            }
            $context['recent_auth'] = $recent;
        }
        $context['membership'] = $membership;
        $context['user_id'] = $user_id;
        $context['action'] = $action;
        return $context;
    }

    public static function clinician(int $user_id, string $action, array $context = array()): array {
        $context = self::actor($user_id, $action, $context);
        $professional = CF01_Contracts::practitioner($user_id, $action);
        if (empty($professional['valid']) || empty($professional['eligible']) || empty($professional['verified']) || !empty($professional['suspended'])) {
            throw new RuntimeException('Current professional eligibility is required.');
        }
        if (!self::not_expired((string) ($professional['expires_at'] ?? ''))) {
            throw new RuntimeException('Professional eligibility has expired.');
        }
        $scopes = array_map('sanitize_key', (array) ($professional['scopes'] ?? array()));
        $purpose = sanitize_key((string) ($context['purpose'] ?? 'clinical_care'));
        if ($scopes && !in_array($purpose, $scopes, true) && !in_array('clinical_care', $scopes, true)) {
            throw new RuntimeException('Professional scope does not authorize this clinical purpose.');
        }
        $context['professional'] = $professional;
        return $context;
    }

    public static function relationship(string $clinical_patient_uuid, int $doctor_user_id, string $purpose, string $action = 'clinical_care'): array {
        $purpose = sanitize_key($purpose);
        $action = sanitize_key($action);
        $row = CF01_DB::row(
            'SELECT * FROM ' . CF01_DB::table('relationships') . ' WHERE patient_uuid = %s AND doctor_user_id = %d AND status = %s AND purpose = %s ORDER BY id DESC LIMIT 1',
            array($clinical_patient_uuid, $doctor_user_id, 'active', $purpose)
        );
        if (!$row) {
            throw new RuntimeException('An active treating relationship is required.');
        }
        self::validate_relationship_window($row);
        self::validate_relationship_scope($row, $purpose, $action);
        return $row;
    }

    public static function relationship_for_record(string $clinical_patient_uuid, int $doctor_user_id, string $purpose, string $relationship_uuid, string $action): array {
        $relationship_uuid = trim($relationship_uuid);
        if ($relationship_uuid === '') {
            throw new RuntimeException('Clinical record is missing its treating relationship reference.');
        }
        $row = CF01_DB::row(
            'SELECT * FROM ' . CF01_DB::table('relationships') . ' WHERE relationship_uuid = %s AND patient_uuid = %s AND doctor_user_id = %d AND status = %s AND purpose = %s LIMIT 1',
            array($relationship_uuid, $clinical_patient_uuid, $doctor_user_id, 'active', sanitize_key($purpose))
        );
        if (!$row) {
            throw new RuntimeException('This clinical record is not within the actor’s current treating relationship.');
        }
        self::validate_relationship_window($row);
        self::validate_relationship_scope($row, sanitize_key($purpose), sanitize_key($action));
        return $row;
    }

    public static function patient_context(int $actor_id, string $patient_uuid, string $purpose = 'clinical_care', string $requested_role = ''): array {
        return CF01_Role_Context::resolve($actor_id, $patient_uuid, $purpose, $requested_role);
    }

    public static function consent(string $clinical_patient_uuid, string $purpose): array {
        $row = CF01_DB::row(
            'SELECT * FROM ' . CF01_DB::table('consents') . ' WHERE patient_uuid = %s AND purpose = %s ORDER BY id DESC LIMIT 1',
            array($clinical_patient_uuid, $purpose)
        );
        if (!$row || ($row['status'] ?? '') !== 'granted') {
            throw new RuntimeException('Active purpose-specific consent is required.');
        }
        $expires_at = self::utc_timestamp((string) ($row['expires_at'] ?? ''));
        if ($expires_at !== null && $expires_at <= time()) {
            throw new RuntimeException('Active purpose-specific consent is required.');
        }
        return $row;
    }

    public static function fields(string $role, string $purpose, array $requested, array $record): array {
        $policy = array(
            'patient' => array('summary', 'encounters', 'attachments', 'prescriptions', 'followups', 'consents', 'access_history'),
            'guardian' => array('summary', 'encounters', 'attachments', 'prescriptions', 'followups', 'consents', 'access_history'),
            'assistant' => array('summary', 'intake', 'observations', 'tasks'),
            'doctor' => array('summary', 'intake', 'totality', 'encounters', 'observations', 'attachments', 'assessments', 'prescriptions', 'followups'),
            'supervisor' => array('summary', 'encounters', 'assessments', 'prescriptions', 'followups', 'quality'),
            'records' => array('summary', 'consents', 'rights', 'retention', 'access_history'),
            'break_glass' => array('summary', 'active_prescriptions', 'allergies', 'red_flags', 'recent_encounters'),
            'auditor' => array('control_metadata', 'masked_audit'),
        );
        $requested = array_values(array_unique(array_map('sanitize_key', $requested)));
        $allowed = array_map('sanitize_key', $policy[$role] ?? array());
        $allowed = array_values(array_unique(array_map('sanitize_key', (array) apply_filters('cf01_field_policy', $allowed, $role, $purpose, $record))));
        return array_values(array_intersect($requested, $allowed));
    }

    public static function patient_owner(int $user_id, string $clinical_patient_uuid): bool {
        $membership = CF01_Contracts::membership($user_id);
        if (empty($membership['valid']) || empty($membership['approved']) || !empty($membership['suspended'])) {
            return false;
        }
        $subject_hash = CF01_Crypto::blind_index((string) $membership['platform_uuid'], 'platform-subject');
        $row = CF01_DB::row(
            'SELECT clinical_uuid FROM ' . CF01_DB::table('patients') . ' WHERE clinical_uuid = %s AND platform_subject_hash = %s AND status NOT IN (%s,%s) LIMIT 1',
            array($clinical_patient_uuid, $subject_hash, 'merged', 'quarantined')
        );
        return $row !== null;
    }

    public static function enforce_rate_limit(int $user_id, string $operation, string $subject_uuid, int $limit, int $window_seconds): void {
        $limit = max(1, $limit);
        $window_seconds = max(60, $window_seconds);
        $bucket = (int) floor(time() / $window_seconds);
        $key = 'cf01_rl_' . hash('sha256', $user_id . '|' . sanitize_key($operation) . '|' . $subject_uuid . '|' . $bucket);
        $count = (int) get_transient($key);
        if ($count >= $limit) {
            CF01_Audit::denied($user_id, 'ClinicalRateLimitExceeded', 'clinical_subject', $subject_uuid, 'abuse_prevention', sanitize_key($operation));
            throw new RuntimeException('Clinical action rate limit exceeded; retry after the bounded window.');
        }
        set_transient($key, $count + 1, $window_seconds + 60);
    }

    public static function not_expired(string $utc): bool {
        $timestamp = self::utc_timestamp($utc);
        return $timestamp !== null && $timestamp > time();
    }

    public static function expected_version(array $row, int $expected): void {
        if ((int) ($row['row_version'] ?? -1) !== $expected) {
            throw new RuntimeException('Stale clinical record version.');
        }
    }

    private static function validate_relationship_window(array $row): void {
        $now = time();
        $starts_at = self::utc_timestamp((string) ($row['starts_at'] ?? ''));
        $ends_at = self::utc_timestamp((string) ($row['ends_at'] ?? ''));
        if ($starts_at === null || $starts_at > $now || ($ends_at !== null && $ends_at <= $now)) {
            throw new RuntimeException('An active treating relationship is required.');
        }
    }

    private static function validate_relationship_scope(array $row, string $purpose, string $action): void {
        $scope = json_decode((string) ($row['scope_json'] ?? ''), true);
        if (!is_array($scope) || !$scope) {
            throw new RuntimeException('Treating relationship scope is missing or invalid.');
        }
        $scope = array_values(array_unique(array_map('sanitize_key', $scope)));
        $accepted = array_unique(array_filter(array($purpose, $action, 'care', 'clinical_care')));
        if (!array_intersect($scope, $accepted)) {
            throw new RuntimeException('Treating relationship scope does not authorize this clinical action.');
        }
    }

    private static function capability_allowed(int $user_id, string $action, array $context): bool {
        $patient_actions = array(
            'view_own_clinical_record', 'view_own_clinical_timeline', 'submit_patient_outcome',
            'request_clinical_right', 'record_own_consent', 'view_own_access_history', 'export_record',
            'consume_clinical_export'
        );
        $capability_map = array(
            'activate_module' => 'cf01_activate_clinical',
            'disable_module' => 'cf01_activate_clinical',
            'create_patient' => 'cf01_manage_clinical_records',
            'create_clinical_patient' => 'cf01_manage_clinical_records',
            'link_patient_identity' => 'cf01_manage_clinical_records',
            'link_platform_identity' => 'cf01_manage_clinical_records',
            'merge_patient' => 'cf01_manage_clinical_records',
            'update_guardian_context' => 'cf01_manage_clinical_records',
            'record_consent' => 'cf01_manage_clinical_records',
            'propose_relationship' => 'cf01_manage_clinical_records',
            'decide_clinical_right' => 'cf01_manage_clinical_rights',
            'fulfill_clinical_export' => 'cf01_manage_clinical_rights',
            'view_access_history' => 'cf01_manage_clinical_rights',
            'manage_retention' => 'cf01_manage_retention',
            'place_hold' => 'cf01_manage_retention',
            'release_hold' => 'cf01_manage_retention',
            'purge_record' => 'cf01_manage_retention',
            'run_clinical_migration' => 'cf01_run_clinical_migrations',
            'run_clinical_rollback' => 'cf01_run_clinical_migrations',
            'review_break_glass' => 'cf01_review_break_glass',
            'revoke_break_glass' => 'cf01_review_break_glass',
            'view_clinical_health' => 'cf01_view_clinical_health',
            'review_attachment' => 'cf01_review_clinical_assets',
            'relink_attachment' => 'cf01_review_clinical_assets',
        );
        if (in_array($action, $patient_actions, true)) {
            $allowed = self::can($user_id, 'read');
        } elseif (isset($capability_map[$action])) {
            $allowed = self::can($user_id, $capability_map[$action]);
        } elseif (str_contains($action, 'break_glass')) {
            $allowed = self::can($user_id, 'cf01_use_break_glass');
        } elseif (str_contains($action, 'attachment')) {
            $allowed = self::can($user_id, 'cf01_manage_clinical_assets') || self::can($user_id, 'cf01_treat_patients');
        } elseif (str_contains($action, 'rights') || str_contains($action, 'export')) {
            $allowed = self::can($user_id, 'cf01_manage_clinical_rights');
        } elseif (str_contains($action, 'retention') || str_contains($action, 'hold')) {
            $allowed = self::can($user_id, 'cf01_manage_retention');
        } elseif (str_contains($action, 'audit')) {
            $allowed = self::can($user_id, 'cf01_audit_clinical');
        } else {
            $allowed = self::can($user_id, 'cf01_treat_patients') || self::can($user_id, 'cf01_manage_clinical_records');
        }
        return (bool) apply_filters('cf01_action_allowed', $allowed, $user_id, $action, $context);
    }

    private static function can(int $user_id, string $capability): bool {
        if (function_exists('user_can')) {
            return user_can($user_id, $capability);
        }
        return current_user_can($capability);
    }

    private static function utc_timestamp(string $value): ?int {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        try {
            $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
            return $date->getTimestamp();
        } catch (Throwable $error) {
            return null;
        }
    }
}
