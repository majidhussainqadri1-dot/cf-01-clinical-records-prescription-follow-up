<?php
require __DIR__ . '/bootstrap.php';
require_once CF01_DIR . 'includes/class-cf01-role-context.php';
require_once CF01_DIR . 'includes/class-cf01-activation-evidence.php';
require_once CF01_DIR . 'includes/class-cf01-lifecycle-rest.php';

if (!function_exists('add_option')) {
    function add_option($key, $value, $deprecated = '', $autoload = 'yes'): bool {
        if (array_key_exists($key, $GLOBALS['cf01_options'])) {
            return false;
        }
        $GLOBALS['cf01_options'][$key] = $value;
        return true;
    }
}
if (!function_exists('delete_option')) {
    function delete_option($key): bool {
        $existed = array_key_exists($key, $GLOBALS['cf01_options']);
        unset($GLOBALS['cf01_options'][$key]);
        return $existed;
    }
}

$count = 0;
$failures = array();
$check = function (bool $condition, string $message) use (&$count, &$failures): void {
    $count++;
    if (!$condition) {
        $failures[] = $message;
    }
};
$expect = function (callable $callback, string $message, string $contains = '') use (&$count, &$failures): void {
    $count++;
    try {
        $callback();
        $failures[] = $message;
    } catch (Throwable $error) {
        if ($contains !== '' && !str_contains($error->getMessage(), $contains)) {
            $failures[] = $message . ' — unexpected: ' . $error->getMessage();
        }
    }
};

$activationEvidence = static function (): array {
    $head = str_repeat('a', 40);
    $environment = 'staging';
    $issued = time() - 60;
    $approved = time() - 120;
    $gates = array(
        'founder_approval',
        'legal_professional_acceptance',
        'independent_security_acceptance',
        'staging_acceptance',
        'backup_restore_acceptance',
        'rollback_rehearsal',
        'operations_staffing',
    );
    $evidence = array(
        'activation_id' => '123e4567-e89b-42d3-a456-426614174000',
        'activation_nonce' => str_repeat('N', 40),
        'environment' => $environment,
        'issued_at' => gmdate('c', $issued),
        'expires_at' => gmdate('c', $issued + 3600),
        'site_fingerprint' => hash('sha256', strtolower(rtrim(home_url('/'), '/')) . '|' . $environment),
        'release' => array(
            'head_sha' => $head,
            'package_sha256' => str_repeat('b', 64),
            'package_name' => 'cf-01-clinical-records-1.0.0.zip',
            'runtime_version' => CF01_VERSION,
            'schema_version' => CF01_SCHEMA_VERSION,
            'contract_version' => CF01_CONTRACT_VERSION,
            'built_at' => gmdate('c', time() - 300),
        ),
        'jurisdictions' => array('PK'),
        'provider_region' => array('provider' => 'approved-provider', 'region' => 'pk-1', 'data_residency_decision' => 'accepted'),
        'recovery_objectives' => array('rpo_minutes' => 15, 'rto_minutes' => 60, 'approved_by' => 'founder'),
    );
    foreach ($gates as $index => $gate) {
        $evidence[$gate] = array(
            'evidence_id' => 'evidence-' . ($index + 1),
            'document_hash' => hash('sha256', $gate),
            'approved_by' => 'approver-' . ($index + 1),
            'approved_at' => gmdate('c', $approved),
            'scope' => 'CF-01 exact release',
            'status' => 'accepted',
            'tested_head' => $head,
            'environment' => $environment,
            'expires_at' => gmdate('c', time() + 7200),
        );
    }
    $evidence['founder_approval']['founder_authority'] = true;
    $evidence['independent_security_acceptance']['independent_reviewer'] = 'independent-reviewer';
    $evidence['operations_staffing']['assigned_roles'] = array(
        'treating_doctor', 'clinical_supervisor', 'privacy_officer', 'records_custodian', 'incident_escalation', 'patient_support',
    );
    return $evidence;
};

// Activation evidence: exact head, environment/site binding, bounded validity and replay resistance.
cf01_reset();
$evidence = $activationEvidence();
$validation = CF01_Activation_Evidence::validate($evidence);
$check(!empty($validation['valid']) && preg_match('/^[a-f0-9]{64}$/', (string) $validation['fingerprint']) === 1, 'Valid activation dossier must produce a stable fingerprint.');
$tampered = $evidence;
$tampered['staging_acceptance']['tested_head'] = str_repeat('c', 40);
$expect(fn() => CF01_Activation_Evidence::validate($tampered), 'Mismatched gate head must fail.', 'exact release head');
$tampered = $evidence;
$tampered['site_fingerprint'] = str_repeat('d', 64);
$expect(fn() => CF01_Activation_Evidence::validate($tampered), 'Cross-site activation dossier must fail.', 'exact site');
$GLOBALS['cf01_options']['cf01_activation_state'] = 'disabled';
$GLOBALS['cf01_options']['cf01_activation_evidence'] = CF01_Crypto::encrypt($evidence, 'activation-evidence');
$check(CF01_Activation_Evidence::guard_state('enabled', 'disabled', 'cf01_activation_state') === 'enabled', 'First exact activation transition must pass.');
CF01_Activation_Evidence::after_option_update('cf01_activation_state', 'disabled', 'enabled');
$GLOBALS['cf01_options']['cf01_activation_state'] = 'disabled';
$expect(fn() => CF01_Activation_Evidence::guard_state('enabled', 'disabled', 'cf01_activation_state'), 'Activation dossier replay must fail.', 'replay');
$GLOBALS['cf01_options']['cf01_activation_state'] = 'enabled';
$expect(fn() => CF01_Activation_Evidence::guard_evidence('new', 'old', 'cf01_activation_evidence'), 'Enabled runtime evidence replacement must fail.', 'immutable');

