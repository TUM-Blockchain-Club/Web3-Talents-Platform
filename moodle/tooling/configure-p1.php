<?php
// Applies P1 attendance, participation, and mentor availability fixtures.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');
require_once($CFG->dirroot . '/local/web3talents/classes/local/mentor_grading_service.php');
require_once($CFG->dirroot . '/local/web3talents/classes/local/participation_service.php');
require_once($CFG->dirroot . '/local/web3talents/classes/local/room_assignment_service.php');

use local_web3talents\local\mentor_grading_service;
use local_web3talents\local\participation_service;
use local_web3talents\local\room_assignment_service;

global $DB;

\core\session\manager::set_user(get_admin());

$course = participation_service::get_configured_course();
$session = participation_service::upsert_session(
    (int)$course->id,
    'P1 Attendance Fixture Session',
    time() + DAYSECS,
    'Fixture session for attendance and mentor availability validation.',
    get_admin()->id
);

$student1 = $DB->get_record('user', ['username' => 'w3t.student1', 'deleted' => 0], '*', MUST_EXIST);
$student2 = $DB->get_record('user', ['username' => 'w3t.student2', 'deleted' => 0], '*', MUST_EXIST);
$mentor = $DB->get_record('user', ['username' => 'w3t.mentor1', 'deleted' => 0], '*', MUST_EXIST);

participation_service::save_attendance(
    (int)$session->id,
    (int)$student1->id,
    participation_service::ATTENDANCE_PRESENT,
    5,
    'Fixture: strong participation.',
    get_admin()->id
);
participation_service::save_attendance(
    (int)$session->id,
    (int)$student2->id,
    participation_service::ATTENDANCE_LATE,
    3,
    'Fixture: joined late.',
    get_admin()->id
);
participation_service::save_availability(
    (int)$session->id,
    (int)$mentor->id,
    participation_service::AVAILABILITY_AVAILABLE,
    'Fixture: available for the live session.'
);

$result = room_assignment_service::get_latest_result_for_course((int)$course->id);
if (!$result) {
    throw new moodle_exception('P1 grading fixtures require a generated room result.');
}

mentor_grading_service::auto_assign_available_mentors((int)$session->id, (int)$result->id, get_admin()->id);
$state = room_assignment_service::get_result_state((int)$result->id);
$assignments = mentor_grading_service::get_assignments_by_room((int)$session->id, (int)$result->id);
$assignedroom = null;
foreach ($state['rooms'] as $roomstate) {
    if (!empty($assignments[(int)$roomstate['room']->id])
            && (int)$assignments[(int)$roomstate['room']->id]->mentorid === (int)$mentor->id) {
        $assignedroom = $roomstate;
        break;
    }
}
if (!$assignedroom) {
    throw new moodle_exception('P1 grading fixtures could not assign a room to the mentor.');
}
foreach ($assignedroom['assignments'] as $roomgroup) {
    foreach ($roomgroup['members'] as $member) {
        mentor_grading_service::save_grade(
            (int)$session->id,
            (int)$result->id,
            (int)$assignedroom['room']->id,
            (int)$member->id,
            7,
            'Fixture: presentation grade.',
            get_admin()->id
        );
    }
}

echo 'P1 attendance, participation, mentor availability, and mentor grading fixtures configured.' . PHP_EOL;
