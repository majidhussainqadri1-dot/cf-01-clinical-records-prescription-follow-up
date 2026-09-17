<?php
defined('ABSPATH') || exit;

final class CF01_Retention {
    private const PURGE_RETRY_STATES = array('scheduled', 'purge_failed', 'purge_pending');

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
        CF01_Authorization::actor($actor_id, 'place_hold');
        if (empty($hold['type']) || empty($hold['reason']) || empty($hold['authority'])) {
            throw new InvalidArgumentException('Hold type, reason and authority are required.');
        }
        return self::with_record_lock($uuid, function () use ($actor_id, $uuid, $hold, $expected_version): array {
            CF01_DB::transaction(function () use ($actor_id, $uuid, $hold, $expected_version): void {
                $row = self::get_for_update($uuid);
                CF01_Authorization::expected_version($row, $expected_version);
                if (($row['status'] ?? '') === 'purged') {
                    throw new RuntimeException('A purged retention record cannot receive a new hold.');
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
                $changes = array(
                    'hold_status' => 'held',
                    'holds_cipher' => CF01_Crypto::encrypt($holds, 'retention-holds'),
                );
                // A crash-recovered purge_pending record becomes an explicit failed
                // purge when a hold wins before a retry. This prevents silent retry
                // while preservation authority is active.
                if (($row['status'] ?? '') === 'purge_pending') {
                    $changes['status'] = 'purge_failed';
                }
                $ok = CF01_DB::update_versioned('retention', $changes, array('retention_uuid' => $uuid), $expected_version);
                if (!$ok) {
                    throw new RuntimeException('Retention record changed concurrently.');
                }
                CF01_Audit::record($actor_id, 'ClinicalRetentionHoldPlaced', 'retention_record', $uuid, 'records_governance', array('hold_type' => $hold['type']));
            });
            return self::get($uuid);
        });
    }