// Guardian authority: stored snapshot alone is insufficient; current File 00 assertion is mandatory.
cf01_reset();
$patient = CF01_Patients::create(1, 'platform-user-9', array('date_of_birth' => '2012-01-01'), 'PK');
$patientUuid = (string) $patient['clinical_uuid'];
CF01_Patients::update_guardian_context(1, $patientUuid, array(
    'status' => 'verified',
    'platform_uuid' => 'platform-user-1',
    'reference' => 'guardian-ref-1',
    'scope' => array('clinical_care'),
    'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
), 1);
add_filter('cf01_guardian_authority_assertion', static function ($value, int $actorId, string $patient, string $purpose, array $snapshot) use ($patientUuid): array {
    return array(
        'valid' => true,
        'accepted' => true,
        'revoked' => false,
        'suspended' => false,
        'contract_version' => '1.1.2',
        'authority_version' => 3,
        'actor_user_id' => $actorId,
        'actor_platform_uuid' => 'platform-user-' . $actorId,
        'patient_uuid' => $patientUuid,
        'guardian_reference' => 'guardian-ref-1',
        'scopes' => array('clinical_care'),
        'expires_at' => gmdate('Y-m-d H:i:s', time() + 600),
    );
}, 10, 5);
$guardianContext = CF01_Role_Context::resolve(1, $patientUuid, 'clinical_care', 'guardian');
$check(($guardianContext['role'] ?? '') === 'guardian' && (int) ($guardianContext['authority_version'] ?? 0) === 3, 'Current guardian assertion must resolve the guardian role.');
$GLOBALS['cf01_current_user'] = 2;
$expect(fn() => CF01_Role_Context::resolve(2, $patientUuid, 'clinical_care', 'guardian'), 'Guardian impersonation must fail.', 'minimum-necessary');

// Records/auditor capability must be bound to a current patient-specific assignment.
cf01_reset();
$patientA = CF01_Patients::create(1, 'platform-user-11', array('date_of_birth' => '1990-01-01'), 'PK');
$patientB = CF01_Patients::create(1, 'platform-user-12', array('date_of_birth' => '1991-01-01'), 'PK');
$patientAUuid = (string) $patientA['clinical_uuid'];
$patientBUuid = (string) $patientB['clinical_uuid'];
$GLOBALS['cf01_current_user'] = 3;
$GLOBALS['cf01_caps'] = array('cf01_audit_clinical' => true);
add_filter('cf01_clinical_oversight_assertion', static function ($value, int $actorId, string $patient, string $purpose, string $role) use ($patientAUuid): array {
    return array(
        'valid' => true,
        'accepted' => true,
        'revoked' => false,
        'suspended' => false,
        'contract_version' => '1.0.0',
        'assignment_reference' => 'audit-assignment-1',
        'assignment_version' => 1,
        'actor_user_id' => $actorId,
        'patient_uuid' => $patientAUuid,
        'purpose' => $purpose,
        'role' => $role,
        'expires_at' => gmdate('Y-m-d H:i:s', time() + 600),
    );
}, 10, 5);
$auditor = CF01_Role_Context::resolve(3, $patientAUuid, 'privacy_transparency', 'auditor');
$check(($auditor['authority'] ?? '') === 'patient_scoped_oversight_contract', 'Patient-scoped auditor assignment must pass.');
$expect(fn() => CF01_Role_Context::resolve(3, $patientBUuid, 'privacy_transparency', 'auditor'), 'Cross-patient auditor scope escalation must fail.', 'patient-scoped');

