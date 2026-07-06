<?php
// Validates Phase 7 fundamentals course materials and communication setup.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');
require_once($CFG->dirroot . '/lib/enrollib.php');
require_once($CFG->libdir . '/messagelib.php');

global $CFG, $DB;

$course = $DB->get_record('course', ['shortname' => 'W3T-FUNDAMENTALS-DEV'], '*', MUST_EXIST);
$coursecontext = context_course::instance($course->id);

function web3t_phase7_assert(bool $condition, string $message): void {
    if (!$condition) {
        throw new moodle_exception("Phase 7 validation failed: {$message}");
    }
    echo "OK: {$message}" . PHP_EOL;
}

function web3t_phase7_module(string $idnumber, string $modulename): stdClass {
    global $DB;

    $sql = "SELECT cm.*, m.name AS modulename
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
             WHERE cm.idnumber = :idnumber
               AND cm.deletioninprogress = 0";
    $cm = $DB->get_record_sql($sql, ['idnumber' => $idnumber], MUST_EXIST);
    web3t_phase7_assert($cm->modulename === $modulename, "{$idnumber} is a {$modulename} module");
    return $cm;
}

$pageids = [
    'w3t_course_home',
    'w3t_material_blockchain_foundations',
    'w3t_material_wallets_transactions',
    'w3t_material_smart_contracts',
    'w3t_material_applications_protocols',
    'w3t_material_security_responsible',
];

foreach ($pageids as $idnumber) {
    $cm = web3t_phase7_module($idnumber, 'page');
    $page = $DB->get_record('page', ['id' => $cm->instance], '*', MUST_EXIST);
    web3t_phase7_assert(trim($page->content) !== '', "{$idnumber} has Moodle-hosted content");
    web3t_phase7_assert(stripos($page->content, 'drive.google.com') === false, "{$idnumber} does not depend on Google Drive content");
}

$urlcm = web3t_phase7_module('w3t_external_ethereum_learn', 'url');
$url = $DB->get_record('url', ['id' => $urlcm->instance], '*', MUST_EXIST);
web3t_phase7_assert($url->externalurl === 'https://ethereum.org/en/learn/', 'approved external URL is configured');
web3t_phase7_assert(stripos($url->externalurl, 'drive.google.com') === false, 'external URL is not a Google Drive dependency');

$foldercm = web3t_phase7_module('w3t_session_handouts_folder', 'folder');
$foldercontext = context_module::instance($foldercm->id);
$files = get_file_storage()->get_area_files($foldercontext->id, 'mod_folder', 'content', 0, 'filename', false);
web3t_phase7_assert(count($files) >= 1, 'session handouts folder has a Moodle-hosted file');

$announcementscm = web3t_phase7_module('w3t_announcements', 'forum');
$forumcm = web3t_phase7_module('w3t_course_forum', 'forum');
$announcements = $DB->get_record('forum', ['id' => $announcementscm->instance], '*', MUST_EXIST);
$courseforum = $DB->get_record('forum', ['id' => $forumcm->instance], '*', MUST_EXIST);
web3t_phase7_assert($DB->record_exists('forum_discussions', ['forum' => $announcements->id, 'name' => 'Moodle course home is ready']), 'announcement discussion exists');
web3t_phase7_assert($DB->record_exists('forum_discussions', ['forum' => $courseforum->id, 'name' => 'Introduce yourself and share one Web3 question']), 'course forum discussion exists');

$student1 = $DB->get_record('user', ['username' => 'w3t.student1', 'deleted' => 0], '*', MUST_EXIST);
$student2 = $DB->get_record('user', ['username' => 'w3t.student2', 'deleted' => 0], '*', MUST_EXIST);
$mentor = $DB->get_record('user', ['username' => 'w3t.mentor1', 'deleted' => 0], '*', MUST_EXIST);
$alumni = $DB->get_record('user', ['username' => 'w3t.alumni1', 'deleted' => 0], '*', MUST_EXIST);

web3t_phase7_assert(is_enrolled($coursecontext, $student1, '', true), 'student one remains enrolled');
web3t_phase7_assert(is_enrolled($coursecontext, $student2, '', true), 'student two remains enrolled');
web3t_phase7_assert(is_enrolled($coursecontext, $mentor, '', true), 'mentor remains enrolled');
web3t_phase7_assert(is_enrolled($coursecontext, $alumni, '', true), 'alumni-style user remains enrolled in same course');

\core\session\manager::set_user($student1);
web3t_phase7_assert(has_capability('mod/page:view', context_module::instance(web3t_phase7_module('w3t_material_blockchain_foundations', 'page')->id)), 'student can access topic-based material page');
web3t_phase7_assert(has_capability('mod/folder:view', $foldercontext), 'student can access Moodle-hosted session handouts');
web3t_phase7_assert(has_capability('mod/forum:startdiscussion', context_module::instance($forumcm->id)), 'student can use course forum');

\core\session\manager::set_user($mentor);
web3t_phase7_assert(has_capability('mod/forum:startdiscussion', context_module::instance($forumcm->id)), 'mentor can participate in course forum');
web3t_phase7_assert(has_capability('mod/forum:addnews', context_module::instance($announcementscm->id)), 'mentor can post announcements');

\core\session\manager::set_user($alumni);
web3t_phase7_assert(has_capability('mod/page:view', context_module::instance(web3t_phase7_module('w3t_material_security_responsible', 'page')->id)), 'alumni-style user can access approved materials');
web3t_phase7_assert(has_capability('mod/forum:startdiscussion', context_module::instance($forumcm->id)), 'alumni-style user can participate in old course forum');

\core\session\manager::set_user($student1);
$messageid = message_post_message($student1, $student2, 'Phase 7 validation direct-message test.', FORMAT_PLAIN);
web3t_phase7_assert((int)$CFG->messaging === 1, 'Moodle direct messaging is enabled');
web3t_phase7_assert((int)$messageid > 0, 'student can direct message another enrolled student');

echo 'Phase 7 fundamentals course materials and communication validation complete.' . PHP_EOL;
