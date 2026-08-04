<?php
defined('ABSPATH') || exit;

final class CF01_Retention {
    public static function schedule(int $actor_id, string $object_type, string $object_uuid, string $policy_key, ?string $eligible_at, array $holds = array()): array {
        CF01_Authorization::actor($actor_id, 'manage_retention');
        if (trim($policy_key) === '') {
            throw new InvalidArgumentException('Approved retention policy key is required.');
        }
        if ($eligible_at !== null && strtotime($eligible_at . ' UTC') === false) {
            throw new InvalidArgumentException('Retention eligibility time is invalid.');
        }
        $uuid = CF01_DB::uuid();
        CF01_DB::insert('retention', array(
            'retention_uuid' => $uuid,
            'object_type' => sanitize_key($object_type),
            'object_uuid' => $object_uuid,
            'policy_key' => sanitize_key($policy_key),
            'eligible_at' => $eligible_at,
            'hold_status' => $holds ? 'held' : 'none',
            'holds_cipher' => CF01_Crypto::encrypt($holds, 'retention-holds'),
            'status' => 'scheduled',
            'purge_started_at' => null,
            'purged_at' => null,
            'purge_receipt_cipher' => null,
            'row_version' => 1,
            'created_at' => CF01_DB::now(),
            'updated_at' => CF01_DB::now(),
        ));
        CF01_Audit::record($actor_id, 'ClinicalRetentionScheduled', 'retention_record', $uuid, 'records_governance', array('policy_key' => $policy_key));
        return self::get($uuid);
    }

    public static function place_hold(int $actor_id, string $uuid, array $hold, int $expected_version): array {
        $row = self::get($uuid);
        CF01_Authorization::actor($actor_id, 'place_hold');
        CF01_Authorization::expected_version($row, $expected_version);
        if (empty($hold['type']) || empty($hold['reason']) || empty($hold['authority'])) {
            throw new InvalidArgumentException('Hold type, reason and authority are required.');
        }
        $holds = CF01_Crypto::decrypt((string) $row['holds_cipher'], 'retention-holds');
        $holds = is_array($holds) ? $holds : array();
        $holds[] = array(
            'hold_uuid' => CF01_DB::uuid(),
            'type' => sanitize_key((string) $hold['type']),
            'reason' => sanitize_textarea_field((string) $hold['reason']),
            'authority' => sanitize_text_field((string) $hold['authority']),
            'placed_by' => $actor_id,
            'placed_at' => CF01_DB::now(),
            'released_at' => null,
        );
        $ok = CF01_DB::update_versioned('retention', array(
            'hold_status' => 'held',
            'holds_cipher' => CF01_Crypto::encrypt($holds, 'retention-holds'),
        ), array('retention_uuid' => $uuid), $expected_version);
        if (!$ok) {
            throw new RuntimeException('Retention record changed concurrently.');
        }
        CF01_Audit::record($actor_id, 'ClinicalRetentionHoldPlaced', 'retention_record', $uuid, 'records_governance', array('hold_type' => $hold['type']));
        return self::get($uuid);
    }

    public static function release_hold(int $actor_id, string $uuid, string $hold_uuid, string $reason, int $expected_version): array {
        $row = self::get($uuid);
        CF01_Authorization::actor($actor_id, 'release_hold');
        CF01_Authorization::expected_version($row, $expected_version);
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Hold release reason is required.');
        }
        $holds = CF01_Crypto::decrypt((string) $row['holds_cipher'], 'retention-holds');
        $active = false;
        $found = false;
        foreach ((array) $holds as &$hold) {
            if (($hold['hold_uuid'] ?? '') === $hold_uuid && empty($hold['released_at'])) {
                if ((int) ($hold['placed_by'] ?? 0) === $actor_id) {
                    throw new RuntimeException('The hold placer cannot release the same hold.');
                }
                $hold['released_at'] = CF01_DB::now();
                $hold['released_by'] = $actor_id;
                $hold['release_reason'] = sanitize_textarea_field($reason);
                $found = true;
            }
            if (empty($hold['released_at'])) {
                $active = true;
            }
        }
        unset($hold);
        if (!$found) {
            throw new RuntimeException('Active hold is unavailable.');
        }
        $ok = CF01_DB::update_versioned('retention', array(
            'hold_status' => $active ? 'held' : 'none',
            'holds_cipher' => CF01_Crypto::encrypt((array) $holds, 'retention-holds'),
        ), array('retention_uuid' => $uuid), $expected_version);
        if (!$ok) {
            throw new RuntimeException('Retention record changed concurrently.');
        }
        CF01_Audit::record($actor_id, 'ClinicalRetentionHoldReleased', 'retention_record', $uuid, 'records_governance', array());
        return self::get($uuid);
    }

    public static function reconcile(): void {
        if (!CF01_DB::is_enabled()) {
            return;
        }
        $rows = CF01_DB::rows('SELECT * FROM ' . CF01_DB::table('retention') . ' WHERE status = %s AND hold_status = %s AND eligible_at IS NOT NULL AND eligible_at <= %s ORDER BY eligible_at ASC LIMIT 50', array('scheduled', 'none', CF01_DB::now()));
        foreach ($rows as $row) {
            try {
                self::purge(0, (string) $row['retention_uuid'], (int) $row['row_version'], true);
            } catch (Throwable $error) {
                CF01_Audit::system('ClinicalRetentionPurgeFailed', 'retention_record', (string) $row['retention_uuid'], 'records_governance', array('code' => 'purge_failed'));
            }
        }
    }

    public static function purge(int $actor_id, string $uuid, int $expected_version, bool $system = false): array {
        $row = self::get($uuid);
        if (!$system) {
            CF01_Authorization::actor($actor_id, 'purge_record');
        }
        CF01_Authorization::expected_version($row, $expected_version);
        if (($row['hold_status'] ?? '') !== 'none' || empty($row['eligible_at']) || strtotime((string) $row['eligible_at'] . ' UTC') > time()) {
            throw new RuntimeException('Record is not eligible for purge.');
        }
        $receipt = apply_filters('cf01_purge_object', null, (string) $row['object_type'], (string) $row['object_uuid']);
        if (!is_array($receipt) || empty($receipt['completed']) || empty($receipt['evidence']) || empty($receipt['backup_expiry_confirmed']) || empty($receipt['approved_by_user_id'])) {
            throw new RuntimeException('Verified native-owner purge receipt with independent approval is required.');
        }
        if (!$system && (int) $receipt['approved_by_user_id'] === $actor_id) {
            throw new RuntimeException('Purge approval and execution require separated duties.');
        }
        $ok = CF01_DB::update_versioned('retention', array(
            'status' => 'purged',
            'purge_started_at' => CF01_DB::now(),
            'purged_at' => CF01_DB::now(),
            'purge_receipt_cipher' => CF01_Crypto::encrypt($receipt, 'retention-purge-receipt'),
        ), array('retention_uuid' => $uuid), $expected_version);
        if (!$ok) {
            throw new RuntimeException('Retention record changed concurrently.');
        }
        CF01_Audit::system('ClinicalRecordPurged', 'retention_record', $uuid, 'records_governance', array('object_type' => $row['object_type']));
        return self::get($uuid);
    }

    public static function get(string $uuid): array {
        $row = CF01_DB::row('SELECT * FROM ' . CF01_DB::table('retention') . ' WHERE retention_uuid = %s LIMIT 1', array($uuid));
        if (!$row) {
            throw new RuntimeException('Retention record is unavailable.');
        }
        return $row;
    }
}
