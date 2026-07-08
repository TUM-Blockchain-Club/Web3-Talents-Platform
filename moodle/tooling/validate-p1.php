<?php
// Validates P1 attendance, participation, and mentor availability features.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');
require_once($CFG->dirroot . '/local/web3talents/classes/local/participation_service.php');

use local_web3talents\local\participation_service;

global $DB;

function web3t_p1_assert(bool $condition, string $message): void {
    if (!$condition) {
        throw new moodle_exception("P1 validation failed: {$message}");
    }
    echo "OK: {$message}" . PHP_EOL;
}

$course = participation_service::get_configured_course();
$coursecontext = context_course::instance($course->id);
$admin = get_admin();
$student = $DB->get_record('user', ['username' => 'w3t.student1', 'deleted' => 0], '*', MUST_EXIST);
$mentor = $DB->get_record('user', ['username' => 'w3t.mentor1', 'deleted' => 0], '*', MUST_EXIST);

web3t_p1_assert($DB->get_manager()->table_exists('local_w3t_session'), 'session table exists');
web3t_p1_assert($DB->get_manager()->table_exists('local_w3t_attendance'), 'attendance table exists');
web3t_p1_assert($DB->get_manager()->table_exists('local_w3t_mavail'), 'mentor availability table exists');

\core\session\manager::set_user($admin);
web3t_p1_assert(has_capability('local/web3talents:manageparticipation', $coursecontext), 'admin can manage participation');
web3t_p1_assert(has_capability('local/web3talents:manageownavailability', $coursecontext), 'admin can access mentor availability');

\core\session\manager::set_user($mentor);
web3t_p1_assert(has_capability('local/web3talents:manageparticipation', $coursecontext), 'mentor can manage participation');
web3t_p1_assert(has_capability('local/web3talents:manageownavailability', $coursecontext), 'mentor can manage own availability');

\core\session\manager::set_user($student);
web3t_p1_assert(!has_capability('local/web3talents:manageparticipation', $coursecontext), 'student cannot manage participation');
web3t_p1_assert(!has_capability('local/web3talents:manageownavailability', $coursecontext), 'student cannot manage mentor availability');

$session = $DB->get_record('local_w3t_session', ['courseid' => $course->id, 'name' => 'P1 Attendance Fixture Session'], '*', MUST_EXIST);
$studentattendance = participation_service::get_attendance_by_user((int)$session->id);
web3t_p1_assert(isset($studentattendance[(int)$student->id]), 'student fixture attendance exists');
web3t_p1_assert($studentattendance[(int)$student->id]->status === participation_service::ATTENDANCE_PRESENT, 'student attendance status is present');
web3t_p1_assert((int)$studentattendance[(int)$student->id]->participation === 5, 'student participation score is stored');

$mentoravailability = participation_service::get_availability_by_user((int)$session->id);
web3t_p1_assert(isset($mentoravailability[(int)$mentor->id]), 'mentor fixture availability exists');
web3t_p1_assert($mentoravailability[(int)$mentor->id]->availability === participation_service::AVAILABILITY_AVAILABLE, 'mentor availability status is available');

web3t_p1_assert(count(participation_service::get_students($course)) >= 2, 'participation service reads enrolled students');
web3t_p1_assert(count(participation_service::get_mentors($course)) >= 1, 'participation service reads enrolled mentors');

echo 'P1 attendance, participation, and mentor availability validation complete.' . PHP_EOL;
