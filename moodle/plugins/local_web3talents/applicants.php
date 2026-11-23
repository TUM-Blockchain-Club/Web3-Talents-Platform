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
$filterstatus = optional_param('status', '', PARAM_ALPHA);
$filters = applicant_service::review_filters();
if ($filterstatus !== '' && !array_key_exists($filterstatus, $filters)) {
    throw new invalid_parameter_exception('Unknown applicant status.');
}
$page = max(0, optional_param('page', 0, PARAM_INT));
$focusid = max(0, optional_param('focus', 0, PARAM_INT));
$form = new applicant_form($url);
if ($data = $form->get_data()) {
    try {
        $record = applicant_service::upsert_applicant($data, 'manual');
        redirect(new moodle_url('/local/web3talents/application_review.php', ['id' => $record->id]));
    } catch (Throwable $e) {
        redirect($url, s($e->getMessage()), null, \core\output\notification::NOTIFY_ERROR);
    }
}
$perpage = 50;
$total = applicant_service::count_applicants($query, $filterstatus);
$pagecount = max(1, (int)ceil($total / $perpage));
$page = min($page, $pagecount - 1);
$applicants = applicant_service::search_applicants($query, $perpage, $page * $perpage, $filterstatus);
$queueparams = ['q' => $query, 'status' => $filterstatus, 'page' => $page];
$PAGE->set_url(new moodle_url($url, $queueparams));
echo $OUTPUT->header();
echo html_writer::link(new moodle_url('/local/web3talents/index.php'),
    get_string('review_back_dashboard', 'local_web3talents'), ['class' => 'btn btn-secondary mb-3']);
echo $OUTPUT->heading(get_string('applicants', 'local_web3talents'));
echo html_writer::tag('p', get_string('applicants_intro', 'local_web3talents'));
$overview = applicant_service::applicant_overview();
echo html_writer::start_tag('section', ['aria-label' => get_string('review_overview', 'local_web3talents')]);
echo html_writer::tag('h3', get_string('review_overview', 'local_web3talents'), ['class' => 'h5']);
echo html_writer::start_div('row mb-2');
foreach (['total' => '', 'accepted' => 'allaccepted', 'rejected' => 'rejected', 'toreview' => 'toreview'] as $key => $filter) {
    echo html_writer::start_div('col-12 col-sm-6 col-xl-3 mb-3');
    $label = get_string('review_overview_' . $key, 'local_web3talents');
    echo html_writer::link(new moodle_url($url, ['status' => $filter]),
        html_writer::tag('span', s($label), ['class' => 'd-block']) .
        html_writer::tag('strong', (string)$overview[$key], ['class' => 'd-block h2 mb-0 mt-2']),
        ['class' => 'd-block border rounded p-3 h-100', 'aria-label' => $label . ': ' . $overview[$key]]);
    echo html_writer::end_div();
}
echo html_writer::end_div();
echo html_writer::tag('p', get_string('review_overview_help', 'local_web3talents'), ['class' => 'text-muted small']);
if ($overview['other']) {
    echo html_writer::tag('p', get_string('review_overview_other', 'local_web3talents', $overview['other']), ['class' => 'text-muted small']);
}
echo html_writer::end_tag('section');
if (!applicant_service::activation_emails_enabled()) {
    echo $OUTPUT->notification(get_string('review_emails_paused', 'local_web3talents'), 'notifyinfo');
}
echo html_writer::link(new moodle_url('/local/web3talents/application_import.php'),
    get_string('review_import_title', 'local_web3talents'), ['class' => 'btn btn-primary mb-4']);
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $url->out(false), 'class' => 'mb-3']);
echo html_writer::tag('label', get_string('search_applicants', 'local_web3talents'), ['for' => 'application-search']);
echo html_writer::empty_tag('input', ['type' => 'search', 'id' => 'application-search', 'name' => 'q',
    'value' => $query, 'class' => 'form-control mb-2']);
echo html_writer::tag('label', get_string('status'), ['for' => 'application-status']);
echo html_writer::select(['' => get_string('review_filter_all', 'local_web3talents')] + $filters,
    'status', $filterstatus, false, ['id' => 'application-status', 'class' => 'form-select mb-2']);
echo html_writer::tag('button', get_string('review_filter_apply', 'local_web3talents'), ['type' => 'submit', 'class' => 'btn btn-secondary']);
echo ' ' . html_writer::link($url, get_string('review_filter_reset', 'local_web3talents'), ['class' => 'btn btn-link']);
echo html_writer::end_tag('form');
$table = new html_table();
$table->head = [get_string('name'), get_string('email'), get_string('cohortid', 'local_web3talents'),
    get_string('status'), get_string('review_approvals', 'local_web3talents'), get_string('actions')];
$statuses = applicant_service::statuses();
$focusvisible = false;
foreach ($applicants as $applicant) {
    $summary = reviews::summary((int)$applicant->id);
    $status = $applicant->status;
    $row = new html_table_row([s($applicant->firstname . ' ' . $applicant->lastname), s($applicant->email),
        s($applicant->cohortid), s($statuses[$status] ?? $status), $summary['approvals'] . ' / 2',
        html_writer::link(new moodle_url('/local/web3talents/application_review.php', ['id' => $applicant->id] + $queueparams),
            get_string('review_application', 'local_web3talents'), ['class' => 'btn btn-sm btn-secondary'])]);
    $row->id = 'applicant-' . $applicant->id;
    $row->style = 'scroll-margin-top: 100px';
    if ((int)$applicant->id === $focusid) {
        $focusvisible = true;
        $row->attributes['class'] = 'table-primary';
    }
    $table->data[] = $row;
}
if ($focusid && !$focusvisible) {
    echo $OUTPUT->notification(get_string('review_return_not_visible', 'local_web3talents'), 'notifyinfo');
}
echo html_writer::div(html_writer::table($table), 'table-responsive');
if (!$applicants) {
    echo html_writer::tag('p', get_string('review_empty_queue', 'local_web3talents'));
}
if ($total > 0) {
    echo html_writer::tag('p', get_string('review_page_summary', 'local_web3talents', (object)[
        'page' => $page + 1, 'pages' => $pagecount, 'total' => $total,
    ]), ['class' => 'text-muted small mt-3']);
    echo $OUTPUT->paging_bar($total, $page, $perpage, new moodle_url($url, ['q' => $query, 'status' => $filterstatus]));
}
echo html_writer::start_tag('details', ['class' => 'border rounded p-3 mt-4']);
echo html_writer::tag('summary', get_string('add_applicant', 'local_web3talents'));
$form->display();
echo html_writer::end_tag('details');
echo $OUTPUT->footer();
