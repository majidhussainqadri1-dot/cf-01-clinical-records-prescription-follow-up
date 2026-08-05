<?php
defined('ABSPATH') || exit;

/**
 * Resolves the current actor's minimum-necessary clinical role for one patient.
 * Role labels never grant access by themselves: current membership, capability,
 * guardian/care-team contract, treating relationship and purpose are revalidated.
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
            CF01_Authorization::actor($actor_id, 'view_own_clinical_record', array('purpose' => $purpose));
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
        if (!empty($guardian['expires_at']) && !CF01_Authorization::not_expired((string) $guardian['expires_at'])) {
            throw new RuntimeException('Verified guardian authority has expired.');
        }
        $scope = array_values(array_unique(array_map('sanitize_key', (array) ($guardian['scope'] ?? array()))));
        if (!$scope || (!in_array($purpose, $scope, true) && !in_array('clinical_care', $scope, true))) {
            throw new RuntimeException('Verified guardian scope does not authorize this clinical purpose.');
        }
        CF01_Authorization::actor($actor_id, 'view_own_clinical_record', array('purpose' => $purpose));
        return array(
            'role' => 'guardian',
            'authority' => 'verified_guardian',
            'guardian_reference' => sanitize_text_field((string) ($guardian['reference'] ?? '')),
        );
    }

    private static function staff(int $actor_id, string $patient_uuid, string $purpose, string $requested_role): ?array {
        if (self::can($actor_id, 'cf01_audit_clinical') && ($requested_role === '' || $requested_role === 'auditor')) {
            CF01_Authorization::actor($actor_id, 'view_clinical_audit', array('purpose' => $purpose));
            return array('role' => 'auditor', 'authority' => 'clinical_audit_capability');
        }
        if ((self::can($actor_id, 'cf01_manage_clinical_rights') || self::can($actor_id, 'cf01_manage_retention')) && ($requested_role === '' || $requested_role === 'records')) {
            CF01_Authorization::actor($actor_id, 'view_access_history', array('purpose' => $purpose));
            return array('role' => 'records', 'authority' => 'records_privacy_capability');
        }

        foreach (self::CARE_TEAM_ROLES as $role) {
            if ($requested_role !== '' && $requested_role !== $role) {
                continue;
            }
            $capability = $role === 'assistant' ? 'cf01_assist_clinical' : 'cf01_supervise_clinical';
            if (!self::can($actor_id, $capability)) {
                continue;
            }
            CF01_Authorization::actor($actor_id, 'view_own_clinical_record', array('purpose' => $purpose));
            $assertion = apply_filters('cf01_care_team_assertion', null, $actor_id, $patient_uuid, $purpose, $role);
            self::validate_care_team_assertion($assertion, $actor_id, $patient_uuid, $purpose, $role);
            return array(
                'role' => $role,
                'authority' => 'accepted_care_team_contract',
                'contract_version' => sanitize_text_field((string) $assertion['contract_version']),
                'assignment_reference' => sanitize_text_field((string) $assertion['assignment_reference']),
            );
        }

        try {
            $professional = CF01_Authorization::clinician($actor_id, 'view_clinical_record', array('purpose' => $purpose));
            $relationship = CF01_Authorization::relationship($patient_uuid, $actor_id, $purpose);
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

    private static function validate_care_team_assertion($assertion, int $actor_id, string $patient_uuid, string $purpose, string $role): void {
        if (!is_array($assertion)
            || empty($assertion['valid'])
            || empty($assertion['accepted'])
            || empty($assertion['contract_version'])
            || empty($assertion['assignment_reference'])
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
