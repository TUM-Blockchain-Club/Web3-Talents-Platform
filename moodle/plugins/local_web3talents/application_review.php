<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

use local_web3talents\local\application_review_service as reviews;
use local_web3talents\local\applicant_service;

admin_externalpage_setup('local_web3talents_applicants');
reviews::require_reviewer();
$id = required_param('id', PARAM_INT);
$url = new moodle_url('/local/web3talents/application_review.php', ['id' => $id]);
$back = new moodle_url('/local/web3talents/applicants.php');
$PAGE->set_url($url);
$PAGE->set_title(get_string('review_application', 'local_web3talents'));
$PAGE->set_heading(get_string('review_application', 'local_web3talents'));
$applicant = $DB->get_record('local_web3talents_app', ['id' => $id], '*', MUST_EXIST);
$action = optional_param('decision', '', PARAM_ALPHA);
if ($action !== '') {
    require_sesskey();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new moodle_exception('error_post_required', 'local_web3talents');
    }
    try {
        if (in_array($action, ['retry', 'resend'], true)) {
            reviews::require_reviewer(true);
            if ($action === 'retry') {
                $user = applicant_service::create_student_account($id);
                $status = empty($user->activationemailsent) ? 'emailpending' : 'created';
            } else {
                $status = applicant_service::resend_activation_email($id) ? 'emailsent' : 'emailpending';
            }
        } else {
            $result = reviews::submit($id, $action, optional_param('comment', '', PARAM_RAW_TRIMMED));
            $status = $result['status'];
        }
        redirect($url, get_string('review_result_' . $status, 'local_web3talents'), null,
            in_array($status, ['emailpending', 'provisionpending']) ?
                \core\output\notification::NOTIFY_WARNING : \core\output\notification::NOTIFY_SUCCESS);
    } catch (Throwable $e) {
        redirect($url, s($e->getMessage()), null, \core\output\notification::NOTIFY_ERROR);
    }
}
$summary = reviews::summary($id);
$ownreview = $DB->get_record('local_w3t_app_review', ['applicantid' => $id, 'reviewerid' => $USER->id]);
$closed = !empty($applicant->userid) || in_array($applicant->status, ['removed', 'deferred'], true);
echo $OUTPUT->header();
echo html_writer::link($back, get_string('review_back', 'local_web3talents'), ['class' => 'btn btn-secondary mb-3']);
echo $OUTPUT->heading(s($applicant->firstname . ' ' . $applicant->lastname));
echo html_writer::tag('p', s($applicant->email . ' · ' . $applicant->cohortid));
echo html_writer::tag('p', get_string('review_progress', 'local_web3talents', $summary['approvals']), ['class' => 'fw-bold']);
if ($summary['objections']) {
    echo $OUTPUT->notification(get_string('review_discussion_help', 'local_web3talents'), 'notifywarning');
}
if (!empty($applicant->userid)) {
    echo $OUTPUT->notification(get_string('review_result_created', 'local_web3talents'), 'notifysuccess');
    if (empty($applicant->activationemailsenttime)) {
        echo $OUTPUT->notification(get_string('review_result_emailpending', 'local_web3talents'), 'notifywarning');
    }
}
echo html_writer::start_div('row');
echo html_writer::start_div('col-lg-8');
echo $OUTPUT->heading(get_string('review_answers', 'local_web3talents'), 3);
$answers = json_decode($applicant->responses ?? '[]', true) ?: [];
$groups = ['background' => [], 'experience' => [], 'motivation' => [], 'files' => [], 'other' => []];
foreach ($answers as $answer) {
    $label = (string)($answer['question'] ?? '');
    $key = match (true) {
        (bool)preg_match('/upload|consent|video|CV as/i', $label) => 'files',
        (bool)preg_match('/expect from|sets you apart|interested in|proof of discussion|research about|shaped your|first came/i', $label) => 'motivation',
        (bool)preg_match('/experience|skills|technolog|hackathon|club\/association|industries|invest|team.work/i', $label) => 'experience',
        (bool)preg_match('/name|email|birth|phone|gender|residence|linkedin profile|occupation|professional|employer|educational/i', $label) => 'background',
        default => 'other',
    };
    $groups[$key][] = $answer;
}
foreach ($groups as $key => $group) {
    if (!$group) {
        continue;
    }
    echo html_writer::start_tag('details', ['class' => 'border rounded p-3 mb-3'] +
        ($key === 'background' ? ['open' => 'open'] : []));
    echo html_writer::tag('summary', s(get_string('review_group_' . $key, 'local_web3talents')), ['class' => 'fw-bold']);
    foreach ($group as $answer) {
        $value = trim((string)$answer['answer']);
        // Answers are data, not markup. In particular, never embed uploaded video/HTML.
        $display = $value === '' ? s(get_string('review_no_answer', 'local_web3talents')) : nl2br(s($value));
        if (preg_match('~^https://[^\s]+$~', $value) && filter_var($value, FILTER_VALIDATE_URL)) {
            $display = html_writer::link($value, get_string('review_open_attachment', 'local_web3talents'),
                ['target' => '_blank', 'rel' => 'noopener noreferrer', 'referrerpolicy' => 'no-referrer']);
        }
        echo html_writer::tag('p', html_writer::tag('strong', s($answer['question'])) . '<br>' . $display,
            ['class' => 'mt-3 text-break']);
    }
    echo html_writer::end_tag('details');
}
if (!$answers) {
    echo html_writer::tag('p', get_string('review_legacy_answers', 'local_web3talents'));
}
if (!empty($applicant->notes)) {
    echo $OUTPUT->heading(get_string('notes', 'local_web3talents'), 3);
    echo html_writer::tag('p', nl2br(s($applicant->notes)));
}
echo html_writer::end_div();
echo html_writer::start_div('col-lg-4');
echo $OUTPUT->heading(get_string('review_decision', 'local_web3talents'), 3);
echo html_writer::tag('p', get_string('review_decision_help', 'local_web3talents'));
foreach ($DB->get_records('local_w3t_app_review', ['applicantid' => $id], 'timemodified ASC') as $review) {
    $reviewer = $DB->get_record('user', ['id' => $review->reviewerid]);
    echo html_writer::tag('p', s($reviewer ? fullname($reviewer) : get_string('deleteduser', 'local_web3talents')) . ': ' .
        s(get_string('review_action_' . $review->decision, 'local_web3talents')));
}
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
foreach (['id' => $id, 'sesskey' => sesskey()] as $name => $value) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
}
echo html_writer::tag('label', get_string('review_private_comment', 'local_web3talents'), ['for' => 'review-comment']);
echo html_writer::tag('textarea', '', ['name' => 'comment', 'id' => 'review-comment', 'rows' => 5,
    'maxlength' => 10000, 'class' => 'form-control mb-2']);
