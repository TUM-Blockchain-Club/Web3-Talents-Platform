<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Admin review page for Moodle groups and Choice source data.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_web3talents\local\course_state_service;

admin_externalpage_setup('local_web3talents_course_state');

$context = context_system::instance();
require_capability('local/web3talents:manage', $context);

$PAGE->set_url(new moodle_url('/local/web3talents/course_state.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('course_state', 'local_web3talents'));
$PAGE->set_heading(get_string('course_state', 'local_web3talents'));

$state = course_state_service::get_state();
$course = $state['course'];
$choice = $state['choice'];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('course_state', 'local_web3talents'));
echo html_writer::tag('p', get_string('course_state_intro', 'local_web3talents'), ['class' => 'lead']);

$actions = [
    html_writer::link(new moodle_url('/local/web3talents/index.php'), get_string('pluginname', 'local_web3talents'), ['class' => 'btn btn-secondary']),
    html_writer::link(new moodle_url('/local/web3talents/applicants.php'), get_string('applicants', 'local_web3talents'), ['class' => 'btn btn-secondary']),
    html_writer::link(new moodle_url('/group/index.php', ['id' => $course->id]), get_string('review_groups', 'local_web3talents'), ['class' => 'btn btn-secondary']),
];
if ($choice) {
    $actions[] = html_writer::link(new moodle_url('/mod/choice/view.php', ['id' => $choice->cm->id]), get_string('review_choice', 'local_web3talents'), ['class' => 'btn btn-secondary']);
}
echo html_writer::div(implode(' ', $actions), 'mb-3');

$summary = $state['summary'];
$summarytable = new html_table();
$summarytable->head = [
    get_string('students', 'local_web3talents'),
    get_string('groups', 'local_web3talents'),
    get_string('choice_responses', 'local_web3talents'),
    get_string('warnings', 'local_web3talents'),
];
$summarytable->data[] = [
    $summary['studentcount'],
    $summary['groupcount'],
    $summary['responsecount'],
    $summary['studentwarningcount'] + $summary['groupwarningcount'],
];
echo html_writer::table($summarytable);

if (!$state['groupwarnings'] && $summary['studentwarningcount'] === 0) {
    echo $OUTPUT->notification(get_string('course_state_no_warnings', 'local_web3talents'), \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->heading(get_string('group_warnings', 'local_web3talents'), 3);
$grouptable = new html_table();
$grouptable->head = [
    get_string('group', 'local_web3talents'),
    get_string('members', 'local_web3talents'),
    get_string('topic_choices', 'local_web3talents'),
    get_string('warnings', 'local_web3talents'),
];
foreach ($state['groupwarnings'] as $warning) {
    $grouptable->data[] = [
        format_string($warning['group']->name),
        $warning['membercount'],
        $warning['choices'] ? s(implode(', ', $warning['choices'])) : get_string('none'),
        s(implode(', ', $warning['warnings'])),
    ];
}
if (!$grouptable->data) {
    $grouptable->data[] = [
        get_string('none'),
        '',
        '',
        get_string('course_state_no_group_warnings', 'local_web3talents'),
    ];
}
echo html_writer::table($grouptable);

echo $OUTPUT->heading(get_string('student_source_data', 'local_web3talents'), 3);
$studenttable = new html_table();
$studenttable->head = [
    get_string('student', 'local_web3talents'),
    get_string('email', 'local_web3talents'),
    get_string('groups', 'local_web3talents'),
    get_string('topic_choices', 'local_web3talents'),
    get_string('warnings', 'local_web3talents'),
];
foreach ($state['studentrows'] as $row) {
    $studenttable->data[] = [
        fullname($row['user']),
        s($row['user']->email),
        $row['groups'] ? s(implode(', ', $row['groups'])) : get_string('none'),
        $row['choices'] ? s(implode(', ', $row['choices'])) : get_string('none'),
        $row['warnings'] ? s(implode(', ', $row['warnings'])) : get_string('none'),
    ];
}
echo html_writer::table($studenttable);

echo $OUTPUT->footer();
