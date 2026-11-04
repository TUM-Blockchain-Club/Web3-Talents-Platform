<?php
// Destructive fixture setup is allowed ONLY in the disposable reviewtest database.
define('CLI_SCRIPT', true);
require_once('/var/www/html/config.php');
if ($CFG->dbname !== 'reviewtest' || getenv('W3T_APPLICATION_REVIEW_TEST') !== '1' || empty($CFG->noemailever)) {
    throw new RuntimeException('Use an isolated reviewtest database with email disabled and explicit test flag.');
}
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');

use local_web3talents\local\applicant_service as applicants;
use local_web3talents\local\application_csv as csv;
use local_web3talents\local\application_review_service as reviews;

function check(bool $ok, string $label): void {
    if (!$ok) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    echo 'PASS: ' . $label . PHP_EOL;
}
function blocked(callable $fn, string $label): void {
    try {
        $fn();
    } catch (Throwable $e) {
        check(true, $label);
        return;
    }
    check(false, $label);
}
function login_as(stdClass $user): void {
    \core\session\manager::set_user($user);
}
function fixture_user(string $name): stdClass {
    global $DB, $CFG;
    $existing = $DB->get_record('user', ['username' => $name]);
    if ($existing) {
        return $existing;
    }
    $id = user_create_user((object)['username' => $name, 'firstname' => ucfirst($name), 'lastname' => 'Fixture',
        'email' => $name . '@example.invalid', 'password' => 'FixtureOnly123!', 'confirmed' => 1,
        'auth' => 'manual', 'mnethostid' => $CFG->mnet_localhost_id]);
    return $DB->get_record('user', ['id' => $id], '*', MUST_EXIST);
}
login_as(get_admin());
set_config('theme', 'boost');
set_config('fundamentals_course_shortname', 'REVIEW-FIXTURE', 'local_web3talents');
set_config('siteadmins', get_admin()->id);
set_config('registerauth', '');
set_config('fullname', 'Application review test');
set_config('shortname', 'ReviewTest');
$course = $DB->get_record('course', ['shortname' => 'REVIEW-FIXTURE']) ?: create_course((object)[
    'fullname' => 'Review fixture course', 'shortname' => 'REVIEW-FIXTURE', 'category' => 1, 'visible' => 1]);
$context = context_course::instance($course->id);
$manager = (int)$DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
$r1 = fixture_user('reviewerone');
$r2 = fixture_user('reviewertwo');
$r3 = fixture_user('reviewerthree');
$student = fixture_user('nonreviewer');
foreach ([$r1, $r2, $r3] as $reviewer) {
    role_assign($manager, $reviewer->id, $context->id);
}
login_as($r1);
$suffix = bin2hex(random_bytes(4));
$header = 'Submission ID,First Name:,Last Name: ,Email: ,Motivation,Consent';
$content = $header . "\n" . 'submission-' . $suffix . ',Alex,Fixture,applicant-' . $suffix . '@example.invalid,"First line,' .
    "\n" . 'second line ""quoted"" <script>alert(1)</script>",Yes';
$rows = csv::parse($content, ',', 'test-cohort');
check(count($rows) === 1 && $rows[0]['error'] === '', 'Tally labels and multiline quoted CSV parse');
$answers = json_decode($rows[0]['responses'], true);
check(count($answers) === 6 && str_contains($answers[4]['answer'], "\n") &&
    str_contains($answers[4]['answer'], '<script>'), 'Every answer is retained as data, not interpreted');
check(reviews::preview($rows)[0]['outcome'] === 'new', 'Preview identifies a new submission');
$beforeusers = $DB->count_records('user');
$import = reviews::import($rows);
check($import['created'] === 1 && $DB->count_records('user') === $beforeusers, 'Import creates pending record only, not user');
$app = $DB->get_record('local_web3talents_app', ['email' => $rows[0]['email']], '*', MUST_EXIST);
check($app->status === 'pending' && empty($app->userid) && empty($app->retentionuntil), 'New application awaits review without activation expiry');
blocked(fn() => applicants::create_student_account($app->id), 'Direct account creation cannot bypass review');
reviews::submit($app->id, 'comment', 'Private reviewer note');
check(!reviews::summary($app->id)['ready'], 'A comment does not count as an approval');
reviews::submit($app->id, 'approve');
reviews::submit($app->id, 'approve');
check(reviews::summary($app->id)['approvals'] === 1 && $DB->count_records('user') === $beforeusers,
    'One reviewer clicking approve twice still counts once');
$comments = $DB->count_records('local_w3t_app_comment', ['applicantid' => $app->id]);
$import = reviews::import($rows);
check($import['created'] === 0 && $import['skipped'] === 1 &&
    $DB->count_records('local_w3t_app_comment', ['applicantid' => $app->id]) === $comments,
    'Re-import preserves comments and approvals');
$changed = $rows;
$changed[0]['responses'] = '[]';
check(reviews::preview($changed)[0]['outcome'] === 'conflict', 'Changed responses are flagged, never silently overwritten');
reviews::import($changed);
check($DB->get_field('local_web3talents_app', 'responses', ['id' => $app->id]) === $rows[0]['responses'],
    'Changed submission import preserves original answers');
