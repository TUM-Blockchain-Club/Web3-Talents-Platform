<?php
/** Read-only production checks; no account, email, token or database changes. */
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
if (in_array('--candidate', $argv, true)) {
    require('/tmp/w3t-setup-applicant-service.php');
}
use local_web3talents\local\applicant_service as applicants;
$CFG->noemailever = true;
$CFG->nosmsever = true;
\core\session\manager::set_user(get_admin());
$overview = applicants::account_setup_overview();
$rows = applicants::account_setup_rows();
if (count($rows) !== $overview['total'] || $overview['total'] !== $overview['activated'] + $overview['pending']) {
    throw new RuntimeException('Totals do not reconcile.');
}
foreach (['setupactivated' => 'activated', 'setuppending' => 'pending'] as $filter => $key) {
    $records = applicants::search_applicants('', 10000, 0, $filter);
    if (count($records) !== $overview[$key] || applicants::count_applicants('', $filter) !== $overview[$key]) {
        throw new RuntimeException('Count and list differ.');
    }
    foreach ($records as $record) {
        $expected = $key === 'activated'
            ? (int)$DB->get_field_sql('SELECT MIN(agreedtime) FROM {local_web3talents_agree} WHERE userid = ?', [$record->userid]) : 0;
        if ($record->activatedtime !== $expected) { throw new RuntimeException('Activation timestamp differs.'); }
    }
    if (applicants::count_applicants('impossible-match-setup-check-826143', $filter) !== 0) {
        throw new RuntimeException('Search not combined with setup filter.');
    }
    $page1 = array_keys(applicants::search_applicants('', 2, 0, $filter));
    $page2 = array_keys(applicants::search_applicants('', 2, 2, $filter));
    if (array_intersect($page1, $page2)) { throw new RuntimeException('Pagination duplicates.'); }
}
if (applicants::activation_times([]) !== []) { throw new RuntimeException('Empty timestamps should be empty.'); }
// Independent scope check: all reported users must be active students, not mentors or deleted accounts.
$course = \local_web3talents\local\participation_service::get_configured_course();
$context = context_course::instance($course->id);
foreach ($rows as $row) {
    $user = $DB->get_record('user', ['id' => $row->userid], 'id,deleted,suspended', MUST_EXIST);
    if ($user->deleted || $user->suspended || !is_enrolled($context, $user, '', true)
            || has_capability('local/web3talents:viewmentorrooms', $context, $row->userid)) {
        throw new RuntimeException('Non-participant included.');
    }
}
if (!in_array('--candidate', $argv, true)) {
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(new moodle_url('/local/web3talents/index.php'));
    $OUTPUT = $PAGE->get_renderer('core');
    $dashboard = new \local_web3talents\output\dashboard();
    $data = $dashboard->export_for_template($OUTPUT);
    $html = $OUTPUT->render_from_template('local_web3talents/dashboard', $data);
    foreach (['Participant account setup', 'Accounts activated', 'Pending account setup', 'Counts checked'] as $label) {
        if (!str_contains($html, $label)) { throw new RuntimeException('Rendered dashboard label missing.'); }
    }
    if (count($data['setupcards']) !== 2 || $data['setuptotal'] !== $overview['total']) {
        throw new RuntimeException('Dashboard counts differ.');
    }
}
echo json_encode(['passed' => true, 'counts' => $overview, 'filters_pagination_and_timestamps' => 'passed',
    'writes' => 0, 'emails' => 0], JSON_PRETTY_PRINT) . PHP_EOL;
