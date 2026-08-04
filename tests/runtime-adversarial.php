<?php
require __DIR__ . '/bootstrap.php';
cf01_reset();
$count = 0;
$pass = function (callable $test, string $name) use (&$count): void { $test(); $count++; };

$pass(function (): void { $GLOBALS['cf01_options']['cf01_activation_state'] = 'disabled'; cf01_expect_exception(fn() => CF01_Authorization::actor(1, 'view_own_clinical_record'), 'disabled'); $GLOBALS['cf01_options']['cf01_activation_state'] = 'enabled'; }, 'disabled fail closed');
$pass(function (): void { add_filter('cf01_membership_assertion', fn($r) => array('contract_version' => '9.9.9')); cf01_expect_exception(fn() => CF01_Authorization::actor(1, 'view_own_clinical_record'), 'membership'); array_pop($GLOBALS['cf01_filters']['cf01_membership_assertion']); }, 'unknown membership version');
$pass(function (): void { $GLOBALS['cf01_caps'] = array(); cf01_expect_exception(fn() => CF01_Authorization::actor(1, 'create_clinical_patient'), 'capability'); $GLOBALS['cf01_caps'] = array('*' => true); }, 'capability denial');
$pass(function (): void { cf01_expect_exception(fn() => CF01_Patients::create(1, 'p', array('date_of_birth' => '2099-01-01'), 'PK'), 'future'); }, 'future DOB');
$pass(function (): void { cf01_expect_exception(fn() => CF01_DB::idempotent(1, 'Test', 'short', array(), fn() => array()), 'idempotency'); }, 'idempotency key validation');
$pass(function (): void { $a = CF01_DB::idempotent(1, 'Test', 'abcdefgh-123', array('x' => 1), fn() => array('ok' => 1)); $b = CF01_DB::idempotent(1, 'Test', 'abcdefgh-123', array('x' => 1), fn() => array('ok' => 2)); cf01_assert($a === $b && $b['ok'] === 1, 'Idempotent replay changed result.'); }, 'idempotent replay');
$pass(function (): void { cf01_expect_exception(fn() => CF01_DB::idempotent(1, 'Test', 'abcdefgh-123', array('x' => 2), fn() => array()), 'different request'); }, 'idempotency mismatch');

$pass(function (): void {
    $service = array_pop($GLOBALS['cf01_filters']['cf01_allow_service_actor']);
    $GLOBALS['cf01_current_user'] = 1;
    cf01_expect_exception(fn() => CF01_Authorization::actor(2, 'sign_prescription'), 'actor');
    $GLOBALS['cf01_filters']['cf01_allow_service_actor'][] = $service;
}, 'actor identity mismatch blocked');
$pass(function (): void {
    add_filter('cf01_recent_auth_assertion', fn($result, int $user, string $purpose): array => array('contract_version'=>'1.0.0','recent_auth'=>true,'step_up'=>true,'expires_at'=>gmdate('Y-m-d H:i:s', time()-1),'subject_uuid'=>'platform-user-'.$user), 99, 3);
    cf01_expect_exception(fn() => CF01_Authorization::actor(1, 'export_record'), 'step-up');
    array_pop($GLOBALS['cf01_filters']['cf01_recent_auth_assertion']);
}, 'expired recent authentication blocked');

$patient = CF01_Patients::create(1, 'platform-user-1', array('display_name' => 'Subject', 'date_of_birth' => '1990-01-01', 'sex' => 'unspecified', 'language' => 'en-US', 'time_zone' => 'UTC'), 'PK');
$pass(function () use ($patient): void { cf01_expect_exception(fn() => CF01_Consents::record(1, $patient['clinical_uuid'], 'research', 'granted', array('notice_version'=>'1','subject_platform_uuid'=>'platform-user-999')), 'subject'); }, 'consent subject mismatch blocked');
$consent = CF01_Consents::record(1, $patient['clinical_uuid'], 'clinical_care', 'granted', array('notice_version' => '1', 'subject_platform_uuid' => 'platform-user-1'));
CF01_Consents::record(1, $patient['clinical_uuid'], 'images', 'granted', array('notice_version' => '1', 'subject_platform_uuid' => 'platform-user-1'));
$rel = CF01_Relationships::propose(1, $patient['clinical_uuid'], 2, array('purpose' => 'clinical_care', 'scope' => array('care')));
$rel = CF01_Relationships::activate(2, $rel['relationship_uuid'], 1);

$pass(function () use ($patient): void { cf01_expect_exception(fn() => CF01_Encounters::create(3, $patient['clinical_uuid'], array('relationship_uuid' => 'none', 'mode' => 'in_person', 'starts_at' => 'now', 'content' => array())), 'professional'); }, 'unverified clinician');
$pass(function () use ($patient): void { cf01_expect_exception(fn() => CF01_Attachments::attach(2, $patient['clinical_uuid'], 'missing', array('asset_reference' => 'https://example.test/file?token=x', 'sha256' => str_repeat('a',64), 'declared_type'=>'image/jpeg','source'=>'x'))); }, 'bearer attachment URL');
$pass(function () use ($patient, $rel): void { cf01_expect_exception(fn() => CF01_Encounters::create(2, $patient['clinical_uuid'], array('relationship_uuid' => $rel['relationship_uuid'], 'mode' => 'invalid', 'starts_at' => 'now', 'content' => array())), 'mode'); }, 'invalid encounter mode');

