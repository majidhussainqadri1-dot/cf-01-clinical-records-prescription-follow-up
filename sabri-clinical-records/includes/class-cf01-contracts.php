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

    public static function dependency_report(): array {
        return array(
            'membership' => function_exists('smc_membership_assertions'),
            'recent_auth' => function_exists('sauth_cf01_recent_authentication_assertion'),
            'practitioner' => function_exists('sgd_cf01_professional_eligibility_assertion'),
            'care_context' => function_exists('swc_cf01_care_context_assertion'),
            'communication_context' => function_exists('sn_cf01_communication_context_assertion'),
            'notifications' => function_exists('sun_cf01_request_notification'),
            'shell' => has_filter('cf01_shell_route_registration'),
            'visual' => has_filter('cf01_visual_component_registration'),
            'assurance' => has_filter('cf01_assurance_manifest_registration'),
            'secure_media' => has_filter('cf01_secure_media_operation'),
            'prescription_safety' => has_filter('cf01_prescription_safety_review'),
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
                'purpose_consent', 'break_glass_ttl', 'durable_audit', 'outbox', 'retention_holds'
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
