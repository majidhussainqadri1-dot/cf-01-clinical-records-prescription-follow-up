<?php
require __DIR__ . '/bootstrap.php';

$count = 0;
$failures = array();
$check = function (bool $condition, string $message) use (&$count, &$failures): void {
    $count++;
    if (!$condition) {
        $failures[] = $message;
    }
};
$expect = function (callable $fn, string $message, string $contains = '') use (&$count, &$failures): void {
    $count++;
    try {
        $fn();
        $failures[] = $message;
    } catch (Throwable $e) {
        if ($contains !== '' && !str_contains(strtolower($e->getMessage()), strtolower($contains))) {
            $failures[] = $message . ' (unexpected message: ' . $e->getMessage() . ')';
        }
    }
};

// Authentication must come from WordPress runtime state, not caller-supplied headers.
cf01_reset();
$GLOBALS['cf01_current_user'] = 0;
$GLOBALS['cf01_caps'] = array('cf01_view_clinical_record' => true);
$request = new WP_REST_Request();
$request->set_header('X-User-ID', '1');
$expect(fn() => CF01_Authorization::actor(1, 'view_clinical_record'), 'Header-based identity spoofing must fail.', 'current authenticated');

// Owner resolution must not trust mutable patient meta as an authority source.
cf01_reset();
$GLOBALS['cf01_current_user'] = 2;
$patient = CF01_Patients::create(2, 'platform-user-1', array('date_of_birth' => '1980-01-01'), 'PK');
$patientUuid = (string) $patient['clinical_uuid'];
$GLOBALS['cf01_current_user'] = 1;
$GLOBALS['cf01_caps'] = array('cf01_view_own_clinical_record' => true);
update_user_meta(1, 'cf01_patient_uuid', $patientUuid);
$expect(fn() => CF01_Authorization::patient_owner(1, $patientUuid), 'Patient ownership must reject mutable-meta takeover.', 'identity contract');

// Clinician relationship assertions must be server-authenticated and bound to actor, patient, purpose and capability.
cf01_reset();
$GLOBALS['cf01_current_user'] = 2;
$patient = CF01_Patients::create(2, 'platform-user-1', array('date_of_birth' => '1980-01-01'), 'PK');
$patientUuid = (string) $patient['clinical_uuid'];
$GLOBALS['cf01_current_user'] = 3;
$GLOBALS['cf01_caps'] = array('cf01_view_clinical_record' => true);
add_filter('cf01_clinical_relationship_assertion', static function ($value, string $patient, int $actor, string $purpose, string $capability): array {
    return array(
        'valid' => true,
        'accepted' => true,
        'revoked' => false,
        'suspended' => false,
        'contract_version' => '1.0.0',
        'relationship_reference' => 'rel-1',
        'relationship_version' => 1,
        'patient_uuid' => $patient,
        'actor_user_id' => $actor,
        'purpose' => $purpose,
        'capability' => $capability,
        'expires_at' => gmdate('Y-m-d H:i:s', time() + 600),
    );
}, 10, 5);
$relationship = CF01_Authorization::relationship($patientUuid, 3, 'clinical_care', 'view_clinical_record');
$check(($relationship['relationship_reference'] ?? '') === 'rel-1', 'Bound relationship assertion must pass.');
remove_all_filters('cf01_clinical_relationship_assertion');
add_filter('cf01_clinical_relationship_assertion', static function ($value, string $patient, int $actor, string $purpose, string $capability): array {
    return array(
        'valid' => true,
        'accepted' => true,
        'revoked' => false,
        'suspended' => false,
        'contract_version' => '1.0.0',
        'relationship_reference' => 'rel-bad',
        'relationship_version' => 1,
        'patient_uuid' => 'ffffffff-ffff-4fff-8fff-ffffffffffff',
        'actor_user_id' => $actor,
        'purpose' => $purpose,
        'capability' => $capability,
        'expires_at' => gmdate('Y-m-d H:i:s', time() + 600),
    );
}, 10, 5);
$expect(fn() => CF01_Authorization::relationship($patientUuid, 3, 'clinical_care', 'view_clinical_record'), 'Mismatched relationship assertion must fail.', 'does not match');

