<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Admin page for weekly group-slot topic rounds.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_web3talents\local\topic_round_service;

admin_externalpage_setup('local_web3talents_topic_rounds');

$context = context_system::instance();
require_capability('local/web3talents:manage', $context);

$course = topic_round_service::get_configured_course();
$url = new moodle_url('/local/web3talents/topic_rounds.php');
$action = optional_param('action', '', PARAM_ALPHAEXT);

function web3t_topic_rounds_table(array $rounds, array $state, moodle_url $url): html_table {
    $roundtable = new html_table();
    $roundtable->head = [
        get_string('topic_round_name', 'local_web3talents'),
        get_string('status'),
        get_string('opens', 'local_web3talents'),
        get_string('closes', 'local_web3talents'),
        get_string('topic_choices', 'local_web3talents'),
        get_string('actions', 'local_web3talents'),
    ];
    foreach ($rounds as $round) {
        $topics = $state['topics'][(int)$round->id] ?? [];
        $topiclabels = [];
        foreach ($topics as $topic) {
            $topiclabels[] = format_string($topic->name) . ' ' . $topic->usedslots . '/' . $topic->slotlimit;
        }
        $actions = '';
        if ($round->status !== topic_round_service::STATUS_FINALIZED) {
            $actions = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-inline']) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'finalize']) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'roundid', 'value' => $round->id]) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
                html_writer::tag('button', get_string('finalize_now', 'local_web3talents'), ['type' => 'submit', 'class' => 'btn btn-secondary btn-sm']) .
                html_writer::end_tag('form');
        }
        $roundtable->data[] = [
            format_string($round->name),
            s($round->status),
            userdate($round->opentime),
            userdate($round->closetime),
            s(implode(', ', $topiclabels)),
            $actions,
        ];
    }
    return $roundtable;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    try {
        if ($action === 'createpartnerset') {
            $setname = required_param('setname', PARAM_TEXT);
            $groupsraw = required_param('groups', PARAM_RAW_TRIMMED);
            $set = topic_round_service::create_partner_set((int)$course->id, $setname);
            $lines = preg_split('/\R+/', $groupsraw) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || !str_contains($line, ':')) {
                    continue;
                }
                [$groupname, $membersraw] = array_map('trim', explode(':', $line, 2));
                $userids = [];
                foreach (array_map('trim', explode(',', $membersraw)) as $username) {
                    if ($username === '') {
                        continue;
                    }
                    $user = $DB->get_record('user', ['username' => core_text::strtolower($username), 'deleted' => 0], '*', MUST_EXIST);
                    $userids[] = (int)$user->id;
                }
                topic_round_service::create_partner_group((int)$set->id, $groupname, $userids);
            }
            redirect($url, get_string('partner_set_saved', 'local_web3talents'), null, \core\output\notification::NOTIFY_SUCCESS);
        }

        if ($action === 'createround') {
            $set = topic_round_service::get_active_partner_set((int)$course->id);
            if (!$set) {
                throw new moodle_exception('error_no_active_partner_set', 'local_web3talents');
            }
            $roundname = required_param('roundname', PARAM_TEXT);
            $defaultslots = required_param('defaultslots', PARAM_INT);
            $durationhours = required_param('durationhours', PARAM_INT);
            $topics = required_param_array('topics', PARAM_TEXT);
            $opentime = time();
            $closetime = $opentime + max(1, $durationhours) * HOURSECS;
            topic_round_service::create_round((int)$course->id, (int)$set->id, $roundname, $opentime, $closetime, max(1, $defaultslots), $topics);
            redirect($url, get_string('topic_round_saved', 'local_web3talents'), null, \core\output\notification::NOTIFY_SUCCESS);
        }

        if ($action === 'finalize') {
            $roundid = required_param('roundid', PARAM_INT);
            topic_round_service::finalize_round($roundid);
            redirect($url, get_string('topic_round_finalized', 'local_web3talents'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    } catch (Throwable $exception) {
        redirect($url, $exception->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$state = topic_round_service::get_admin_state((int)$course->id);
$active = topic_round_service::get_active_partner_set((int)$course->id);
$hasopenround = topic_round_service::has_open_round((int)$course->id);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('topic_rounds', 'local_web3talents'));
$PAGE->set_heading(get_string('topic_rounds', 'local_web3talents'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('topic_rounds', 'local_web3talents'));
echo html_writer::tag('p', get_string('topic_rounds_intro', 'local_web3talents'), ['class' => 'lead']);
echo html_writer::div(
    html_writer::link(new moodle_url('/local/web3talents/index.php'), get_string('pluginname', 'local_web3talents'), ['class' => 'btn btn-secondary']) . ' ' .
    html_writer::link(new moodle_url('/local/web3talents/course_state.php'), get_string('course_state', 'local_web3talents'), ['class' => 'btn btn-secondary']) . ' ' .
    html_writer::link(new moodle_url('/local/web3talents/choose_topic.php'), get_string('choose_weekly_topic', 'local_web3talents'), ['class' => 'btn btn-secondary']),
    'mb-3'
);

echo html_writer::start_div('row');
echo html_writer::start_div('col-md-6');
echo $OUTPUT->box_start('generalbox');
echo $OUTPUT->heading(get_string('create_partner_set', 'local_web3talents'), 3);
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'createpartnerset']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::label(get_string('partner_set_name', 'local_web3talents'), 'setname');
echo html_writer::empty_tag('input', ['id' => 'setname', 'name' => 'setname', 'type' => 'text', 'class' => 'form-control mb-2', 'value' => 'Partner Set ' . userdate(time(), '%Y-%m-%d')]);
echo html_writer::label(get_string('partner_groups_textarea', 'local_web3talents'), 'groups');
echo html_writer::tag('textarea', "Alpha: w3t.student1, w3t.alumni1\nBeta: w3t.student2, w3t.phase8.warning", ['id' => 'groups', 'name' => 'groups', 'class' => 'form-control mb-2', 'rows' => 5]);
echo html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');
echo $OUTPUT->box_end();
echo html_writer::end_div();

echo html_writer::start_div('col-md-6');
echo $OUTPUT->box_start('generalbox');
echo $OUTPUT->heading(get_string('create_topic_round', 'local_web3talents'), 3);
if ($active) {
    echo html_writer::tag('p', get_string('active_partner_set', 'local_web3talents', format_string($active->name)));
    if ($hasopenround) {
        echo $OUTPUT->notification(get_string('open_round_exists', 'local_web3talents'), \core\output\notification::NOTIFY_WARNING);
    } else {
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'createround']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::label(get_string('topic_round_name', 'local_web3talents'), 'roundname');
        echo html_writer::empty_tag('input', ['id' => 'roundname', 'name' => 'roundname', 'type' => 'text', 'class' => 'form-control mb-2', 'value' => 'Week ' . userdate(time(), '%V') . ' Topic Selection']);
        echo html_writer::label(get_string('default_group_slots', 'local_web3talents'), 'defaultslots');
        echo html_writer::empty_tag('input', ['id' => 'defaultslots', 'name' => 'defaultslots', 'type' => 'number', 'min' => 1, 'class' => 'form-control mb-2', 'value' => 5]);
        foreach (topic_round_service::default_topics() as $index => $topicname) {
            $fieldid = 'topic_' . $index;
            echo html_writer::label(get_string('topic_number', 'local_web3talents', $index + 1), $fieldid);
            echo html_writer::empty_tag('input', ['id' => $fieldid, 'name' => 'topics[]', 'type' => 'text', 'class' => 'form-control mb-2', 'value' => $topicname]);
        }
        echo html_writer::label(get_string('voting_duration_hours', 'local_web3talents'), 'durationhours');
        echo html_writer::empty_tag('input', ['id' => 'durationhours', 'name' => 'durationhours', 'type' => 'number', 'min' => 1, 'class' => 'form-control mb-2', 'value' => 24]);
        echo html_writer::tag('button', get_string('create_topic_round', 'local_web3talents'), ['type' => 'submit', 'class' => 'btn btn-primary']);
        echo html_writer::end_tag('form');
    }
} else {
    echo $OUTPUT->notification(get_string('no_active_partner_set', 'local_web3talents'), \core\output\notification::NOTIFY_WARNING);
}
echo $OUTPUT->box_end();
echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->heading(get_string('partner_sets', 'local_web3talents'), 3);
foreach ($state['sets'] as $set) {
    echo $OUTPUT->box_start('generalbox mb-3');
    echo $OUTPUT->heading(format_string($set->name) . ((int)$set->active ? ' - ' . get_string('active') : ''), 4);
    $groups = $state['groups'][(int)$set->id] ?? [];
    if (!$groups) {
        echo html_writer::tag('p', get_string('none'));
    }
    foreach ($groups as $group) {
        $members = array_map('fullname', $group->members);
        echo html_writer::tag('p', s($group->name) . ': ' . s(implode(', ', $members)));
    }
    echo $OUTPUT->box_end();
}

echo $OUTPUT->heading(get_string('topic_rounds', 'local_web3talents'), 3);
$recentrounds = array_slice($state['rounds'], 0, 3, true);
$olderrounds = array_slice($state['rounds'], 3, null, true);
if (!$recentrounds) {
    echo html_writer::tag('p', get_string('none'));
} else {
    echo html_writer::table(web3t_topic_rounds_table($recentrounds, $state, $url));
}
if ($olderrounds) {
    echo html_writer::start_tag('details', ['class' => 'mt-3']);
    echo html_writer::tag('summary', get_string('older_topic_rounds', 'local_web3talents', count($olderrounds)), ['class' => 'btn btn-secondary']);
    echo html_writer::table(web3t_topic_rounds_table($olderrounds, $state, $url));
    echo html_writer::end_tag('details');
}

echo $OUTPUT->footer();
