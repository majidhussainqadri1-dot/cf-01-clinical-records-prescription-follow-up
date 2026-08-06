<?php
defined('ABSPATH') || exit;

final class CF01_Contracts {
    private const MEMBERSHIP_VERSION = '1.1.2';
    private const RECENT_AUTH_VERSION = '1.0.0';
    private const PRACTITIONER_VERSION = '1.0.0';
    private const CARE_CONTEXT_VERSION = '1.0.0';
    private const NOTIFICATION_VERSION = '1.0.0';
    private const COMMUNICATION_VERSION = '1.0.0';
    private const SHELL_VERSION = '1.0.0';
    private const VISUAL_VERSION = '1.0.0';
    private const MEDIA_VERSION = '1.0.0';
    private const ASSURANCE_VERSION = '1.0.0';
    private const SAFETY_VERSION = '1.0.0';

    public static function membership(int $user_id): array {
        $result = null;
        if (function_exists('smc_membership_assertions')) {
            $result = smc_membership_assertions($user_id);
        }
        $result = apply_filters('cf01_membership_assertion', $result, $user_id);
        return self::validate($result, self::MEMBERSHIP_VERSION, array(
            'platform_uuid', 'approved', 'suspended', 'account_class', 'guardian_context'
        ));
    }

    public static function recent_auth(int $user_id, string $purpose): array {
        $result = null;
        if (function_exists('sauth_cf01_recent_authentication_assertion')) {
            $result = sauth_cf01_recent_authentication_assertion($user_id, $purpose);
        }
        $result = apply_filters('cf01_recent_auth_assertion', $result, $user_id, $purpose);
        return self::validate($result, self::RECENT_AUTH_VERSION, array('recent_auth', 'step_up', 'expires_at', 'subject_uuid'));
    }

    public static function practitioner(int $user_id, string $purpose = 'clinical_care'): array {
        $result = null;
        if (function_exists('sgd_cf01_professional_eligibility_assertion')) {
            $result = sgd_cf01_professional_eligibility_assertion($user_id, $purpose);
        }
        $result = apply_filters('cf01_practitioner_assertion', $result, $user_id, $purpose);
        return self::validate($result, self::PRACTITIONER_VERSION, array(
            'eligible', 'verified', 'suspended', 'expires_at', 'professional_uuid', 'jurisdictions', 'scopes'
        ));
    }

    public static function care_context(string $reference, int $actor_id): array {
        $result = null;
        if (function_exists('swc_cf01_care_context_assertion')) {
            $result = swc_cf01_care_context_assertion($reference, $actor_id);
        }
        $result = apply_filters('cf01_care_context_assertion', $result, $reference, $actor_id);
        return self::validate($result, self::CARE_CONTEXT_VERSION, array(
            'patient_platform_uuid', 'practitioner_platform_uuid', 'appointment_reference', 'actor_platform_uuid'
        ));
    }

    public static function notify(array $request): array {
        $request['producer_contract'] = 'cf01.clinical.notification';
        $request['producer_version'] = self::NOTIFICATION_VERSION;
        $result = null;
        if (function_exists('sun_cf01_request_notification')) {
            $result = sun_cf01_request_notification($request);
        }
        $result = apply_filters('cf01_notification_request', $result, $request);
        if (!is_array($result)) {
            return array('accepted' => false, 'retryable' => true, 'code' => 'notification_unavailable');
        }
        return array(
            'accepted' => !empty($result['accepted']),
            'suppressed' => !empty($result['suppressed']),
            'retryable' => !empty($result['retryable']),
            'reference' => isset($result['reference']) ? (string) $result['reference'] : '',
            'code' => isset($result['code']) ? (string) $result['code'] : 'unknown',
        );
    }



