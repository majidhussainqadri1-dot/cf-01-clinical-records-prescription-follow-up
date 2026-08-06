<?php
$root = dirname(__DIR__);
$failures = array();
$count = 0;
$check = static function (bool $condition, string $message) use (&$failures, &$count): void {
    $count++;
    if (!$condition) {
        $failures[] = $message;
    }
};
$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);
    return is_string($content) ? $content : '';
};

$css = $read('sabri-clinical-records/assets/css/clinical.css');
$js = $read('sabri-clinical-records/assets/js/clinical.js');
$rest = $read('sabri-clinical-records/includes/class-cf01-rest.php');
$patients = $read('sabri-clinical-records/includes/class-cf01-patients.php');
$crypto = $read('sabri-clinical-records/includes/class-cf01-crypto.php');
$authorization = $read('sabri-clinical-records/includes/class-cf01-authorization.php');
$contracts = $read('sabri-clinical-records/includes/class-cf01-contracts.php');
$roles = $read('sabri-clinical-records/includes/class-cf01-role-context.php');
$relationships = $read('sabri-clinical-records/includes/class-cf01-relationships.php');
$consents = $read('sabri-clinical-records/includes/class-cf01-consents.php');
$attachments = $read('sabri-clinical-records/includes/class-cf01-attachments.php');
$encounters = $read('sabri-clinical-records/includes/class-cf01-encounters.php');
$prescriptions = $read('sabri-clinical-records/includes/class-cf01-prescriptions.php');
$followups = $read('sabri-clinical-records/includes/class-cf01-followups.php');
$rights = $read('sabri-clinical-records/includes/class-cf01-rights.php');
$migrations = $read('sabri-clinical-records/includes/class-cf01-migrations.php');
$breakGlass = $read('sabri-clinical-records/includes/class-cf01-break-glass.php');
$plugin = $read('sabri-clinical-records/sabri-clinical-records.php');
$pluginReadme = $read('sabri-clinical-records/readme.txt');
$packageScript = $read('tools/package.sh');

$check(str_contains($plugin, '* Version: 1.0.1'), 'WordPress plugin metadata does not identify runtime 1.0.1.');
$check(str_contains($plugin, "define('CF01_VERSION', '1.0.1')"), 'CF01_VERSION is not 1.0.1.');
$check(str_contains($plugin, "define('CF01_SCHEMA_VERSION', '1.0.0')"), 'Unchanged schema version is not explicitly preserved at 1.0.0.');
$check(str_contains($plugin, "define('CF01_CONTRACT_VERSION', '1.0.0')"), 'Unchanged public contract version is not explicitly preserved at 1.0.0.');
$check(str_contains($pluginReadme, 'Stable tag: 1.0.1'), 'WordPress stable tag is not 1.0.1.');
$check(str_contains($pluginReadme, '= 1.0.1 ='), 'Runtime 1.0.1 changelog entry is missing.');
$check(str_contains($packageScript, 'VERSION="1.0.1"'), 'Deterministic package identity is not 1.0.1.');
$check(!str_contains($packageScript, 'VERSION="1.0.0"'), 'Superseded 1.0.0 package identity remains active.');

$check(str_contains($css, '--cf01-primary:'), 'Green primary token is missing.');
$check(str_contains($css, '#0b6b3a'), 'Approved green fallback is missing.');
$check(!str_contains($css, '--cf01-orange'), 'Superseded orange primary token remains.');
$check(str_contains($css, '.cf01-icon'), 'Meaningful icon component styling is missing.');
$check(str_contains($css, '44px'), 'Minimum touch-target evidence is missing.');
$check(str_contains($css, '[dir="rtl"]'), 'RTL-aware presentation is missing.');

