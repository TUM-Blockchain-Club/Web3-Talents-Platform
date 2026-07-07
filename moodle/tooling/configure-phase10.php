<?php
// Applies Phase 10 Zoom CSV export setup.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');
require_once($CFG->dirroot . '/local/web3talents/classes/local/room_assignment_service.php');

use local_web3talents\local\room_assignment_service;

global $DB;

\core\session\manager::set_user(get_admin());

$course = $DB->get_record('course', ['shortname' => 'W3T-FUNDAMENTALS-DEV'], '*', MUST_EXIST);
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
