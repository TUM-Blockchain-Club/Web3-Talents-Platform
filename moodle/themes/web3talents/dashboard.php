<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Logged-in Web3 Talents student home (Materials + Assignment views).
 *
 * Renders the current student's real Moodle and local_web3talents data, with
 * clear empty states where programme data does not exist yet.
 *
 * @package    theme_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// The configured-course helper lives in the local_web3talents plugin lib
// (not auto-loaded on a standalone theme page); load it if present.
$pluginlib = $CFG->dirroot . '/local/web3talents/lib.php';
if (file_exists($pluginlib)) {
    require_once($pluginlib);
}
$course = function_exists('local_web3talents_get_configured_course')
    ? local_web3talents_get_configured_course() : null;

if ($course) {
    require_login($course);
    $context = context_course::instance($course->id);
    require_capability('local/web3talents:viewstudentrooms', $context);
} else {
    require_login();
    $context = context_system::instance();
}

$view = optional_param('view', 'materials', PARAM_ALPHA);
if ($view !== 'assignment') {
    $view = 'materials';
}

$url = new moodle_url('/theme/web3talents/dashboard.php', ['view' => $view]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('frontpage');
$PAGE->set_title('Home · Web3 Talents');
$PAGE->set_heading('Home');
$PAGE->add_body_class('web3t-page');
$PAGE->add_body_class('web3t-dashboard-page');

$common = theme_web3talents_common_context($OUTPUT);
$icon = function(string $name): string {
    global $OUTPUT;
    return $OUTPUT->image_url('dashboard/' . $name, 'theme_web3talents')->out(false);
};

$materialsurl = new moodle_url('/theme/web3talents/dashboard.php');
$assignmenturl = new moodle_url('/theme/web3talents/dashboard.php', ['view' => 'assignment']);
$dash = theme_web3talents_dashboard_context($course, $USER, $view);

$templatecontext = array_merge($common, $dash, [
    'logouturl' => (new moodle_url('/login/logout.php', ['sesskey' => sesskey()]))->out(false),
    'initials' => theme_web3talents_initials($USER),
    'materialsurl' => $materialsurl->out(false),
    'assignmenturl' => $assignmenturl->out(false),
    'showmaterials' => $view === 'materials',
    'showassignment' => $view === 'assignment',
    'icons' => [
        'video' => $icon('video'), 'clock' => $icon('clock'), 'lock' => $icon('lock'),
        'joinzoom' => $icon('join-zoom'), 'book' => $icon('book'), 'chevron' => $icon('chevron'),
        'tabplay' => $icon('tab-play'), 'tabdoc' => $icon('tab-doc'), 'play' => $icon('play'),
        'monitor' => $icon('monitor'), 'uploaddim' => $icon('upload-dim'), 'download' => $icon('download'),
        'info' => $icon('info'), 'uploadcloud' => $icon('upload-cloud'), 'chat' => $icon('chat'),
        'external' => $icon('external'),
    ],
]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_web3talents/dashboard', $templatecontext);
echo $OUTPUT->footer();
