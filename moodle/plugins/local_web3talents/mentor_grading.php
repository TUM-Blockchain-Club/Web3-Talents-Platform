<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Presentation-week mentor grading page.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_web3talents\local\mentor_grading_service;
use local_web3talents\local\participation_service;
use local_web3talents\local\room_assignment_service;

$course = participation_service::get_configured_course();
require_login($course);

$coursecontext = context_course::instance($course->id);
$canassign = has_capability('local/web3talents:assignroommentors', $coursecontext);
$cangrade = has_capability('local/web3talents:gradeassignedroom', $coursecontext);
if (!$canassign && !$cangrade) {
    require_capability('local/web3talents:gradeassignedroom', $coursecontext);
}

$url = new moodle_url('/local/web3talents/mentor_grading.php');
$action = optional_param('action', '', PARAM_ALPHAEXT);

$sessions = participation_service::get_sessions((int)$course->id);
$result = room_assignment_service::get_latest_result_for_course((int)$course->id);
$sessionid = optional_param('sessionid', 0, PARAM_INT);
if (!$sessionid && $sessions) {
    $sessionid = (int)reset($sessions)->id;
}
$resultid = optional_param('resultid', $result ? (int)$result->id : 0, PARAM_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    try {
        $sessionid = required_param('sessionid', PARAM_INT);
        $resultid = required_param('resultid', PARAM_INT);

        if ($action === 'autoassign') {
            require_capability('local/web3talents:assignroommentors', $coursecontext);
            $counts = mentor_grading_service::auto_assign_available_mentors($sessionid, $resultid, (int)$USER->id);
            redirect(
                new moodle_url($url, ['sessionid' => $sessionid, 'resultid' => $resultid]),
                get_string('mentor_rooms_auto_assigned', 'local_web3talents', $counts),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }

        if ($action === 'assignmentor') {
            require_capability('local/web3talents:assignroommentors', $coursecontext);
            $roomid = required_param('roomid', PARAM_INT);
            $mentorid = required_param('mentorid', PARAM_INT);
            mentor_grading_service::assign_mentor($sessionid, $resultid, $roomid, $mentorid, (int)$USER->id);
            redirect(new moodle_url($url, ['sessionid' => $sessionid, 'resultid' => $resultid]), get_string('mentor_room_assignment_saved', 'local_web3talents'), null, \core\output\notification::NOTIFY_SUCCESS);
        }

        if ($action === 'savegrades') {
            require_capability('local/web3talents:gradeassignedroom', $coursecontext);
            $roomid = required_param('roomid', PARAM_INT);
            $assignments = mentor_grading_service::get_assignments_by_room($sessionid, $resultid);
            $assignment = $assignments[$roomid] ?? null;
            if (!$canassign && (!$assignment || (int)$assignment->mentorid !== (int)$USER->id)) {
                throw new moodle_exception('error_not_assigned_room', 'local_web3talents');
            }

            $grades = required_param_array('grade', PARAM_INT);
            $notes = optional_param_array('notes', [], PARAM_TEXT);
            foreach ($grades as $userid => $grade) {
                mentor_grading_service::save_grade(
                    $sessionid,
                    $resultid,
                    $roomid,
                    (int)$userid,
                    ((int)$grade) >= 0 ? (int)$grade : null,
                    $notes[$userid] ?? '',
                    (int)$USER->id
                );
            }
            redirect(new moodle_url($url, ['sessionid' => $sessionid, 'resultid' => $resultid]), get_string('mentor_grades_saved', 'local_web3talents'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    } catch (Throwable $exception) {
        redirect(new moodle_url($url, ['sessionid' => $sessionid, 'resultid' => $resultid]), $exception->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$PAGE->set_url($url);
$PAGE->set_context($coursecontext);
$PAGE->set_title(get_string('mentor_grading', 'local_web3talents'));
$PAGE->set_heading(get_string('mentor_grading', 'local_web3talents'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('mentor_grading', 'local_web3talents'));
echo html_writer::tag('p', get_string('mentor_grading_intro', 'local_web3talents'), ['class' => 'lead']);

echo html_writer::div(
    html_writer::link(new moodle_url('/local/web3talents/index.php'), get_string('pluginname', 'local_web3talents'), ['class' => 'btn btn-secondary']) . ' ' .
    html_writer::link(new moodle_url('/local/web3talents/mentor_availability.php'), get_string('mentor_availability', 'local_web3talents'), ['class' => 'btn btn-secondary']) . ' ' .
    html_writer::link(new moodle_url('/local/web3talents/room_assignments.php'), get_string('room_assignments', 'local_web3talents'), ['class' => 'btn btn-secondary']),
    'mb-3'
);

if (!$sessions) {
    echo $OUTPUT->notification(get_string('no_sessions', 'local_web3talents'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}
if (!$resultid) {
    echo $OUTPUT->notification(get_string('no_room_result', 'local_web3talents'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

$sessionoptions = [];
foreach ($sessions as $session) {
    $sessionoptions[(int)$session->id] = format_string($session->name) . ' - ' . userdate($session->sessiondate, get_string('strftimedatetimeshort'));
}
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $url->out(false), 'class' => 'mb-3']);
echo html_writer::label(get_string('select_session', 'local_web3talents'), 'sessionid', false, ['class' => 'me-2']);
echo html_writer::select($sessionoptions, 'sessionid', $sessionid, false, ['id' => 'sessionid', 'class' => 'form-select d-inline-block w-auto me-2']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'resultid', 'value' => $resultid]);
echo html_writer::tag('button', get_string('view'), ['type' => 'submit', 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

$state = room_assignment_service::get_result_state($resultid);
$assignments = mentor_grading_service::get_assignments_by_room($sessionid, $resultid);
$grades = mentor_grading_service::get_grades_by_user($sessionid, $resultid);
$mentors = participation_service::get_mentors($course);
$mentoroptions = [0 => get_string('unassigned', 'local_web3talents')];
foreach ($mentors as $mentor) {
    $mentoroptions[(int)$mentor->id] = fullname($mentor);
}

if ($canassign) {
    echo $OUTPUT->box_start('generalbox mb-4');
    echo $OUTPUT->heading(get_string('mentor_room_assignments_admin', 'local_web3talents'), 3);
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false), 'class' => 'mb-3']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'autoassign']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sessionid', 'value' => $sessionid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'resultid', 'value' => $resultid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::tag('button', get_string('auto_assign_available_mentors', 'local_web3talents'), ['type' => 'submit', 'class' => 'btn btn-primary']);
    echo html_writer::end_tag('form');

    $assignmenttable = new html_table();
    $assignmenttable->head = [
        get_string('room', 'local_web3talents'),
        get_string('mentor', 'local_web3talents'),
        get_string('actions', 'local_web3talents'),
    ];
    foreach ($state['rooms'] as $roomstate) {
        $roomid = (int)$roomstate['room']->id;
        $assignment = $assignments[$roomid] ?? null;
        $form = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-flex gap-2']) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'assignmentor']) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sessionid', 'value' => $sessionid]) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'resultid', 'value' => $resultid]) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'roomid', 'value' => $roomid]) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
            html_writer::select($mentoroptions, 'mentorid', $assignment->mentorid ?? 0, false, ['class' => 'form-select']) .
            html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-secondary btn-sm']) .
            html_writer::end_tag('form');
        $assignmenttable->data[] = [
            s($roomstate['room']->roomname),
            $assignment && isset($mentors[(int)$assignment->mentorid]) ? fullname($mentors[(int)$assignment->mentorid]) : get_string('unassigned', 'local_web3talents'),
            $form,
        ];
    }
    echo html_writer::table($assignmenttable);
    echo $OUTPUT->box_end();
}

$visiblerooms = $canassign ? $state['rooms'] : mentor_grading_service::rooms_for_mentor($state, $assignments, (int)$USER->id);
if (!$visiblerooms) {
    echo $OUTPUT->notification(get_string('no_assigned_mentor_room', 'local_web3talents'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

$gradeoptions = [-1 => get_string('not_graded', 'local_web3talents')];
for ($grade = mentor_grading_service::MIN_GRADE; $grade <= mentor_grading_service::MAX_GRADE; $grade++) {
    $gradeoptions[$grade] = (string)$grade;
}

foreach ($visiblerooms as $roomstate) {
    $roomid = (int)$roomstate['room']->id;
    $assignment = $assignments[$roomid] ?? null;
    echo $OUTPUT->box_start('generalbox mb-4');
    echo $OUTPUT->heading(s($roomstate['room']->roomname), 3);
    echo html_writer::tag('p', get_string('assigned_mentor', 'local_web3talents') . ' ' .
        ($assignment && isset($mentors[(int)$assignment->mentorid]) ? fullname($mentors[(int)$assignment->mentorid]) : get_string('unassigned', 'local_web3talents')));

    $studentrows = [];
    foreach ($roomstate['assignments'] as $roomgroup) {
        foreach ($roomgroup['members'] as $member) {
            $studentrows[(int)$member->id] = [
                'user' => $member,
                'topic' => $roomgroup['topic'] ? $roomgroup['topic']->name : get_string('none'),
                'group' => $roomgroup['pgroup']->name,
            ];
        }
    }

    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'savegrades']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sessionid', 'value' => $sessionid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'resultid', 'value' => $resultid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'roomid', 'value' => $roomid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    $table = new html_table();
    $table->head = [
        get_string('student', 'local_web3talents'),
        get_string('email', 'local_web3talents'),
        get_string('topic', 'local_web3talents'),
        get_string('partner_group', 'local_web3talents'),
        get_string('presentation_grade', 'local_web3talents'),
        get_string('notes', 'local_web3talents'),
    ];
    foreach ($studentrows as $userid => $row) {
        $grade = $grades[$userid] ?? null;
        $table->data[] = [
            fullname($row['user']),
            s($row['user']->email),
            format_string($row['topic']),
            format_string($row['group']),
            html_writer::select($gradeoptions, "grade[{$userid}]", $grade->grade ?? -1, false, ['class' => 'form-select']),
            html_writer::empty_tag('input', ['name' => "notes[{$userid}]", 'type' => 'text', 'class' => 'form-control', 'value' => $grade->notes ?? '']),
        ];
    }
    echo html_writer::table($table);
    echo html_writer::tag('button', get_string('save_grades', 'local_web3talents'), ['type' => 'submit', 'class' => 'btn btn-primary']);
    echo html_writer::end_tag('form');
    echo $OUTPUT->box_end();
}

echo $OUTPUT->footer();
