<?php
// Applies Phase 8 Moodle groups and Choice source-data setup.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/mod/choice/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/resourcelib.php');
require_once($CFG->dirroot . '/local/web3talents/classes/local/course_state_service.php');

use local_web3talents\local\course_state_service;

global $CFG, $DB;

\core\session\manager::set_user(get_admin());

$course = $DB->get_record('course', ['shortname' => 'W3T-FUNDAMENTALS-DEV'], '*', MUST_EXIST);
$testpassword = getenv('WEB3T_PHASE2_TEST_PASSWORD') ?: 'ChangeMe123!';

function web3t_phase8_log(string $message): void {
    echo $message . PHP_EOL;
}

function web3t_phase8_role_id(string $shortname): int {
    global $DB;

    return (int)$DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
}

function web3t_phase8_ensure_user(string $username, string $firstname, string $lastname, string $email, string $password): stdClass {
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

function web3t_phase8_enrol(stdClass $course, stdClass $user, string $roleshortname): void {
    if (!enrol_try_internal_enrol($course->id, $user->id, web3t_phase8_role_id($roleshortname))) {
        throw new moodle_exception("Could not enrol {$user->username} as {$roleshortname}");
    }
}

function web3t_phase8_module_exists(stdClass $course, string $idnumber): bool {
    global $DB;

    return $DB->record_exists('course_modules', [
        'course' => $course->id,
        'idnumber' => $idnumber,
        'deletioninprogress' => 0,
    ]);
}

function web3t_phase8_ensure_topic_choice(stdClass $course): stdClass {
    global $DB;

    $existingcm = $DB->get_record('course_modules', [
        'course' => $course->id,
        'idnumber' => course_state_service::CHOICE_IDNUMBER,
        'deletioninprogress' => 0,
    ]);
    if ($existingcm) {
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 11], '*', MUST_EXIST);
        \core_courseformat\formatactions::cm($course->id)->move_end_section((int)$existingcm->id, (int)$section->id);
    } else {
        [, , , , $moduleinfo] = prepare_new_moduleinfo_data($course, 'choice', 11);
        $moduleinfo->name = 'Fundamentals Topic Selection';
        $moduleinfo->introeditor = [
            'text' => 'Select the topic you want to focus on for the next live session.',
            'format' => FORMAT_HTML,
            'itemid' => 0,
        ];
        $moduleinfo->option = course_state_service::launch_topics();
        $moduleinfo->limit = array_fill(0, count(course_state_service::launch_topics()), 0);
        $moduleinfo->allowupdate = 1;
        $moduleinfo->showresults = 0;
        $moduleinfo->publish = 0;
        $moduleinfo->display = 0;
        $moduleinfo->allowmultiple = 0;
        $moduleinfo->showunanswered = 1;
        $moduleinfo->includeinactive = 0;
        $moduleinfo->cmidnumber = course_state_service::CHOICE_IDNUMBER;
        add_moduleinfo($moduleinfo, $course);
        web3t_phase8_log('Created Choice: Fundamentals Topic Selection');
    }

    $state = course_state_service::get_choice($course);
    $choice = $state->choice;
    $existing = [];
    foreach ($DB->get_records('choice_options', ['choiceid' => $choice->id]) as $option) {
        $existing[core_text::strtolower(trim($option->text))] = true;
    }
    foreach (course_state_service::launch_topics() as $topic) {
        $key = core_text::strtolower($topic);
        if (!isset($existing[$key])) {
            $DB->insert_record('choice_options', [
                'choiceid' => $choice->id,
                'text' => $topic,
                'maxanswers' => 0,
                'timemodified' => time(),
            ]);
            web3t_phase8_log("Added Choice option: {$topic}");
        }
    }

    return course_state_service::get_choice($course);
}

