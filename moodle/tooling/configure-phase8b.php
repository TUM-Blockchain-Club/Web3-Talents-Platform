<?php
// Applies Phase 8B weekly group-slot topic selection setup.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/local/web3talents/classes/local/course_state_service.php');
require_once($CFG->dirroot . '/local/web3talents/classes/local/topic_round_service.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/resourcelib.php');

use local_web3talents\local\topic_round_service;

global $CFG, $DB;

\core\session\manager::set_user(get_admin());

$course = $DB->get_record('course', ['shortname' => 'W3T-FUNDAMENTALS-DEV'], '*', MUST_EXIST);
$testpassword = getenv('WEB3T_PHASE2_TEST_PASSWORD') ?: 'ChangeMe123!';

function web3t_phase8b_log(string $message): void {
    echo $message . PHP_EOL;
}

function web3t_phase8b_role_id(string $shortname): int {
    global $DB;

    return (int)$DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
}

function web3t_phase8b_ensure_user(string $username, string $firstname, string $lastname, string $email, string $password): stdClass {
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

function web3t_phase8b_enrol(stdClass $course, stdClass $user): void {
    if (!enrol_try_internal_enrol($course->id, $user->id, web3t_phase8b_role_id('student'))) {
        throw new moodle_exception("Could not enrol {$user->username} as student");
    }
}

function web3t_phase8b_topic_id(int $roundid, string $topicname): int {
    global $DB;

    $topics = $DB->get_records('local_w3t_topic', ['roundid' => $roundid]);
    foreach ($topics as $topic) {
        if (trim($topic->name) === $topicname) {
            return (int)$topic->id;
        }
    }
    throw new moodle_exception("Missing Phase 8B topic {$topicname}");
}

function web3t_phase8b_module_exists(stdClass $course, string $idnumber): bool {
    global $DB;

    return $DB->record_exists('course_modules', [
        'course' => $course->id,
        'idnumber' => $idnumber,
        'deletioninprogress' => 0,
    ]);
}

function web3t_phase8b_ensure_choose_topic_link(stdClass $course): void {
    global $CFG, $DB;

    $idnumber = 'w3t_choose_weekly_topic';
    $existing = $DB->get_record('course_modules', [
        'course' => $course->id,
        'idnumber' => $idnumber,
        'deletioninprogress' => 0,
    ]);
    if ($existing) {
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 11], '*', MUST_EXIST);
        \core_courseformat\formatactions::cm($course->id)->move_end_section((int)$existing->id, (int)$section->id);
        web3t_phase8b_log('Course link already exists: Choose Weekly Topic');
        return;
    }

    [, , , , $moduleinfo] = prepare_new_moduleinfo_data($course, 'url', 11);
    $moduleinfo->name = 'Choose Weekly Topic';
    $moduleinfo->introeditor = [
        'text' => 'Choose or review the current Web3 Talents weekly topic for your partner group.',
        'format' => FORMAT_HTML,
        'itemid' => 0,
    ];
    $moduleinfo->externalurl = $CFG->wwwroot . '/local/web3talents/choose_topic.php';
    $moduleinfo->display = RESOURCELIB_DISPLAY_AUTO;
    $moduleinfo->printintro = 1;
    $moduleinfo->popupwidth = 620;
    $moduleinfo->popupheight = 450;
    $moduleinfo->cmidnumber = $idnumber;

    add_moduleinfo($moduleinfo, $course);
    web3t_phase8b_log('Created course link: Choose Weekly Topic');
}

function web3t_phase8b_finalize_open_rounds(stdClass $course): void {
    global $DB;

    $rounds = $DB->get_records('local_w3t_round', [
        'courseid' => $course->id,
        'status' => topic_round_service::STATUS_OPEN,
    ]);
    foreach ($rounds as $round) {
        topic_round_service::finalize_round((int)$round->id);
        web3t_phase8b_log("Finalized existing open round: {$round->name}");
    }
}

$student1 = $DB->get_record('user', ['username' => 'w3t.student1', 'deleted' => 0], '*', MUST_EXIST);
$student2 = $DB->get_record('user', ['username' => 'w3t.student2', 'deleted' => 0], '*', MUST_EXIST);
$alumni = web3t_phase8b_ensure_user('w3t.alumni1', 'Alumni', 'One', 'w3t.alumni1@example.test', $testpassword);
$warning = web3t_phase8b_ensure_user('w3t.phase8.warning', 'Phase Eight', 'Warning', 'w3t.phase8.warning@example.test', $testpassword);
$third = web3t_phase8b_ensure_user('w3t.phase8b.third', 'Phase EightB', 'Third', 'w3t.phase8b.third@example.test', $testpassword);

foreach ([$student1, $student2, $alumni, $warning, $third] as $user) {
    web3t_phase8b_enrol($course, $user);
}

web3t_phase8b_ensure_choose_topic_link($course);
web3t_phase8b_finalize_open_rounds($course);

$set = topic_round_service::create_partner_set((int)$course->id, 'Phase 8B Partner Set ' . userdate(time(), '%Y-%m-%d %H:%M:%S'));
topic_round_service::create_partner_group((int)$set->id, 'Phase 8B Alpha Pair', [(int)$student1->id, (int)$alumni->id]);
topic_round_service::create_partner_group((int)$set->id, 'Phase 8B Beta Trio', [(int)$student2->id, (int)$warning->id, (int)$third->id]);
web3t_phase8b_log('Created Phase 8B partner set with one pair and one trio.');

$round = topic_round_service::create_round(
    (int)$course->id,
    (int)$set->id,
    'Phase 8B Weekly Topic Selection',
    time() - MINSECS,
    time() + HOURSECS,
    1,
    ['Blockchain Foundations', 'Wallets And Transactions', 'Smart Contracts', 'Applications And Protocols']
);
web3t_phase8b_log('Created Phase 8B topic round with one group slot per topic.');

$blockchain = web3t_phase8b_topic_id((int)$round->id, 'Blockchain Foundations');
$wallets = web3t_phase8b_topic_id((int)$round->id, 'Wallets And Transactions');
$smartcontracts = web3t_phase8b_topic_id((int)$round->id, 'Smart Contracts');

topic_round_service::select_topic((int)$round->id, (int)$student1->id, $blockchain);
topic_round_service::select_topic((int)$round->id, (int)$alumni->id, $wallets);
topic_round_service::select_topic((int)$round->id, (int)$student2->id, $smartcontracts);

purge_all_caches();

web3t_phase8b_log('Phase 8B weekly group-slot topic selection configuration complete.');
