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
$check(str_contains($rest, "CF01_Audit::access($actor"), 'Read-access audit evidence is missing.');
$check(str_contains($rest, "'Referrer-Policy' => 'no-referrer'"), 'Private REST referrer policy is missing.');
$check(str_contains($patients, 'for_platform_subject'), 'Canonical own-record resolver is missing.');
$check(str_contains($patients, "status NOT IN (%s,%s)"), 'Quarantined/merged clinical identities are not excluded.');

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

echo "CF-01 three-plan correction review: {$count} PASS, 0 FAIL\n";
