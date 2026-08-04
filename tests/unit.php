<?php
require __DIR__ . '/bootstrap.php';
cf01_reset();
$count = 0;
$ok = function (bool $condition, string $message) use (&$count): void { cf01_assert($condition, $message); $count++; };

$envelope = CF01_Crypto::encrypt(array('x' => 1), 'test');
$ok(CF01_Crypto::decrypt($envelope, 'test')['x'] === 1, 'Encryption round trip failed.');
cf01_expect_exception(fn() => CF01_Crypto::decrypt($envelope, 'other'), 'purpose mismatch'); $count++;
$ok(strlen(CF01_Crypto::blind_index('ABC', 'subject')) === 64, 'Blind index invalid.');
$signature = CF01_Crypto::sign(array('b' => 2, 'a' => 1), 'test');
$ok(CF01_Crypto::verify(array('a' => 1, 'b' => 2), 'test', $signature), 'Canonical signature failed.');

$patient = CF01_Patients::create(1, 'platform-user-1', array('display_name' => 'Subject', 'date_of_birth' => '1990-01-01', 'sex' => 'unspecified', 'language' => 'en-US', 'time_zone' => 'Asia/Karachi'), 'PK');
$ok($patient['status'] === 'active', 'Clinical patient was not created.');
$patient2 = CF01_Patients::create(1, 'platform-user-1', array('display_name' => 'Subject', 'date_of_birth' => '1990-01-01', 'sex' => 'unspecified', 'language' => 'en-US', 'time_zone' => 'Asia/Karachi'), 'PK');
$ok($patient2['clinical_uuid'] === $patient['clinical_uuid'], 'Patient create is not idempotent by identity.');
$ok(CF01_Authorization::patient_owner(1, $patient['clinical_uuid']), 'Patient ownership failed.');

$consent = CF01_Consents::record(1, $patient['clinical_uuid'], 'clinical_care', 'granted', array('notice_version' => '1.0', 'subject_platform_uuid' => 'platform-user-1'));
$ok($consent['status'] === 'granted', 'Consent was not granted.');
CF01_Consents::record(1, $patient['clinical_uuid'], 'images', 'granted', array('notice_version' => '1.0', 'subject_platform_uuid' => 'platform-user-1'));
$relationship = CF01_Relationships::propose(1, $patient['clinical_uuid'], 2, array('purpose' => 'clinical_care', 'scope' => array('care'), 'source_reference' => 'appointment-opaque'));
$relationship = CF01_Relationships::activate(2, $relationship['relationship_uuid'], 1);
$ok($relationship['status'] === 'active', 'Treating relationship was not activated.');

$encounter = CF01_Encounters::create(2, $patient['clinical_uuid'], array('relationship_uuid' => $relationship['relationship_uuid'], 'mode' => 'in_person', 'starts_at' => 'now', 'content' => array('chief_complaints' => array('coded-complaint'), 'narrative' => 'Clinical narrative.', 'totality' => array('totality'), 'temperament' => 'temperament', 'miasmatic_assessment' => 'assessment')));
$ok($encounter['status'] === 'draft', 'Encounter draft was not created.');
$observation = CF01_Encounters::add_observation(2, $encounter['encounter_uuid'], 'objective', array('value' => 'bounded'), array('source' => 'clinician', 'observed_at' => CF01_DB::now()));
$ok($observation['status'] === 'active', 'Observation was not created.');
$assessment = CF01_Encounters::create_assessment(2, $encounter['encounter_uuid'], array('totality' => 'totality', 'temperament' => 'temperament', 'miasmatic_assessment' => 'miasm', 'clinical_reasoning' => 'reasoning'));
$assessment = CF01_Encounters::sign_assessment(2, $assessment['assessment_uuid'], 1);
$ok($assessment['status'] === 'signed', 'Assessment was not signed.');
$encounter = CF01_Encounters::update_draft(2, $encounter['encounter_uuid'], CF01_Encounters::content($encounter), 'in_progress', 1);
$encounter = CF01_Encounters::update_draft(2, $encounter['encounter_uuid'], CF01_Encounters::content($encounter), 'ready_to_sign', 2);
$encounter = CF01_Encounters::sign(2, $encounter['encounter_uuid'], 3);
$ok($encounter['status'] === 'signed' && !empty($encounter['signature']), 'Encounter signature failed.');
cf01_expect_exception(fn() => CF01_Encounters::update_draft(2, $encounter['encounter_uuid'], array('narrative' => 'overwrite'), 'in_progress', 4), 'immutable'); $count++;
$addendum = CF01_Encounters::addendum(2, $encounter['encounter_uuid'], array('narrative' => 'Correction addendum.'));
$ok($addendum['parent_encounter_uuid'] === $encounter['encounter_uuid'], 'Addendum provenance failed.');

