<?php
defined('ABSPATH') || exit;

final class CF01_Audit {
    public static function record(int $actor_id, string $action, string $object_type, string $object_uuid, string $purpose, array $metadata): string {
        return self::write($actor_id, $action, $object_type, $object_uuid, $purpose, $metadata, 'success');
    }

    public static function denied(int $actor_id, string $action, string $object_type, string $object_uuid, string $purpose, string $code): string {
        return self::write($actor_id, $action, $object_type, $object_uuid, $purpose, array('code' => $code), 'denied');
    }

    public static function system(string $action, string $object_type, string $object_uuid, string $purpose, array $metadata): string {
        return self::write(0, $action, $object_type, $object_uuid, $purpose, $metadata, 'success');
    }

    public static function access(int $actor_id, string $patient_uuid, string $action, string $object_type, string $object_uuid, string $purpose, string $result): void {
        $event_uuid = self::write($actor_id, $action, $object_type, $object_uuid, $purpose, array('patient_uuid' => $patient_uuid), $result);
        CF01_DB::insert('access', array(
            'event_uuid' => $event_uuid,
            'patient_uuid' => $patient_uuid,
            'actor_pseudonym' => self::actor_pseudonym($actor_id),
            'action' => sanitize_key($action),
            'object_type' => sanitize_key($object_type),
            'object_uuid' => $object_uuid,
            'purpose' => sanitize_key($purpose),
            'result' => sanitize_key($result),
            'occurred_at' => CF01_DB::now(),
        ));
    }

    private static function write(int $actor_id, string $action, string $object_type, string $object_uuid, string $purpose, array $metadata, string $result): string {
        return CF01_DB::transaction(function () use ($actor_id, $action, $object_type, $object_uuid, $purpose, $metadata, $result): string {
            $uuid = CF01_DB::uuid();
            $previous = CF01_DB::row('SELECT chain_hash FROM ' . CF01_DB::table('audit') . ' ORDER BY id DESC LIMIT 1 FOR UPDATE');
            $previous_hash = (string) ($previous['chain_hash'] ?? str_repeat('0', 64));
            $record = array(
                'event_uuid' => $uuid,
                'actor_pseudonym' => self::actor_pseudonym($actor_id),
                'action' => sanitize_key($action),
                'object_type' => sanitize_key($object_type),
                'object_uuid' => $object_uuid,
                'purpose' => sanitize_key($purpose),
                'result' => sanitize_key($result),
                'metadata' => self::minimize($metadata),
                'occurred_at' => CF01_DB::now(),
                'previous_hash' => $previous_hash,
            );
            $record_hash = hash('sha256', CF01_Crypto::canonical_json($record));
            $chain_hash = hash('sha256', $previous_hash . '|' . $record_hash);
            try {
                CF01_DB::insert('audit', array(
                    'event_uuid' => $uuid,
                    'actor_pseudonym' => $record['actor_pseudonym'],
                    'action' => $record['action'],
                    'object_type' => $record['object_type'],
                    'object_uuid' => $object_uuid,
                    'purpose' => $record['purpose'],
                    'result' => $record['result'],
                    'metadata_cipher' => CF01_Crypto::encrypt($record['metadata'], 'audit-metadata'),
                    'record_hash' => $record_hash,
                    'previous_hash' => $previous_hash,
                    'chain_hash' => $chain_hash,
                    'occurred_at' => $record['occurred_at'],
                ));
            } catch (Throwable $error) {
                throw new RuntimeException('Clinical audit persistence failed; action cannot be treated as complete.', 0, $error);
            }
            return $uuid;
        });
    }

    private static function actor_pseudonym(int $actor_id): string {
        return hash_hmac('sha256', (string) $actor_id, CF01_Crypto::key() ?? str_repeat("\0", 32));
    }

    private static function minimize(array $metadata): array {
        $blocked = array('password', 'token', 'secret', 'body', 'message', 'diagnosis', 'remedy', 'dose', 'phone', 'email', 'name');
        $result = array();
        foreach ($metadata as $key => $value) {
            $normalized = strtolower((string) $key);
            $unsafe = false;
            foreach ($blocked as $word) {
                if (str_contains($normalized, $word)) {
                    $unsafe = true;
                    break;
                }
            }
            if (!$unsafe) {
                $result[$key] = is_scalar($value) || $value === null ? $value : '[structured]';
            }
        }
        return $result;
    }
}