function web3t_phase8_ensure_group(stdClass $course, string $name, string $idnumber): stdClass {
    global $DB;

    $group = $DB->get_record('groups', ['courseid' => $course->id, 'idnumber' => $idnumber]);
    if (!$group) {
        $groupid = groups_create_group((object)[
            'courseid' => $course->id,
            'name' => $name,
            'idnumber' => $idnumber,
            'description' => "Phase 8 partner group {$name}.",
            'descriptionformat' => FORMAT_HTML,
        ]);
        web3t_phase8_log("Created group: {$name}");
        return $DB->get_record('groups', ['id' => $groupid], '*', MUST_EXIST);
    }

    $group->name = $name;
    $group->description = "Phase 8 partner group {$name}.";
    $group->descriptionformat = FORMAT_HTML;
    groups_update_group($group);
    web3t_phase8_log("Group already exists: {$name}");
    return $DB->get_record('groups', ['id' => $group->id], '*', MUST_EXIST);
}

function web3t_phase8_ensure_group_member(stdClass $group, stdClass $user): void {
    global $DB;

    if (!$DB->record_exists('groups_members', ['groupid' => $group->id, 'userid' => $user->id])) {
        groups_add_member($group, $user);
        web3t_phase8_log("Added {$user->username} to {$group->name}");
    }
}

function web3t_phase8_option_id(stdClass $choice, string $text): int {
    global $DB;

    $options = $DB->get_records('choice_options', ['choiceid' => $choice->id]);
    foreach ($options as $option) {
        if (core_text::strtolower(trim($option->text)) === core_text::strtolower(trim($text))) {
            return (int)$option->id;
        }
    }
    throw new moodle_exception("Missing Choice option: {$text}");
}

function web3t_phase8_submit_choice(stdClass $course, stdClass $choicecm, stdClass $choice, stdClass $user, string $topic): void {
    $optionid = web3t_phase8_option_id($choice, $topic);
    \core\session\manager::set_user($user);
    choice_user_submit_response($optionid, $choice, $user->id, $course, $choicecm);
    \core\session\manager::set_user(get_admin());
    web3t_phase8_log("Recorded {$topic} choice for {$user->username}");
}

$student1 = $DB->get_record('user', ['username' => 'w3t.student1', 'deleted' => 0], '*', MUST_EXIST);
$student2 = $DB->get_record('user', ['username' => 'w3t.student2', 'deleted' => 0], '*', MUST_EXIST);
$alumni = web3t_phase8_ensure_user('w3t.alumni1', 'Alumni', 'One', 'w3t.alumni1@example.test', $testpassword);
$warning = web3t_phase8_ensure_user('w3t.phase8.warning', 'Phase Eight', 'Warning', 'w3t.phase8.warning@example.test', $testpassword);

foreach ([$student1, $student2, $alumni, $warning] as $user) {
    web3t_phase8_enrol($course, $user, 'student');
}

$course->groupmode = VISIBLEGROUPS;
$course->groupmodeforce = 0;
update_course($course);
$course = $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);

$choicebundle = web3t_phase8_ensure_topic_choice($course);
$choicecm = get_coursemodule_from_id('choice', $choicebundle->cm->id, $course->id, false, MUST_EXIST);
$choice = $choicebundle->choice;

$alpha = web3t_phase8_ensure_group($course, 'W3T Partner Group Alpha', 'w3t_partner_alpha');
$beta = web3t_phase8_ensure_group($course, 'W3T Partner Group Beta', 'w3t_partner_beta');
$alumnigroup = web3t_phase8_ensure_group($course, 'W3T Partner Group Alumni', 'w3t_partner_alumni');

web3t_phase8_ensure_group_member($alpha, $student1);
web3t_phase8_ensure_group_member($alpha, $alumni);
web3t_phase8_ensure_group_member($beta, $student2);
web3t_phase8_ensure_group_member($alumnigroup, $alumni);

web3t_phase8_submit_choice($course, $choicecm, $choice, $student1, 'Blockchain Foundations');
web3t_phase8_submit_choice($course, $choicecm, $choice, $student2, 'Smart Contracts');
web3t_phase8_submit_choice($course, $choicecm, $choice, $alumni, 'Wallets And Transactions');

rebuild_course_cache($course->id, true);
purge_all_caches();

web3t_phase8_log('Phase 8 Moodle groups and Choice source-data configuration complete.');
