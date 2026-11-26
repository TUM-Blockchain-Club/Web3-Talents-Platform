<?php
/** Attendance evidence and occurrence-specific identity review. */
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/web3talents/lib.php');

use local_web3talents\local\participation_service;
use local_web3talents\local\zoom_attendance_service as attendance;
use local_web3talents\local\zoom_attendance_config;
use local_web3talents\local\zoom_attendance_math;

$course = participation_service::get_configured_course();
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/web3talents:manageparticipation', $context);
$sessionid = required_param('sessionid', PARAM_INT);
$session = $DB->get_record('local_w3t_session', ['id' => $sessionid, 'courseid' => $course->id], '*', MUST_EXIST);
$url = new moodle_url('/local/web3talents/zoom_attendance.php', ['sessionid' => $sessionid]);
$isadmin = has_capability('local/web3talents:manage', context_system::instance());
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $action = required_param('action', PARAM_ALPHA);
    try {
        if ($action === 'review') {
            attendance::review($sessionid, required_param('rowid', PARAM_INT), required_param('userid', PARAM_INT), (int)$USER->id);
        } else if ($action === 'sync') {
            require_capability('local/web3talents:manage', context_system::instance());
            attendance::sync();
        } else {
            throw new moodle_exception('invalidrequest');
        }
        redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (Throwable $e) {
        redirect($url, get_string('zoom_attendance_action_error', 'local_web3talents'), null,
            \core\output\notification::NOTIFY_ERROR);
    }
}
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title('Zoom attendance');
$PAGE->set_heading('Zoom attendance');
$PAGE->set_cacheable(false);
echo $OUTPUT->header();
echo $OUTPUT->heading('Zoom attendance: ' . format_string($session->name));
echo html_writer::link(new moodle_url('/local/web3talents/participation.php', ['sessionid' => $sessionid]), 'Back to attendance');
echo html_writer::tag('p', 'Verified email matches are recorded as present when Zoom reports time in the meeting. '
    . 'Duration is shown for review, not used to calculate participation scores. No one is automatically marked absent. '
    . 'Manual attendance records always take precedence. A reviewer match applies only to this meeting occurrence.');
$config = zoom_attendance_config::read();
$state = empty($config['enabled']) ? 'disabled' : (get_config('local_web3talents', 'zoom_attendance_state') ?: 'waiting');
echo $OUTPUT->notification(get_string('zoom_attendance_state_' . $state, 'local_web3talents'),
    in_array($state, ['disabled', 'error'], true) ? 'warning' : 'info');
$lastcheck = (int)get_config('local_web3talents', 'zoom_attendance_lastcheck');
if ($lastcheck) {
    echo html_writer::tag('p', 'Last sync check: ' . userdate($lastcheck));
}
if ((int)get_config('local_web3talents', 'zoom_attendance_unmapped')) {
    echo $OUTPUT->notification('Some meeting occurrences could not be matched uniquely to a Moodle session. '
        . 'An organiser should check session dates and the meeting ID. No attendance was guessed for those occurrences.', 'warning');
}
if ($isadmin) {
    echo html_writer::link(new moodle_url('/local/web3talents/zoom_attendance_settings.php'), 'Configure Zoom attendance', ['class' => 'btn btn-secondary me-2']);
    echo $OUTPUT->single_button(new moodle_url($url, ['action' => 'sync']), 'Check Zoom now', 'post');
}
$students = participation_service::get_students($course);
$choices = [0 => 'Needs review', -1 => 'Ignore (host, mentor or not a participant)'];
foreach ($students as $student) {
    $choices[$student->id] = fullname($student) . ' (' . $student->email . ')';
}
$rows = attendance::evidence($sessionid);
if (!$rows) {
    echo $OUTPUT->notification('No attendance report has been imported for this session yet. Reports are checked after the meeting ends.', 'info');
} else {
    $table = new html_table();
    $table->head = ['Zoom name / email', 'Meeting start', 'Time in meeting', 'Match', 'Review'];
    foreach ($rows as $row) {
        $form = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
        foreach (['action' => 'review', 'sesskey' => sesskey(), 'rowid' => $row->id] as $key => $value) {
            $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $key, 'value' => $value]);
        }
        $form .= html_writer::select($choices, 'userid', $row->decision === 'ignored' ? -1 : $row->userid, false,
            ['class' => 'form-select', 'aria-label' => 'Match ' . $row->displayname]);
        $form .= html_writer::tag('button', 'Save match', ['class' => 'btn btn-secondary mt-2', 'type' => 'submit']);
        $form .= html_writer::end_tag('form');
        $table->data[] = [s($row->displayname) . '<br>' . s($row->email ?: 'Email not provided by Zoom'),
            userdate($row->starttime), round(zoom_attendance_math::seconds(json_decode($row->intervals, true)) / 60, 1) . ' min',
            s($row->decision), $form];
    }
    echo html_writer::table($table);
}
echo $OUTPUT->footer();
