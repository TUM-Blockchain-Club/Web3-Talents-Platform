<?php
// Validates Phase 5 accepted-applicant workflow.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');
require_once($CFG->libdir . '/enrollib.php');

use local_web3talents\local\applicant_service;

global $DB;

function web3t_phase5_assert(bool $condition, string $message): void {
    if (!$condition) {
        throw new moodle_exception("Phase 5 validation failed: {$message}");
    }
    echo "OK: {$message}" . PHP_EOL;
}

function web3t_phase5_expect_exception(callable $callback, string $message): void {
    try {
        $callback();
    } catch (Throwable $exception) {
        echo "OK: {$message}" . PHP_EOL;
        return;
    }
    throw new moodle_exception("Phase 5 validation failed: {$message}");
}

\core\session\manager::set_user(get_admin());

web3t_phase5_assert($DB->get_manager()->table_exists('local_web3talents_app'), 'accepted-applicant table exists');

$manual = $DB->get_record('local_web3talents_app', ['email' => 'w3t.phase5.student@example.test'], '*', MUST_EXIST);
$csv = $DB->get_record('local_web3talents_app', ['email' => 'w3t.phase5.csv@example.test'], '*', MUST_EXIST);
$excel = $DB->get_record('local_web3talents_app', ['email' => 'w3t.phase5.excel@example.test'], '*', MUST_EXIST);
$blocked = $DB->get_record('local_web3talents_app', ['email' => 'w3t.phase5.blocked@example.test'], '*', MUST_EXIST);

web3t_phase5_assert($manual->status === applicant_service::STATUS_ACCOUNT_CREATED, 'manual accepted applicant has account-created status');
web3t_phase5_assert($csv->source === 'csv', 'CSV import created an applicant record');
web3t_phase5_assert($excel->source === 'excel', 'Excel import created an applicant record');
web3t_phase5_assert((int)$manual->retentionuntil > time(), 'accepted applicant has one-month retention marker');
web3t_phase5_assert(!empty($manual->userid), 'created applicant record stores Moodle user id');
web3t_phase5_assert(!empty($manual->activationemailsenttime), 'activation email send time is recorded');

$user = $DB->get_record('user', ['id' => $manual->userid, 'deleted' => 0], '*', MUST_EXIST);
web3t_phase5_assert($user->email === 'w3t.phase5.student@example.test', 'created Moodle user has accepted applicant email');

$course = $DB->get_record('course', ['shortname' => 'W3T-FUNDAMENTALS-DEV'], '*', MUST_EXIST);
$coursecontext = context_course::instance($course->id);
web3t_phase5_assert(is_enrolled($coursecontext, $user, '', true), 'created student is enrolled in fundamentals course');
web3t_phase5_assert(user_has_role_assignment($user->id, (int)$DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST), $coursecontext->id), 'created student has student role');
web3t_phase5_assert(!has_capability('local/web3talents:manageacceptedapplicants', context_system::instance(), $user), 'created student cannot manage applicants');

$searchresults = applicant_service::search_applicants('phase5.csv');
web3t_phase5_assert(count($searchresults) === 1, 'search finds applicant by email');

web3t_phase5_expect_exception(
    fn() => applicant_service::create_student_account((int)$manual->id, get_admin()->id),
    'duplicate account creation is blocked'
);
web3t_phase5_expect_exception(
    fn() => applicant_service::create_student_account((int)$blocked->id, get_admin()->id),
    'non-accepted applicant account creation is blocked'
);

echo 'Phase 5 accepted-applicant workflow validation complete.' . PHP_EOL;
