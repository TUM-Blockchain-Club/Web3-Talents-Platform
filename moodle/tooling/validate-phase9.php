<?php
// Validates Phase 9 hidden room generation.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');
require_once($CFG->dirroot . '/local/web3talents/classes/local/course_state_service.php');
require_once($CFG->dirroot . '/local/web3talents/classes/local/topic_round_service.php');
require_once($CFG->dirroot . '/local/web3talents/classes/local/room_assignment_service.php');

use local_web3talents\local\room_assignment_service;

global $DB;

\core\session\manager::set_user(get_admin());

function web3t_phase9_assert(bool $condition, string $message): void {
    if (!$condition) {
        throw new moodle_exception("Phase 9 validation failed: {$message}");
    }
    echo "OK: {$message}" . PHP_EOL;
}

foreach (['local_w3t_room_result', 'local_w3t_room', 'local_w3t_room_group'] as $table) {
    web3t_phase9_assert($DB->get_manager()->table_exists($table), "{$table} table exists");
}

$course = $DB->get_record('course', ['shortname' => 'W3T-FUNDAMENTALS-DEV'], '*', MUST_EXIST);
$rounds = $DB->get_records('local_w3t_round', ['courseid' => $course->id, 'name' => 'Phase 9 Room Generation Round'], 'id DESC', '*', 0, 1);
$round = reset($rounds);
web3t_phase9_assert($round !== false, 'Phase 9 finalized round exists');

$result = room_assignment_service::get_latest_result((int)$round->id);
web3t_phase9_assert($result !== null, 'latest room result exists');
web3t_phase9_assert((int)$result->roomcount === 2, 'room result stores selected room count');

$state = room_assignment_service::get_result_state((int)$result->id);
$roomnames = array_map(fn($roomstate) => $roomstate['room']->roomname, $state['rooms']);
web3t_phase9_assert($roomnames === ['Room1', 'Room2'], 'room names match exact RoomN format');

$placements = [];
foreach ($state['rooms'] as $roomstate) {
    foreach ($roomstate['assignments'] as $assignment) {
        $placements[$assignment['pgroup']->name] = [
            'room' => $roomstate['room'],
            'topic' => $assignment['topic'],
            'reason' => $assignment['reason'],
            'members' => $assignment['members'],
        ];
    }
}

foreach (['Phase 9 Alpha', 'Phase 9 Beta Trio', 'Phase 9 No Choice', 'Phase 9 Delta', 'Phase 9 Echo'] as $groupname) {
    web3t_phase9_assert(isset($placements[$groupname]), "{$groupname} is placed in a room");
}
web3t_phase9_assert($placements['Phase 9 Alpha']['topic']->name === 'Blockchain Foundations', 'same group choice assigns correctly');
web3t_phase9_assert($placements['Phase 9 Beta Trio']['topic']->name === 'Wallets And Transactions', 'three-person group choice assigns correctly');
web3t_phase9_assert($placements['Phase 9 No Choice']['reason'] === 'no-choice-balanced', 'no-choice group gets balanced fallback topic');
web3t_phase9_assert(count($placements['Phase 9 Beta Trio']['members']) === 3, 'three-person partner group is never split');
web3t_phase9_assert(count($placements['Phase 9 Alpha']['members']) === 2, 'two-person partner group is never split');

$sourcegroup = $DB->get_record('local_w3t_pgroup', ['name' => 'Phase 9 Alpha', 'partnersetid' => $round->partnersetid], '*', MUST_EXIST);
$targetroom = null;
foreach ($state['rooms'] as $roomstate) {
    if ((int)$roomstate['room']->id !== (int)$placements['Phase 9 Alpha']['room']->id) {
        $targetroom = $roomstate['room'];
        break;
    }
}
web3t_phase9_assert($targetroom !== null, 'manual move target room exists');
room_assignment_service::move_group((int)$result->id, (int)$sourcegroup->id, (int)$targetroom->id);
$movedstate = room_assignment_service::get_result_state((int)$result->id);
$movedroom = null;
foreach ($movedstate['rooms'] as $roomstate) {
    foreach ($roomstate['assignments'] as $assignment) {
        if ($assignment['pgroup']->name === 'Phase 9 Alpha') {
            $movedroom = $roomstate['room']->roomname;
        }
    }
}
web3t_phase9_assert($movedroom === $targetroom->roomname, 'manual move updates latest result');
web3t_phase9_assert($DB->record_exists('local_web3talents_log', ['eventtype' => 'room_group_moved', 'courseid' => $course->id]), 'manual move is logged');

echo 'Phase 9 hidden room generation validation complete.' . PHP_EOL;