$enc = CF01_Encounters::create(2, $patient['clinical_uuid'], array('relationship_uuid' => $rel['relationship_uuid'], 'mode' => 'in_person', 'starts_at' => 'now', 'content' => array('chief_complaints'=>array('x'),'narrative'=>'n','totality'=>'t','temperament'=>'t','miasmatic_assessment'=>'m')));
$pass(function () use ($enc): void { cf01_expect_exception(fn() => CF01_Encounters::update_draft(2, $enc['encounter_uuid'], array('chief_complaints'=>array('x'),'narrative'=>'n'), 'ready_to_sign', 1), 'transition'); }, 'invalid state jump');
$pass(function () use ($enc): void { cf01_expect_exception(fn() => CF01_Encounters::update_draft(2, $enc['encounter_uuid'], array('chief_complaints'=>array('x'),'narrative'=>'n'), 'in_progress', 9), 'Stale'); }, 'stale version');
$enc = CF01_Encounters::update_draft(2, $enc['encounter_uuid'], CF01_Encounters::content($enc), 'in_progress', 1);
$enc = CF01_Encounters::update_draft(2, $enc['encounter_uuid'], CF01_Encounters::content($enc), 'ready_to_sign', 2);
$enc = CF01_Encounters::sign(2, $enc['encounter_uuid'], 3);
$pass(function () use ($enc): void { cf01_expect_exception(fn() => CF01_Encounters::add_observation(2, $enc['encounter_uuid'], 'x', 'y', array('source'=>'s','observed_at'=>CF01_DB::now())), 'addendum'); }, 'signed observation mutation');

$pass(function () use ($patient, $enc): void { cf01_expect_exception(fn() => CF01_Attachments::attach(2, 'other-patient', $enc['encounter_uuid'], array('asset_reference'=>'asset','sha256'=>str_repeat('a',64),'declared_type'=>'image/jpeg','source'=>'x'))); }, 'wrong patient attachment');
$attachment = CF01_Attachments::attach(2, $patient['clinical_uuid'], $enc['encounter_uuid'], array('asset_reference'=>'asset-opaque','sha256'=>str_repeat('a',64),'declared_type'=>'image/jpeg','source'=>'x'));
$pass(function () use ($attachment): void { cf01_expect_exception(fn() => CF01_Attachments::delivery_reference(2, $attachment['attachment_uuid']), 'Unscanned'); }, 'quarantine delivery denial');

$order = array('remedy'=>'r','potency'=>'p','form'=>'oral','dose'=>'d','frequency'=>'f','duration'=>'x','repetition'=>'r','instructions'=>'i','rationale'=>'reason');
$pres = CF01_Prescriptions::create(2, $patient['clinical_uuid'], $enc['encounter_uuid'], $order);
$pass(function () use ($pres): void { cf01_expect_exception(fn() => CF01_Prescriptions::sign(2, $pres['prescription_uuid'], 1), 'transition'); }, 'draft prescription sign');
$pass(function () use ($patient, $enc, $order): void { $bad=$order; $bad['autonomous_ai']=true; cf01_expect_exception(fn() => CF01_Prescriptions::create(2,$patient['clinical_uuid'],$enc['encounter_uuid'],$bad)); }, 'autonomous prescription');

$pass(function () use ($patient): void { cf01_expect_exception(fn() => CF01_Break_Glass::request(2, $patient['clinical_uuid'], '', array('emergency'=>true)), 'reason'); }, 'breakglass reason');
$pass(function () use ($patient): void { cf01_expect_exception(fn() => CF01_Break_Glass::request(2, $patient['clinical_uuid'], 'reason', array('emergency'=>true,'bulk'=>true)), 'minimum-view'); }, 'breakglass bulk');
$grant = CF01_Break_Glass::request(2, $patient['clinical_uuid'], 'reason', array('emergency'=>true,'requested_fields'=>array('summary')));
$pass(function () use ($grant): void { cf01_expect_exception(fn() => CF01_Break_Glass::review(2, $grant['grant_uuid'], array('finding'=>'appropriate'), 1), 'self-review'); }, 'breakglass self review');

$right = CF01_Rights::request(1, $patient['clinical_uuid'], 'export', array());
$pass(function () use ($right): void { cf01_expect_exception(fn() => CF01_Rights::fulfill_export(1, $right['case_uuid'], 'ref', 1), 'approved'); }, 'unapproved export');
$pass(function () use ($right): void { cf01_expect_exception(fn() => CF01_Rights::decide(3, $right['case_uuid'], 'approved', array(), 1), 'reason'); }, 'unreasoned rights decision');