    public static function release_hold(int $actor_id, string $uuid, string $hold_uuid, string $reason, int $expected_version): array {
        CF01_Authorization::actor($actor_id, 'release_hold');
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Hold release reason is required.');
        }
        return self::with_record_lock($uuid, function () use ($actor_id, $uuid, $hold_uuid, $reason, $expected_version): array {
            CF01_DB::transaction(function () use ($actor_id, $uuid, $hold_uuid, $reason, $expected_version): void {
                $row = self::get_for_update($uuid);
                CF01_Authorization::expected_version($row, $expected_version);
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
            });
            return self::get($uuid);
        });
    }

    public static function reconcile(): void {
        if (!CF01_DB::is_enabled()) {
            return;
        }
        $rows = CF01_DB::rows(
            'SELECT * FROM ' . CF01_DB::table('retention') . ' WHERE status IN (%s,%s,%s) AND hold_status = %s AND eligible_at IS NOT NULL AND eligible_at <= %s ORDER BY eligible_at ASC LIMIT 50',
            array('scheduled', 'purge_failed', 'purge_pending', 'none', CF01_DB::now())
        );
        foreach ($rows as $row) {
            try {
                self::purge(0, (string) $row['retention_uuid'], (int) $row['row_version'], true);
            } catch (Throwable $error) {
                try {
                    CF01_Audit::system('ClinicalRetentionPurgeFailed', 'retention_record', (string) $row['retention_uuid'], 'records_governance', array('code' => 'purge_failed'));
                } catch (Throwable $ignored) {
                    // The purge method itself persists a fail-closed status where
                    // possible; reconciliation must continue for unrelated rows.
                }
            }
        }
    }

    public static function purge(int $actor_id, string $uuid, int $expected_version, bool $system = false): array {
        if (!$system) {
            CF01_Authorization::actor($actor_id, 'purge_record');
        }
        return self::with_record_lock($uuid, function () use ($actor_id, $uuid, $expected_version, $system): array {
            $row = self::get($uuid);
            CF01_Authorization::expected_version($row, $expected_version);
            self::assert_purge_eligible($row);

            $provider_version = $expected_version;
            if (($row['status'] ?? '') !== 'purge_pending') {
                CF01_DB::transaction(function () use ($actor_id, $uuid, $row, $expected_version, $system): void {
                    $current = self::get_for_update($uuid);
                    CF01_Authorization::expected_version($current, $expected_version);
                    self::assert_purge_eligible($current);
                    $ok = CF01_DB::update_versioned('retention', array(
                        'status' => 'purge_pending',
                        'purge_started_at' => CF01_DB::now(),
                    ), array('retention_uuid' => $uuid), $expected_version);
                    if (!$ok) {
                        throw new RuntimeException('Retention record changed concurrently before purge.');
                    }
                    $audit_actor = $system ? 0 : $actor_id;
                    if ($audit_actor > 0) {
                        CF01_Audit::record($audit_actor, 'ClinicalRetentionPurgeStarted', 'retention_record', $uuid, 'records_governance', array('object_type' => $row['object_type']));
                    } else {
                        CF01_Audit::system('ClinicalRetentionPurgeStarted', 'retention_record', $uuid, 'records_governance', array('object_type' => $row['object_type']));
                    }
                });
                $provider_version++;
                $row = self::get($uuid);
            }

            try {
                $receipt = apply_filters('cf01_purge_object', null, (string) $row['object_type'], (string) $row['object_uuid']);
                if (!is_array($receipt) || empty($receipt['completed']) || empty($receipt['evidence']) || empty($receipt['backup_expiry_confirmed']) || empty($receipt['approved_by_user_id'])) {
                    throw new RuntimeException('Verified native-owner purge receipt with independent approval is required.');
                }
                if (!$system && (int) $receipt['approved_by_user_id'] === $actor_id) {
                    throw new RuntimeException('Purge approval and execution require separated duties.');
                }
            } catch (Throwable $error) {
                self::mark_purge_failed($uuid, $provider_version);
                throw $error;
            }

            try {
                CF01_DB::transaction(function () use ($actor_id, $uuid, $row, $receipt, $provider_version, $system): void {
                    $current = self::get_for_update($uuid);
                    CF01_Authorization::expected_version($current, $provider_version);
                    if (($current['status'] ?? '') !== 'purge_pending') {
                        throw new RuntimeException('Retention purge is no longer pending.');
                    }
                    self::assert_purge_eligible($current, true);
                    $ok = CF01_DB::update_versioned('retention', array(
                        'status' => 'purged',
                        'purged_at' => CF01_DB::now(),
                        'purge_receipt_cipher' => CF01_Crypto::encrypt($receipt, 'retention-purge-receipt'),
                    ), array('retention_uuid' => $uuid), $provider_version);
                    if (!$ok) {
                        throw new RuntimeException('Retention record changed concurrently after provider purge.');
                    }
                    $audit_actor = $system ? 0 : $actor_id;
                    if ($audit_actor > 0) {
                        CF01_Audit::record($audit_actor, 'ClinicalRecordPurged', 'retention_record', $uuid, 'records_governance', array('object_type' => $row['object_type']));
                    } else {
                        CF01_Audit::system('ClinicalRecordPurged', 'retention_record', $uuid, 'records_governance', array('object_type' => $row['object_type']));
                    }
                });
            } catch (Throwable $error) {
                // If the provider accepted deletion but local finalization failed,
                // preserve an explicit reconciliation state instead of pretending
                // the canonical ledger is still merely scheduled.
                try {
                    $current = self::get($uuid);
                    if (($current['status'] ?? '') === 'purge_pending') {
                        self::mark_purge_failed($uuid, (int) $current['row_version']);
                    }
                } catch (Throwable $ignored) {
                    // Fail closed: the original error remains authoritative and an
                    // operator must reconcile the provider receipt with the ledger.
                }
                throw $error;
            }
            return self::get($uuid);
        });
    }

    public static function get(string $uuid): array {
        $row = CF01_DB::row('SELECT * FROM ' . CF01_DB::table('retention') . ' WHERE retention_uuid = %s LIMIT 1', array($uuid));
        if (!$row) {
            throw new RuntimeException('Retention record is unavailable.');
        }
        return $row;
    }

    private static function get_for_update(string $uuid): array {
        $row = CF01_DB::row('SELECT * FROM ' . CF01_DB::table('retention') . ' WHERE retention_uuid = %s LIMIT 1 FOR UPDATE', array($uuid));
        if (!$row) {
            throw new RuntimeException('Retention record is unavailable.');
        }
        return $row;
    }

    private static function assert_purge_eligible(array $row, bool $pending_only = false): void {
        $status = (string) ($row['status'] ?? '');
        $allowed = $pending_only ? array('purge_pending') : self::PURGE_RETRY_STATES;
        if (!in_array($status, $allowed, true)
            || ($row['hold_status'] ?? '') !== 'none'
            || empty($row['eligible_at'])
            || strtotime((string) $row['eligible_at'] . ' UTC') > time()
        ) {
            throw new RuntimeException('Record is not eligible for purge.');
        }
    }

    private static function mark_purge_failed(string $uuid, int $expected_version): void {
        CF01_DB::transaction(function () use ($uuid, $expected_version): void {
            $current = self::get_for_update($uuid);
            CF01_Authorization::expected_version($current, $expected_version);
            if (($current['status'] ?? '') !== 'purge_pending') {
                return;
            }
            $ok = CF01_DB::update_versioned('retention', array(
                'status' => 'purge_failed',
            ), array('retention_uuid' => $uuid), $expected_version);
            if (!$ok) {
                throw new RuntimeException('Retention purge failure state changed concurrently.');
            }
            CF01_Audit::system('ClinicalRetentionPurgeFailed', 'retention_record', $uuid, 'records_governance', array('code' => 'provider_or_finalize_failed'));
        });
    }

    private static function with_record_lock(string $uuid, callable $callback) {
        global $wpdb;
        if (!method_exists($wpdb, 'get_var') || !method_exists($wpdb, 'prepare')) {
            throw new RuntimeException('Atomic retention locking is unavailable.');
        }
        $lock_name = 'cf01_ret_' . substr(hash('sha256', $uuid), 0, 48);
        $acquired = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 2)', $lock_name));
        if ((string) $acquired !== '1') {
            throw new RuntimeException('Retention record is busy; retry after the current protected transition completes.');
        }
        try {
            return $callback();
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        }
    }
}
