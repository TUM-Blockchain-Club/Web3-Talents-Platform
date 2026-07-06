<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Student weekly topic-selection page.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_web3talents\local\topic_round_service;

$course = topic_round_service::get_configured_course();
require_login($course);

$coursecontext = context_course::instance($course->id);
require_capability('local/web3talents:viewstudentrooms', $coursecontext);

$url = new moodle_url('/local/web3talents/choose_topic.php');
$round = topic_round_service::get_current_round((int)$course->id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    try {
        $roundid = required_param('roundid', PARAM_INT);
        $topicid = required_param('topicid', PARAM_INT);
        topic_round_service::select_topic($roundid, (int)$USER->id, $topicid);
        redirect($url, get_string('topic_choice_saved', 'local_web3talents'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (Throwable $exception) {
        redirect($url, $exception->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$PAGE->set_url($url);
$PAGE->set_context($coursecontext);
$PAGE->set_title(get_string('choose_weekly_topic', 'local_web3talents'));
$PAGE->set_heading(get_string('choose_weekly_topic', 'local_web3talents'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('choose_weekly_topic', 'local_web3talents'));

if (!$round) {
    echo $OUTPUT->notification(get_string('no_topic_round_available', 'local_web3talents'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

$state = topic_round_service::get_student_state((int)$round->id, (int)$USER->id);
$selectedtopic = $state['choice'] ? $state['choice']->topicid : 0;
$topic = topic_round_service::get_user_choice_topic((int)$round->id, (int)$USER->id);

echo html_writer::tag('p', get_string('topic_round_student_intro', 'local_web3talents', format_string($round->name)), ['class' => 'lead']);

if (!$state['pgroup']) {
    echo $OUTPUT->notification(get_string('error_no_partner_group', 'local_web3talents'), \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->footer();
    exit;
}

$membernames = array_map('fullname', $state['members']);
echo html_writer::tag('p', get_string('your_partner_group', 'local_web3talents', (object)[
    'group' => format_string($state['pgroup']->name),
    'members' => implode(', ', $membernames),
]));

if ($topic) {
    echo $OUTPUT->notification(get_string('current_topic_choice', 'local_web3talents', format_string($topic->name)), \core\output\notification::NOTIFY_INFO);
}

if (!$state['isopen']) {
    echo $OUTPUT->notification(get_string('topic_round_closed', 'local_web3talents'), \core\output\notification::NOTIFY_INFO);
}

$table = new html_table();
$table->head = [
    get_string('topic', 'local_web3talents'),
    get_string('group_slots_left', 'local_web3talents'),
    get_string('actions', 'local_web3talents'),
];
foreach ($state['topics'] as $topicrecord) {
    $button = '';
    if ($state['isopen']) {
        $disabled = ((int)$topicrecord->slotsleft <= 0 && (int)$selectedtopic !== (int)$topicrecord->id);
        $button = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-inline']) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'roundid', 'value' => $round->id]) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'topicid', 'value' => $topicrecord->id]) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
            html_writer::tag('button', (int)$selectedtopic === (int)$topicrecord->id ? get_string('selected', 'local_web3talents') : get_string('choose_topic', 'local_web3talents'), [
                'type' => 'submit',
                'class' => (int)$selectedtopic === (int)$topicrecord->id ? 'btn btn-success btn-sm' : 'btn btn-primary btn-sm',
                'disabled' => $disabled ? 'disabled' : null,
            ]) .
            html_writer::end_tag('form');
    }
    $table->data[] = [
        format_string($topicrecord->name),
        $topicrecord->slotsleft . ' / ' . $topicrecord->slotlimit,
        $button,
    ];
}
echo html_writer::table($table);

echo $OUTPUT->footer();
