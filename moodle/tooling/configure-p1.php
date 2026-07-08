<?php
// Applies P1 attendance, participation, and mentor availability fixtures.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');
require_once($CFG->dirroot . '/local/web3talents/classes/local/participation_service.php');

use local_web3talents\local\participation_service;

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

echo 'P1 attendance, participation, and mentor availability fixtures configured.' . PHP_EOL;
