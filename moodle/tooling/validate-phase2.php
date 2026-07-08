<?php
// Validates the repeatable Moodle base configuration for Phase 2.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');

global $CFG, $DB;

$testpassword = getenv('WEB3T_PHASE2_TEST_PASSWORD') ?: 'ChangeMe123!';
$course = $DB->get_record('course', ['shortname' => 'W3T-FUNDAMENTALS-DEV'], '*', MUST_EXIST);
$coursecontext = context_course::instance($course->id);

function web3t_assert(bool $condition, string $message): void {
    if (!$condition) {
        throw new moodle_exception("Phase 2 validation failed: {$message}");
    }
    echo "OK: {$message}" . PHP_EOL;
}

function web3t_get_module(string $idnumber, string $modulename): stdClass {
    global $DB;

    $sql = "SELECT cm.*, m.name AS modulename
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
             WHERE cm.idnumber = :idnumber
               AND cm.deletioninprogress = 0";
    $cm = $DB->get_record_sql($sql, ['idnumber' => $idnumber], MUST_EXIST);
    web3t_assert($cm->modulename === $modulename, "{$idnumber} is a {$modulename} module");
    return $cm;
}

function web3t_user(string $username, string $password): stdClass {
    global $DB;

    $user = $DB->get_record('user', ['username' => $username, 'deleted' => 0], '*', MUST_EXIST);
    web3t_assert((bool)authenticate_user_login($username, $password, false), "{$username} can authenticate");
    return $user;
}

function web3t_has_course_role(stdClass $course, stdClass $user, string $roleshortname): bool {
    global $DB;

    $context = context_course::instance($course->id);
    $roleid = $DB->get_field('role', 'id', ['shortname' => $roleshortname], MUST_EXIST);
    return $DB->record_exists('role_assignments', [
        'contextid' => $context->id,
        'userid' => $user->id,
        'roleid' => $roleid,
    ]);
}

web3t_assert($CFG->fullname === 'Web3 Talents Moodle', 'site full name is configured');
web3t_assert($CFG->shortname === 'Web3 Talents', 'site short name is configured');
web3t_assert((int)$CFG->messaging === 1, 'messaging is enabled');
web3t_assert((int)$CFG->messagingallusers === 0, 'direct messaging is limited to shared-course users');
web3t_assert($CFG->registerauth === '', 'public self-registration is disabled');

web3t_assert($course->format === 'topics', 'fundamentals course uses topic sections');
web3t_assert(course_get_format($course)->get_last_section_number() >= 11, 'fundamentals course has ten topic sections plus topic selection');

$sectionnames = [
    0 => 'Overview',
];
for ($topic = 1; $topic <= 10; $topic++) {
    $sectionnames[$topic] = "Topic {$topic}";
}
$sectionnames[11] = 'Topic Selection';

foreach ($sectionnames as $sectionnum => $name) {
    $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $sectionnum], '*', MUST_EXIST);
    web3t_assert($section->name === $name, "section {$sectionnum} is named {$name}");
}

$announcements = web3t_get_module('w3t_announcements', 'forum');
$courseforum = web3t_get_module('w3t_course_forum', 'forum');
$choicecm = web3t_get_module('w3t_topic_choice', 'choice');

$announcementcount = $DB->count_records_sql(
    "SELECT COUNT(1)
       FROM {course_modules} cm
       JOIN {modules} m ON m.id = cm.module
       JOIN {forum} f ON f.id = cm.instance
      WHERE cm.course = :courseid
        AND cm.deletioninprogress = 0
        AND m.name = 'forum'
        AND " . $DB->sql_compare_text('f.name') . " = :name
        AND f.type = 'news'",
    ['courseid' => $course->id, 'name' => 'Announcements']
);
web3t_assert((int)$announcementcount === 1, 'course has exactly one Announcements forum');

for ($topic = 1; $topic <= 10; $topic++) {
    for ($subtopic = 1; $subtopic <= 4; $subtopic++) {
        $idnumber = sprintf('w3t_topic_%02d_subtopic_%02d', $topic, $subtopic);
        web3t_get_module($idnumber, 'subsection');
    }
}
web3t_assert(true, 'course has four Moodle subsections under each topic');

$choice = $DB->get_record('choice', ['id' => $choicecm->instance], '*', MUST_EXIST);
$choiceoptions = $DB->count_records('choice_options', ['choiceid' => $choice->id]);
web3t_assert($choiceoptions >= 5, 'topic Choice has baseline options');

$student1 = web3t_user('w3t.student1', $testpassword);
$student2 = web3t_user('w3t.student2', $testpassword);
$mentor = web3t_user('w3t.mentor1', $testpassword);
$programadmin = web3t_user('w3t.programadmin', $testpassword);

web3t_assert(is_enrolled($coursecontext, $student1, '', true), 'student one is enrolled');
web3t_assert(is_enrolled($coursecontext, $student2, '', true), 'student two is enrolled');
web3t_assert(is_enrolled($coursecontext, $mentor, '', true), 'mentor is enrolled');
web3t_assert(is_enrolled($coursecontext, $programadmin, '', true), 'program admin is enrolled');

web3t_assert(web3t_has_course_role($course, $student1, 'student'), 'student one has student role');
web3t_assert(web3t_has_course_role($course, $student2, 'student'), 'student two has student role');
web3t_assert(web3t_has_course_role($course, $mentor, 'editingteacher'), 'mentor has teacher role');
web3t_assert(web3t_has_course_role($course, $programadmin, 'manager'), 'program admin has manager role');

\core\session\manager::set_user($student1);
web3t_assert(has_capability('mod/forum:startdiscussion', context_module::instance($courseforum->id)), 'student can post in course forum');
web3t_assert(has_capability('mod/choice:choose', context_module::instance($choicecm->id)), 'student can use topic Choice');

\core\session\manager::set_user($mentor);
web3t_assert(has_capability('moodle/course:update', $coursecontext), 'mentor can update the course');

\core\session\manager::set_user($programadmin);
web3t_assert(has_capability('moodle/course:update', $coursecontext), 'program admin can update the course');

echo 'Phase 2 validation complete.' . PHP_EOL;
