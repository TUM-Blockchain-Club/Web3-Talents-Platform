<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Logged-in Web3 Talents dashboard (Materials + Assignment views).
 *
 * High-fidelity implementation of the TUM Blockchain Club "Dashboard
 * Materials" / "Dashboard Assignment" Figma frames. Styles live in
 * scss/pages/dashboard.scss, scoped to `.web3t-dashboard`.
 *
 * Sidebar topics, timeline dates, materials list and assignment/team/mentor
 * content are the exact Figma placeholder content — data wiring is a later
 * phase. The header (logo, nav links, log out, avatar initials) is real.
 *
 * @package    theme_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$view = optional_param('view', 'materials', PARAM_ALPHA);
if ($view !== 'assignment') {
    $view = 'materials';
}

$context = context_system::instance();
$url = new moodle_url('/theme/web3talents/dashboard.php', ['view' => $view]);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('frontpage');
$PAGE->set_title('Dashboard');
$PAGE->set_heading('Dashboard');
$PAGE->add_body_class('web3t-page');
$PAGE->add_body_class('web3t-dashboard-page');

$common = theme_web3talents_common_context($OUTPUT);
$icon = function(string $name): string {
    global $OUTPUT;
    return $OUTPUT->image_url('dashboard/' . $name, 'theme_web3talents')->out(false);
};

$initials = core_text::strtoupper(
    core_text::substr(trim($USER->firstname ?? ''), 0, 1) . core_text::substr(trim($USER->lastname ?? ''), 0, 1)
);

$materialsurl = new moodle_url('/theme/web3talents/dashboard.php');
$assignmenturl = new moodle_url('/theme/web3talents/dashboard.php', ['view' => 'assignment']);

// Sidebar topic list — exact Figma placeholder content (Dashboard Materials frame).
$topics = [
    ['num' => '1', 'title' => 'Topic 1', 'dates' => 'Jan 15 - Jan 21'],
    ['num' => '2', 'title' => 'Topic 2', 'dates' => 'Jan 22 - Jan 28'],
    ['num' => '3', 'title' => 'Topic 3', 'dates' => 'Jan 29 - Feb 4', 'active' => true],
    ['num' => '4', 'title' => 'Topic 4', 'dates' => 'Feb 5 - Feb 11', 'current' => true],
    ['num' => '5', 'title' => 'Topic 5', 'dates' => 'Feb 12 - Feb 18'],
    ['num' => '6', 'title' => 'Topic 6', 'dates' => 'Feb 19 - Feb 25', 'locked' => true],
    ['num' => '7', 'title' => 'Topic 7', 'dates' => 'Feb 26 - Mar 4', 'locked' => true],
    ['num' => '8', 'title' => 'Topic 8', 'dates' => 'Mar 5 - Mar 11', 'locked' => true],
];
foreach ($topics as $i => $topic) {
    $topics[$i]['url'] = $materialsurl->out(false);
}

// Placeholder dot lines used by the Task Description body and mentor message.
$dotlines = [['dot' => '.'], ['dot' => '.'], ['dot' => '.'], ['dot' => '.'], ['dot' => '.']];

$templatecontext = array_merge($common, [
    'logouturl' => (new moodle_url('/login/logout.php', ['sesskey' => sesskey()]))->out(false),
    'initials' => $initials,

    'materialsurl' => $materialsurl->out(false),
    'assignmenturl' => $assignmenturl->out(false),
    'showmaterials' => $view === 'materials',
    'showassignment' => $view === 'assignment',

    'icons' => [
        'video' => $icon('video'),
        'clock' => $icon('clock'),
        'lock' => $icon('lock'),
        'joinzoom' => $icon('join-zoom'),
        'book' => $icon('book'),
        'chevron' => $icon('chevron'),
        'tabplay' => $icon('tab-play'),
        'tabdoc' => $icon('tab-doc'),
        'play' => $icon('play'),
        'monitor' => $icon('monitor'),
        'uploaddim' => $icon('upload-dim'),
        'download' => $icon('download'),
        'info' => $icon('info'),
        'uploadcloud' => $icon('upload-cloud'),
        'chat' => $icon('chat'),
        'external' => $icon('external'),
    ],

    'topics' => $topics,

    'timeline' => [
        ['label' => 'Speaker Presentation', 'date' => 'Jan 29', 'state' => 'past'],
        ['label' => 'Group Meeting', 'date' => 'Jan 31', 'state' => 'active', 'active' => true],
        ['label' => 'Group Presentations', 'date' => 'Feb 2', 'state' => 'future'],
    ],

    // Course Materials view — exact Figma placeholder rows.
    'teamrows' => [
        ['title' => 'Team Presentation 2', 'meta' => '8 slides · Uploaded Jan 28'],
        ['title' => 'Team Presentation 2', 'meta' => '8 slides · Uploaded Jan 28'],
        ['title' => 'Team Presentation 3', 'meta' => '8 slides · Uploaded Jan 28'],
        ['title' => 'Team Presentation 4', 'meta' => 'Awaiting submission', 'awaiting' => true],
        ['title' => 'Team Presentation 4', 'meta' => '8 slides · Uploaded Jan 28'],
    ],

    // Assignment view — exact Figma placeholder content.
    'taskdots' => $dotlines,
    'mentordots' => $dotlines,
    'team' => [
        ['initials' => 'M1', 'name' => 'Team Member 1', 'role' => 'You'],
        ['initials' => 'M1', 'name' => 'Team Member 2', 'role' => 'Member'],
        ['initials' => 'M1', 'name' => 'Team Member 3', 'role' => 'Member'],
        ['initials' => 'M1', 'name' => 'Team Member 4', 'role' => 'Member'],
        ['initials' => 'M1', 'name' => 'Team Member 5', 'role' => 'Member'],
    ],
    'resources' => [
        ['label' => 'Resource Item 1', 'typeicon' => $icon('res-link')],
        ['label' => 'Resource Item 2', 'typeicon' => $icon('res-doc')],
        ['label' => 'Resource Item 3', 'typeicon' => $icon('res-play')],
        ['label' => 'Resource Item 4', 'typeicon' => $icon('res-external')],
    ],
]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_web3talents/dashboard', $templatecontext);
echo $OUTPUT->footer();