$check(str_contains($js, "createElementNS('http://www.w3.org/2000/svg'"), 'Inline semantic icons are missing.');
$check(str_contains($js, "back: isUrdu ? 'واپس' : 'Back'"), 'Back control localization is missing.');
$check(str_contains($js, "home: isUrdu ? 'ہوم' : 'Home'"), 'Home control localization is missing.');
$check(str_contains($js, "view === 'my_record'"), 'Own-record route renderer is missing.');
$check(str_contains($js, "view === 'patient'"), 'Patient route renderer is missing.');
$check(str_contains($js, "view === 'encounter'"), 'Encounter route renderer is missing.');
$check(str_contains($js, "view === 'prescription'"), 'Prescription route renderer is missing.');
$check(str_contains($js, "view === 'followup'"), 'Follow-up route renderer is missing.');
$check(!str_contains($js, 'innerHTML'), 'Clinical rendering must not use innerHTML.');
foreach (array('localStorage', 'sessionStorage', 'indexedDB', 'serviceWorker', 'document.cookie') as $token) {
    $check(!str_contains($js, $token), 'Forbidden browser persistence token: ' . $token);
}

$check(str_contains($rest, "array('/me', 'GET', 'my_record')"), 'Own-record REST route is missing.');
$check(str_contains($rest, "array('/prescriptions/(?P<id>[a-f0-9-]{36})', 'GET', 'prescription')"), 'Prescription read route is missing.');
$check(str_contains($rest, "array('/followups/(?P<id>[a-f0-9-]{36})', 'GET', 'followup')"), 'Follow-up read route is missing.');
$check(str_contains($rest, 'authorize_patient_read'), 'Shared patient/doctor read authorization is missing.');
$check(str_contains($rest, 'CF01_Audit::access($actor'), 'Read-access audit evidence is missing.');
$check(str_contains($rest, "'Referrer-Policy' => 'no-referrer'"), 'Private REST referrer policy is missing.');
$check(str_contains($patients, 'for_platform_subject'), 'Canonical own-record resolver is missing.');
$check(str_contains($patients, "status NOT IN (%s,%s)"), 'Quarantined/merged clinical identities are not excluded.');

$check(str_contains($crypto, 'function current_key_version'), 'Versioned clinical encryption-key selection is missing.');
$check(str_contains($crypto, "'kv' => \$key_version"), 'Clinical encryption envelopes do not record their key version.');
$check(str_contains($crypto, "!array_key_exists('kv', \$payload)"), 'Legacy clinical encryption envelopes are not backward-readable after rotation.');
$check(str_contains($crypto, 'function rotate_key'), 'Governed clinical encryption-key rotation is missing.');
$check(str_contains($crypto, 'function encryption_key'), 'Versioned encryption subkey derivation is missing.');
$check(str_contains($crypto, 'Required historical clinical encryption key is unavailable.'), 'Missing historical key material does not fail closed.');

$check(str_contains($contracts, 'function relationship_source'), 'Canonical relationship-source assertion is missing.');
$check(str_contains($contracts, 'function subject_identity'), 'Subject identity assertion contract is missing.');
$check(str_contains($contracts, 'function guardian_authority'), 'Guardian-authority assertion contract is missing.');
$check(str_contains($contracts, 'function clinical_template'), 'Versioned clinical-template contract is missing.');
$check(str_contains($contracts, 'function terminology_mapping'), 'Terminology mapping contract is missing.');
$check(str_contains($contracts, 'function emergency_policy'), 'Emergency/red-flag policy contract is missing.');
$check(str_contains($contracts, 'function local_timestamp'), 'UTC/local/time-zone provenance helper is missing.');

$check(str_contains($authorization, 'relationship_for_record'), 'Record-bound treating relationship enforcement is missing.');
$check(str_contains($authorization, 'validate_relationship_scope'), 'Treating relationship scope enforcement is missing.');
$check(str_contains($authorization, 'enforce_rate_limit'), 'Clinical abuse/rate-limit control is missing.');
$check(str_contains($authorization, "'consume_clinical_export'"), 'High-risk export consumption is not step-up governed.');
$check(str_contains($authorization, "'relink_attachment'"), 'High-risk attachment relink is not step-up governed.');
$check(str_contains($authorization, "'rotate_clinical_key'"), 'Clinical encryption-key rotation is not step-up governed.');
$check(str_contains($authorization, "'cf01_manage_clinical_keys'"), 'Clinical encryption-key rotation lacks a dedicated capability.');
$check(str_contains($roles, "if (\$requested_role === '' || \$requested_role !== \$role)"), 'Care-team roles may still be inferred without an explicit requested role.');
$check(str_contains($roles, 'cf01_clinical_oversight_assertion'), 'Patient-scoped records/auditor oversight assertion is missing.');

