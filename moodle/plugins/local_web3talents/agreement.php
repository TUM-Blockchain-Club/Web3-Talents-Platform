<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * First-login agreement page.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_web3talents\form\agreement_form;
use local_web3talents\local\agreement_service;

require_login();

$context = context_system::instance();
$url = new moodle_url('/local/web3talents/agreement.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('agreement_title', 'local_web3talents'));
$PAGE->set_heading(get_string('agreement_title', 'local_web3talents'));

$form = new agreement_form($url);
if ($data = $form->get_data()) {
    if (!empty($data->accepted)) {
        agreement_service::accept_current((int)$USER->id);
        $returnurl = $SESSION->wantsurl ?? new moodle_url('/my/courses.php');
        unset($SESSION->wantsurl);
        redirect($returnurl, get_string('agreement_saved', 'local_web3talents'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('agreement_title', 'local_web3talents'));
echo html_writer::tag('p', get_string('agreement_intro', 'local_web3talents'), ['class' => 'lead']);
echo $OUTPUT->box(format_text(agreement_service::current_text(), FORMAT_MARKDOWN), 'generalbox');
$form->display();
echo $OUTPUT->footer();