// Break-glass: one active grant, professional continuity, no active/self final review.
cf01_reset();
$GLOBALS['cf01_current_user'] = 2;
$patient = CF01_Patients::create(2, 'platform-user-1', array('date_of_birth' => '1980-01-01'), 'PK');
$patientUuid = (string) $patient['clinical_uuid'];
$grant = CF01_Break_Glass::request(2, $patientUuid, 'Immediate emergency medication review', array(
    'emergency' => true,
    'emergency_reference' => 'emergency-incident-1',
    'device_reference' => 'device-session-1',
    'requested_fields' => array('summary', 'active_prescriptions'),
));
$expect(fn() => CF01_Break_Glass::request(2, $patientUuid, 'Duplicate emergency request', array(
    'emergency' => true,
    'emergency_reference' => 'emergency-incident-2',
    'device_reference' => 'device-session-1',
    'requested_fields' => array('summary'),
)), 'Duplicate active break-glass grant must fail.', 'already exists');
$assertion = CF01_Break_Glass::assertion(2, (string) $grant['grant_uuid'], array('summary'));
$check(($assertion['export_allowed'] ?? true) === false && ($assertion['persistent_access'] ?? true) === false, 'Break-glass must never create export or persistent access.');
$GLOBALS['cf01_current_user'] = 3;
$expect(fn() => CF01_Break_Glass::review(3, (string) $grant['grant_uuid'], array('finding' => 'appropriate', 'reviewer_reference' => 'review-1', 'conflict_disclosed' => false), 1), 'Active grant final review must fail.', 'revoked or expire');
$revoked = CF01_Break_Glass::revoke(3, (string) $grant['grant_uuid'], 'Emergency ended', 1);
$GLOBALS['cf01_current_user'] = 2;
$expect(fn() => CF01_Break_Glass::review(2, (string) $grant['grant_uuid'], array('finding' => 'appropriate', 'reviewer_reference' => 'review-2', 'conflict_disclosed' => false), (int) $revoked['row_version']), 'Break-glass self-review must fail.', 'self-review');
$GLOBALS['cf01_current_user'] = 3;
$reviewed = CF01_Break_Glass::review(3, (string) $grant['grant_uuid'], array('finding' => 'appropriate', 'reviewer_reference' => 'review-3', 'conflict_disclosed' => false), (int) $revoked['row_version']);
$check(($reviewed['review_status'] ?? '') === 'reviewed', 'Independent break-glass review must complete after revocation.');

// Access-history cursors are signed and patient-bound; patient views never reveal actor/object references.
cf01_reset();
$patientUuid = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
CF01_DB::insert('access', array(
    'event_uuid' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    'patient_uuid' => $patientUuid,
    'actor_pseudonym' => str_repeat('c', 64),
    'action' => 'ClinicalRecordViewed',
    'object_type' => 'clinical_patient',
    'object_uuid' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
    'purpose' => 'clinical_care',
    'result' => 'success',
    'occurred_at' => gmdate('Y-m-d H:i:s'),
));
$accessPage = new ReflectionMethod(CF01_Lifecycle_REST::class, 'access_page');
$accessPage->setAccessible(true);
$patientPage = $accessPage->invoke(null, $patientUuid, 'patient', '', 10);
$recordsPage = $accessPage->invoke(null, $patientUuid, 'records', '', 10);
$check(!array_key_exists('actor_reference', $patientPage['items'][0]) && !array_key_exists('object_reference', $patientPage['items'][0]), 'Patient access history must not leak pseudonymous internal references.');
$check(isset($recordsPage['items'][0]['actor_reference'], $recordsPage['items'][0]['object_reference']), 'Authorized records view may receive bounded pseudonymous references.');
$encodeCursor = new ReflectionMethod(CF01_Lifecycle_REST::class, 'encode_cursor');
$encodeCursor->setAccessible(true);
$decodeCursor = new ReflectionMethod(CF01_Lifecycle_REST::class, 'decode_cursor');
$decodeCursor->setAccessible(true);
$cursor = $encodeCursor->invoke(null, $patientUuid, gmdate('Y-m-d H:i:s'), 'dddddddd-dddd-4ddd-8ddd-dddddddddddd');
$expect(fn() => $decodeCursor->invoke(null, $cursor . 'x', $patientUuid), 'Tampered cursor must fail.', 'Invalid access-history cursor');
$expect(fn() => $decodeCursor->invoke(null, $cursor, 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee'), 'Cross-patient cursor replay must fail.', 'cursor context');

// Versioned races and controlled disable/rollback consistency remain release gates.
$relationshipSource = file_get_contents(CF01_DIR . 'includes/class-cf01-relationships.php');
$consentSource = file_get_contents(CF01_DIR . 'includes/class-cf01-consents.php');
$check(str_contains($relationshipSource, 'expected_version') && str_contains($relationshipSource, 'update_versioned'), 'Relationship transitions must remain optimistic-concurrency protected.');
$check(str_contains($consentSource, 'expected_version') && str_contains($consentSource, 'update_versioned'), 'Consent withdrawal must remain optimistic-concurrency protected.');
cf01_reset();
$GLOBALS['cf01_schedules'] = array(
    'cf01_process_outbox' => time() + 60,
    'cf01_retention_reconcile' => time() + 60,
    'cf01_followup_reconcile' => time() + 60,
);
CF01_Migrations::disable_runtime(1, 'Round-3 controlled disable test');
$check(get_option('cf01_activation_state') === 'disabled', 'Controlled disable must close the runtime gate.');
$check(!$GLOBALS['cf01_schedules'], 'Controlled disable must clear every CF-01 recurring job.');

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
echo "CF-01 adversarial security round 3: {$count} PASS, 0 FAIL\n";
