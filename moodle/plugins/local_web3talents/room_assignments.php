<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Admin page for hidden room generation and manual movement.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_web3talents\local\room_assignment_service;
use local_web3talents\local\topic_round_service;

admin_externalpage_setup('local_web3talents_room_assignments');

$context = context_system::instance();
require_capability('local/web3talents:manage', $context);

$course = topic_round_service::get_configured_course();
$coursecontext = context_course::instance($course->id);
$url = new moodle_url('/local/web3talents/room_assignments.php');
$action = optional_param('action', '', PARAM_ALPHAEXT);
$selectedroundid = optional_param('roundid', 0, PARAM_INT);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'downloadzoomcsv') {
    require_sesskey();
    require_capability('local/web3talents:downloadzoomcsv', $coursecontext);

    require_once($CFG->libdir . '/csvlib.class.php');

    $resultid = required_param('resultid', PARAM_INT);
    $DB->get_record('local_w3t_room_result', ['id' => $resultid, 'courseid' => $course->id], '*', MUST_EXIST);
    $records = room_assignment_service::get_zoom_csv_rows($resultid, (int)$USER->id);
    $csv = csv_export_writer::print_array($records, 'comma', '"', true);
    send_file($csv, room_assignment_service::get_zoom_csv_filename($resultid), 0, 0, true, true, 'text/csv');
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'downloadinternal') {
    require_sesskey();
    require_capability('local/web3talents:downloadzoomcsv', $coursecontext);

    $resultid = required_param('resultid', PARAM_INT);
    $DB->get_record('local_w3t_room_result', ['id' => $resultid, 'courseid' => $course->id], '*', MUST_EXIST);
    $filepath = room_assignment_service::write_internal_excel_file($resultid, (int)$USER->id);
    send_file(
        $filepath,
        room_assignment_service::get_internal_excel_filename($resultid),
        0,
        0,
        false,
        true,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    try {
        if ($action === 'generate') {
            require_capability('local/web3talents:managerooms', $coursecontext);
            $roundid = required_param('roundid', PARAM_INT);
            $roomcount = required_param('roomcount', PARAM_INT);
            room_assignment_service::generate($roundid, max(1, $roomcount), (int)$USER->id);
            redirect(new moodle_url($url, ['roundid' => $roundid]), get_string('rooms_generated', 'local_web3talents'), null, \core\output\notification::NOTIFY_SUCCESS);
        }

        if ($action === 'move') {
            require_capability('local/web3talents:managerooms', $coursecontext);
            $resultid = required_param('resultid', PARAM_INT);
            $roundid = required_param('roundid', PARAM_INT);
            $pgroupid = required_param('pgroupid', PARAM_INT);
            $targetroomid = required_param('targetroomid', PARAM_INT);
            room_assignment_service::move_group($resultid, $pgroupid, $targetroomid);
            redirect(new moodle_url($url, ['roundid' => $roundid]), get_string('room_group_moved', 'local_web3talents'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    } catch (Throwable $exception) {
        redirect($url, $exception->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$rounds = $DB->get_records('local_w3t_round', [
    'courseid' => $course->id,
    'status' => topic_round_service::STATUS_FINALIZED,
], 'id DESC');
if (!$selectedroundid && $rounds) {
    foreach ($rounds as $round) {
        if ($DB->record_exists('local_w3t_room_result', ['roundid' => $round->id])) {
            $selectedroundid = (int)$round->id;
            break;
        }
    }
    if (!$selectedroundid) {
        $selectedroundid = (int)reset($rounds)->id;
    }
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('room_assignments', 'local_web3talents'));
$PAGE->set_heading(get_string('room_assignments', 'local_web3talents'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('room_assignments', 'local_web3talents'));
echo html_writer::tag('p', get_string('room_assignments_intro', 'local_web3talents'), ['class' => 'lead']);
echo html_writer::div(
    html_writer::link(new moodle_url('/local/web3talents/index.php'), get_string('pluginname', 'local_web3talents'), ['class' => 'btn btn-secondary']) . ' ' .
    html_writer::link(new moodle_url('/local/web3talents/topic_rounds.php'), get_string('topic_rounds', 'local_web3talents'), ['class' => 'btn btn-secondary']),
    'mb-3'
);

if (!$rounds) {
    echo $OUTPUT->notification(get_string('no_finalized_rounds', 'local_web3talents'), \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::start_tag('form', ['method' => 'get', 'action' => $url->out(false), 'class' => 'mb-3']);
echo html_writer::label(get_string('topic_round_name', 'local_web3talents'), 'roundid');
$roundoptions = [];
foreach ($rounds as $round) {
    $roundoptions[(int)$round->id] = format_string($round->name);
}
echo html_writer::select($roundoptions, 'roundid', $selectedroundid, false, ['id' => 'roundid', 'class' => 'form-select mb-2']);
echo html_writer::tag('button', get_string('show'), ['type' => 'submit', 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

$selectedround = $DB->get_record('local_w3t_round', ['id' => $selectedroundid], '*', MUST_EXIST);
$groups = $DB->get_records('local_w3t_pgroup', ['partnersetid' => $selectedround->partnersetid]);
$topics = $DB->get_records('local_w3t_topic', ['roundid' => $selectedround->id]);
$recommendedroomcount = max(1, (int)ceil(count($groups) / max(1, count($topics))));

echo $OUTPUT->box_start('generalbox mb-4');
echo $OUTPUT->heading(get_string('generate_rooms', 'local_web3talents'), 3);
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'generate']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'roundid', 'value' => $selectedround->id]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::label(get_string('room_count', 'local_web3talents'), 'roomcount');
echo html_writer::empty_tag('input', ['id' => 'roomcount', 'name' => 'roomcount', 'type' => 'number', 'min' => 1, 'class' => 'form-control mb-2', 'value' => $recommendedroomcount]);
echo html_writer::tag('button', get_string('generate_rooms', 'local_web3talents'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');
echo $OUTPUT->box_end();

$result = room_assignment_service::get_latest_result((int)$selectedround->id);
if (!$result) {
    echo $OUTPUT->notification(get_string('no_room_result', 'local_web3talents'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

$state = room_assignment_service::get_result_state((int)$result->id);
if ($state['warnings']) {
    echo $OUTPUT->notification(html_writer::alist(array_map('s', $state['warnings'])), \core\output\notification::NOTIFY_WARNING);
}

if (has_capability('local/web3talents:downloadzoomcsv', $coursecontext)) {
    echo html_writer::div(
        html_writer::link(
            new moodle_url($url, [
                'action' => 'downloadinternal',
                'roundid' => $selectedround->id,
                'resultid' => $result->id,
                'sesskey' => sesskey(),
            ]),
            get_string('download_internal_room_assignments', 'local_web3talents'),
            ['class' => 'btn btn-secondary']
        ) . ' ' .
        html_writer::link(
            new moodle_url($url, [
                'action' => 'downloadzoomcsv',
                'roundid' => $selectedround->id,
                'resultid' => $result->id,
                'sesskey' => sesskey(),
            ]),
            get_string('download_zoom_csv', 'local_web3talents'),
            ['class' => 'btn btn-primary']
        ),
        'mb-3'
    );
}

$roomoptions = [];
foreach ($state['rooms'] as $roomstate) {
    $roomoptions[(int)$roomstate['room']->id] = $roomstate['room']->roomname;
}

foreach ($state['rooms'] as $roomstate) {
    echo $OUTPUT->box_start('generalbox mb-3');
    echo $OUTPUT->heading(s($roomstate['room']->roomname), 3);
    $table = new html_table();
    $table->head = [
        get_string('group', 'local_web3talents'),
        get_string('members', 'local_web3talents'),
        get_string('topic', 'local_web3talents'),
        get_string('assignment_reason', 'local_web3talents'),
        get_string('move_to_room', 'local_web3talents'),
    ];
    foreach ($roomstate['assignments'] as $assignment) {
        $membernames = array_map('fullname', $assignment['members']);
        $moveform = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-flex gap-2']) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'move']) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'roundid', 'value' => $selectedround->id]) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'resultid', 'value' => $result->id]) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'pgroupid', 'value' => $assignment['pgroup']->id]) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
            html_writer::select($roomoptions, 'targetroomid', $roomstate['room']->id, false, ['class' => 'form-select']) .
            html_writer::tag('button', get_string('move', 'local_web3talents'), ['type' => 'submit', 'class' => 'btn btn-secondary btn-sm']) .
            html_writer::end_tag('form');
        $table->data[] = [
            s($assignment['pgroup']->name),
            s(implode(', ', $membernames)),
            $assignment['topic'] ? format_string($assignment['topic']->name) : get_string('none'),
            s($assignment['reason']),
            $moveform,
        ];
    }
    echo html_writer::table($table);
    echo $OUTPUT->box_end();
}

echo $OUTPUT->footer();
