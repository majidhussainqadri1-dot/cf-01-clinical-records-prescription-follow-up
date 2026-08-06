<?php
defined('ABSPATH') || exit;

final class CF01_Break_Glass {
    private const STATES = array('requested', 'granted', 'revoked', 'expired', 'review_pending', 'reviewed', 'rejected');
    private const MAX_TTL = 15 * MINUTE_IN_SECONDS;
    private const REVIEW_FINDINGS = array('appropriate', 'policy_deviation', 'misuse', 'insufficient_evidence', 'follow_up_required');

    public static function request(int $actor_id, string $patient_uuid, string $reason, array $context): array {
        CF01_Patients::get($patient_uuid);
        CF01_Authorization::enforce_rate_limit($actor_id, 'break_glass_actor', (string) $actor_id, 6, DAY_IN_SECONDS);
        CF01_Authorization::enforce_rate_limit($actor_id, 'break_glass_patient', $patient_uuid, 3, HOUR_IN_SECONDS);
        $actor = CF01_Authorization::clinician($actor_id, 'grant_break_glass', array('purpose' => 'emergency_care', 'patient_uuid' => $patient_uuid));
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Explicit emergency reason is required.');
        }
        if (empty($context['emergency']) || !empty($context['bulk']) || !empty($context['export'])) {
            throw new RuntimeException('Break-glass is restricted to a single emergency minimum-view request.');
        }

        $emergency_reference = sanitize_text_field((string) ($context['emergency_reference'] ?? ''));
        if ($emergency_reference === '') {
            $emergency_reference = 'server-emergency-' . CF01_DB::uuid();
        }
        $device_reference = sanitize_text_field((string) ($context['device_reference'] ?? ($context['device'] ?? '')));
        if ($device_reference === '') {
            $device_reference = 'server-device-' . substr(hash('sha256', $actor_id . '|' . $patient_uuid . '|' . $emergency_reference), 0, 32);
        }

        $existing = CF01_DB::row(
            'SELECT * FROM ' . CF01_DB::table('breakglass') . ' WHERE patient_uuid = %s AND actor_user_id = %d AND status = %s ORDER BY id DESC LIMIT 1',
            array($patient_uuid, $actor_id, 'granted')
        );
        if ($existing) {
            if (CF01_Authorization::not_expired((string) ($existing['expires_at'] ?? ''))) {
                throw new RuntimeException('An active break-glass grant already exists for this clinician and patient.');
            }
            self::expire($existing);
        }

