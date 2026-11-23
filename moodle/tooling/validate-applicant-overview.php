<?php
// Isolated in-memory aggregate tests. No Moodle config, database, accounts or emails.
require(__DIR__ . '/../plugins/local_web3talents/classes/local/applicant_service.php');
use local_web3talents\local\applicant_service as applicants;
function get_string($key, $component) { return $key; }
function check($ok, $label) {
    if (!$ok) { throw new RuntimeException('FAIL: ' . $label); }
    echo 'PASS: ' . $label . PHP_EOL;
}
$DB = new class {
    public array $counts = [];
    public function get_records_sql($sql) {
        check(str_contains($sql, 'COUNT(1) AS applicantcount') && str_contains($sql, 'GROUP BY status')
            && !str_contains($sql, 'WHERE'), 'Overview is one global aggregate, not applicant response loading');
        return array_map(fn($status, $count) => (object)['status'=>$status, 'applicantcount'=>$count],
            array_keys($this->counts), array_values($this->counts));
    }
};
check(applicants::applicant_overview() === ['total'=>0, 'accepted'=>0, 'rejected'=>0, 'toreview'=>0, 'other'=>0], 'Empty queue shows zero totals');
$DB->counts = ['pending'=>'71', 'discussion'=>'8', 'accepted'=>'5', 'accountcreated'=>'12',
    'accountactivated'=>'4', 'rejected'=>'9', 'deferred'=>'3', 'removed'=>'2', 'futurestatus'=>'1'];
$totals = applicants::applicant_overview();
check($totals === ['total'=>115, 'accepted'=>21, 'rejected'=>9, 'toreview'=>79, 'other'=>6], 'Every status counted once in the correct bucket');
check($totals['total'] === array_sum(array_diff_key($totals, ['total'=>true])), 'Buckets reconcile with total');
$DB->counts['pending']--;
$DB->counts['accepted']++;
$next = applicants::applicant_overview();
check($next['total'] === 115 && $next['toreview'] === 78 && $next['accepted'] === 22, 'Approval moves a record from review to accepted');
$DB->counts['accepted']--;
$DB->counts['accountcreated']++;
check(applicants::applicant_overview() === $next, 'Provisioning does not reduce accepted total');
$page = file_get_contents(__DIR__ . '/../plugins/local_web3talents/applicants.php');
check(str_contains($page, "applicant_service::applicant_overview()") && str_contains($page, "'accepted' => 'allaccepted'")
    && str_contains($page, "'toreview' => 'toreview'"), 'Cards link to matching aggregate filters');
check(strpos($page, 'reviews::require_reviewer();') < strpos($page, 'applicant_service::applicant_overview()'), 'Overview remains reviewer-only');
