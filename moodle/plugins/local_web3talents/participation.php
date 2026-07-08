<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Attendance and participation tracking page.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_web3talents\local\participation_service;

$course = participation_service::get_configured_course();
require_login($course);

$coursecontext = context_course::instance($course->id);
require_capability('local/web3talents:manageparticipation', $coursecontext);

$url = new moodle_url('/local/web3talents/participation.php');
$action = optional_param('action', '', PARAM_ALPHAEXT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    try {
        if ($action === 'createsession') {
            $name = required_param('sessionname', PARAM_TEXT);
            $dateinput = required_param('sessiondate', PARAM_RAW_TRIMMED);
            $notes = optional_param('sessionnotes', '', PARAM_TEXT);
            $sessiondate = strtotime($dateinput);
            participation_service::upsert_session((int)$course->id, $name, $sessiondate ?: 0, $notes, (int)$USER->id);
            redirect($url, get_string('participation_session_saved', 'local_web3talents'), null, \core\output\notification::NOTIFY_SUCCESS);
        }

        if ($action === 'saveattendance') {
            $sessionid = required_param('sessionid', PARAM_INT);
            $students = participation_service::get_students($course);
            $statuses = required_param_array('status', PARAM_ALPHA);
            $participation = required_param_array('participation', PARAM_INT);
            $notes = optional_param_array('notes', [], PARAM_TEXT);
            foreach ($students as $student) {
                $userid = (int)$student->id;
                participation_service::save_attendance(
                    $sessionid,
                    $userid,
                    $statuses[$userid] ?? participation_service::ATTENDANCE_ABSENT,
                    (int)($participation[$userid] ?? 0),
                    $notes[$userid] ?? '',
                    (int)$USER->id
                );
            }
            redirect(new moodle_url($url, ['sessionid' => $sessionid]), get_string('attendance_saved', 'local_web3talents'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    } catch (Throwable $exception) {
        redirect($url, $exception->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$sessions = participation_service::get_sessions((int)$course->id);
$selectedsessionid = optional_param('sessionid', 0, PARAM_INT);
if (!$selectedsessionid && $sessions) {
    $selectedsessionid = (int)reset($sessions)->id;
}
$selectedsession = $selectedsessionid ? participation_service::get_session($selectedsessionid) : null;
$students = participation_service::get_students($course);
$attendance = $selectedsession ? participation_service::get_attendance_by_user((int)$selectedsession->id) : [];
$availability = $selectedsession ? participation_service::get_availability_by_user((int)$selectedsession->id) : [];
$mentors = participation_service::get_mentors($course);

$PAGE->set_url($url);
$PAGE->set_context($coursecontext);
$PAGE->set_title(get_string('participation', 'local_web3talents'));
$PAGE->set_heading(get_string('participation', 'local_web3talents'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('participation', 'local_web3talents'));
echo html_writer::tag('p', get_string('participation_intro', 'local_web3talents'), ['class' => 'lead']);

echo html_writer::div(
    html_writer::link(new moodle_url('/local/web3talents/index.php'), get_string('pluginname', 'local_web3talents'), ['class' => 'btn btn-secondary']) . ' ' .
    html_writer::link(new moodle_url('/local/web3talents/mentor_availability.php'), get_string('mentor_availability', 'local_web3talents'), ['class' => 'btn btn-secondary']),
    'mb-3'
);

echo $OUTPUT->box_start('generalbox mb-4');
echo $OUTPUT->heading(get_string('create_session', 'local_web3talents'), 3);
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false), 'class' => 'row g-2']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'createsession']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::div(
    html_writer::label(get_string('session_name', 'local_web3talents'), 'sessionname') .
    html_writer::empty_tag('input', ['id' => 'sessionname', 'name' => 'sessionname', 'type' => 'text', 'class' => 'form-control', 'value' => 'Live session ' . userdate(time(), '%Y-%m-%d')]),
    'col-md-4'
);
echo html_writer::div(
    html_writer::label(get_string('session_date', 'local_web3talents'), 'sessiondate') .
    html_writer::empty_tag('input', ['id' => 'sessiondate', 'name' => 'sessiondate', 'type' => 'datetime-local', 'class' => 'form-control', 'value' => userdate(time(), '%Y-%m-%dT%H:%M')]),
    'col-md-3'
);
echo html_writer::div(
    html_writer::label(get_string('notes', 'local_web3talents'), 'sessionnotes') .
    html_writer::empty_tag('input', ['id' => 'sessionnotes', 'name' => 'sessionnotes', 'type' => 'text', 'class' => 'form-control']),
    'col-md-3'
);
echo html_writer::div(
    html_writer::tag('button', get_string('create_session', 'local_web3talents'), ['type' => 'submit', 'class' => 'btn btn-primary mt-4']),
    'col-md-2'
);
echo html_writer::end_tag('form');
echo $OUTPUT->box_end();

if (!$sessions) {
    echo $OUTPUT->notification(get_string('no_sessions', 'local_web3talents'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

$sessionoptions = [];
foreach ($sessions as $session) {
    $sessionoptions[(int)$session->id] = format_string($session->name) . ' - ' . userdate($session->sessiondate, get_string('strftimedatetimeshort'));
}
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $url->out(false), 'class' => 'mb-3']);
echo html_writer::label(get_string('select_session', 'local_web3talents'), 'sessionid', false, ['class' => 'me-2']);
echo html_writer::select($sessionoptions, 'sessionid', $selectedsessionid, false, ['id' => 'sessionid', 'class' => 'form-select d-inline-block w-auto me-2']);
echo html_writer::tag('button', get_string('view'), ['type' => 'submit', 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

if ($selectedsession) {
    echo $OUTPUT->heading(format_string($selectedsession->name), 3);
    echo html_writer::tag('p', userdate($selectedsession->sessiondate, get_string('strftimedatetimeshort')));

    $mentoritems = [];
    foreach ($mentors as $mentor) {
        $record = $availability[(int)$mentor->id] ?? null;
        $label = $record ? participation_service::availability_statuses()[$record->availability] : get_string('availability_status_unknown', 'local_web3talents');
        $mentoritems[] = fullname($mentor) . ': ' . $label;
    }
    if ($mentoritems) {
        echo html_writer::tag('p', get_string('mentor_availability_summary', 'local_web3talents') . ' ' . s(implode('; ', $mentoritems)));
    }

    $statuses = participation_service::attendance_statuses();
    $scores = array_combine(range(0, 5), range(0, 5));
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'saveattendance']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sessionid', 'value' => $selectedsession->id]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $table = new html_table();
    $table->head = [
        get_string('student', 'local_web3talents'),
        get_string('email', 'local_web3talents'),
        get_string('attendance_status', 'local_web3talents'),
        get_string('participation_score', 'local_web3talents'),
        get_string('notes', 'local_web3talents'),
    ];
    foreach ($students as $student) {
        $record = $attendance[(int)$student->id] ?? null;
        $table->data[] = [
            fullname($student),
            s($student->email),
            html_writer::select($statuses, "status[{$student->id}]", $record->status ?? participation_service::ATTENDANCE_ABSENT, false, ['class' => 'form-select']),
            html_writer::select($scores, "participation[{$student->id}]", $record->participation ?? 0, false, ['class' => 'form-select']),
            html_writer::empty_tag('input', ['name' => "notes[{$student->id}]", 'type' => 'text', 'class' => 'form-control', 'value' => $record->notes ?? '']),
        ];
    }
    echo html_writer::table($table);
    echo html_writer::tag('button', get_string('save_attendance', 'local_web3talents'), ['type' => 'submit', 'class' => 'btn btn-primary']);
    echo html_writer::end_tag('form');
}

echo $OUTPUT->footer();
