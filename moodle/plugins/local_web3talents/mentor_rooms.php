<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Mentor read-only room assignment overview.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_web3talents\local\room_assignment_service;
use local_web3talents\local\topic_round_service;

$course = topic_round_service::get_configured_course();
require_login($course);

$coursecontext = context_course::instance($course->id);
require_capability('local/web3talents:viewmentorrooms', $coursecontext);

$url = new moodle_url('/local/web3talents/mentor_rooms.php');
$PAGE->set_url($url);
$PAGE->set_context($coursecontext);
$PAGE->set_title(get_string('mentor_room_assignments', 'local_web3talents'));
$PAGE->set_heading(get_string('mentor_room_assignments', 'local_web3talents'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('mentor_room_assignments', 'local_web3talents'));

$result = room_assignment_service::get_latest_result_for_course((int)$course->id);
if (!$result) {
    echo $OUTPUT->notification(get_string('no_visible_room_assignment', 'local_web3talents'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

$state = room_assignment_service::get_result_state((int)$result->id);
echo html_writer::tag('p', get_string('mentor_room_assignments_intro', 'local_web3talents', format_string($state['round']->name)), ['class' => 'lead']);

foreach ($state['rooms'] as $roomstate) {
    echo $OUTPUT->box_start('generalbox mb-3');
    echo $OUTPUT->heading(s($roomstate['room']->roomname), 3);

    $table = new html_table();
    $table->head = [
        get_string('topic', 'local_web3talents'),
        get_string('partner_group', 'local_web3talents'),
        get_string('members', 'local_web3talents'),
    ];
    foreach ($roomstate['assignments'] as $assignment) {
        $membernames = array_map('fullname', $assignment['members']);
        $table->data[] = [
            $assignment['topic'] ? format_string($assignment['topic']->name) : get_string('none'),
            format_string($assignment['pgroup']->name),
            s(implode(', ', $membernames)),
        ];
    }
    echo html_writer::table($table);
    echo $OUTPUT->box_end();
}

echo $OUTPUT->footer();
