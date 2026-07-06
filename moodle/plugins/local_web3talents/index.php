<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Web3 Talents admin landing page.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_web3talents');

$context = context_system::instance();
require_capability('local/web3talents:manage', $context);

$PAGE->set_url(new moodle_url('/local/web3talents/index.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_web3talents'));
$PAGE->set_heading(get_string('pluginname', 'local_web3talents'));

$dashboard = new \local_web3talents\output\dashboard();

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_web3talents/dashboard', $dashboard->export_for_template($OUTPUT));
echo $OUTPUT->footer();