// Consent contract must reject stale/revoked/mismatched assertions.
cf01_reset();
add_filter('cf01_consent_assertion', static function ($value, string $patient, string $purpose): array {
    return array(
        'valid' => true,
        'accepted' => true,
        'status' => 'active',
        'consent_reference' => 'consent-1',
        'consent_version' => 2,
        'contract_version' => '1.0.0',
        'patient_uuid' => $patient,
        'purpose' => $purpose,
        'expires_at' => gmdate('Y-m-d H:i:s', time() + 600),
    );
}, 10, 3);
$consent = CF01_Authorization::consent('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'clinical_care');
$check(($consent['consent_reference'] ?? '') === 'consent-1', 'Current consent assertion must pass.');
remove_all_filters('cf01_consent_assertion');
add_filter('cf01_consent_assertion', static function ($value, string $patient, string $purpose): array {
    return array(
        'valid' => true,
        'accepted' => true,
        'status' => 'revoked',
        'consent_reference' => 'consent-2',
        'consent_version' => 3,
        'contract_version' => '1.0.0',
        'patient_uuid' => $patient,
        'purpose' => $purpose,
        'expires_at' => gmdate('Y-m-d H:i:s', time() + 600),
    );
}, 10, 3);
$expect(fn() => CF01_Authorization::consent('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'clinical_care'), 'Revoked consent assertion must fail.', 'not current');

// Minimum-view assertion must not broaden requested fields.
cf01_reset();
add_filter('cf01_clinical_minimum_view_assertion', static function ($value, string $view, string $purpose, array $requested): array {
    return array(
        'valid' => true,
        'accepted' => true,
        'contract_version' => '1.0.0',
        'assertion_version' => 1,
        'view' => $view,
        'purpose' => $purpose,
        'requested_fields' => $requested,
        'allowed_fields' => array_merge($requested, array('extra-secret-field')),
        'expires_at' => gmdate('Y-m-d H:i:s', time() + 600),
    );
}, 10, 4);
$expect(fn() => CF01_Authorization::fields('break_glass', 'emergency_care', array('summary'), array()), 'Minimum-view assertion must not broaden fields.', 'broadened');

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
$activeGrant = CF01_Break_Glass::get((string) $grant['grant_uuid']);
$activeVersion = (int) $activeGrant['row_version'];
$GLOBALS['cf01_current_user'] = 3;
$expect(fn() => CF01_Break_Glass::review(3, (string) $grant['grant_uuid'], array('finding' => 'appropriate', 'reviewer_reference' => 'review-1', 'conflict_disclosed' => false), $activeVersion), 'Active grant final review must fail.', 'revoked or expire');
$revoked = CF01_Break_Glass::revoke(3, (string) $grant['grant_uuid'], 'Emergency ended', $activeVersion);
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
$GLOBALS['cf01_current_user'] = 1;
$GLOBALS['cf01_caps'] = array('cf01_view_own_clinical_record' => true);
add_filter('cf01_patient_identity_assertion', static function ($value, int $actorId, string $patient) use ($patientUuid): array {
    return array(
        'valid' => true,
        'accepted' => true,
        'revoked' => false,
        'suspended' => false,
        'contract_version' => '1.0.0',
        'assertion_version' => 1,
        'actor_user_id' => $actorId,
        'patient_uuid' => $patientUuid,
        'subject_reference' => 'patient-subject-1',
        'expires_at' => gmdate('Y-m-d H:i:s', time() + 600),
    );
}, 10, 3);
$history = CF01_Access_History::list_for_patient(1, $patientUuid, '', 10);
$item = $history['items'][0] ?? array();
$check(isset($item['category'], $item['display_label'], $item['occurred_at']), 'Patient access-history entry must expose safe display fields.');
$check(!isset($item['actor_pseudonym'], $item['object_uuid'], $item['event_uuid']), 'Patient access history must not expose internal actor/object references.');

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

echo "CF-01 adversarial round 3: {$count} PASS, 0 FAIL\n";