final class CF01_Outbox {
    public static function enqueue(string $event, array $payload, string $aggregate_uuid): string {
        $event = self::normalize_plan_event($event, $payload);
        $event = self::canonical_event_name($event);
        $uuid = CF01_DB::uuid();
        if (!empty($payload['patient_uuid']) && empty($payload['recipient_platform_uuid'])) {
            $patient = CF01_DB::row('SELECT platform_subject_cipher FROM ' . CF01_DB::table('patients') . ' WHERE clinical_uuid = %s LIMIT 1', array((string) $payload['patient_uuid']));
            if ($patient && !empty($patient['platform_subject_cipher'])) {
                $recipient = CF01_Crypto::decrypt((string) $patient['platform_subject_cipher'], 'patient-platform-link');
                if (is_string($recipient) && $recipient !== '') {
                    $payload['recipient_platform_uuid'] = $recipient;
                }
            }
        }
        $payload['destination_reference'] = $uuid;
        $payload['aggregate_uuid'] = $aggregate_uuid;
        $payload['event_name'] = $event;
        $minimized = self::minimize_payload($payload);
        CF01_DB::insert('outbox', array(
            'event_uuid' => $uuid,
            'event_name' => $event,
            'aggregate_uuid' => $aggregate_uuid,
            'payload_cipher' => CF01_Crypto::encrypt($minimized, 'outbox-payload'),
            'status' => 'pending',
            'attempts' => 0,
            'available_at' => CF01_DB::now(),
            'last_error_code' => null,
            'delivered_at' => null,
            'row_version' => 1,
            'created_at' => CF01_DB::now(),
            'updated_at' => CF01_DB::now(),
        ));
        return $uuid;
    }

    public static function process(): void {
        if (!CF01_DB::is_enabled()) {
            return;
        }
        $rows = CF01_DB::rows('SELECT * FROM ' . CF01_DB::table('outbox') . ' WHERE status IN (%s,%s) AND available_at <= %s ORDER BY id ASC LIMIT 50', array('pending', 'retry', CF01_DB::now()));
        foreach ($rows as $row) {
            self::deliver($row);
        }
    }

    public static function deliver(array $row): void {
        $payload = CF01_Crypto::decrypt((string) $row['payload_cipher'], 'outbox-payload');
        $event_name = (string) $row['event_name'];
        $event_key = strtolower($event_name);
        $request = array(
            'recipient_platform_uuid' => (string) ($payload['recipient_platform_uuid'] ?? ''),
            'template_key' => self::template($event_name),
            'action_category' => self::category($event_name),
            'destination_reference' => (string) $row['event_uuid'],
            'urgency' => !empty($payload['red_flag']) ? 'high' : 'normal',
            'expires_at' => gmdate('Y-m-d\TH:i:s\Z', time() + self::notification_ttl($event_name)),
            'mandatory_policy' => str_contains($event_key, 'breakglass') ? 'clinical_access_alert' : '',
            'correlation_id' => (string) $row['event_uuid'],
            'dedupe_key' => hash('sha256', (string) $row['event_uuid']),
        );
        if ($request['recipient_platform_uuid'] === '') {
            $result = array('accepted' => false, 'retryable' => true, 'code' => 'recipient_unavailable');
        } else {
            $result = CF01_Contracts::notify($request);
        }
        $expected = (int) $row['row_version'];
        if (!empty($result['accepted']) || !empty($result['suppressed'])) {
            CF01_DB::update_versioned('outbox', array(
                'status' => !empty($result['suppressed']) ? 'suppressed' : 'delivered',
                'delivered_at' => CF01_DB::now(),
                'delivery_reference' => sanitize_text_field((string) ($result['reference'] ?? '')),
            ), array('event_uuid' => $row['event_uuid']), $expected);
            return;
        }
        $attempts = (int) $row['attempts'] + 1;
        $retryable = !empty($result['retryable']) && $attempts < 8;
        CF01_DB::update_versioned('outbox', array(
            'status' => $retryable ? 'retry' : 'dead_letter',
            'attempts' => $attempts,
            'available_at' => gmdate('Y-m-d H:i:s', time() + min(3600, 60 * (2 ** min(6, $attempts)))),
            'last_error_code' => sanitize_key((string) ($result['code'] ?? 'delivery_failed')),
        ), array('event_uuid' => $row['event_uuid']), $expected);
    }

