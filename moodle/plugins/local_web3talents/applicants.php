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
require_once($CFG->dirroot . '/local/web3talents/lib.php');

use local_web3talents\form\applicant_form;
use local_web3talents\form\import_form;
use local_web3talents\local\agreement_service;
use local_web3talents\local\applicant_service;

admin_externalpage_setup('local_web3talents_applicants');

// Program admins hold their manager role on the course, so capabilities are checked there.
$context = local_web3talents_admin_context();
require_capability('local/web3talents:manageacceptedapplicants', $context);

$url = new moodle_url('/local/web3talents/applicants.php');
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('applicants', 'local_web3talents'));
$PAGE->set_heading(get_string('applicants', 'local_web3talents'));

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$query = optional_param('q', '', PARAM_TEXT);

if (in_array($action, ['create', 'resendactivation'], true) && $id) {
    require_capability('local/web3talents:createstudentaccounts', $context);
    require_sesskey();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        // Creating a user, enrolling them, and mailing a password must never be a plain GET.
        redirect($url, get_string('error_post_required', 'local_web3talents'), null, \core\output\notification::NOTIFY_ERROR);
    }

    $applicant = $DB->get_record('local_web3talents_app', ['id' => $id], '*', IGNORE_MISSING);
    if (!$applicant) {
        redirect($url, get_string('error_unknown_applicant', 'local_web3talents'), null, \core\output\notification::NOTIFY_ERROR);
    }

    try {
        if ($action === 'resendactivation') {
            $sent = applicant_service::resend_activation_email($id);
            redirect(
                $url,
                get_string($sent ? 'activation_email_resent' : 'error_email_failed', 'local_web3talents'),
                null,
                $sent ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_WARNING
            );
        }

        if (!optional_param('confirm', 0, PARAM_BOOL)) {
            $PAGE->set_url($url);
            $PAGE->set_context(context_system::instance());
            $continue = new \core\output\single_button(
                new moodle_url($url, ['action' => 'create', 'id' => $id, 'confirm' => 1, 'sesskey' => sesskey()]),
                get_string('createaccount', 'local_web3talents'),
                'post'
            );
            echo $OUTPUT->header();
            echo $OUTPUT->confirm(
                get_string('createaccount_confirm', 'local_web3talents', (object)[
                    'name' => s(trim($applicant->firstname . ' ' . $applicant->lastname)),
                    'email' => s($applicant->email),
                ]),
                $continue,
                new \core\output\single_button($url, get_string('cancel'), 'get')
            );
            echo $OUTPUT->footer();
            exit;
        }

        $user = applicant_service::create_student_account($id);
        if (empty($user->activationemailsent)) {
            redirect(
                $url,
                get_string('createdaccount_no_email', 'local_web3talents', fullname($user)),
                null,
                \core\output\notification::NOTIFY_WARNING
            );
        }
        redirect($url, get_string('createdaccount', 'local_web3talents', fullname($user)), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (Throwable $exception) {
        redirect($url, $exception->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$submittedtype = $_SERVER['REQUEST_METHOD'] === 'POST' ? optional_param('formtype', '', PARAM_ALPHA) : '';
$manualform = $submittedtype === 'import' ? null : new applicant_form($url);
$importform = $submittedtype === 'manual' ? null : new import_form($url);

if ($manualform && ($data = $manualform->get_data())) {
    if (($data->formtype ?? '') === 'manual') {
        try {
            applicant_service::upsert_applicant($data, 'manual');
            redirect($url, get_string('applicant_saved', 'local_web3talents'), null, \core\output\notification::NOTIFY_SUCCESS);
        } catch (Throwable $exception) {
            redirect($url, $exception->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
        }
    }
}

if ($importform && ($data = $importform->get_data())) {
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

/**
 * Render one applicant row action as a POST form.
 *
 * @param moodle_url $url Page url.
 * @param string $action Action name.
 * @param int $applicantid Applicant id.
 * @param string $label Button label.
 * @param string $buttonclass Bootstrap button class.
 * @return string
 */
function web3t_applicant_action_form(moodle_url $url, string $action, int $applicantid, string $label, string $buttonclass): string {
    return html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-inline']) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $action]) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $applicantid]) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
        html_writer::tag('button', $label, ['type' => 'submit', 'class' => 'btn btn-sm ' . $buttonclass]) .
        html_writer::end_tag('form');
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('applicants', 'local_web3talents'));
echo html_writer::tag('p', get_string('applicants_intro', 'local_web3talents'), ['class' => 'lead']);
echo local_web3talents_action_bar([
    [
        'url' => new moodle_url('/local/web3talents/index.php'),
        'label' => get_string('pluginname', 'local_web3talents'),
        'capability' => 'local/web3talents:manage',
        'context' => $context,
    ],
    [
        'url' => new moodle_url('/local/web3talents/course_state.php'),
        'label' => get_string('course_state', 'local_web3talents'),
        'capability' => 'local/web3talents:manage',
        'context' => $context,
    ],
]);

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
if ($manualform) {
    echo html_writer::start_div($importform ? 'col-md-6' : 'col-12');
    echo $OUTPUT->box_start('generalbox');
    echo $OUTPUT->heading(get_string('add_applicant', 'local_web3talents'), 3);
    $manualform->display();
    echo $OUTPUT->box_end();
    echo html_writer::end_div();
}

if ($importform) {
    echo html_writer::start_div($manualform ? 'col-md-6' : 'col-12');
    echo $OUTPUT->box_start('generalbox');
    echo $OUTPUT->heading(get_string('import_applicants', 'local_web3talents'), 3);
    $importform->display();
    echo $OUTPUT->box_end();
    echo html_writer::end_div();
}
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
    if (has_capability('local/web3talents:createstudentaccounts', $context)) {
        if ($applicant->status === applicant_service::STATUS_ACCEPTED && empty($applicant->userid)) {
            $actions = web3t_applicant_action_form($url, 'create', (int)$applicant->id, get_string('createaccount', 'local_web3talents'), 'btn-primary');
        } else if (!empty($applicant->userid) && empty($applicant->activationemailsenttime)) {
            $actions = web3t_applicant_action_form($url, 'resendactivation', (int)$applicant->id, get_string('resend_activation_email', 'local_web3talents'), 'btn-secondary');
        }
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
