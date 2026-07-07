<?php
// Validates Phase 10 Zoom CSV export.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');
require_once($CFG->dirroot . '/local/web3talents/classes/local/room_assignment_service.php');

use local_web3talents\local\room_assignment_service;

global $DB;

\core\session\manager::set_user(get_admin());

function web3t_phase10_assert(bool $condition, string $message): void {
    if (!$condition) {
        throw new moodle_exception("Phase 10 validation failed: {$message}");
    }
    echo "OK: {$message}" . PHP_EOL;
}

$course = $DB->get_record('course', ['shortname' => 'W3T-FUNDAMENTALS-DEV'], '*', MUST_EXIST);
$rounds = $DB->get_records('local_w3t_round', ['courseid' => $course->id, 'name' => 'Phase 9 Room Generation Round'], 'id DESC', '*', 0, 1);
$round = reset($rounds);
web3t_phase10_assert($round !== false, 'Phase 9 room round exists for Zoom export');

$result = room_assignment_service::get_latest_result((int)$round->id);
web3t_phase10_assert($result !== null, 'latest room result exists for Zoom export');

$state = room_assignment_service::get_result_state((int)$result->id);
$placements = [];
foreach ($state['rooms'] as $roomstate) {
    foreach ($roomstate['assignments'] as $assignment) {
        $placements[$assignment['pgroup']->name] = $roomstate['room'];
    }
}

$sourcegroup = $DB->get_record('local_w3t_pgroup', ['name' => 'Phase 9 Alpha', 'partnersetid' => $round->partnersetid], '*', MUST_EXIST);
$targetroom = null;
foreach ($state['rooms'] as $roomstate) {
    if ((int)$roomstate['room']->id !== (int)$placements['Phase 9 Alpha']->id) {
        $targetroom = $roomstate['room'];
        break;
    }
}
web3t_phase10_assert($targetroom !== null, 'manual move target room exists before CSV export');
room_assignment_service::move_group((int)$result->id, (int)$sourcegroup->id, (int)$targetroom->id);

$rows = room_assignment_service::get_zoom_csv_rows((int)$result->id, get_admin()->id);
web3t_phase10_assert($rows[0] === ['Pre-assign Room Name', 'Email Address'], 'Zoom CSV header matches breakout-room template');
web3t_phase10_assert(count($rows) === 12, 'Zoom CSV has one row per assigned participant plus header');

$roombyemail = [];
foreach (array_slice($rows, 1) as $row) {
    [$roomname, $email] = $row;
    $roombyemail[$email] = $roomname;
}

$expectedemails = [
    'w3t.student1@example.test',
    'w3t.alumni1@example.test',
    'w3t.student2@example.test',
    'w3t.phase8.warning@example.test',
    'w3t.phase8b.third@example.test',
    'w3t.phase9.no1@example.test',
    'w3t.phase9.no2@example.test',
    'w3t.phase9.d1@example.test',
    'w3t.phase9.d2@example.test',
    'w3t.phase9.e1@example.test',
    'w3t.phase9.e2@example.test',
];
foreach ($expectedemails as $email) {
    web3t_phase10_assert(isset($roombyemail[$email]), "{$email} appears in Zoom CSV");
}

web3t_phase10_assert($roombyemail['w3t.student1@example.test'] === $targetroom->roomname, 'Zoom CSV reflects manual room moves');
web3t_phase10_assert($roombyemail['w3t.alumni1@example.test'] === $targetroom->roomname, 'Zoom CSV keeps moved partner group together');
web3t_phase10_assert($DB->record_exists('local_web3talents_log', ['eventtype' => 'zoom_csv_downloaded', 'courseid' => $course->id]), 'Zoom CSV download is logged');

echo 'Phase 10 Zoom CSV export validation complete.' . PHP_EOL;