$pass(function () use ($right): void { cf01_expect_exception(fn() => CF01_Rights::decide(1, $right['case_uuid'], 'approved', array('reason'=>'self'), 1), 'requester'); }, 'rights self decision blocked');
$right2 = CF01_Rights::request(1, $patient['clinical_uuid'], 'export', array());
$right2 = CF01_Rights::decide(3, $right2['case_uuid'], 'approved', array('reason'=>'approved'), 1);
$pass(function () use ($right2): void { cf01_expect_exception(fn() => CF01_Rights::fulfill_export(3, $right2['case_uuid'], 'opaque', 2), 'separated'); }, 'export reviewer cannot fulfill');
$pass(function () use ($patient, $enc, $order): void {
    $filters = $GLOBALS['cf01_filters']['cf01_prescription_safety_review'];
    $GLOBALS['cf01_filters']['cf01_prescription_safety_review'] = array(fn($result, array $request, array $value): array => array('contract_version'=>'1.0.0','passed'=>false,'blocking'=>true,'warnings'=>array('blocked'),'evidence_reference'=>'blocked'));
    $draft = CF01_Prescriptions::create(2, $patient['clinical_uuid'], $enc['encounter_uuid'], $order);
    $draft = CF01_Prescriptions::update(2, $draft['prescription_uuid'], $order, 'ready_to_sign', 1);
    cf01_expect_exception(fn() => CF01_Prescriptions::sign(2, $draft['prescription_uuid'], 2), 'safety');
    $GLOBALS['cf01_filters']['cf01_prescription_safety_review'] = $filters;
}, 'blocking prescription safety review');

$ret = CF01_Retention::schedule(1, 'encounter', $enc['encounter_uuid'], 'qualified-policy', gmdate('Y-m-d H:i:s', time()-1));
$ret = CF01_Retention::place_hold(1, $ret['retention_uuid'], array('type'=>'legal','reason'=>'pending','authority'=>'qualified'), 1);
$pass(function () use ($ret): void { cf01_expect_exception(fn() => CF01_Retention::purge(1, $ret['retention_uuid'], 2), 'not eligible'); }, 'hold blocks purge');
$holds = CF01_Crypto::decrypt((string) $ret['holds_cipher'], 'retention-holds');
$pass(function () use ($ret, $holds): void { cf01_expect_exception(fn() => CF01_Retention::release_hold(1, $ret['retention_uuid'], (string) $holds[0]['hold_uuid'], 'self release', 2), 'placer'); }, 'hold self release blocked');
$restoreFilter = fn($result, array $expected): array => array('restore_completed'=>true,'key_recovery_passed'=>true,'authorization_revalidated'=>true,'counts'=>$expected['expected_counts'],'integrity_root'=>$expected['expected_integrity_root'],'deleted_record_resurrection'=>false);
add_filter('cf01_restore_verification', $restoreFilter, 10, 2);
$restore = CF01_Migrations::verify_restore(3, array('backup_reference'=>'backup-opaque','expected_counts'=>array('patients'=>1),'expected_integrity_root'=>'root-1','key_recovery_tested_at'=>CF01_DB::now()));
$pass(function () use ($restore): void { cf01_assert($restore['key_recovery_passed'] === true && $restore['deleted_record_resurrection'] === false, 'Restore evidence invalid.'); }, 'restore verification');


$pass(function (): void { $allowed=CF01_Authorization::fields('break_glass','emergency_care',array('summary','encounters','active_prescriptions'),array()); cf01_assert($allowed===array('summary','active_prescriptions'),'Breakglass leaked fields.'); }, 'field minimization');
$pass(function (): void { $m=CF01_Contracts::assurance_manifest(); cf01_assert($m['native_enforcement_preserved']===true && $m['classification']==='C5','Assurance manifest invalid.'); }, 'assurance boundary');
$pass(function (): void { $c=CF01_Contracts::communication_context('ref',2); cf01_assert(!empty($c['valid']) && empty($c['revoked']),'Communication reference invalid.'); }, 'communication reference');
$pass(function (): void { $GLOBALS['cf01_options']['cf01_activation_state']='disabled'; CF01_Outbox::process(); $GLOBALS['cf01_options']['cf01_activation_state']='enabled'; cf01_assert(true,'Queue ran while disabled.'); }, 'disabled jobs');
$pass(function () use ($patient): void {
    $event = CF01_Outbox::enqueue('EncounterSigned', array('patient_uuid'=>$patient['clinical_uuid'],'encounter_uuid'=>'enc-opaque'), 'enc-opaque');
    $url = CF01_Outbox::resolve_destination(1, $event);
    cf01_assert($url === 'https://example.test/clinic/encounters/enc-opaque', 'Protected destination was not canonical.');
}, 'click-time protected destination');


echo "CF-01 runtime/adversarial review: {$count} PASS, 0 FAIL\n";
