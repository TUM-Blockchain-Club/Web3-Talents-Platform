<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Accepted-applicant management page.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

use local_web3talents\form\applicant_form;
use local_web3talents\form\import_form;
use local_web3talents\local\agreement_service;
use local_web3talents\local\applicant_service;

admin_externalpage_setup('local_web3talents_applicants');

$context = context_system::instance();
require_capability('local/web3talents:manageacceptedapplicants', $context);

$url = new moodle_url('/local/web3talents/applicants.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('applicants', 'local_web3talents'));
$PAGE->set_heading(get_string('applicants', 'local_web3talents'));

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$query = optional_param('q', '', PARAM_TEXT);

if ($action === 'create' && $id) {
    require_capability('local/web3talents:createstudentaccounts', $context);
    require_sesskey();

    try {
        $user = applicant_service::create_student_account($id);
        redirect($url, get_string('createdaccount', 'local_web3talents', fullname($user)), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (Throwable $exception) {
        redirect($url, $exception->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$manualform = new applicant_form($url);
$importform = new import_form($url);

if ($data = $manualform->get_data()) {
    if (($data->formtype ?? '') === 'manual') {
        try {
            applicant_service::upsert_applicant($data, 'manual');
            redirect($url, get_string('applicant_saved', 'local_web3talents'), null, \core\output\notification::NOTIFY_SUCCESS);
        } catch (Throwable $exception) {
            redirect($url, $exception->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
        }
    }
}

if ($data = $importform->get_data()) {
    if (($data->formtype ?? '') === 'import') {
        try {
            $filename = $importform->get_new_filename('applicantfile');
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if ($extension === 'csv') {
                $content = $importform->get_file_content('applicantfile');
                $result = applicant_service::import_csv($content, $data->encoding, $data->delimiter_name);
            } else {
                $filepath = $importform->save_temp_file('applicantfile');
                $result = applicant_service::import_excel($filepath);
                @unlink($filepath);
            }

            $message = get_string('importresult', 'local_web3talents', (object)$result);
            if (!empty($result['errors'])) {
                $message .= html_writer::alist($result['errors']);
            }
            redirect($url, $message, null, empty($result['errors']) ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_WARNING);
        } catch (Throwable $exception) {
            redirect($url, $exception->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
        }
    }
}

$applicants = applicant_service::search_applicants($query);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('applicants', 'local_web3talents'));
echo html_writer::tag('p', get_string('applicants_intro', 'local_web3talents'), ['class' => 'lead']);

echo $OUTPUT->box_start('generalbox mb-4');
echo $OUTPUT->heading(get_string('search_applicants', 'local_web3talents'), 3);
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $url->out(false), 'class' => 'mb-3']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'q',
    'value' => s($query),
    'class' => 'form-control mb-2',
    'placeholder' => get_string('search_applicants', 'local_web3talents'),
]);
echo html_writer::tag('button', get_string('search'), ['type' => 'submit', 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');
echo $OUTPUT->box_end();

echo html_writer::start_div('row');
echo html_writer::start_div('col-md-6');
echo $OUTPUT->box_start('generalbox');
echo $OUTPUT->heading(get_string('add_applicant', 'local_web3talents'), 3);
$manualform->display();
echo $OUTPUT->box_end();
echo html_writer::end_div();

echo html_writer::start_div('col-md-6');
echo $OUTPUT->box_start('generalbox');
echo $OUTPUT->heading(get_string('import_applicants', 'local_web3talents'), 3);
$importform->display();
echo $OUTPUT->box_end();
echo html_writer::end_div();
echo html_writer::end_div();

$table = new html_table();
$table->head = [
    get_string('firstname', 'local_web3talents'),
    get_string('lastname', 'local_web3talents'),
    get_string('email', 'local_web3talents'),
    get_string('cohortid', 'local_web3talents'),
    get_string('status', 'local_web3talents'),
    get_string('accountstatus', 'local_web3talents'),
    get_string('agreementstatus', 'local_web3talents'),
    get_string('retentionuntil', 'local_web3talents'),
    get_string('actions', 'local_web3talents'),
];
$table->attributes['class'] = 'generaltable mt-4';

$statuses = applicant_service::statuses();
foreach ($applicants as $applicant) {
    $account = empty($applicant->userid) ? get_string('noaccount', 'local_web3talents') : get_string('accountcreated', 'local_web3talents');
    $agreement = '-';
    if (!empty($applicant->userid)) {
        $acceptance = agreement_service::get_current_acceptance((int)$applicant->userid);
        $agreement = $acceptance
            ? get_string('agreement_status_accepted', 'local_web3talents', userdate($acceptance->agreedtime, get_string('strftimedatetimeshort')))
            : get_string('agreement_status_pending', 'local_web3talents');
    }
    $retention = empty($applicant->retentionuntil) ? '-' : userdate($applicant->retentionuntil, get_string('strftimedatefullshort'));
    $actions = '-';
    if ($applicant->status === applicant_service::STATUS_ACCEPTED && empty($applicant->userid)
            && has_capability('local/web3talents:createstudentaccounts', $context)) {
        $actions = html_writer::link(
            new moodle_url('/local/web3talents/applicants.php', [
                'action' => 'create',
                'id' => $applicant->id,
                'sesskey' => sesskey(),
            ]),
            get_string('createaccount', 'local_web3talents'),
            ['class' => 'btn btn-sm btn-primary']
        );
    }

    $table->data[] = [
        s($applicant->firstname),
        s($applicant->lastname),
        s($applicant->email),
        s($applicant->cohortid),
        s($statuses[$applicant->status] ?? $applicant->status),
        s($account),
        s($agreement),
        s($retention),
        $actions,
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
