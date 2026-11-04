<?php
// Application review queue. Account actions are handled on the individual review page.
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

use local_web3talents\form\applicant_form;
use local_web3talents\local\applicant_service;
use local_web3talents\local\application_review_service as reviews;

admin_externalpage_setup('local_web3talents_applicants');
reviews::require_reviewer();
$url = new moodle_url('/local/web3talents/applicants.php');
$PAGE->set_url($url);
$PAGE->set_title(get_string('applicants', 'local_web3talents'));
$PAGE->set_heading(get_string('applicants', 'local_web3talents'));
$query = optional_param('q', '', PARAM_TEXT);
$page = max(0, optional_param('page', 0, PARAM_INT));
$form = new applicant_form($url);
if ($data = $form->get_data()) {
    try {
        $record = applicant_service::upsert_applicant($data, 'manual');
        redirect(new moodle_url('/local/web3talents/application_review.php', ['id' => $record->id]));
    } catch (Throwable $e) {
        redirect($url, s($e->getMessage()), null, \core\output\notification::NOTIFY_ERROR);
    }
}
$applicants = applicant_service::search_applicants($query, 51, $page * 50);
$hasnext = count($applicants) > 50;
$applicants = array_slice($applicants, 0, 50);
echo $OUTPUT->header();
echo html_writer::link(new moodle_url('/local/web3talents/index.php'),
    get_string('review_back_dashboard', 'local_web3talents'), ['class' => 'btn btn-secondary mb-3']);
echo $OUTPUT->heading(get_string('applicants', 'local_web3talents'));
echo html_writer::tag('p', get_string('applicants_intro', 'local_web3talents'));
echo html_writer::link(new moodle_url('/local/web3talents/application_import.php'),
    get_string('review_import_title', 'local_web3talents'), ['class' => 'btn btn-primary mb-4']);
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $url->out(false), 'class' => 'mb-3']);
echo html_writer::tag('label', get_string('search_applicants', 'local_web3talents'), ['for' => 'application-search']);
echo html_writer::empty_tag('input', ['type' => 'search', 'id' => 'application-search', 'name' => 'q',
    'value' => $query, 'class' => 'form-control mb-2']);
echo html_writer::tag('button', get_string('search'), ['type' => 'submit', 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');
$table = new html_table();
$table->head = [get_string('name'), get_string('email'), get_string('cohortid', 'local_web3talents'),
    get_string('status'), get_string('review_approvals', 'local_web3talents'), get_string('actions')];
$statuses = applicant_service::statuses();
foreach ($applicants as $applicant) {
    $summary = reviews::summary((int)$applicant->id);
    $status = $applicant->status;
    if (empty($applicant->userid) && !in_array($status, ['removed', 'deferred'], true)) {
        $status = $summary['objections'] ? 'discussion' : ($summary['ready'] ? 'accepted' : 'pending');
    }
    $table->data[] = [s($applicant->firstname . ' ' . $applicant->lastname), s($applicant->email),
        s($applicant->cohortid), s($statuses[$status] ?? $status), $summary['approvals'] . ' / 2',
        html_writer::link(new moodle_url('/local/web3talents/application_review.php', ['id' => $applicant->id]),
            get_string('review_application', 'local_web3talents'), ['class' => 'btn btn-sm btn-secondary'])];
}
echo html_writer::div(html_writer::table($table), 'table-responsive');
if (!$applicants) {
    echo html_writer::tag('p', get_string('review_empty_queue', 'local_web3talents'));
}
if ($page > 0) {
    echo html_writer::link(new moodle_url($url, ['q' => $query, 'page' => $page - 1]),
        get_string('previous'), ['class' => 'btn btn-secondary me-2']);
}
if ($hasnext) {
    echo html_writer::link(new moodle_url($url, ['q' => $query, 'page' => $page + 1]),
        get_string('next'), ['class' => 'btn btn-secondary']);
}
echo html_writer::start_tag('details', ['class' => 'border rounded p-3 mt-4']);
echo html_writer::tag('summary', get_string('add_applicant', 'local_web3talents'));
$form->display();
echo html_writer::end_tag('details');
echo $OUTPUT->footer();
