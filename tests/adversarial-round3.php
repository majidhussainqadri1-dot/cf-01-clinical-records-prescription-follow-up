<?php
require __DIR__ . '/bootstrap.php';

$count = 0;
$failures = array();
$check = function (bool $condition, string $message) use (&$count, &$failures): void {
    $count++;
    if (!$condition) $failures[] = $message;
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

// Authenticated actor identity is authoritative unless an explicit service-actor contract permits delegation.
cf01_reset();
$GLOBALS['cf01_filters']['cf01_allow_service_actor'] = array();
$GLOBALS['cf01_current_user'] = 1;
$GLOBALS['cf01_caps'] = array('*' => true);
$expect(fn() => CF01_Authorization::actor(2, 'view_clinical_record'), 'Cross-user actor spoofing must fail.', 'identity mismatch');

// Patient ownership is derived from canonical membership platform identity + patient blind index, not mutable user metadata.
cf01_reset();
$GLOBALS['cf01_current_user'] = 2;
$patient = CF01_Patients::create(2, 'platform-user-2', array('date_of_birth' => '1980-01-01'), 'PK');
$patientUuid = (string) $patient['clinical_uuid'];
$check(CF01_Authorization::patient_owner(2, $patientUuid) === true, 'Canonical patient owner must resolve.');
$check(CF01_Authorization::patient_owner(1, $patientUuid) === false, 'Unrelated membership must not become patient owner.');
$authorizationSource = file_get_contents(CF01_DIR . 'includes/class-cf01-authorization.php');
$check(!str_contains($authorizationSource, 'get_user_meta('), 'Patient ownership must not depend on mutable user meta.');

// A merely proposed relationship must never authorize clinical reads.
cf01_reset();
$GLOBALS['cf01_current_user'] = 2;
$GLOBALS['cf01_caps'] = array('*' => true);
$patient = CF01_Patients::create(2, 'platform-user-1', array('date_of_birth' => '1980-01-01'), 'PK');
$patientUuid = (string) $patient['clinical_uuid'];
$relationship = CF01_Relationships::propose(2, $patientUuid, 2, array(
    'purpose' => 'clinical_care',
    'scope' => array('clinical_care'),
    'source_reference' => 'relationship-round3',
));
$check(($relationship['status'] ?? '') === 'proposed', 'Relationship must begin proposed.');
$expect(fn() => CF01_Authorization::relationship($patientUuid, 2, 'clinical_care', 'view_clinical_record'), 'Proposed relationship must not authorize access.', 'active treating relationship');

// Consent authorization must read the canonical consent row and reject revoked state.
cf01_reset();
$patientUuid = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
CF01_DB::insert('consents', array(
    'consent_uuid' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    'patient_uuid' => $patientUuid,
    'purpose' => 'clinical_care',
    'notice_version' => '1',
    'status' => 'granted',
    'granted_at' => CF01_DB::now(),
    'withdrawn_at' => null,
    'expires_at' => gmdate('Y-m-d H:i:s', time() + 600),
    'row_version' => 1,
    'created_at' => CF01_DB::now(),
    'updated_at' => CF01_DB::now(),
));
$consent = CF01_Authorization::consent($patientUuid, 'clinical_care');
$check(($consent['status'] ?? '') === 'granted', 'Current canonical consent must authorize its purpose.');
$updated = CF01_DB::update_versioned('consents', array('status' => 'withdrawn', 'withdrawn_at' => CF01_DB::now()), array('consent_uuid' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'), 1);
$check($updated === true, 'Versioned consent withdrawal fixture must update exactly one canonical row.');
$expect(fn() => CF01_Authorization::consent($patientUuid, 'clinical_care'), 'Withdrawn consent must fail closed.', 'active purpose-specific consent');

// Minimum-necessary projection must never broaden requested fields beyond the role policy.
$fields = CF01_Authorization::fields('break_glass', 'emergency_care', array('summary', 'secret_internal_field'), array());
$check($fields === array('summary'), 'Minimum-necessary field projection must drop unauthorized fields.');

// Break-glass consumes optimistic versions; use of a grant advances row_version and stale revocation must fail.
cf01_reset();
$GLOBALS['cf01_current_user'] = 2;
$GLOBALS['cf01_caps'] = array('*' => true);
$patient = CF01_Patients::create(2, 'platform-user-2', array('date_of_birth' => '1980-01-01'), 'PK');
$patientUuid = (string) $patient['clinical_uuid'];
$grant = CF01_Break_Glass::request(2, $patientUuid, 'Immediate emergency medication review', array(
    'emergency' => true,
    'emergency_reference' => 'emergency-incident-round3',
    'device_reference' => 'device-session-round3',
    'requested_fields' => array('summary', 'active_prescriptions'),
));
$initialVersion = (int) $grant['row_version'];
$assertion = CF01_Break_Glass::assertion(2, (string) $grant['grant_uuid'], array('summary'));
$check(($assertion['export_allowed'] ?? true) === false && ($assertion['persistent_access'] ?? true) === false, 'Break-glass must not create export or persistent access.');
$used = CF01_Break_Glass::get((string) $grant['grant_uuid']);
$check((int) $used['row_version'] > $initialVersion, 'Break-glass use must advance optimistic row version.');
$GLOBALS['cf01_current_user'] = 3;
$expect(fn() => CF01_Break_Glass::revoke(3, (string) $grant['grant_uuid'], 'Emergency ended', $initialVersion), 'Stale break-glass revocation must fail.', 'version conflict');
$revoked = CF01_Break_Glass::revoke(3, (string) $grant['grant_uuid'], 'Emergency ended', (int) $used['row_version']);
$check(($revoked['status'] ?? '') === 'revoked', 'Current-version break-glass revocation must succeed.');

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

echo "CF-01 adversarial round 3: {$count} PASS, 0 FAIL\n";