$attachment = CF01_Attachments::attach(2, $patient['clinical_uuid'], $encounter['encounter_uuid'], array('asset_reference' => 'asset-opaque-1', 'sha256' => str_repeat('a', 64), 'declared_type' => 'image/jpeg', 'source' => 'clinical-upload'));
$attachment = CF01_Attachments::review_scan(2, $attachment['attachment_uuid'], 'ready', array('scanner' => 'approved-scanner', 'signature' => 'signed-evidence', 'completed_at' => CF01_DB::now(), 'detected_type' => 'image/jpeg'), 1);
$ok($attachment['scan_status'] === 'ready', 'Attachment scanner review failed.');
$ok(CF01_Attachments::delivery_reference(2, $attachment['attachment_uuid']) === 'delivery-grant-opaque', 'Secure attachment delivery failed.');

$order = array('remedy' => 'Clinician selected', 'potency' => 'Clinician selected', 'form' => 'oral', 'dose' => 'Clinician entered', 'frequency' => 'Clinician entered', 'duration' => 'Clinician entered', 'repetition' => 'Clinician entered', 'instructions' => 'Follow clinician instructions.', 'rationale' => 'Documented clinical rationale.', 'language' => 'en-US');
$prescription = CF01_Prescriptions::create(2, $patient['clinical_uuid'], $encounter['encounter_uuid'], $order);
$prescription = CF01_Prescriptions::update(2, $prescription['prescription_uuid'], $order, 'ready_to_sign', 1);
$prescription = CF01_Prescriptions::sign(2, $prescription['prescription_uuid'], 2);
$ok($prescription['status'] === 'signed' && !empty($prescription['signature']), 'Prescription signature failed.');
$ok(empty(CF01_Prescriptions::order($prescription)['autonomous_ai']), 'Autonomous AI flag was not prohibited.');

$followup = CF01_Followups::plan(2, $patient['clinical_uuid'], $prescription['prescription_uuid'], array('due_at' => gmdate('Y-m-d H:i:s', time() + 3600), 'questionnaire' => array(array('key' => 'change', 'prompt' => 'Describe change', 'type' => 'text', 'required' => true)), 'reminders' => array(24), 'objectives' => array('review')));
$ok($followup['status'] === 'planned', 'Follow-up plan failed.');
$GLOBALS['wpdb']->update(CF01_DB::table('followups'), array('status' => 'due'), array('followup_uuid' => $followup['followup_uuid']));
$followup = CF01_Followups::get($followup['followup_uuid']);
$outcome = CF01_Followups::submit_outcome(1, $followup['followup_uuid'], array('answers' => array('change' => 'reported'), 'adherence' => 'reported', 'red_flags' => array()), (int) $followup['row_version']);
$ok($outcome['review_status'] === 'pending', 'Patient outcome was not pending review.');
$outcome = CF01_Followups::review(2, $outcome['outcome_uuid'], array('assessment' => 'Reviewed by clinician.', 'next_plan' => 'Continue review.', 'contact_required' => false), 1);
$ok($outcome['review_status'] === 'reviewed', 'Clinician outcome review failed.');

$rights = CF01_Rights::request(1, $patient['clinical_uuid'], 'export', array('scope' => 'own_record'));
$rights = CF01_Rights::decide(3, $rights['case_uuid'], 'approved', array('reason' => 'Verified request.'), 1);
$manifest = CF01_Rights::export_manifest_for_case(4, $rights['case_uuid'], array('demographics', 'encounters', 'prescriptions', 'followups'));
$ok(isset($manifest['manifest_hash']) && isset($manifest['records']['demographics']), 'Approved export manifest failed.');
$prepared = CF01_Rights::fulfill_export(4, $rights['case_uuid'], 'export-opaque-1', 2);
$reference = CF01_Rights::consume_export(1, $rights['case_uuid'], $prepared['token'], 3);
$ok($reference === 'delivery-grant-opaque', 'One-time export retrieval failed.');
cf01_expect_exception(fn() => CF01_Rights::consume_export(1, $rights['case_uuid'], $prepared['token'], 4), 'already used'); $count++;

$grant = CF01_Break_Glass::request(2, $patient['clinical_uuid'], 'Emergency minimum view required.', array('emergency' => true, 'requested_fields' => array('summary', 'active_prescriptions'), 'bulk' => false, 'export' => false));
$assertion = CF01_Break_Glass::assertion(2, $grant['grant_uuid'], array('summary', 'active_prescriptions', 'encounters'));
$ok($assertion['export_allowed'] === false && count($assertion['fields']) === 2, 'Break-glass minimum view failed.');

$schema = CF01_Migrations::schema('');
$ok(count($schema) === 18, 'Schema inventory is incomplete.');
$ok(count(CF01_Encounters::state_map()) === 6, 'Encounter state machine missing.');
$ok(isset(CF01_Prescriptions::state_map()['signed']), 'Prescription state machine missing.');
$ok(isset(CF01_Followups::state_map()['overdue']), 'Follow-up state machine missing.');

echo "CF-01 unit review: {$count} PASS, 0 FAIL\n";
