<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Student view of the latest live-session room assignment.
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
require_capability('local/web3talents:viewstudentrooms', $coursecontext);

$url = new moodle_url('/local/web3talents/my_room.php');
$PAGE->set_url($url);
$PAGE->set_context($coursecontext);
$PAGE->set_title(get_string('my_room_assignment', 'local_web3talents'));
$PAGE->set_heading(get_string('my_room_assignment', 'local_web3talents'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('my_room_assignment', 'local_web3talents'));

$state = room_assignment_service::get_user_room_state((int)$course->id, (int)$USER->id);
if (!$state) {
    echo $OUTPUT->notification(get_string('no_visible_room_assignment', 'local_web3talents'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

$membernames = array_map('fullname', $state['members']);
$table = new html_table();
$table->head = [
    get_string('session', 'local_web3talents'),
    get_string('room', 'local_web3talents'),
    get_string('topic', 'local_web3talents'),
    get_string('partner_group', 'local_web3talents'),
    get_string('members', 'local_web3talents'),
];
$table->data[] = [
    format_string($state['round']->name),
    s($state['room']->roomname),
    $state['topic'] ? format_string($state['topic']->name) : get_string('none'),
    format_string($state['assignment']['pgroup']->name),
    s(implode(', ', $membernames)),
];

echo html_writer::tag('p', get_string('my_room_assignment_intro', 'local_web3talents'), ['class' => 'lead']);
echo html_writer::table($table);

echo $OUTPUT->footer();
