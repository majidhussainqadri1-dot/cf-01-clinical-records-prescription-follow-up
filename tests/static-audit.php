<?php
require __DIR__ . '/bootstrap.php';
$root = dirname(__DIR__);
$count = 0;
$failures = array();
$check = function (bool $condition, string $message) use (&$count, &$failures): void { $count++; if (!$condition) $failures[] = $message; };
$read = fn(string $path): string => file_get_contents($root . '/' . $path) ?: '';
$allPhp = '';
foreach (glob($root . '/sabri-clinical-records/**/*.php') ?: array() as $file) $allPhp .= file_get_contents($file) . "\n";
$allPhp .= $read('sabri-clinical-records/sabri-clinical-records.php');
$js = $read('sabri-clinical-records/assets/js/clinical.js');
$trace = $read('docs/REQUIREMENTS-TRACEABILITY.md');
$security = $read('docs/SECURITY-PRIVACY-ARCHITECTURE.md');
$migration = $read('docs/MIGRATION-ROLLBACK.md');

for ($i = 1; $i <= 32; $i++) {
    $id = sprintf('CF01-FR-%03d', $i);
    $check(substr_count($trace, $id) === 1, $id . ' must appear exactly once in traceability.');
}

$requiredFiles = array(
    'sabri-clinical-records/sabri-clinical-records.php',
    'sabri-clinical-records/includes/class-cf01-db.php',
    'sabri-clinical-records/includes/class-cf01-crypto.php',
    'sabri-clinical-records/includes/class-cf01-contracts.php',
    'sabri-clinical-records/includes/class-cf01-authorization.php',
    'sabri-clinical-records/includes/class-cf01-patients.php',
    'sabri-clinical-records/includes/class-cf01-relationships.php',
    'sabri-clinical-records/includes/class-cf01-consents.php',
    'sabri-clinical-records/includes/class-cf01-encounters.php',
    'sabri-clinical-records/includes/class-cf01-attachments.php',
    'sabri-clinical-records/includes/class-cf01-prescriptions.php',
    'sabri-clinical-records/includes/class-cf01-followups.php',
    'sabri-clinical-records/includes/class-cf01-rights.php',
    'sabri-clinical-records/includes/class-cf01-break-glass.php',
    'sabri-clinical-records/includes/class-cf01-audit-outbox.php',
    'sabri-clinical-records/includes/class-cf01-retention.php',
    'sabri-clinical-records/includes/class-cf01-rest.php',
    'sabri-clinical-records/includes/class-cf01-ui-health.php',
    'sabri-clinical-records/includes/class-cf01-migrations.php',
    'sabri-clinical-records/assets/js/clinical.js',
    'sabri-clinical-records/assets/css/clinical.css',
    'tools/validate_runtime.py', 'tools/package.sh',
);
foreach ($requiredFiles as $file) $check(is_file($root . '/' . $file), 'Missing required file: ' . $file);

$patterns = array(
    "update_option('cf01_activation_state', 'disabled'" => 'Activation must default disabled.',
    "CF01_SCHEMA_INSTALL_APPROVED" => 'Schema install gate missing.',
    "aes-256-gcm" => 'AES-GCM encryption missing.',
    "cf01_master_key_material" => 'Managed key filter missing.',
    "blind_index" => 'Blind index missing.',
    "canonical_json" => 'Canonical signing serialization missing.',
    "recent_auth" => 'Recent authentication missing.',
    "step_up" => 'Step-up check missing.',
    "professional_uuid" => 'Professional identity binding missing.',
    "platform_subject_hash" => 'Clinical identity compartment missing.',
    "Duplicate clinical identity" => 'Duplicate quarantine missing.',
    "CareRelationshipActivated" => 'Relationship event missing.',
    "ClinicalConsentWithdrawn" => 'Consent withdrawal missing.',
    "Signed or tombstoned encounter content is immutable" => 'Encounter immutability missing.',
    "EncounterAddendumAdded" => 'Encounter addendum missing.',
    "ClinicalObservationCorrected" => 'Observation correction missing.',
    "scanner_evidence_cipher" => 'Scanner evidence missing.',
    "Secure clinical media provider" => 'Secure media fail-closed missing.',
    "autonomous_ai" => 'Autonomous AI prohibition missing.',
    "PrescriptionSuperseded" => 'Prescription supersession missing.',
    "prescription-discontinuation" => 'Encrypted discontinuation reason missing.',
    "PatientOutcomeSubmitted" => 'Patient outcome missing.',
    "automatic_prescription_change" => 'Automatic prescription mutation boundary missing.',
    "FollowUpStatusReconciled" => 'Follow-up due reconciliation missing.',
    "cf01_field_policy" => 'Field authorization policy missing.',
    "ClinicalRecordViewed" => 'Access-history write missing.',
    "ClinicalExportDownloaded" => 'Export access event missing.',
    "export_token_consumed_at" => 'One-time export token missing.',
    "BreakGlassRecordViewed" => 'Break-glass use audit missing.',
    "MAX_TTL" => 'Break-glass TTL missing.',
    "export_allowed' => false" => 'Break-glass export denial missing.',
    "update_versioned" => 'Optimistic concurrency missing.',
    "Idempotency-Key" => 'API idempotency key missing.',
    "request_too_large" => 'Request size gate missing.',
    "Cache-Control' => 'no-store" => 'REST no-store header missing.',
    "X-Robots-Tag" => 'Noindex header missing.',
    "'Referrer-Policy' => 'no-referrer'" => 'No-referrer policy missing.',
    "'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()'" => 'Restrictive browser permissions policy missing.',
    "Clinical audit persistence failed" => 'Audit failure must block completion.',
    "dead_letter" => 'Outbox dead-letter missing.',
    "ClinicalRetentionHoldPlaced" => 'Retention hold missing.',
    "Verified native-owner purge receipt" => 'Purge receipt requirement missing.',
    "cf01_migration_lock" => 'Migration lock missing.',
    "next_cursor" => 'Resumable migration cursor missing.',
    "rollback" => 'Rollback surface missing.',
    "native_enforcement_preserved" => 'Native enforcement assurance missing.',
);
foreach ($patterns as $pattern => $message) $check(str_contains($allPhp, $pattern), $message);

foreach (array('localStorage', 'sessionStorage', 'indexedDB', 'serviceWorker', 'caches.open') as $token) {
    $check(!str_contains($js, $token), 'Offline clinical storage token is prohibited: ' . $token);
}
$check(str_contains($js, "cache: 'no-store'"), 'Browser request no-store missing.');
$check(str_contains($js, "referrerPolicy: 'no-referrer'"), 'Browser no-referrer missing.');
$check(str_contains($js, 'pagehide'), 'Page-exit data clearing missing.');
$check(str_contains($security, 'C5'), 'C5 classification missing in architecture.');
$check(str_contains($security, 'No real/synthetic patient fixture'), 'Public repository data prohibition missing.');
$check(str_contains($migration, 'dry run'), 'Migration dry-run law missing.');
$check(str_contains($migration, 'reconciliation'), 'Migration reconciliation missing.');
$check(str_contains($migration, 'rollback'), 'Migration rollback law missing.');

$forbidden = array('eval(', 'shell_exec(', 'passthru(', 'wp_remote_get($request', "permission_callback' => '__return_true'", '_smc_totp_secret', '_smc_identity_verified', '_smc_doctor_verified');
foreach ($forbidden as $token) $check(!str_contains($allPhp, $token), 'Forbidden implementation token: ' . $token);

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
echo "CF-01 static ownership/security review: {$count} PASS, 0 FAIL\n";