    public static function communication_context(string $reference, int $actor_id): array {
        $result = null;
        if (function_exists('sn_cf01_communication_context_assertion')) {
            $result = sn_cf01_communication_context_assertion($reference, $actor_id);
        }
        $result = apply_filters('cf01_communication_context_assertion', $result, $reference, $actor_id);
        $validated = self::validate($result, self::COMMUNICATION_VERSION, array('reference_uuid', 'conversation_reference', 'purpose', 'expires_at', 'revoked'));
        if (!empty($validated['valid']) && (!empty($validated['revoked']) || !CF01_Authorization::not_expired((string) $validated['expires_at']))) {
            return array('valid' => false, 'code' => 'communication_reference_inactive');
        }
        return $validated;
    }

    public static function shell_routes(): array {
        $manifest = array(
            'contract_version' => self::SHELL_VERSION,
            'owner' => 'CF-01',
            'routes' => array('/clinic/records', '/clinic/patients/{clinical_id}', '/my-health-record', '/clinic/encounters/{id}', '/clinic/prescriptions/{id}', '/clinic/follow-ups/{id}', '/admin/clinical-governance'),
            'private' => true,
            'no_store' => true,
            'noindex' => true,
            'safe_mode' => true,
        );
        $result = apply_filters('cf01_shell_route_registration', null, $manifest);
        return self::validate($result, self::SHELL_VERSION, array('registered', 'private', 'no_store'));
    }

    public static function visual_components(): array {
        $manifest = array(
            'contract_version' => self::VISUAL_VERSION,
            'components' => array('clinical-shell', 'clinical-summary', 'encounter-editor', 'prescription-card', 'followup-form', 'rights-case', 'restricted-state'),
            'rtl' => true,
            'keyboard' => true,
            'reduced_motion' => true,
            'no_clinical_telemetry' => true,
        );
        $result = apply_filters('cf01_visual_component_registration', null, $manifest);
        return self::validate($result, self::VISUAL_VERSION, array('accepted', 'rtl', 'accessibility'));
    }

    public static function secure_media(string $operation, array $request): array {
        $request = array_merge($request, array('contract_version' => self::MEDIA_VERSION, 'domain' => 'CF-01', 'classification' => 'C5'));
        $result = apply_filters('cf01_secure_media_operation', null, $operation, $request);
        return self::validate($result, self::MEDIA_VERSION, array('accepted', 'asset_reference', 'privacy_class'));
    }

    public static function register_assurance(): array {
        $manifest = self::assurance_manifest();
        $manifest['contract_version'] = self::ASSURANCE_VERSION;
        $result = apply_filters('cf01_assurance_manifest_registration', null, $manifest);
        return self::validate($result, self::ASSURANCE_VERSION, array('registered', 'native_enforcement_preserved'));
    }



    public static function prescription_safety(string $patient_uuid, int $actor_id, array $order): array {
        $request = array(
            'contract_version' => self::SAFETY_VERSION,
            'patient_uuid' => $patient_uuid,
            'actor_user_id' => $actor_id,
            'order_hash' => hash('sha256', self::safe_order_fingerprint($order)),
            'purpose' => 'prescription_safety_review',
        );
        $result = apply_filters('cf01_prescription_safety_review', null, $request, $order);
        return self::validate($result, self::SAFETY_VERSION, array('passed', 'blocking', 'warnings', 'evidence_reference'));
    }

    private static function safe_order_fingerprint(array $order): string {
        $minimal = array_intersect_key($order, array_flip(array('remedy', 'potency', 'form', 'dose', 'frequency', 'duration', 'repetition')));
        return CF01_Crypto::canonical_json($minimal);
    }