$check(str_contains($relationships, 'CF01_Contracts::relationship_source'), 'Relationship creation is not owner-asserted.');
$check(str_contains($relationships, 'require_termination_reconciliation'), 'Relationship termination does not reconcile open clinical work.');
$check(str_contains($consents, 'Clinical age evidence is unavailable.'), 'Missing date-of-birth still fails open for minor governance.');
$check(str_contains($consents, 'Guardian authority is no longer current.'), 'Consent does not revalidate guardian authority at action time.');

$check(str_contains($attachments, 'CF01_Role_Context::resolve'), 'Attachment delivery lacks patient-scoped role authorization.');
$check(str_contains($attachments, "array('attachments')"), 'Attachment delivery lacks field-level authorization.');
$check(str_contains($attachments, "scan_status'] ?? '') !== 'ready'"), 'Attachment delivery does not fail closed on quarantine/scan state.');
$check(str_contains($attachments, "'actor_user_id' => \$actor_id"), 'Secure-media delivery is not actor-bound.');
$check(str_contains($attachments, "'records'"), 'Attachment relink is not restricted to records authority.');

$check(str_contains($encounters, 'CF01_Contracts::clinical_template'), 'Encounter templates are not owner/version validated.');
$check(str_contains($encounters, 'relationship_for_record'), 'Encounter mutation is not bound to its exact relationship.');
$check(str_contains($encounters, 'CF01_Contracts::terminology_mapping'), 'Encounter terminology mappings are not round-trip governed.');
$check(str_contains($encounters, 'CF01_Contracts::emergency_policy'), 'Encounter red flags do not invoke the emergency contract.');
$check(str_contains($encounters, 'function verify_integrity'), 'Signed encounter restore integrity verifier is missing.');
$check(str_contains($encounters, 'CF01_Contracts::local_timestamp'), 'Encounter signatures lack local-time provenance.');

$check(str_contains($prescriptions, 'reject_ambiguous_abbreviations'), 'Ambiguous prescription abbreviations are not rejected.');
$check(str_contains($prescriptions, 'A valid prescription language tag is required.'), 'Prescription language tagging is not validated.');
$check(str_contains($prescriptions, 'relationship_for_record'), 'Prescription mutation is not bound to its exact encounter relationship.');
$check(str_contains($prescriptions, 'function verify_integrity'), 'Signed prescription restore integrity verifier is missing.');

$check(str_contains($followups, 'normalize_reminders'), 'Follow-up reminder policy normalization is missing.');
$check(str_contains($followups, "'opt_in'"), 'Follow-up reminders do not require opt-in.');
$check(str_contains($followups, "'quiet_start'"), 'Follow-up quiet-hours control is missing.');
$check(str_contains($followups, 'CF01_Contracts::emergency_policy'), 'Patient-reported red flags do not invoke the emergency contract.');

$check(str_contains($rights, "enforce_rate_limit(\$actor_id, 'build_export'"), 'Clinical export generation lacks bounded rate limiting.');
$check(str_contains($rights, "enforce_rate_limit(\$actor_id, 'consume_export'"), 'Clinical export consumption lacks bounded rate limiting.');
$check(str_contains($rights, "'attachments'"), 'Clinical export manifest lacks attachment inventory support.');
$check(str_contains($migrations, 'verify_signed_records'), 'Restore verification does not cryptographically inspect signed clinical records.');
$check(str_contains($migrations, 'CF01_Encounters::verify_integrity'), 'Restore does not verify encounter signatures.');
$check(str_contains($migrations, 'CF01_Prescriptions::verify_integrity'), 'Restore does not verify prescription signatures.');
$check(str_contains($breakGlass, "enforce_rate_limit(\$actor_id, 'break_glass_actor'"), 'Break-glass actor abuse control is missing.');
$check(str_contains($breakGlass, "enforce_rate_limit(\$actor_id, 'break_glass_patient'"), 'Break-glass patient-target abuse control is missing.');

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

echo "CF-01 three-plan correction review: {$count} PASS, 0 FAIL\n";
