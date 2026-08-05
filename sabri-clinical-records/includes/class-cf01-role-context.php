<?php
defined('ABSPATH') || exit;

/**
 * Resolves one minimum-necessary clinical role for one patient and purpose.
 * Labels/capabilities never grant patient access without a current owner assertion.
 */
final class CF01_Role_Context {
    private const CARE_TEAM_ROLES = array('assistant', 'supervisor');

    public static function resolve(int $actor_id, string $patient_uuid, string $purpose = 'clinical_care', string $requested_role = ''): array {
        CF01_Patients::get($patient_uuid);
        $purpose = sanitize_key($purpose);
        if ($purpose === '') {
            throw new InvalidArgumentException('A clinical access purpose is required.');
        }

        $requested_role = sanitize_key($requested_role);
        $context = self::patient_or_guardian($actor_id, $patient_uuid, $purpose);
        if ($context === null) {
            $context = self::staff($actor_id, $patient_uuid, $purpose, $requested_role);
        }
        if ($context === null) {
            throw new RuntimeException('No current minimum-necessary clinical role authorizes this patient context.');
        }
        if ($requested_role !== '' && !hash_equals($requested_role, (string) $context['role'])) {
            throw new RuntimeException('Requested clinical role does not match current authority.');
        }
        $context['actor_user_id'] = $actor_id;
        $context['patient_uuid'] = $patient_uuid;
        $context['purpose'] = $purpose;
        $context['resolved_at'] = CF01_DB::now();
        return $context;
    }

    public static function fields(array $context, array $requested): array {
        return CF01_Authorization::fields(
            (string) $context['role'],
            (string) $context['purpose'],
            $requested,
            array('patient_uuid' => (string) $context['patient_uuid'])
        );
    }

    private static function patient_or_guardian(int $actor_id, string $patient_uuid, string $purpose): ?array {
        if (CF01_Authorization::patient_owner($actor_id, $patient_uuid)) {
            CF01_Authorization::actor($actor_id, 'view_own_clinical_record', array('purpose' => $purpose, 'patient_uuid' => $patient_uuid));
            return array('role' => 'patient', 'authority' => 'patient_owner');
        }

        $patient = CF01_Patients::get($patient_uuid);
        $guardian = CF01_Patients::guardian_context($patient);
        if (($guardian['status'] ?? '') !== 'verified') {
            return null;
        }
        $membership = CF01_Contracts::membership($actor_id);
        if (empty($membership['valid']) || empty($membership['approved']) || !empty($membership['suspended'])) {
            return null;
        }
        $guardian_subject = (string) ($guardian['platform_uuid'] ?? '');
        $actor_subject = (string) ($membership['platform_uuid'] ?? '');
        if ($guardian_subject === '' || $actor_subject === '' || !hash_equals($guardian_subject, $actor_subject)) {
            return null;
        }
        if (empty($guardian['reference'])) {
            throw new RuntimeException('Verified guardian authority reference is required.');
        }
        if (!empty($guardian['expires_at']) && !CF01_Authorization::not_expired((string) $guardian['expires_at'])) {
            throw new RuntimeException('Verified guardian authority has expired.');
        }
        $scope = array_values(array_unique(array_map('sanitize_key', (array) ($guardian['scope'] ?? array()))));
        if (!$scope || (!in_array($purpose, $scope, true) && !in_array('clinical_care', $scope, true))) {
            throw new RuntimeException('Verified guardian scope does not authorize this clinical purpose.');
        }

        $assertion = apply_filters('cf01_guardian_authority_assertion', null, $actor_id, $patient_uuid, $purpose, $guardian);
        self::validate_guardian_assertion($assertion, $actor_id, $actor_subject, $patient_uuid, $purpose, $guardian);
        CF01_Authorization::actor($actor_id, 'view_own_clinical_record', array('purpose' => $purpose, 'patient_uuid' => $patient_uuid));
        return array(
            'role' => 'guardian',
            'authority' => 'current_verified_guardian_contract',
            'guardian_reference' => sanitize_text_field((string) $guardian['reference']),
            'contract_version' => sanitize_text_field((string) $assertion['contract_version']),
            'authority_version' => (int) $assertion['authority_version'],
        );
    }

