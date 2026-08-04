<?php
defined('ABSPATH') || exit;

final class CF01_Break_Glass {
    private const STATES = array('requested', 'granted', 'revoked', 'expired', 'review_pending', 'reviewed', 'rejected');
    private const MAX_TTL = 15 * MINUTE_IN_SECONDS;

    public static function request(int $actor_id, string $patient_uuid, string $reason, array $context): array {
        CF01_Patients::get($patient_uuid);
        CF01_Authorization::clinician($actor_id, 'grant_break_glass', array('purpose' => 'emergency_care'));
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Explicit emergency reason is required.');
        }
        if (empty($context['emergency']) || !empty($context['bulk']) || !empty($context['export'])) {
            throw new RuntimeException('Break-glass is restricted to a single emergency minimum-view request.');
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
        $uuid = CF01_DB::uuid();
        $expires = gmdate('Y-m-d H:i:s', time() + self::MAX_TTL);
        CF01_DB::insert('breakglass', array(
            'grant_uuid' => $uuid,
            'patient_uuid' => $patient_uuid,
            'actor_user_id' => $actor_id,
            'status' => 'granted',
            'reason_cipher' => CF01_Crypto::encrypt($reason, 'break-glass-reason'),
            'context_cipher' => CF01_Crypto::encrypt(array(
                'emergency' => true,
                'requested_fields' => $requested_fields,
                'location' => sanitize_text_field((string) ($context['location'] ?? '')),
                'device' => sanitize_text_field((string) ($context['device'] ?? '')),
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
        CF01_Audit::record($actor_id, 'BreakGlassGranted', 'break_glass', $uuid, 'emergency_care', array('expires_at' => $expires, 'fields' => $requested_fields));
        CF01_Outbox::enqueue('BreakGlassGranted', array('grant_uuid' => $uuid, 'patient_uuid' => $patient_uuid, 'expires_at' => $expires), $uuid);
        return self::get($uuid);
    }

    public static function assertion(int $actor_id, string $grant_uuid, array $requested_fields): array {
        $row = self::get($grant_uuid);
        if ((int) $row['actor_user_id'] !== $actor_id || ($row['status'] ?? '') !== 'granted') {
            throw new RuntimeException('Break-glass grant is unavailable.');
        }
        if (!CF01_Authorization::not_expired((string) $row['expires_at'])) {
            self::expire($row);
            throw new RuntimeException('Break-glass grant expired.');
        }
        CF01_Authorization::clinician($actor_id, 'use_break_glass', array('purpose' => 'emergency_care'));
        $context = CF01_Crypto::decrypt((string) ($row['context_cipher'] ?? ''), 'break-glass-context');
        if (!is_array($context) || empty($context['emergency'])) {
            throw new RuntimeException('Break-glass context is unavailable.');
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
        CF01_Authorization::actor($actor_id, 'revoke_break_glass');
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
        CF01_Authorization::actor($actor_id, 'review_break_glass');
        CF01_Authorization::expected_version($row, $expected_version);
        if ((int) $row['actor_user_id'] === $actor_id) {
            throw new RuntimeException('Break-glass user cannot self-review the access.');
        }
        if (($row['review_status'] ?? '') === 'reviewed') {
            throw new RuntimeException('Break-glass access was already reviewed.');
        }
        if (empty($review['finding'])) {
            throw new InvalidArgumentException('Break-glass review finding is required.');
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
        CF01_Audit::record($actor_id, 'BreakGlassReviewed', 'break_glass', $grant_uuid, 'emergency_review', array('finding' => sanitize_key((string) $review['finding'])));
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
