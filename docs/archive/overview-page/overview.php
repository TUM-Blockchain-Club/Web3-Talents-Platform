<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Public Web3 Talents overview page.
 *
 * @package    theme_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$context = context_system::instance();
$url = new moodle_url('/theme/web3talents/overview.php');

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('frontpage');
$PAGE->set_title(get_string('overviewtitle', 'theme_web3talents'));
$PAGE->set_heading(get_string('overviewtitle', 'theme_web3talents'));
$PAGE->add_body_class('web3t-overview-page');

$loginurl = new moodle_url('/login/index.php');
$heroimage = new moodle_url('/theme/web3talents/pix/overview-hero.png');

$templatecontext = [
    'loginurl' => $loginurl->out(false),
    'overviewurl' => $url->out(false),
    'heroimage' => $heroimage->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_web3talents/overview', $templatecontext);
echo $OUTPUT->footer();
