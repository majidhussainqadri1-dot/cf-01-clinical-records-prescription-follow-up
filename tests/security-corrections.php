<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$count = 0;

$assert = static function (bool $condition, string $message) use (&$count): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $count++;
};

$source = static function (string $relative) use ($root): string {
    $content = file_get_contents($root . '/' . $relative);
    if (!is_string($content)) {
        throw new RuntimeException('Unable to read review source: ' . $relative);
    }
    return $content;
};

$authorization = $source('sabri-clinical-records/includes/class-cf01-authorization.php');
$consents = $source('sabri-clinical-records/includes/class-cf01-consents.php');
$relationships = $source('sabri-clinical-records/includes/class-cf01-relationships.php');
$encounters = $source('sabri-clinical-records/includes/class-cf01-encounters.php');
$rights = $source('sabri-clinical-records/includes/class-cf01-rights.php');
$breakGlass = $source('sabri-clinical-records/includes/class-cf01-break-glass.php');

$assert(str_contains($authorization, 'WHERE patient_uuid = %s AND purpose = %s ORDER BY id DESC LIMIT 1'), 'Latest consent must be selected without filtering away later withdrawal or decline.');
$assert(str_contains($authorization, '($row[\'status\'] ?? \'\') !== \'granted\''), 'Latest consent status must be explicitly granted.');
$assert(str_contains($authorization, '$starts_at === null || $starts_at > $now'), 'Treating relationship must have started before use.');
$assert(str_contains($authorization, 'new DateTimeImmutable($value, new DateTimeZone(\'UTC\'))'), 'Authorization timestamps must use timezone-aware parsing.');
$assert(str_contains($authorization, 'function_exists(\'user_can\')'), 'Explicit-user WordPress capability evaluation must be preferred.');
$assert(str_contains($authorization, '\'record_own_consent\''), 'Patient consent must use a dedicated own-subject action.');
$assert(str_contains($authorization, '\'view_own_access_history\''), 'Patient access history must use a dedicated own-subject action.');

$assert(str_contains($consents, '&& !empty($guardian[\'platform_uuid\'])'), 'Guardian authorization must bind to a verified platform identity.');
$assert(!str_contains($consents, '$guardian_actor || $guardian_reference'), 'A disclosed guardian reference must never authorize an unrelated actor.');
$assert(str_contains($consents, 'apply_filters(\'cf01_legal_majority_age\''), 'Legal-majority age must be jurisdiction-policy configurable.');
$assert(str_contains($consents, 'CF01_Authorization::actor($actor_id, \'record_own_consent\')'), 'Own or guardian consent action must recheck current actor eligibility.');

$assert(str_contains($relationships, 'authorize_relationship_actor($actor_id, $row'), 'Every relationship activation or transition must authorize the current actor.');
$assert(str_contains($relationships, 'require_target_practitioner'), 'Assigned doctor eligibility must be checked without actor impersonation.');
$assert(str_contains($relationships, 'CF01_DB::transaction(function () use ($actor_id, $relationship_uuid'), 'Relationship activation must be atomic.');
$assert(str_contains($relationships, 'function_exists(\'user_can\')') && str_contains($relationships, '&& !$manager'), 'Unassigned clinicians must not change another relationship.');

$assert(str_contains($encounters, 'CF01_Authorization::consent($patient_uuid, \'teleconsultation\')'), 'Teleconsultation encounters must require teleconsultation consent.');
$assert(str_contains($encounters, 'array(\'status\' => \'addended\')'), 'Adding an addendum must version and mark its signed parent.');
$assert(str_contains($encounters, 'mark_entered_in_error') && str_contains($encounters, 'CF01_Authorization::relationship((string) $row[\'patient_uuid\'], $actor_id, \'clinical_care\')'), 'Entered-in-error action must remain relationship-scoped.');
$assert(str_contains($encounters, 'return CF01_DB::transaction(function () use ($actor_id, $old, $observation_uuid'), 'Observation replacement and supersession must be atomic.');
$assert(str_contains($encounters, 'A new assessment requires an open encounter.'), 'Assessment creation must reject closed or tombstoned encounters.');
$assert(str_contains($encounters, 'Assessment must be signed while its encounter remains open.'), 'Assessment signing must remain encounter-state bound.');

$assert(str_contains($breakGlass, 'CF01_Patients::get($patient_uuid);'), 'Break-glass must verify the patient record exists.');
$assert(str_contains($breakGlass, 'CF01_Authorization::clinician($actor_id, \'use_break_glass\''), 'Break-glass reads must recheck current professional eligibility.');
$assert(str_contains($breakGlass, 'array_intersect(array_map(\'sanitize_key\', $requested_fields), $granted_fields)'), 'Break-glass reads must not exceed fields authorized at grant time.');
$assert(str_contains($breakGlass, 'if (!$updated) {') && str_contains($breakGlass, 'BreakGlassExpired'), 'Break-glass expiry events must only emit after a successful state transition.');

$assert(str_contains($rights, 'CF01_Authorization::actor($actor_id, \'request_clinical_right\')'), 'Every rights request, including self-service, must recheck actor eligibility.');
$assert(str_contains($rights, 'guardian_or_representative($actor_id, $patient_uuid'), 'Representative checks must use the explicit requesting actor.');
$assert(!str_contains($rights, '(!$same_actor && !$same_reference)'), 'Reference-only representative authority must be prohibited.');
$providerPosition = strpos($rights, 'CF01_Contracts::secure_media(\'delivery\'');
$consumePosition = strpos($rights, 'array(\'export_token_consumed_at\' => CF01_DB::now())');
$assert(is_int($providerPosition) && is_int($consumePosition) && $providerPosition < $consumePosition, 'Export token must not be consumed before delivery provider acceptance.');
$assert(str_contains($rights, 'Correction case and encounter patient do not match.'), 'Correction cases must be patient-bound to the target encounter.');
$assert(str_contains($rights, 'CF01_DB::transaction(function () use ($actor_id, $case_uuid, $encounter_uuid'), 'Correction addendum and case fulfillment must be atomic.');
$assert(str_contains($rights, 'bounded_rows($key, $patient_uuid, 10000)'), 'Synchronous exports must fail instead of silently truncating clinical records.');
$assert(str_contains($rights, 'CF01_Authorization::actor($actor_id, \'view_own_access_history\')'), 'Patient access-history reads must pass the own-record authorization gate.');

fwrite(STDOUT, "CF-01 security correction assertions: {$count} PASS\n");
