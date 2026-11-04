<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

use local_web3talents\local\application_csv;
use local_web3talents\local\application_review_service as reviews;
use local_web3talents\form\application_import_form;

admin_externalpage_setup('local_web3talents_applicants');
reviews::require_reviewer();
$url = new moodle_url('/local/web3talents/application_import.php');
$back = new moodle_url('/local/web3talents/applicants.php');
$PAGE->set_url($url);
$PAGE->set_title(get_string('review_import_title', 'local_web3talents'));
$PAGE->set_heading(get_string('review_import_title', 'local_web3talents'));

if (isset($SESSION->w3timport) && $SESSION->w3timport['expires'] < time()) {
    unset($SESSION->w3timport);
}
if (optional_param('confirmimport', 0, PARAM_BOOL)) {
    require_sesskey();
    $token = required_param('token', PARAM_ALPHANUM);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($SESSION->w3timport) ||
            !hash_equals($SESSION->w3timport['token'], $token)) {
        throw new moodle_exception('review_preview_expired', 'local_web3talents');
    }
    $rows = $SESSION->w3timport['rows'];
    unset($SESSION->w3timport);
    $result = reviews::import($rows);
    redirect($back, get_string('review_import_result', 'local_web3talents', (object)$result), null,
        \core\output\notification::NOTIFY_SUCCESS);
}
$form = new application_import_form($url);
if ($form->is_cancelled()) {
    unset($SESSION->w3timport);
    redirect($back);
}
$rows = null;
$error = '';
if ($data = $form->get_data()) {
    try {
        $rows = reviews::preview(application_csv::parse($form->get_file_content('csv'), ',', $data->cohort));
        $SESSION->w3timport = ['rows' => $rows, 'token' => bin2hex(random_bytes(24)), 'expires' => time() + 1800];
    } catch (Throwable $e) {
        unset($SESSION->w3timport);
        $error = $e->getMessage();
    }
}
echo $OUTPUT->header();
echo html_writer::link($back, get_string('review_back', 'local_web3talents'), ['class' => 'btn btn-secondary mb-3']);
echo $OUTPUT->heading(get_string('review_import_title', 'local_web3talents'));
echo html_writer::tag('p', get_string('review_import_help', 'local_web3talents'));
if ($error) {
    echo $OUTPUT->notification(s($error), 'notifyproblem');
}
if ($rows !== null) {
    $counts = array_count_values(array_column($rows, 'outcome'));
    foreach ($counts as $outcome => $count) {
        echo html_writer::tag('p', s(get_string('review_import_' . $outcome, 'local_web3talents')) . ': ' . (int)$count);
    }
    $table = new html_table();
    $table->head = [get_string('review_csv_row', 'local_web3talents'), get_string('name'), get_string('email'),
        get_string('status'), get_string('review_import_issue', 'local_web3talents')];
    foreach ($rows as $row) {
        $table->data[] = [(int)$row['row'], s($row['firstname'] . ' ' . $row['lastname']), s($row['email']),
            s(get_string('review_import_' . $row['outcome'], 'local_web3talents')), s($row['error'])];
    }
    echo html_writer::div(html_writer::table($table), 'table-responsive');
    if (!empty($counts['new'])) {
        echo $OUTPUT->single_button(new moodle_url($url, ['confirmimport' => 1,
            'token' => $SESSION->w3timport['token'], 'sesskey' => sesskey()]),
            get_string('review_confirm_import', 'local_web3talents', $counts['new']), 'post');
    }
    echo html_writer::tag('p', html_writer::link($url, get_string('review_choose_another', 'local_web3talents')));
} else {
    $form->display();
}
echo $OUTPUT->footer();