    private static function staff(int $actor_id, string $patient_uuid, string $purpose, string $requested_role): ?array {
        if (self::can($actor_id, 'cf01_audit_clinical') && ($requested_role === '' || $requested_role === 'auditor')) {
            CF01_Authorization::actor($actor_id, 'view_clinical_audit', array('purpose' => $purpose, 'patient_uuid' => $patient_uuid));
            return self::oversight_context($actor_id, $patient_uuid, $purpose, 'auditor');
        }
        if ((self::can($actor_id, 'cf01_manage_clinical_rights') || self::can($actor_id, 'cf01_manage_retention')) && ($requested_role === '' || $requested_role === 'records')) {
            CF01_Authorization::actor($actor_id, 'view_access_history', array('purpose' => $purpose, 'patient_uuid' => $patient_uuid));
            return self::oversight_context($actor_id, $patient_uuid, $purpose, 'records');
        }

        foreach (self::CARE_TEAM_ROLES as $role) {
            if ($requested_role !== '' && $requested_role !== $role) {
                continue;
            }
            $capability = $role === 'assistant' ? 'cf01_assist_clinical' : 'cf01_supervise_clinical';
            if (!self::can($actor_id, $capability)) {
                continue;
            }
            CF01_Authorization::actor($actor_id, 'view_own_clinical_record', array('purpose' => $purpose, 'patient_uuid' => $patient_uuid));
            $assertion = apply_filters('cf01_care_team_assertion', null, $actor_id, $patient_uuid, $purpose, $role);
            self::validate_care_team_assertion($assertion, $actor_id, $patient_uuid, $purpose, $role);
            return array(
                'role' => $role,
                'authority' => 'accepted_care_team_contract',
                'contract_version' => sanitize_text_field((string) $assertion['contract_version']),
                'assignment_reference' => sanitize_text_field((string) $assertion['assignment_reference']),
                'assignment_version' => (int) $assertion['assignment_version'],
            );
        }

        try {
            $professional = CF01_Authorization::clinician($actor_id, 'view_clinical_record', array('purpose' => $purpose, 'patient_uuid' => $patient_uuid));
            $relationship = CF01_Authorization::relationship($patient_uuid, $actor_id, $purpose);
            CF01_Authorization::consent($patient_uuid, $purpose);
            return array(
                'role' => 'doctor',
                'authority' => 'active_treating_relationship',
                'professional_uuid' => sanitize_text_field((string) $professional['professional']['professional_uuid']),
                'relationship_uuid' => sanitize_text_field((string) $relationship['relationship_uuid']),
                'relationship_version' => (int) $relationship['row_version'],
            );
        } catch (Throwable $error) {
            return null;
        }
    }

    private static function oversight_context(int $actor_id, string $patient_uuid, string $purpose, string $role): array {
        $assertion = apply_filters('cf01_clinical_oversight_assertion', null, $actor_id, $patient_uuid, $purpose, $role);
        if (!is_array($assertion)
            || empty($assertion['valid'])
            || empty($assertion['accepted'])
            || !empty($assertion['revoked'])
            || !empty($assertion['suspended'])
            || empty($assertion['contract_version'])
            || empty($assertion['assignment_reference'])
            || (int) ($assertion['assignment_version'] ?? 0) < 1
            || (int) ($assertion['actor_user_id'] ?? 0) !== $actor_id
            || !hash_equals($patient_uuid, (string) ($assertion['patient_uuid'] ?? ''))
            || !hash_equals($purpose, sanitize_key((string) ($assertion['purpose'] ?? '')))
            || !hash_equals($role, sanitize_key((string) ($assertion['role'] ?? '')))
            || empty($assertion['expires_at'])
            || !CF01_Authorization::not_expired((string) $assertion['expires_at'])
        ) {
            throw new RuntimeException('A current patient-scoped oversight assignment is required.');
        }
        return array(
            'role' => $role,
            'authority' => 'patient_scoped_oversight_contract',
            'contract_version' => sanitize_text_field((string) $assertion['contract_version']),
            'assignment_reference' => sanitize_text_field((string) $assertion['assignment_reference']),
            'assignment_version' => (int) $assertion['assignment_version'],
        );
    }

    private static function validate_guardian_assertion($assertion, int $actor_id, string $actor_subject, string $patient_uuid, string $purpose, array $guardian): void {
        $scopes = is_array($assertion) ? array_values(array_unique(array_map('sanitize_key', (array) ($assertion['scopes'] ?? array())))) : array();
        if (!is_array($assertion)
            || empty($assertion['valid'])
            || empty($assertion['accepted'])
            || !empty($assertion['revoked'])
            || !empty($assertion['suspended'])
            || empty($assertion['contract_version'])
            || (int) ($assertion['authority_version'] ?? 0) < 1
            || (int) ($assertion['actor_user_id'] ?? 0) !== $actor_id
            || !hash_equals($actor_subject, (string) ($assertion['actor_platform_uuid'] ?? ''))
            || !hash_equals($patient_uuid, (string) ($assertion['patient_uuid'] ?? ''))
            || !hash_equals((string) $guardian['reference'], (string) ($assertion['guardian_reference'] ?? ''))
            || (!$scopes || (!in_array($purpose, $scopes, true) && !in_array('clinical_care', $scopes, true)))
            || empty($assertion['expires_at'])
            || !CF01_Authorization::not_expired((string) $assertion['expires_at'])
        ) {
            throw new RuntimeException('A current File 00 guardian-authority assertion is required.');
        }
    }

    private static function validate_care_team_assertion($assertion, int $actor_id, string $patient_uuid, string $purpose, string $role): void {
        if (!is_array($assertion)
            || empty($assertion['valid'])
            || empty($assertion['accepted'])
            || !empty($assertion['revoked'])
            || !empty($assertion['suspended'])
            || empty($assertion['contract_version'])
            || empty($assertion['assignment_reference'])
            || (int) ($assertion['assignment_version'] ?? 0) < 1
            || (int) ($assertion['actor_user_id'] ?? 0) !== $actor_id
            || !hash_equals($patient_uuid, (string) ($assertion['patient_uuid'] ?? ''))
            || !hash_equals($purpose, sanitize_key((string) ($assertion['purpose'] ?? '')))
            || !hash_equals($role, sanitize_key((string) ($assertion['role'] ?? '')))
        ) {
            throw new RuntimeException('Accepted current care-team assignment contract is required.');
        }
        if (empty($assertion['expires_at']) || !CF01_Authorization::not_expired((string) $assertion['expires_at'])) {
            throw new RuntimeException('Care-team assignment has expired or has no bounded expiry.');
        }
    }

    private static function can(int $user_id, string $capability): bool {
        return function_exists('user_can') ? user_can($user_id, $capability) : current_user_can($capability);
    }
}
