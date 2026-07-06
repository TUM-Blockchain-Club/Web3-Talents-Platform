<?php
// Applies Phase 9 hidden room-generation setup.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');
require_once($CFG->dirroot . '/local/web3talents/classes/local/course_state_service.php');
require_once($CFG->dirroot . '/local/web3talents/classes/local/topic_round_service.php');
require_once($CFG->dirroot . '/local/web3talents/classes/local/room_assignment_service.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');

use local_web3talents\local\room_assignment_service;
use local_web3talents\local\topic_round_service;

global $CFG, $DB;

\core\session\manager::set_user(get_admin());

$course = $DB->get_record('course', ['shortname' => 'W3T-FUNDAMENTALS-DEV'], '*', MUST_EXIST);
$testpassword = getenv('WEB3T_PHASE2_TEST_PASSWORD') ?: 'ChangeMe123!';

function web3t_phase9_log(string $message): void {
    echo $message . PHP_EOL;
}

function web3t_phase9_role_id(string $shortname): int {
    global $DB;

    return (int)$DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
}

function web3t_phase9_ensure_user(string $username, string $firstname, string $lastname, string $email, string $password): stdClass {
    global $CFG, $DB;

    $username = core_text::strtolower(trim($username));
    $user = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
    if (!$user) {
        $user = (object)[
            'auth' => 'manual',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'username' => $username,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email,
            'lang' => $CFG->lang ?? 'en',
            'city' => 'Munich',
            'country' => 'DE',
        ];
        $user->id = user_create_user($user, false, false);
    } else {
        $user->firstname = $firstname;
        $user->lastname = $lastname;
        $user->email = $email;
        $user->auth = 'manual';
        $user->confirmed = 1;
        $user->timemodified = time();
        user_update_user($user, false, false);
    }

    $user = $DB->get_record('user', ['username' => $username, 'deleted' => 0], '*', MUST_EXIST);
    update_internal_user_password($user, $password, true);
    set_user_preference('auth_forcepasswordchange', 0, $user);
    return $user;
}

function web3t_phase9_enrol(stdClass $course, stdClass $user): void {
    if (!enrol_try_internal_enrol($course->id, $user->id, web3t_phase9_role_id('student'))) {
        throw new moodle_exception("Could not enrol {$user->username} as student");
    }
}

function web3t_phase9_finalize_open_rounds(stdClass $course): void {
    global $DB;

    $rounds = $DB->get_records('local_w3t_round', [
        'courseid' => $course->id,
        'status' => topic_round_service::STATUS_OPEN,
    ]);
    foreach ($rounds as $round) {
        topic_round_service::finalize_round((int)$round->id);
        web3t_phase9_log("Finalized existing open round: {$round->name}");
    }
}

function web3t_phase9_topic_id(int $roundid, string $topicname): int {
    global $DB;

    $topics = $DB->get_records('local_w3t_topic', ['roundid' => $roundid]);
    foreach ($topics as $topic) {
        if (trim($topic->name) === $topicname) {
            return (int)$topic->id;
        }
    }
    throw new moodle_exception("Missing Phase 9 topic {$topicname}");
}

$users = [
    'student1' => $DB->get_record('user', ['username' => 'w3t.student1', 'deleted' => 0], '*', MUST_EXIST),
    'alumni' => web3t_phase9_ensure_user('w3t.alumni1', 'Alumni', 'One', 'w3t.alumni1@example.test', $testpassword),
    'student2' => $DB->get_record('user', ['username' => 'w3t.student2', 'deleted' => 0], '*', MUST_EXIST),
    'warning' => web3t_phase9_ensure_user('w3t.phase8.warning', 'Phase Eight', 'Warning', 'w3t.phase8.warning@example.test', $testpassword),
    'third' => web3t_phase9_ensure_user('w3t.phase8b.third', 'Phase EightB', 'Third', 'w3t.phase8b.third@example.test', $testpassword),
    'no1' => web3t_phase9_ensure_user('w3t.phase9.no1', 'Phase Nine', 'No One', 'w3t.phase9.no1@example.test', $testpassword),
    'no2' => web3t_phase9_ensure_user('w3t.phase9.no2', 'Phase Nine', 'No Two', 'w3t.phase9.no2@example.test', $testpassword),
    'd1' => web3t_phase9_ensure_user('w3t.phase9.d1', 'Phase Nine', 'D One', 'w3t.phase9.d1@example.test', $testpassword),
    'd2' => web3t_phase9_ensure_user('w3t.phase9.d2', 'Phase Nine', 'D Two', 'w3t.phase9.d2@example.test', $testpassword),
    'e1' => web3t_phase9_ensure_user('w3t.phase9.e1', 'Phase Nine', 'E One', 'w3t.phase9.e1@example.test', $testpassword),
    'e2' => web3t_phase9_ensure_user('w3t.phase9.e2', 'Phase Nine', 'E Two', 'w3t.phase9.e2@example.test', $testpassword),
];

foreach ($users as $user) {
    web3t_phase9_enrol($course, $user);
}

web3t_phase9_finalize_open_rounds($course);

$set = topic_round_service::create_partner_set((int)$course->id, 'Phase 9 Partner Set ' . userdate(time(), '%Y-%m-%d %H:%M:%S'));
$groups = [];
$groups['alpha'] = topic_round_service::create_partner_group((int)$set->id, 'Phase 9 Alpha', [(int)$users['student1']->id, (int)$users['alumni']->id]);
$groups['beta'] = topic_round_service::create_partner_group((int)$set->id, 'Phase 9 Beta Trio', [(int)$users['student2']->id, (int)$users['warning']->id, (int)$users['third']->id]);
$groups['nochoice'] = topic_round_service::create_partner_group((int)$set->id, 'Phase 9 No Choice', [(int)$users['no1']->id, (int)$users['no2']->id]);
$groups['delta'] = topic_round_service::create_partner_group((int)$set->id, 'Phase 9 Delta', [(int)$users['d1']->id, (int)$users['d2']->id]);
$groups['echo'] = topic_round_service::create_partner_group((int)$set->id, 'Phase 9 Echo', [(int)$users['e1']->id, (int)$users['e2']->id]);
web3t_phase9_log('Created Phase 9 partner groups.');

$round = topic_round_service::create_round(
    (int)$course->id,
    (int)$set->id,
    'Phase 9 Room Generation Round',
    time() - HOURSECS,
    time() + HOURSECS,
    5,
    ['Blockchain Foundations', 'Wallets And Transactions', 'Smart Contracts', 'Applications And Protocols']
);

$blockchain = web3t_phase9_topic_id((int)$round->id, 'Blockchain Foundations');
$wallets = web3t_phase9_topic_id((int)$round->id, 'Wallets And Transactions');
$smartcontracts = web3t_phase9_topic_id((int)$round->id, 'Smart Contracts');
$applications = web3t_phase9_topic_id((int)$round->id, 'Applications And Protocols');

topic_round_service::select_topic((int)$round->id, (int)$users['student1']->id, $blockchain);
topic_round_service::select_topic((int)$round->id, (int)$users['student2']->id, $wallets);
topic_round_service::select_topic((int)$round->id, (int)$users['d1']->id, $smartcontracts);
topic_round_service::select_topic((int)$round->id, (int)$users['e1']->id, $applications);

$DB->set_field('local_w3t_round', 'closetime', time() - MINSECS, ['id' => $round->id]);
topic_round_service::finalize_round((int)$round->id);
room_assignment_service::generate((int)$round->id, 2, get_admin()->id);

purge_all_caches();

web3t_phase9_log('Phase 9 hidden room-generation configuration complete.');