        $requested_fields = CF01_Authorization::fields(
            'break_glass',
            'emergency_care',
            array_values((array) ($context['requested_fields'] ?? array())),
            array('patient_uuid' => $patient_uuid)
        );
        if (!$requested_fields) {
            throw new InvalidArgumentException('At least one authorized emergency minimum-view field is required.');
        }
        $recent = CF01_DB::row(
            'SELECT COUNT(*) AS total FROM ' . CF01_DB::table('breakglass') . ' WHERE actor_user_id = %d AND created_at >= %s',
            array($actor_id, gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS))
        );
        if ((int) ($recent['total'] ?? 0) >= 2) {
            CF01_Outbox::enqueue('BreakGlassRepeatedUseAlert', array('patient_uuid' => $patient_uuid, 'actor_reference' => hash('sha256', (string) $actor_id)), $patient_uuid);
        }
        $uuid = CF01_DB::uuid();
        $expires = gmdate('Y-m-d H:i:s', time() + self::MAX_TTL);
        $professional_uuid = sanitize_text_field((string) ($actor['professional']['professional_uuid'] ?? ''));
        if ($professional_uuid === '') {
            throw new RuntimeException('A current professional identity is required for break-glass.');
        }
        CF01_DB::insert('breakglass', array(
            'grant_uuid' => $uuid,
            'patient_uuid' => $patient_uuid,
            'actor_user_id' => $actor_id,
            'status' => 'granted',
            'reason_cipher' => CF01_Crypto::encrypt($reason, 'break-glass-reason'),
            'context_cipher' => CF01_Crypto::encrypt(array(
                'emergency' => true,
                'emergency_reference' => $emergency_reference,
                'requested_fields' => $requested_fields,
                'location' => sanitize_text_field((string) ($context['location'] ?? '')),
                'device_reference' => $device_reference,
                'professional_uuid' => $professional_uuid,
                'grant_nonce_hash' => hash('sha256', $uuid . '|' . $patient_uuid . '|' . $actor_id . '|' . CF01_DB::now()),
            ), 'break-glass-context'),
            'granted_at' => CF01_DB::now(),
            'expires_at' => $expires,
            'revoked_at' => null,
            'review_status' => 'pending',
            'review_cipher' => null,
            'row_version' => 1,
            'created_at' => CF01_DB::now(),
            'updated_at' => CF01_DB::now(),
        ));
        CF01_Audit::record($actor_id, 'BreakGlassGranted', 'break_glass', $uuid, 'emergency_care', array(
            'expires_at' => $expires,
            'fields' => $requested_fields,
            'emergency_reference_hash' => hash('sha256', $emergency_reference),
        ));
        CF01_Outbox::enqueue('BreakGlassGranted', array('grant_uuid' => $uuid, 'patient_uuid' => $patient_uuid, 'expires_at' => $expires), $uuid);
        return self::get($uuid);
    }

    public static function assertion(int $actor_id, string $grant_uuid, array $requested_fields): array {
        $row = self::get($grant_uuid);
        if ((int) $row['actor_user_id'] !== $actor_id || ($row['status'] ?? '') !== 'granted' || ($row['review_status'] ?? '') === 'reviewed') {
            throw new RuntimeException('Break-glass grant is unavailable.');
        }
        if (!CF01_Authorization::not_expired((string) $row['expires_at'])) {
            self::expire($row);
            throw new RuntimeException('Break-glass grant expired.');
        }
        $actor = CF01_Authorization::clinician($actor_id, 'use_break_glass', array('purpose' => 'emergency_care', 'patient_uuid' => $row['patient_uuid']));
        $context = CF01_Crypto::decrypt((string) ($row['context_cipher'] ?? ''), 'break-glass-context');
        if (!is_array($context) || empty($context['emergency']) || empty($context['grant_nonce_hash'])) {
            throw new RuntimeException('Break-glass context is unavailable.');
        }
        $current_professional = (string) ($actor['professional']['professional_uuid'] ?? '');
        $granted_professional = (string) ($context['professional_uuid'] ?? '');
        if ($current_professional === '' || $granted_professional === '' || !hash_equals($granted_professional, $current_professional)) {
            throw new RuntimeException('Break-glass professional identity changed after grant.');
        }
        $granted_fields = array_values(array_unique(array_map('sanitize_key', (array) ($context['requested_fields'] ?? array()))));
        $requested_fields = array_values(array_intersect(array_map('sanitize_key', $requested_fields), $granted_fields));
        $allowed = CF01_Authorization::fields('break_glass', 'emergency_care', $requested_fields, array('patient_uuid' => $row['patient_uuid']));
        if (!$allowed) {
            throw new RuntimeException('No emergency minimum-view fields are authorized.');
        }
        CF01_Audit::record($actor_id, 'BreakGlassRecordViewed', 'break_glass', $grant_uuid, 'emergency_care', array('fields' => $allowed));
        return array(
            'valid' => true,
            'grant_uuid' => $grant_uuid,
            'patient_uuid' => (string) $row['patient_uuid'],
            'fields' => $allowed,
            'expires_at' => (string) $row['expires_at'],
            'export_allowed' => false,
            'relationship_created' => false,
            'persistent_access' => false,
        );
    }

    public static function revoke(int $actor_id, string $grant_uuid, string $reason, int $expected_version): array {
        $row = self::get($grant_uuid);
        CF01_Authorization::actor($actor_id, 'revoke_break_glass', array('patient_uuid' => $row['patient_uuid']));
        CF01_Authorization::expected_version($row, $expected_version);
        if (($row['status'] ?? '') !== 'granted') {
            return $row;
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Revocation reason is required.');
        }
        $ok = CF01_DB::update_versioned('breakglass', array(
            'status' => 'revoked',
            'revoked_at' => CF01_DB::now(),
            'revocation_reason_cipher' => CF01_Crypto::encrypt($reason, 'break-glass-revocation'),
            'review_status' => 'pending',
        ), array('grant_uuid' => $grant_uuid), $expected_version);
        if (!$ok) {
            throw new RuntimeException('Break-glass grant changed concurrently.');
        }
        CF01_Audit::record($actor_id, 'BreakGlassRevoked', 'break_glass', $grant_uuid, 'emergency_care', array());
        CF01_Outbox::enqueue('BreakGlassRevoked', array('grant_uuid' => $grant_uuid, 'patient_uuid' => $row['patient_uuid']), $grant_uuid);
        return self::get($grant_uuid);
    }

    public static function review(int $actor_id, string $grant_uuid, array $review, int $expected_version): array {
        $row = self::get($grant_uuid);
        CF01_Authorization::actor($actor_id, 'review_break_glass', array('patient_uuid' => $row['patient_uuid']));
        CF01_Authorization::expected_version($row, $expected_version);
        if ((int) $row['actor_user_id'] === $actor_id) {
            throw new RuntimeException('Break-glass user cannot self-review the access.');
        }
        if (($row['status'] ?? '') === 'granted') {
            throw new RuntimeException('An active break-glass grant must be revoked or expire before final review.');
        }
        if (($row['review_status'] ?? '') === 'reviewed') {
            throw new RuntimeException('Break-glass access was already reviewed.');
        }
        $finding = sanitize_key((string) ($review['finding'] ?? ''));
        if (!in_array($finding, self::REVIEW_FINDINGS, true)) {
            throw new InvalidArgumentException('A recognized break-glass review finding is required.');
        }
        if (empty($review['reviewer_reference']) || !array_key_exists('conflict_disclosed', $review)) {
            throw new InvalidArgumentException('Reviewer reference and conflict disclosure are required.');
        }
        if (in_array($finding, array('misuse', 'policy_deviation'), true)) {
            $enforcement = apply_filters('cf01_break_glass_misuse_enforcement', null, array(
                'contract_version' => '1.0.0',
                'grant_uuid' => $grant_uuid,
                'patient_uuid' => (string) $row['patient_uuid'],
                'actor_user_id' => (int) $row['actor_user_id'],
                'finding' => $finding,
                'suspension_evaluation_required' => true,
            ));
            if (!is_array($enforcement) || ($enforcement['contract_version'] ?? '') !== '1.0.0' || empty($enforcement['accepted']) || empty($enforcement['alerted']) || empty($enforcement['suspension_evaluated'])) {
                throw new RuntimeException('Break-glass misuse requires accepted security alert and suspension evaluation evidence.');
            }
        }
        $ok = CF01_DB::update_versioned('breakglass', array(
            'review_status' => 'reviewed',
            'review_cipher' => CF01_Crypto::encrypt(self::sanitize($review), 'break-glass-review'),
            'reviewed_by_user_id' => $actor_id,
            'reviewed_at' => CF01_DB::now(),
        ), array('grant_uuid' => $grant_uuid), $expected_version);
        if (!$ok) {
            throw new RuntimeException('Break-glass review changed concurrently.');
        }
        CF01_Audit::record($actor_id, 'BreakGlassReviewed', 'break_glass', $grant_uuid, 'emergency_review', array('finding' => $finding));
        CF01_Outbox::enqueue('BreakGlassReviewed', array('grant_uuid' => $grant_uuid, 'patient_uuid' => $row['patient_uuid'], 'finding' => $finding), $grant_uuid);
        return self::get($grant_uuid);
    }

    public static function get(string $uuid): array {
        $row = CF01_DB::row('SELECT * FROM ' . CF01_DB::table('breakglass') . ' WHERE grant_uuid = %s LIMIT 1', array($uuid));
        if (!$row) {
            throw new RuntimeException('Break-glass grant is unavailable.');
        }
        return $row;
    }

    public static function states(): array {
        return self::STATES;
    }

    private static function expire(array $row): void {
        $updated = CF01_DB::update_versioned(
            'breakglass',
            array('status' => 'expired', 'review_status' => 'pending'),
            array('grant_uuid' => $row['grant_uuid']),
            (int) $row['row_version']
        );
        if (!$updated) {
            return;
        }
        CF01_Audit::system('BreakGlassExpired', 'break_glass', (string) $row['grant_uuid'], 'emergency_care', array());
        CF01_Outbox::enqueue('BreakGlassExpired', array('grant_uuid' => $row['grant_uuid'], 'patient_uuid' => $row['patient_uuid']), (string) $row['grant_uuid']);
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