    public static function resolve_destination(int $actor_id, string $reference): string {
        $row = CF01_DB::row('SELECT * FROM ' . CF01_DB::table('outbox') . ' WHERE event_uuid = %s LIMIT 1', array($reference));
        if (!$row) {
            throw new RuntimeException('Protected destination is unavailable.');
        }
        $payload = CF01_Crypto::decrypt((string) $row['payload_cipher'], 'outbox-payload');
        if (!is_array($payload) || empty($payload['patient_uuid']) || empty($payload['aggregate_uuid'])) {
            throw new RuntimeException('Protected destination is unavailable.');
        }
        $patient_uuid = (string) $payload['patient_uuid'];
        if (!CF01_Authorization::patient_owner($actor_id, $patient_uuid)) {
            CF01_Authorization::clinician($actor_id, 'view_clinical_record');
            CF01_Authorization::relationship($patient_uuid, $actor_id, 'clinical_care');
        } else {
            CF01_Authorization::actor($actor_id, 'view_own_clinical_record');
        }
        $event = strtolower((string) ($payload['event_name'] ?? $row['event_name']));
        $aggregate = rawurlencode((string) $payload['aggregate_uuid']);
        if (str_contains($event, 'prescription')) {
            $path = '/clinic/prescriptions/' . $aggregate;
        } elseif (str_contains($event, 'followup') || str_contains($event, 'outcome')) {
            $path = '/clinic/follow-ups/' . $aggregate;
        } elseif (str_contains($event, 'encounter')) {
            $path = '/clinic/encounters/' . $aggregate;
        } else {
            $path = '/my-health-record';
        }
        $url = home_url($path);
        $parts = wp_parse_url($url);
        $home = wp_parse_url(home_url('/'));
        if (!is_array($parts) || !is_array($home) || ($parts['scheme'] ?? '') !== 'https' || !hash_equals(strtolower((string) ($home['host'] ?? '')), strtolower((string) ($parts['host'] ?? ''))) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || isset($parts['query'])) {
            throw new RuntimeException('Protected destination failed same-origin validation.');
        }
        CF01_Audit::access($actor_id, $patient_uuid, 'ClinicalNotificationDestinationResolved', 'outbox_event', $reference, 'clinical_navigation', 'success');
        return $url;
    }

    private static function normalize_plan_event(string $event, array $payload): string {
        $event = trim($event);
        if (strcasecmp($event, 'FollowUpStatusChanged') === 0 && sanitize_key((string) ($payload['status'] ?? '')) === 'overdue') {
            return 'FollowUpOverdue';
        }
        return $event;
    }

    private static function canonical_event_name(string $event): string {
        $event = trim($event);
        if ($event === '' || strlen($event) > 96 || !preg_match('/^[A-Za-z][A-Za-z0-9.:-]*$/', $event)) {
            throw new InvalidArgumentException('Clinical event name is invalid.');
        }
        return $event;
    }

    private static function notification_ttl(string $event): int {
        $ttl = (int) apply_filters('cf01_notification_expiry_seconds', DAY_IN_SECONDS, $event);
        return max(300, min(7 * DAY_IN_SECONDS, $ttl));
    }

    private static function template(string $event): string {
        return str_contains(strtolower($event), 'breakglass') ? 'clinical_access_alert' : 'private_clinical_update';
    }

    private static function category(string $event): string {
        $event = strtolower($event);
        if (str_contains($event, 'followup')) {
            return 'clinical_followup';
        }
        if (str_contains($event, 'prescription')) {
            return 'clinical_prescription';
        }
        if (str_contains($event, 'breakglass')) {
            return 'clinical_security';
        }
        return 'clinical_record';
    }

    private static function minimize_payload(array $payload): array {
        $allowed = array('patient_uuid', 'recipient_platform_uuid', 'destination_reference', 'aggregate_uuid', 'event_name', 'followup_uuid', 'prescription_uuid', 'encounter_uuid', 'case_uuid', 'grant_uuid', 'retention_uuid', 'status', 'due_at', 'expires_at', 'red_flag');
        return array_intersect_key($payload, array_flip($allowed));
    }
}
