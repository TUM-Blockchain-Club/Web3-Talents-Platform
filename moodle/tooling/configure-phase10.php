<?php
// Applies Phase 10 Zoom CSV export setup.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/local/web3talents/classes/local/room_assignment_service.php');
require_once($CFG->libdir . '/resourcelib.php');

use local_web3talents\local\room_assignment_service;

global $DB;

\core\session\manager::set_user(get_admin());

function web3t_phase10_module_exists(stdClass $course, string $idnumber): bool {
    global $DB;

    return $DB->record_exists('course_modules', [
        'course' => $course->id,
        'idnumber' => $idnumber,
        'deletioninprogress' => 0,
    ]);
}

function web3t_phase10_ensure_my_room_link(stdClass $course): void {
    global $CFG;

    $idnumber = 'w3t_my_room_assignment';
    if (web3t_phase10_module_exists($course, $idnumber)) {
        echo 'Course link already exists: My room assignment' . PHP_EOL;
        return;
    }

    [, , , , $moduleinfo] = prepare_new_moduleinfo_data($course, 'url', 6);
    $moduleinfo->name = 'My room assignment';
    $moduleinfo->introeditor = [
        'text' => 'View your Web3 Talents live-session room assignment.',
        'format' => FORMAT_HTML,
        'itemid' => 0,
    ];
    $moduleinfo->externalurl = $CFG->wwwroot . '/local/web3talents/my_room.php';
    $moduleinfo->display = RESOURCELIB_DISPLAY_AUTO;
    $moduleinfo->printintro = 1;
    $moduleinfo->popupwidth = 620;
    $moduleinfo->popupheight = 450;
    $moduleinfo->cmidnumber = $idnumber;

    add_moduleinfo($moduleinfo, $course);
    rebuild_course_cache($course->id, true);
    echo 'Created course link: My room assignment' . PHP_EOL;
}

$course = $DB->get_record('course', ['shortname' => 'W3T-FUNDAMENTALS-DEV'], '*', MUST_EXIST);
web3t_phase10_ensure_my_room_link($course);

$rounds = $DB->get_records('local_w3t_round', ['courseid' => $course->id, 'name' => 'Phase 9 Room Generation Round'], 'id DESC', '*', 0, 1);
$round = reset($rounds);
if (!$round) {
    throw new moodle_exception('Phase 10 requires the Phase 9 room-generation fixture.');
}

$result = room_assignment_service::get_latest_result((int)$round->id);
if (!$result) {
    throw new moodle_exception('Phase 10 requires a generated Phase 9 room result.');
}

echo 'Phase 10 Zoom CSV export configuration complete.' . PHP_EOL;
