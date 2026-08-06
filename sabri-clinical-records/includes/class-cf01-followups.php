<?php
defined('ABSPATH') || exit;

final class CF01_Followups {
    private const STATES = array(
        'planned' => array('due', 'cancelled', 'rescheduled'),
        'due' => array('response_submitted', 'overdue', 'cancelled', 'rescheduled'),
        'overdue' => array('response_submitted', 'cancelled', 'rescheduled'),
        'response_submitted' => array('under_review'),
        'under_review' => array('reviewed', 'needs_contact'),
        'needs_contact' => array('under_review', 'reviewed', 'rescheduled'),
        'reviewed' => array('closed', 'rescheduled'),
        'rescheduled' => array('due', 'cancelled'),
        'closed' => array(),
        'cancelled' => array(),
    );

    public static function plan(int $actor_id, string $patient_uuid, string $prescription_uuid, array $plan): array {
        CF01_Authorization::clinician($actor_id, 'plan_followup');
        CF01_Authorization::consent($patient_uuid, 'clinical_care');
        $prescription = CF01_Prescriptions::get($prescription_uuid);
        if ((string) $prescription['patient_uuid'] !== $patient_uuid || (string) $prescription['status'] !== 'signed') {
            throw new RuntimeException('Follow-up requires the patient’s active signed prescription.');
        }
        $encounter = CF01_Encounters::get((string) $prescription['encounter_uuid']);
        CF01_Authorization::relationship_for_record($patient_uuid, $actor_id, 'clinical_care', (string) $encounter['relationship_uuid'], 'plan_followup');
        $due_at = self::future_utc($plan['due_at'] ?? null);
        $overdue_at = self::future_utc($plan['overdue_at'] ?? gmdate('Y-m-d H:i:s', strtotime($due_at . ' UTC') + DAY_IN_SECONDS));
        if (strtotime($overdue_at . ' UTC') <= strtotime($due_at . ' UTC')) {
            throw new InvalidArgumentException('Follow-up overdue time must be after its due time.');
        }
        $questionnaire = self::normalize_questionnaire((array) ($plan['questionnaire'] ?? array()));
        $uuid = CF01_DB::uuid();
        CF01_DB::insert('followups', array(
            'followup_uuid' => $uuid,
            'patient_uuid' => $patient_uuid,
            'prescription_uuid' => $prescription_uuid,
            'status' => 'planned',
            'due_at' => $due_at,
            'overdue_at' => $overdue_at,
            'reminder_policy_json' => wp_json_encode(self::normalize_reminders((array) ($plan['reminders'] ?? array()), $patient_uuid)),
            'questionnaire_cipher' => CF01_Crypto::encrypt($questionnaire, 'followup-questionnaire'),
            'plan_cipher' => CF01_Crypto::encrypt(array(
                'objectives' => self::sanitize($plan['objectives'] ?? array()),
                'expected_outcomes' => self::sanitize($plan['expected_outcomes'] ?? array()),
                'red_flag_instructions' => self::sanitize($plan['red_flag_instructions'] ?? ''),
                'next_plan' => self::sanitize($plan['next_plan'] ?? ''),
            ), 'followup-plan'),
            'created_by_user_id' => $actor_id,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'closed_at' => null,
            'row_version' => 1,
            'created_at' => CF01_DB::now(),
            'updated_at' => CF01_DB::now(),
        ));
        CF01_Audit::record($actor_id, 'FollowUpPlanned', 'follow_up_plan', $uuid, 'clinical_care', array('due_at' => $due_at));
        CF01_Outbox::enqueue('FollowUpPlanned', array('followup_uuid' => $uuid, 'patient_uuid' => $patient_uuid, 'due_at' => $due_at), $uuid);
        return self::get($uuid);
    }