login_as($r2);
blocked(fn() => reviews::submit($app->id, 'reject'), 'An objection requires an explanation');
reviews::submit($app->id, 'reject', 'Please discuss this application');
login_as($r3);
reviews::submit($app->id, 'approve');
check(reviews::summary($app->id)['approvals'] === 2 && !reviews::summary($app->id)['ready'] &&
    !$DB->get_field('local_web3talents_app', 'userid', ['id' => $app->id]),
    'An objection blocks provisioning even with two approvals');
check($DB->get_field('local_web3talents_app', 'status', ['id' => $app->id]) === 'discussion', 'Conflicting decisions show Needs discussion');
login_as($student);
blocked(fn() => reviews::submit($app->id, 'approve'), 'Student cannot approve');
blocked(fn() => reviews::submit($app->id, 'comment', 'Not authorised'), 'Student cannot leave review comments');
blocked(fn() => reviews::import($rows), 'Student cannot import applications');
login_as($r2);
reviews::submit($app->id, 'withdraw');
check(reviews::summary($app->id)['ready'] && !$DB->get_field('local_web3talents_app', 'userid', ['id' => $app->id]),
    'Withdrawing objection does not unexpectedly create an account');
login_as($r3);
role_unassign($manager, $r3->id, $context->id);
check(!reviews::summary($app->id)['ready'], 'Revoked reviewer permission invalidates that approval');
role_assign($manager, $r3->id, $context->id);
login_as($r1);
if (!$DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'manual', 'status' => ENROL_INSTANCE_ENABLED])) {
    $countbeforefailure = $DB->count_records('user');
    blocked(fn() => applicants::create_student_account($app->id), 'Enrolment failure aborts account creation');
    check($DB->count_records('user') === $countbeforefailure &&
        !$DB->get_field('local_web3talents_app', 'userid', ['id' => $app->id]), 'Failed enrolment rolls back user and applicant link');
    enrol_get_plugin('manual')->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
}
$user = applicants::create_student_account($app->id);
check($DB->count_records('user') === $beforeusers + 1 && is_enrolled($context, $user),
    'Two eligible approvals create exactly one enrolled student account');
check(empty($user->activationemailsent) &&
    !$DB->get_field('local_web3talents_app', 'activationemailsenttime', ['id' => $app->id]),
    'Disabled email is reported as pending, not incorrectly marked delivered');
check(!applicants::resend_activation_email($app->id), 'Email retry remains pending while outbound mail is disabled');
blocked(fn() => applicants::create_student_account($app->id), 'Repeating provisioning does not create a duplicate account');
blocked(fn() => reviews::submit($app->id, 'reject', 'Too late'), 'Provisioned application decisions are closed');
reviews::submit($app->id, 'comment', 'Post-acceptance note');
check($DB->count_records('user') === $beforeusers + 1, 'Comments on accepted applicants do not create accounts');

// Normal second approval automatically provisions. A missing course is retryable.
$new = applicants::upsert_applicant((object)['firstname' => 'Case', 'lastname' => 'Two',
    'email' => 'second-' . $suffix . '@example.invalid', 'status' => 'accepted']);
check($new->status === 'pending', 'Manually supplied accepted status cannot bypass review');
reviews::submit($new->id, 'approve');
login_as($r2);
$result = reviews::submit($new->id, 'approve');
check(in_array($result['status'], ['created', 'emailpending']) &&
    (bool)$DB->get_field('local_web3talents_app', 'userid', ['id' => $new->id]), 'Second independent approval automatically provisions');

login_as(get_admin());
$retry = applicants::upsert_applicant((object)['firstname' => 'Retry', 'lastname' => 'Fixture',
    'email' => 'retry-' . $suffix . '@example.invalid']);
reviews::submit($retry->id, 'approve');
// Course-scoped reviewers need a valid course for authorisation, so a second site admin is used for this failure fixture.
$adminids = get_config('core', 'siteadmins');
set_config('siteadmins', $adminids . ',' . $r2->id);
set_config('fundamentals_course_shortname', 'MISSING-TEST-COURSE', 'local_web3talents');
login_as($r2);
$beforeusers = $DB->count_records('user');
$result = reviews::submit($retry->id, 'approve');
check($result['status'] === 'provisionpending' && $DB->count_records('user') === $beforeusers,
    'Missing configured course keeps approvals and creates no orphan user');
set_config('fundamentals_course_shortname', 'REVIEW-FIXTURE', 'local_web3talents');
$user = applicants::create_student_account($retry->id);
check(is_enrolled($context, $user), 'Provisioning retry succeeds after configuration is fixed');
set_config('siteadmins', $adminids);
login_as($r1);
$duplicate = $rows;
$duplicate[0]['email'] = 'changedemail-' . $suffix . '@example.invalid';
check(reviews::preview($duplicate)[0]['outcome'] === 'conflict', 'Submission ID detects changed-email duplicate');
check(reviews::preview([$rows[0], $rows[0]])[1]['outcome'] === 'duplicate', 'Duplicate rows within a file are skipped');
$invalid = csv::parse($header . "\n" . 'invalid,First,Last,not-an-email,Answer,Yes');
check($invalid[0]['error'] !== '', 'Malformed email is rejected');
blocked(fn() => csv::parse('First Name,First Name,Last Name,Email'), 'Ambiguous identity columns are rejected');
check(true, 'Application review integration checks completed; real applicants and external email untouched');
