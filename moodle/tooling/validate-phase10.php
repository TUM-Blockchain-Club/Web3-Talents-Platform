<?php
// Validates Phase 10 Zoom CSV export.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');
require_once($CFG->dirroot . '/local/web3talents/classes/local/room_assignment_service.php');

use local_web3talents\local\room_assignment_service;
use PhpOffice\PhpSpreadsheet\IOFactory;

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
web3t_phase10_assert(room_assignment_service::get_latest_result_for_course((int)$course->id) !== null, 'latest course room result is available');

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

$roundslug = trim(clean_filename($round->name), '-_ .');
$zoomfilename = room_assignment_service::get_zoom_csv_filename((int)$result->id);
$internalfilename = room_assignment_service::get_internal_excel_filename((int)$result->id);
web3t_phase10_assert(str_starts_with($zoomfilename, $roundslug), 'Zoom CSV filename starts with topic round name');
web3t_phase10_assert(!str_contains($zoomfilename, 'result-'), 'Zoom CSV filename avoids internal result ids');
web3t_phase10_assert(str_starts_with($internalfilename, $roundslug), 'internal workbook filename starts with topic round name');
web3t_phase10_assert(str_ends_with($internalfilename, 'internal-room-assignments.xlsx'), 'internal workbook filename uses old export title');

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

$student = $DB->get_record('user', ['username' => 'w3t.student1', 'deleted' => 0], '*', MUST_EXIST);
$studentroomstate = room_assignment_service::get_user_room_state((int)$course->id, (int)$student->id);
web3t_phase10_assert($studentroomstate !== null, 'student room state is available');
web3t_phase10_assert($studentroomstate['room']->roomname === $targetroom->roomname, 'student room state reflects manual moves');
web3t_phase10_assert($studentroomstate['assignment']['pgroup']->name === 'Phase 9 Alpha', 'student sees own partner group assignment');

$internalpath = room_assignment_service::write_internal_excel_file((int)$result->id, get_admin()->id);
web3t_phase10_assert(file_exists($internalpath) && substr(file_get_contents($internalpath, false, null, 0, 2), 0, 2) === 'PK', 'internal workbook is a valid XLSX container');
$spreadsheet = IOFactory::load($internalpath);
$workbookrows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
$spreadsheet->disconnectWorksheets();
unlink($internalpath);

web3t_phase10_assert(($workbookrows[0][0] ?? '') === 'Buddy Groups', 'internal workbook title matches old export');
$topicheaderindex = array_search('Group 1: Blockchain Foundations', array_column($workbookrows, 0), true);
web3t_phase10_assert($topicheaderindex !== false, 'internal workbook groups rows by topic');
web3t_phase10_assert($workbookrows[$topicheaderindex + 1][0] === 'Breakout Room #', 'internal workbook includes breakout room header');
web3t_phase10_assert($workbookrows[$topicheaderindex + 1][1] === 'Person 1', 'internal workbook includes person columns');
web3t_phase10_assert((bool)array_filter($workbookrows, fn($row) => in_array('Student One', $row, true)), 'internal workbook includes participant names');
web3t_phase10_assert($DB->record_exists('local_web3talents_log', ['eventtype' => 'internal_room_assignments_downloaded', 'courseid' => $course->id]), 'internal workbook download is logged');

echo 'Phase 10 Zoom CSV export validation complete.' . PHP_EOL;