    public static function submit_outcome(int $actor_id, string $followup_uuid, array $response, int $expected_version): array {
        $followup = self::get($followup_uuid);
        if (!CF01_Authorization::patient_owner($actor_id, (string) $followup['patient_uuid'])) {
            CF01_Authorization::actor($actor_id, 'submit_patient_outcome');
        }
        CF01_Authorization::expected_version($followup, $expected_version);
        if (!in_array((string) $followup['status'], array('due', 'overdue'), true)) {
            throw new RuntimeException('This follow-up is not accepting a response.');
        }
        $normalized = self::normalize_response($response);
        $red_flags = array_values(array_unique(array_map('sanitize_key', (array) ($normalized['red_flags'] ?? array()))));
        $emergency = CF01_Contracts::emergency_policy((string) $followup['patient_uuid'], $actor_id, $red_flags, 'patient_outcome');
        if (empty($emergency['valid'])) {
            throw new RuntimeException('Approved emergency guidance and clinician alert are required for red-flag outcomes.');
        }
        $outcome_uuid = CF01_DB::uuid();
        CF01_DB::transaction(function () use ($actor_id, $followup, $followup_uuid, $normalized, $outcome_uuid, $expected_version): void {
            CF01_DB::insert('outcomes', array(
                'outcome_uuid' => $outcome_uuid,
                'followup_uuid' => $followup_uuid,
                'patient_uuid' => $followup['patient_uuid'],
                'response_cipher' => CF01_Crypto::encrypt($normalized, 'patient-outcome'),
                'red_flag' => !empty($normalized['red_flags']) ? 1 : 0,
                'submitted_by_user_id' => $actor_id,
                'review_status' => 'pending',
                'review_cipher' => null,
                'reviewed_by_user_id' => null,
                'reviewed_at' => null,
                'row_version' => 1,
                'created_at' => CF01_DB::now(),
                'updated_at' => CF01_DB::now(),
            ));
            $ok = CF01_DB::update_versioned('followups', array('status' => 'response_submitted'), array('followup_uuid' => $followup_uuid), $expected_version);
            if (!$ok) {
                throw new RuntimeException('Follow-up changed concurrently.');
            }
            CF01_Audit::record($actor_id, 'PatientOutcomeSubmitted', 'patient_reported_outcome', $outcome_uuid, 'clinical_care', array('followup_uuid' => $followup_uuid, 'red_flag' => !empty($normalized['red_flags'])));
            CF01_Outbox::enqueue('PatientOutcomeSubmitted', array('outcome_uuid' => $outcome_uuid, 'followup_uuid' => $followup_uuid, 'red_flag' => !empty($normalized['red_flags'])), $outcome_uuid);
        });
        return self::outcome($outcome_uuid);
    }

    public static function review(int $actor_id, string $outcome_uuid, array $review, int $expected_outcome_version): array {
        $outcome = self::outcome($outcome_uuid);
        $followup = self::get((string) $outcome['followup_uuid']);
        CF01_Authorization::clinician($actor_id, 'review_followup');
        $prescription = CF01_Prescriptions::get((string) $followup['prescription_uuid']);
        $encounter = CF01_Encounters::get((string) $prescription['encounter_uuid']);
        CF01_Authorization::relationship_for_record((string) $outcome['patient_uuid'], $actor_id, 'clinical_care', (string) $encounter['relationship_uuid'], 'review_followup');
        CF01_Authorization::expected_version($outcome, $expected_outcome_version);
        if (($outcome['review_status'] ?? '') !== 'pending') {
            throw new RuntimeException('Outcome was already reviewed.');
        }
        $normalized = array(
            'assessment' => self::sanitize($review['assessment'] ?? ''),
            'change' => self::sanitize($review['change'] ?? ''),
            'next_plan' => self::sanitize($review['next_plan'] ?? ''),
            'contact_required' => !empty($review['contact_required']),
            'prescription_changed_automatically' => false,
        );
        if ($normalized['assessment'] === '') {
            throw new InvalidArgumentException('Clinical follow-up assessment is required.');
        }
        CF01_DB::transaction(function () use ($actor_id, $outcome_uuid, $normalized, $expected_outcome_version, $followup): void {
            $ok = CF01_DB::update_versioned('outcomes', array(
                'review_status' => 'reviewed',
                'review_cipher' => CF01_Crypto::encrypt($normalized, 'outcome-review'),
                'reviewed_by_user_id' => $actor_id,
                'reviewed_at' => CF01_DB::now(),
            ), array('outcome_uuid' => $outcome_uuid), $expected_outcome_version);
            if (!$ok) {
                throw new RuntimeException('Outcome changed concurrently.');
            }
            $next = !empty($normalized['contact_required']) ? 'needs_contact' : 'reviewed';
            $followup_version = (int) $followup['row_version'];
            $followup_ok = CF01_DB::update_versioned('followups', array(
                'status' => $next,
                'reviewed_by_user_id' => $actor_id,
                'reviewed_at' => CF01_DB::now(),
            ), array('followup_uuid' => $followup['followup_uuid']), $followup_version);
            if (!$followup_ok) {
                throw new RuntimeException('Follow-up changed concurrently.');
            }
            CF01_Audit::record($actor_id, 'FollowUpReviewed', 'patient_reported_outcome', $outcome_uuid, 'clinical_care', array('status' => $next));
            CF01_Outbox::enqueue('FollowUpReviewed', array('outcome_uuid' => $outcome_uuid, 'followup_uuid' => $followup['followup_uuid'], 'status' => $next), $outcome_uuid);
        });
        return self::outcome($outcome_uuid);
    }