    public static function relationship_source(string $reference, int $actor_id, string $patient_uuid, int $doctor_user_id, string $purpose, array $scope): array {
        $reference = sanitize_text_field($reference);
        $result = apply_filters('cf01_relationship_source_assertion', null, $reference, $actor_id, $patient_uuid, $doctor_user_id, sanitize_key($purpose), $scope);
        if ($reference === '' && is_array($result)) {
            $reference = sanitize_text_field((string) ($result['source_reference'] ?? ''));
        }
        if (!is_array($result) && $reference !== '') {
            $care = self::care_context($reference, $actor_id);
            $patient = CF01_Patients::get($patient_uuid);
            $patient_platform_uuid = CF01_Crypto::decrypt((string) ($patient['platform_subject_cipher'] ?? ''), 'patient-platform-link');
            $doctor_membership = self::membership($doctor_user_id);
            if (!empty($care['valid'])
                && is_string($patient_platform_uuid)
                && hash_equals($patient_platform_uuid, (string) ($care['patient_platform_uuid'] ?? ''))
                && hash_equals((string) ($doctor_membership['platform_uuid'] ?? ''), (string) ($care['practitioner_platform_uuid'] ?? ''))
            ) {
                $result = array(
                    'contract_version' => '1.0.0', 'accepted' => true, 'revoked' => false,
                    'source_reference' => $reference, 'patient_uuid' => $patient_uuid,
                    'doctor_user_id' => $doctor_user_id, 'purpose' => sanitize_key($purpose),
                    'scope' => $scope, 'expires_at' => gmdate('Y-m-d H:i:s', time() + HOUR_IN_SECONDS),
                );
            }
        }
        $validated = self::validate($result, '1.0.0', array(
            'accepted', 'revoked', 'source_reference', 'patient_uuid', 'doctor_user_id', 'purpose', 'scope', 'expires_at'
        ));
        $asserted_scope = array_values(array_unique(array_map('sanitize_key', (array) ($validated['scope'] ?? array()))));
        $requested_scope = array_values(array_unique(array_map('sanitize_key', $scope)));
        if (empty($validated['valid'])
            || empty($validated['accepted'])
            || !empty($validated['revoked'])
            || !hash_equals($reference, (string) ($validated['source_reference'] ?? ''))
            || !hash_equals($patient_uuid, (string) ($validated['patient_uuid'] ?? ''))
            || (int) ($validated['doctor_user_id'] ?? 0) !== $doctor_user_id
            || !hash_equals(sanitize_key($purpose), sanitize_key((string) ($validated['purpose'] ?? '')))
            || !$requested_scope
            || array_diff($requested_scope, $asserted_scope)
            || empty($validated['expires_at'])
            || !CF01_Authorization::not_expired((string) $validated['expires_at'])
        ) {
            return array('valid' => false, 'code' => 'relationship_source_unaccepted');
        }
        return $validated;
    }

    public static function subject_identity(string $platform_uuid, int $actor_id, string $purpose): array {
        $platform_uuid = trim($platform_uuid);
        if ($platform_uuid === '') {
            return array('valid' => false, 'code' => 'subject_identity_missing');
        }
        $result = apply_filters('cf01_subject_identity_assertion', null, $platform_uuid, $actor_id, sanitize_key($purpose));
        $validated = self::validate($result, '1.0.0', array(
            'subject_platform_uuid', 'active', 'revoked', 'expires_at', 'evidence_reference'
        ));
        if (empty($validated['valid'])
            || empty($validated['active'])
            || !empty($validated['revoked'])
            || empty($validated['expires_at'])
            || !CF01_Authorization::not_expired((string) $validated['expires_at'])
            || !hash_equals($platform_uuid, (string) ($validated['subject_platform_uuid'] ?? ''))
        ) {
            return array('valid' => false, 'code' => 'subject_identity_inactive');
        }
        return $validated;
    }

