<?php
// Standalone query-contract checks. No Moodle config, live data, credentials or email.
require(__DIR__ . '/../plugins/local_web3talents/classes/local/applicant_service.php');
use local_web3talents\local\applicant_service as applicants;
function get_string($key, $component) { return $key; }
class invalid_parameter_exception extends RuntimeException {}
$DB = new class {
    public array $countquery = [];
    public array $listquery = [];
    public function sql_like_escape($text) { return addcslashes($text, '%_\\'); }
    public function sql_like($field, $param, $casesensitive) { return "$field ILIKE $param"; }
    public function count_records_select(...$args) { $this->countquery = $args; return 81; }
    public function get_records_select(...$args) { $this->listquery = $args; return []; }
};
function check(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException('FAIL: ' . $label); }
    echo 'PASS: ' . $label . PHP_EOL;
}
foreach (['', '   ', 'Anna', 'a%_b', "O'Reilly"] as $query) {
    check(applicants::count_applicants($query) === 81, 'Count uses database aggregate');
    applicants::search_applicants($query, 50, 50);
    check($DB->countquery === array_slice($DB->listquery, 0, 3), 'Count and listing use identical search criteria');
    check(array_slice($DB->listquery, 3) === ['id ASC', '*', 50, 50],
        'Stable sorting, second-page offset and limit retained');
}
applicants::count_applicants(' a%_b ');
check($DB->countquery[2]['firstname'] === '%a\\%\\_b%' &&
    !str_contains($DB->countquery[1], 'a%_b'), 'Search is trimmed, parameterized and wildcard escaped');
applicants::count_applicants('  ');
check($DB->countquery[1] === '' && $DB->countquery[2] === [], 'Empty search counts all applicants');
foreach (array_keys(applicants::statuses()) as $status) {
    applicants::count_applicants('Alex', $status);
    applicants::search_applicants('Alex', 50, 50, $status);
    check($DB->countquery === array_slice($DB->listquery, 0, 3), 'Status count matches paginated results: ' . $status);
    check(str_contains($DB->countquery[1], ') AND status = :filterstatus') &&
        $DB->countquery[2]['filterstatus'] === $status, 'Status and search are combined safely');
}
foreach (['toreview' => ['pending', 'discussion'], 'allaccepted' => ['accepted', 'accountcreated', 'accountactivated']] as $filter => $members) {
    applicants::count_applicants('Alex', $filter);
    applicants::search_applicants('Alex', 50, 50, $filter);
    check($DB->countquery === array_slice($DB->listquery, 0, 3), 'Aggregate filter count matches list: ' . $filter);
    check(str_contains($DB->countquery[1], ') AND status IN (') &&
        array_values(array_filter($DB->countquery[2], fn($key) => str_starts_with($key, 'filterstatus'), ARRAY_FILTER_USE_KEY)) === $members,
        'Aggregate filter has exact parameterized status membership: ' . $filter);
    check(!array_key_exists($filter, applicants::statuses()) && array_key_exists($filter, applicants::review_filters()),
        'Aggregate filter cannot become a stored application status');
}
try {
    applicants::count_applicants('', 'not-a-status');
    throw new RuntimeException('Unknown filter accepted');
} catch (invalid_parameter_exception $e) { check(true, 'Unknown filters rejected'); }