    public static function reschedule(int $actor_id, string $uuid, string $due_at, string $reason, int $expected_version): array {
        $row = self::get($uuid);
        CF01_Authorization::clinician($actor_id, 'reschedule_followup');
        self::authorize_followup_clinician($actor_id, $row, 'reschedule_followup');
        CF01_Authorization::expected_version($row, $expected_version);
        if (in_array((string) $row['status'], array('closed', 'cancelled'), true)) {
            throw new RuntimeException('Closed or cancelled follow-up cannot be rescheduled.');
        }
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Reschedule reason is required.');
        }
        $ok = CF01_DB::update_versioned('followups', array(
            'status' => 'rescheduled',
            'due_at' => self::future_utc($due_at),
            'reschedule_reason_cipher' => CF01_Crypto::encrypt($reason, 'followup-reschedule'),
        ), array('followup_uuid' => $uuid), $expected_version);
        if (!$ok) {
            throw new RuntimeException('Follow-up changed concurrently.');
        }
        CF01_Audit::record($actor_id, 'FollowUpRescheduled', 'follow_up_plan', $uuid, 'clinical_care', array());
        CF01_Outbox::enqueue('FollowUpRescheduled', array('followup_uuid' => $uuid, 'due_at' => self::get($uuid)['due_at']), $uuid);
        return self::get($uuid);
    }

    public static function close(int $actor_id, string $uuid, string $reason, int $expected_version): array {
        $row = self::get($uuid);
        CF01_Authorization::clinician($actor_id, 'close_followup');
        self::authorize_followup_clinician($actor_id, $row, 'close_followup');
        CF01_Authorization::expected_version($row, $expected_version);
        if (!in_array((string) $row['status'], array('reviewed', 'needs_contact'), true)) {
            throw new RuntimeException('Follow-up requires clinical review before closure.');
        }
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Closure reason is required.');
        }
        $ok = CF01_DB::update_versioned('followups', array(
            'status' => 'closed',
            'closed_at' => CF01_DB::now(),
            'closure_reason_cipher' => CF01_Crypto::encrypt($reason, 'followup-closure'),
        ), array('followup_uuid' => $uuid), $expected_version);
        if (!$ok) {
            throw new RuntimeException('Follow-up changed concurrently.');
        }
        CF01_Audit::record($actor_id, 'FollowUpClosed', 'follow_up_plan', $uuid, 'clinical_care', array());
        return self::get($uuid);
    }

    public static function reconcile_due(): void {
        if (!CF01_DB::is_enabled()) {
            return;
        }
        $now = CF01_DB::now();
        $due_rows = CF01_DB::rows('SELECT followup_uuid, status, due_at, overdue_at, row_version, patient_uuid FROM ' . CF01_DB::table('followups') . ' WHERE status IN (%s,%s) AND due_at <= %s ORDER BY due_at ASC LIMIT 100', array('planned', 'rescheduled', $now));
        foreach ($due_rows as $row) {
            self::reconcile_row($row, 'due');
        }
        $overdue_rows = CF01_DB::rows('SELECT followup_uuid, status, due_at, overdue_at, row_version, patient_uuid FROM ' . CF01_DB::table('followups') . ' WHERE status = %s AND overdue_at IS NOT NULL AND overdue_at <= %s ORDER BY overdue_at ASC LIMIT 100', array('due', $now));
        foreach ($overdue_rows as $row) {
            self::reconcile_row($row, 'overdue');
        }
    }

    private static function reconcile_row(array $row, string $next): void {
        $ok = CF01_DB::update_versioned('followups', array('status' => $next), array('followup_uuid' => $row['followup_uuid']), (int) $row['row_version']);
        if ($ok) {
            CF01_Audit::system('FollowUpStatusReconciled', 'follow_up_plan', (string) $row['followup_uuid'], 'clinical_care', array('status' => $next));
            $current = self::get((string) $row['followup_uuid']);
            if (self::reminder_delivery_allowed($current)) {
                CF01_Outbox::enqueue('FollowUpStatusChanged', array('followup_uuid' => $row['followup_uuid'], 'patient_uuid' => $row['patient_uuid'], 'status' => $next), (string) $row['followup_uuid']);
            } else {
                CF01_Audit::system('FollowUpReminderSuppressed', 'follow_up_plan', (string) $row['followup_uuid'], 'notification_preference', array('status' => $next));
            }
        }
    }

    public static function get(string $uuid): array {
        $row = CF01_DB::row('SELECT * FROM ' . CF01_DB::table('followups') . ' WHERE followup_uuid = %s LIMIT 1', array($uuid));
        if (!$row) {
            throw new RuntimeException('Follow-up is unavailable.');
        }
        return $row;
    }

    public static function outcome(string $uuid): array {
        $row = CF01_DB::row('SELECT * FROM ' . CF01_DB::table('outcomes') . ' WHERE outcome_uuid = %s LIMIT 1', array($uuid));
        if (!$row) {
            throw new RuntimeException('Patient-reported outcome is unavailable.');
        }
        return $row;
    }

    public static function state_map(): array {
        return self::STATES;
    }

    private static function normalize_questionnaire(array $items): array {
        $result = array();
        foreach ($items as $index => $item) {
            if (!is_array($item) || empty($item['key']) || empty($item['prompt'])) {
                throw new InvalidArgumentException('Each questionnaire item requires a key and prompt.');
            }
            $result[] = array(
                'key' => sanitize_key((string) $item['key']),
                'prompt' => sanitize_text_field((string) $item['prompt']),
                'type' => in_array(($item['type'] ?? ''), array('text', 'number', 'scale', 'boolean', 'choice'), true) ? $item['type'] : 'text',
                'required' => !empty($item['required']),
                'choices' => self::sanitize($item['choices'] ?? array()),
                'order' => $index + 1,
            );
        }
        return $result;
    }

    private static function normalize_response(array $response): array {
        $allowed = array('answers', 'adherence', 'changes', 'aggravation', 'new_symptoms', 'adverse_events', 'red_flags', 'patient_note', 'submitted_at');
        $normalized = array();
        foreach ($allowed as $field) {
            if (array_key_exists($field, $response)) {
                $normalized[$field] = self::sanitize($response[$field]);
            }
        }
        if (empty($normalized['answers']) && empty($normalized['patient_note'])) {
            throw new InvalidArgumentException('A follow-up response is required.');
        }
        $normalized['clinician_review_required'] = true;
        $normalized['automatic_assessment'] = false;
        $normalized['automatic_prescription_change'] = false;
        return $normalized;
    }

    private static function normalize_reminders(array $policy, string $patient_uuid): array {
        $demographics = CF01_Patients::demographics(CF01_Patients::get($patient_uuid));
        $time_zone = sanitize_text_field((string) ($policy['time_zone'] ?? $demographics['time_zone'] ?? 'UTC'));
        try {
            new DateTimeZone($time_zone);
        } catch (Throwable $error) {
            throw new InvalidArgumentException('A valid reminder time zone is required.');
        }
        $offsets = array();
        $raw_offsets = array_key_exists('offset_hours', $policy) ? (array) $policy['offset_hours'] : array_filter($policy, 'is_int');
        foreach ($raw_offsets as $item) {
            $hours = (int) $item;
            if ($hours >= 1 && $hours <= 720) {
                $offsets[] = $hours;
            }
        }
        $quiet_start = sanitize_text_field((string) ($policy['quiet_start'] ?? '22:00'));
        $quiet_end = sanitize_text_field((string) ($policy['quiet_end'] ?? '07:00'));
        foreach (array($quiet_start, $quiet_end) as $clock) {
            if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $clock)) {
                throw new InvalidArgumentException('Quiet-hours values must use HH:MM.');
            }
        }
        return array(
            'opt_in' => !empty($policy['opt_in']),
            'offset_hours' => array_values(array_unique($offsets)),
            'quiet_start' => $quiet_start,
            'quiet_end' => $quiet_end,
            'time_zone' => $time_zone,
            'no_shame_or_streaks' => true,
        );
    }

    private static function reminder_delivery_allowed(array $followup): bool {
        $policy = json_decode((string) ($followup['reminder_policy_json'] ?? ''), true);
        if (!is_array($policy) || empty($policy['opt_in'])) {
            return false;
        }
        try {
            $zone = new DateTimeZone((string) ($policy['time_zone'] ?? 'UTC'));
            $now = new DateTimeImmutable('now', $zone);
        } catch (Throwable $error) {
            return false;
        }
        $clock = $now->format('H:i');
        $start = (string) ($policy['quiet_start'] ?? '22:00');
        $end = (string) ($policy['quiet_end'] ?? '07:00');
        $quiet = $start <= $end ? ($clock >= $start && $clock < $end) : ($clock >= $start || $clock < $end);
        return !$quiet;
    }

    private static function authorize_followup_clinician(int $actor_id, array $followup, string $action): void {
        $prescription = CF01_Prescriptions::get((string) $followup['prescription_uuid']);
        $encounter = CF01_Encounters::get((string) $prescription['encounter_uuid']);
        CF01_Authorization::relationship_for_record((string) $followup['patient_uuid'], $actor_id, 'clinical_care', (string) $encounter['relationship_uuid'], $action);
    }

    private static function future_utc($value): string {
        $timestamp = strtotime((string) $value);
        if (!is_int($timestamp) || $timestamp <= time()) {
            throw new InvalidArgumentException('Follow-up due time must be in the future.');
        }
        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    private static function sanitize($value) {
        if (is_array($value)) {
            return array_map(array(__CLASS__, 'sanitize'), $value);
        }
        if (is_bool($value) || is_numeric($value) || $value === null) {
            return $value;
        }
        return sanitize_textarea_field((string) $value);
    }
}
