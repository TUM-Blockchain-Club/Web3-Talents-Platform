<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Mentor availability page.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_web3talents\local\participation_service;

$course = participation_service::get_configured_course();
require_login($course);

$coursecontext = context_course::instance($course->id);
require_capability('local/web3talents:manageownavailability', $coursecontext);

$url = new moodle_url('/local/web3talents/mentor_availability.php');
$action = optional_param('action', '', PARAM_ALPHAEXT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    try {
        if ($action === 'saveavailability') {
            $sessions = participation_service::get_sessions((int)$course->id);
            $availability = required_param_array('availability', PARAM_ALPHA);
            $notes = optional_param_array('notes', [], PARAM_TEXT);
            foreach ($sessions as $session) {
                $sessionid = (int)$session->id;
                participation_service::save_availability(
                    $sessionid,
                    (int)$USER->id,
                    $availability[$sessionid] ?? participation_service::AVAILABILITY_TENTATIVE,
                    $notes[$sessionid] ?? ''
                );
            }
            redirect($url, get_string('mentor_availability_saved', 'local_web3talents'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    } catch (Throwable $exception) {
        redirect($url, $exception->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$sessions = participation_service::get_sessions((int)$course->id);
$mentors = participation_service::get_mentors($course);
$statuses = participation_service::availability_statuses();

$PAGE->set_url($url);
$PAGE->set_context($coursecontext);
$PAGE->set_title(get_string('mentor_availability', 'local_web3talents'));
$PAGE->set_heading(get_string('mentor_availability', 'local_web3talents'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('mentor_availability', 'local_web3talents'));
echo html_writer::tag('p', get_string('mentor_availability_intro', 'local_web3talents'), ['class' => 'lead']);

echo html_writer::div(
    html_writer::link(new moodle_url('/local/web3talents/index.php'), get_string('pluginname', 'local_web3talents'), ['class' => 'btn btn-secondary']) . ' ' .
    html_writer::link(new moodle_url('/local/web3talents/participation.php'), get_string('participation', 'local_web3talents'), ['class' => 'btn btn-secondary']),
    'mb-3'
);

if (!$sessions) {
    echo $OUTPUT->notification(get_string('no_sessions', 'local_web3talents'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'saveavailability']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

$table = new html_table();
$table->head = [
    get_string('session', 'local_web3talents'),
    get_string('session_date', 'local_web3talents'),
    get_string('your_availability', 'local_web3talents'),
    get_string('notes', 'local_web3talents'),
    get_string('mentor_responses', 'local_web3talents'),
];

foreach ($sessions as $session) {
    $availability = participation_service::get_availability_by_user((int)$session->id);
    $own = $availability[(int)$USER->id] ?? null;
    $responses = [];
    foreach ($mentors as $mentor) {
        $record = $availability[(int)$mentor->id] ?? null;
        if (!$record) {
            continue;
        }
        $responses[] = fullname($mentor) . ': ' . $statuses[$record->availability];
    }
    $table->data[] = [
        format_string($session->name),
        userdate($session->sessiondate, get_string('strftimedatetimeshort')),
        html_writer::select($statuses, "availability[{$session->id}]", $own->availability ?? participation_service::AVAILABILITY_TENTATIVE, false, ['class' => 'form-select']),
        html_writer::empty_tag('input', ['name' => "notes[{$session->id}]", 'type' => 'text', 'class' => 'form-control', 'value' => $own->notes ?? '']),
        $responses ? s(implode('; ', $responses)) : get_string('none'),
    ];
}

echo html_writer::table($table);
echo html_writer::tag('button', get_string('save_availability', 'local_web3talents'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