    public static function guardian_authority(int $actor_id, string $patient_uuid, string $purpose, string $reference = ''): array {
        $membership = self::membership($actor_id);
        if (empty($membership['valid']) || empty($membership['approved']) || !empty($membership['suspended'])) {
            return array('valid' => false, 'code' => 'guardian_actor_ineligible');
        }
        $result = apply_filters('cf01_guardian_authority_assertion', null, $actor_id, $patient_uuid, sanitize_key($purpose), array('reference' => $reference));
        $validated = self::validate($result, '1.0.0', array(
            'accepted', 'revoked', 'suspended', 'actor_user_id', 'actor_platform_uuid',
            'patient_uuid', 'guardian_reference', 'scopes', 'authority_version', 'expires_at'
        ));
        $scopes = array_values(array_unique(array_map('sanitize_key', (array) ($validated['scopes'] ?? array()))));
        if (empty($validated['valid'])
            || empty($validated['accepted'])
            || !empty($validated['revoked'])
            || !empty($validated['suspended'])
            || (int) ($validated['actor_user_id'] ?? 0) !== $actor_id
            || !hash_equals((string) ($membership['platform_uuid'] ?? ''), (string) ($validated['actor_platform_uuid'] ?? ''))
            || !hash_equals($patient_uuid, (string) ($validated['patient_uuid'] ?? ''))
            || ($reference !== '' && !hash_equals($reference, (string) ($validated['guardian_reference'] ?? '')))
            || !$scopes
            || !in_array(sanitize_key($purpose), $scopes, true)
            || (int) ($validated['authority_version'] ?? 0) < 1
            || empty($validated['expires_at'])
            || !CF01_Authorization::not_expired((string) $validated['expires_at'])
        ) {
            return array('valid' => false, 'code' => 'guardian_authority_inactive');
        }
        return $validated;
    }

    public static function clinical_template(string $key, string $version, string $context): array {
        $key = sanitize_key($key);
        $version = sanitize_text_field($version);
        $result = apply_filters('cf01_clinical_template_assertion', null, $key, $version, sanitize_key($context));
        if (!is_array($result) && in_array($key, array('general', 'addendum'), true) && $version === '1.0.0') {
            $result = array(
                'contract_version' => '1.0.0', 'accepted' => true, 'revoked' => false,
                'template_key' => $key, 'template_version' => $version,
                'context' => sanitize_key($context), 'effective_at' => '2026-08-01 00:00:00',
                'historical_rendering_supported' => true,
            );
        }
        $validated = self::validate($result, '1.0.0', array(
            'accepted', 'revoked', 'template_key', 'template_version', 'context', 'effective_at', 'historical_rendering_supported'
        ));
        if (empty($validated['valid'])
            || empty($validated['accepted'])
            || !empty($validated['revoked'])
            || !hash_equals($key, sanitize_key((string) ($validated['template_key'] ?? '')))
            || !hash_equals($version, (string) ($validated['template_version'] ?? ''))
            || !hash_equals(sanitize_key($context), sanitize_key((string) ($validated['context'] ?? '')))
            || empty($validated['historical_rendering_supported'])
        ) {
            return array('valid' => false, 'code' => 'clinical_template_unaccepted');
        }
        return $validated;
    }

    public static function terminology_mapping(array $mapping): array {
        if (!$mapping) {
            return array('valid' => true, 'accepted' => true, 'mappings' => array());
        }
        $result = apply_filters('cf01_terminology_mapping_assertion', null, $mapping);
        $validated = self::validate($result, '1.0.0', array(
            'accepted', 'profile_version', 'terminology_version', 'mappings', 'round_trip_preserved'
        ));
        if (empty($validated['valid']) || empty($validated['accepted']) || empty($validated['round_trip_preserved'])) {
            return array('valid' => false, 'code' => 'terminology_mapping_unaccepted');
        }
        foreach ((array) $validated['mappings'] as $item) {
            if (!is_array($item) || empty($item['local_id']) || empty($item['label']) || empty($item['code'])) {
                return array('valid' => false, 'code' => 'terminology_mapping_incomplete');
            }
        }
        return $validated;
    }