$buttons = ['comment'];
if (!$closed) {
    if (has_capability('local/web3talents:createstudentaccounts', reviews::context())) {
        $buttons[] = 'approve';
        if ($summary['ready']) {
            $buttons[] = 'retry';
        }
    }
    $buttons[] = 'reject';
    if ($ownreview) {
        $buttons[] = 'withdraw';
    }
} else if (!empty($applicant->userid) && empty($applicant->activationemailsenttime) &&
        has_capability('local/web3talents:createstudentaccounts', reviews::context())) {
    $buttons[] = 'resend';
}
foreach ($buttons as $button) {
    $alreadyapproved = $button === 'approve' && ($ownreview->decision ?? '') === 'approve';
    echo html_writer::tag('button', get_string($alreadyapproved ? 'review_approved_already' : 'review_action_' . $button, 'local_web3talents'),
        ['name' => 'decision', 'value' => $button, 'type' => 'submit',
            'class' => 'btn mb-2 me-2 ' . ($button === 'approve' ? 'btn-primary' : 'btn-secondary')] +
        ($alreadyapproved ? ['disabled' => 'disabled'] : []));
}
echo html_writer::end_tag('form');
echo $OUTPUT->heading(get_string('review_history', 'local_web3talents'), 3);
foreach ($DB->get_records('local_w3t_app_comment', ['applicantid' => $id], 'timecreated DESC, id DESC') as $entry) {
    $author = $DB->get_record('user', ['id' => $entry->reviewerid]);
    echo html_writer::start_div('border-bottom py-2');
    echo html_writer::tag('strong', s($author ? fullname($author) : get_string('deleteduser', 'local_web3talents')));
    echo html_writer::tag('p', s(userdate($entry->timecreated)) . ' · ' .
        s(get_string('review_action_' . $entry->action, 'local_web3talents')));
    echo html_writer::tag('p', nl2br(s($entry->body)), ['class' => 'text-break']);
    echo html_writer::end_div();
}
echo html_writer::end_div();
echo html_writer::end_div();
echo $OUTPUT->footer();
