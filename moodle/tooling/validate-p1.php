<?php
// Validates P1 attendance, participation, and mentor availability features.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');
require_once($CFG->dirroot . '/local/web3talents/classes/local/mentor_grading_service.php');
require_once($CFG->dirroot . '/local/web3talents/classes/local/participation_service.php');
require_once($CFG->dirroot . '/local/web3talents/classes/local/room_assignment_service.php');

use local_web3talents\local\mentor_grading_service;
use local_web3talents\local\participation_service;
use local_web3talents\local\room_assignment_service;

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
web3t_p1_assert($DB->get_manager()->table_exists('local_w3t_room_mentor'), 'room mentor assignment table exists');
web3t_p1_assert($DB->get_manager()->table_exists('local_w3t_room_grade'), 'presentation grade table exists');

\core\session\manager::set_user($admin);
web3t_p1_assert(has_capability('local/web3talents:manageparticipation', $coursecontext), 'admin can manage participation');
web3t_p1_assert(has_capability('local/web3talents:manageownavailability', $coursecontext), 'admin can access mentor availability');
web3t_p1_assert(has_capability('local/web3talents:assignroommentors', $coursecontext), 'admin can assign room mentors');
web3t_p1_assert(has_capability('local/web3talents:gradeassignedroom', $coursecontext), 'admin can grade assigned rooms');

\core\session\manager::set_user($mentor);
web3t_p1_assert(has_capability('local/web3talents:manageparticipation', $coursecontext), 'mentor can manage participation');
web3t_p1_assert(has_capability('local/web3talents:manageownavailability', $coursecontext), 'mentor can manage own availability');
web3t_p1_assert(!has_capability('local/web3talents:assignroommentors', $coursecontext), 'mentor cannot assign room mentors');
web3t_p1_assert(has_capability('local/web3talents:gradeassignedroom', $coursecontext), 'mentor can grade assigned room');

\core\session\manager::set_user($student);
web3t_p1_assert(!has_capability('local/web3talents:manageparticipation', $coursecontext), 'student cannot manage participation');
web3t_p1_assert(!has_capability('local/web3talents:manageownavailability', $coursecontext), 'student cannot manage mentor availability');
web3t_p1_assert(!has_capability('local/web3talents:gradeassignedroom', $coursecontext), 'student cannot grade presentation rooms');

$session = $DB->get_record('local_w3t_session', ['courseid' => $course->id, 'name' => 'P1 Attendance Fixture Session'], '*', MUST_EXIST);
$studentattendance = participation_service::get_attendance_by_user((int)$session->id);
web3t_p1_assert(isset($studentattendance[(int)$student->id]), 'student fixture attendance exists');
web3t_p1_assert($studentattendance[(int)$student->id]->status === participation_service::ATTENDANCE_PRESENT, 'student attendance status is present');
web3t_p1_assert((int)$studentattendance[(int)$student->id]->participation === 5, 'student participation score is stored');

$mentoravailability = participation_service::get_availability_by_user((int)$session->id);
web3t_p1_assert(isset($mentoravailability[(int)$mentor->id]), 'mentor fixture availability exists');
web3t_p1_assert($mentoravailability[(int)$mentor->id]->availability === participation_service::AVAILABILITY_AVAILABLE, 'mentor availability status is available');

$result = room_assignment_service::get_latest_result_for_course((int)$course->id);
web3t_p1_assert($result !== null, 'room result exists for mentor grading');
$assignments = mentor_grading_service::get_assignments_by_room((int)$session->id, (int)$result->id);
$mentorassignment = null;
foreach ($assignments as $assignment) {
    if ((int)$assignment->mentorid === (int)$mentor->id) {
        $mentorassignment = $assignment;
        break;
    }
}
web3t_p1_assert($mentorassignment !== null, 'available mentor is assigned to one presentation room');
web3t_p1_assert(count(array_filter($assignments, fn($assignment) => (int)$assignment->mentorid === (int)$mentor->id)) === 1, 'mentor has exactly one official room assignment');

$grades = mentor_grading_service::get_grades_by_user((int)$session->id, (int)$result->id);
$gradedroomstudents = [];
$state = room_assignment_service::get_result_state((int)$result->id);
foreach ($state['rooms'] as $roomstate) {
    if ((int)$roomstate['room']->id !== (int)$mentorassignment->roomid) {
        continue;
    }
    foreach ($roomstate['assignments'] as $roomgroup) {
        foreach ($roomgroup['members'] as $member) {
            $gradedroomstudents[(int)$member->id] = true;
        }
    }
}
web3t_p1_assert($gradedroomstudents !== [], 'assigned room contains students to grade');
foreach (array_keys($gradedroomstudents) as $userid) {
    web3t_p1_assert(isset($grades[$userid]), "student {$userid} has a presentation grade");
    web3t_p1_assert((int)$grades[$userid]->grade === 7, "student {$userid} presentation grade is stored as 7");
}

web3t_p1_assert(count(participation_service::get_students($course)) >= 2, 'participation service reads enrolled students');
web3t_p1_assert(count(participation_service::get_mentors($course)) >= 1, 'participation service reads enrolled mentors');

echo 'P1 attendance, participation, mentor availability, and mentor grading validation complete.' . PHP_EOL;