    public static function emergency_policy(string $patient_uuid, int $actor_id, array $red_flags, string $source): array {
        if (!$red_flags) {
            return array('valid' => true, 'accepted' => true, 'guidance' => '', 'alert_reference' => '');
        }
        $request = array(
            'contract_version' => '1.0.0',
            'patient_uuid' => $patient_uuid,
            'actor_user_id' => $actor_id,
            'source' => sanitize_key($source),
            'red_flag_codes' => array_values(array_unique(array_map('sanitize_key', $red_flags))),
            'autonomous_diagnosis' => false,
        );
        $result = apply_filters('cf01_emergency_policy_request', null, $request);
        $validated = self::validate($result, '1.0.0', array('accepted', 'guidance', 'alerted', 'alert_reference', 'local_emergency_direction'));
        if (empty($validated['valid']) || empty($validated['accepted']) || empty($validated['guidance']) || empty($validated['alerted']) || empty($validated['local_emergency_direction'])) {
            return array('valid' => false, 'code' => 'emergency_policy_unavailable');
        }
        return $validated;
    }

    public static function local_timestamp(string $utc, string $time_zone): array {
        try {
            $zone = new DateTimeZone($time_zone !== '' ? $time_zone : 'UTC');
            $value = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
        } catch (Throwable $error) {
            throw new InvalidArgumentException('A valid clinical time zone is required.');
        }
        return array(
            'utc' => $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'local' => $value->setTimezone($zone)->format(DateTimeInterface::ATOM),
            'time_zone' => $zone->getName(),
        );
    }

    public static function dependency_report(): array {
        return array(
            'membership' => function_exists('smc_membership_assertions'),
            'recent_auth' => function_exists('sauth_cf01_recent_authentication_assertion'),
            'practitioner' => function_exists('sgd_cf01_professional_eligibility_assertion'),
            'care_context' => function_exists('swc_cf01_care_context_assertion'),
            'relationship_source' => has_filter('cf01_relationship_source_assertion'),
            'communication_context' => function_exists('sn_cf01_communication_context_assertion'),
            'notifications' => function_exists('sun_cf01_request_notification'),
            'shell' => has_filter('cf01_shell_route_registration'),
            'visual' => has_filter('cf01_visual_component_registration'),
            'assurance' => has_filter('cf01_assurance_manifest_registration'),
            'secure_media' => has_filter('cf01_secure_media_operation'),
            'prescription_safety' => has_filter('cf01_prescription_safety_review'),
            'subject_identity' => has_filter('cf01_subject_identity_assertion'),
            'guardian_authority' => has_filter('cf01_guardian_authority_assertion'),
            'clinical_template' => has_filter('cf01_clinical_template_assertion'),
            'terminology_mapping' => has_filter('cf01_terminology_mapping_assertion'),
            'emergency_policy' => has_filter('cf01_emergency_policy_request'),
        );
    }

    public static function assurance_manifest(): array {
        return array(
            'contract' => 'cf01.assurance-manifest',
            'version' => CF01_CONTRACT_VERSION,
            'native_enforcement_preserved' => true,
            'classification' => 'C5',
            'activation_state' => CF01_DB::activation_state(),
            'controls' => array(
                'field_authorization', 'encrypted_clinical_fields', 'immutable_signed_records',
                'purpose_consent', 'current_guardian_authority', 'object_scoped_relationships',
                'break_glass_ttl', 'durable_audit', 'outbox', 'retention_holds', 'emergency_escalation',
                'template_and_terminology_provenance'
            ),
        );
    }

    private static function validate($result, string $expected_version, array $required): array {
        if (!is_array($result)) {
            return array('valid' => false, 'code' => 'contract_unavailable');
        }
        if (($result['contract_version'] ?? null) !== $expected_version) {
            return array('valid' => false, 'code' => 'contract_version_mismatch');
        }
        foreach ($required as $field) {
            if (!array_key_exists($field, $result)) {
                return array('valid' => false, 'code' => 'contract_field_missing', 'field' => $field);
            }
        }
        $result['valid'] = true;
        return $result;
    }
}
