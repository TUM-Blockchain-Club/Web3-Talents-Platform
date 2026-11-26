<?php
/** HTTPS-only, administrator-only secret entry. No secrets are returned to the browser. */
require_once(__DIR__ . '/../../config.php');
use local_web3talents\local\zoom_attendance_config as configuration;
use local_web3talents\local\zoom_attendance_client;

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);
require_capability('local/web3talents:manage', $context);
if (!is_https() || empty($CFG->cookiesecure)) {
    throw new moodle_exception('zoom_httpsrequired', 'local_web3talents');
}
$url = new moodle_url('/local/web3talents/zoom_attendance_settings.php');
$config = configuration::read();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    try {
        $action = required_param('action', PARAM_ALPHA);
        if ($action === 'disable') {
            $config['enabled'] = false;
        } else if ($action === 'save') {
            foreach (['account_id', 'client_id', 'client_secret'] as $field) {
                $value = optional_param($field, '', PARAM_RAW_TRIMMED);
                if ($value !== '') {
                    if (strlen($value) > 512 || preg_match('/[\x00-\x20\x7f]/', $value)) {
                        throw new RuntimeException('Invalid credential');
                    }
                    $config[$field] = $value;
                }
                if (empty($config[$field])) {
                    throw new RuntimeException('Missing credential');
                }
            }
            $config['meeting_id'] = required_param('meeting_id', PARAM_RAW_TRIMMED);
            unset($config['host_id']);
            $client = new zoom_attendance_client($config);
            $meeting = $client->meeting();
            $config['host_id'] = $meeting['host_id'];
            $config['enabled'] = (bool)optional_param('enabled', 0, PARAM_BOOL);
        } else {
            throw new RuntimeException('Invalid action');
        }
        configuration::save($config);
        set_config('zoom_attendance_state', 'waiting', 'local_web3talents');
        $DB->insert_record('local_web3talents_log', (object)['userid' => $USER->id, 'courseid' => null,
            'eventtype' => 'zoom_attendance_configured', 'metadata' => json_encode(['enabled' => !empty($config['enabled'])]),
            'timecreated' => time()]);
        redirect($url, 'Configuration saved. Meeting access was verified when saving credentials; participant-report permissions are checked when a completed session is synced.',
            null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (Throwable $e) {
        redirect($url, 'Could not save this configuration. Check the app credentials, meeting ID and read-only Zoom scopes. '
            . 'The previous configuration is unchanged unless the final audit write failed.', null, \core\output\notification::NOTIFY_ERROR);
    }
}
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title('Zoom attendance connection');
$PAGE->set_heading('Zoom attendance connection');
$PAGE->set_cacheable(false);
echo $OUTPUT->header();
echo $OUTPUT->heading('Zoom attendance connection');
echo html_writer::tag('p', 'Use a Server-to-Server OAuth app owned by the account hosting the course meeting. '
    . 'Secrets are stored privately on the Moodle server, outside the web root. Leave credential fields blank to keep existing values.');
echo html_writer::tag('p', 'Required scopes: meeting:read:meeting:admin, meeting:read:list_past_instances:admin, '
    . 'meeting:read:list_past_participants:admin. Activate the app after adding scopes.');
echo html_writer::tag('p', 'Current state: ' . (empty($config['enabled']) ? 'Not enabled' : 'Enabled') . '. '
    . 'After a session ends, the dedicated attendance worker checks reports every 15 minutes, starting two hours after the scheduled start. '
    . 'This does not enable host delegation or send emails.');
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false), 'autocomplete' => 'off']);
foreach (['action' => 'save', 'sesskey' => sesskey()] as $key => $value) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $key, 'value' => $value]);
}
foreach (['account_id' => 'Account ID', 'client_id' => 'Client ID', 'client_secret' => 'Client secret', 'meeting_id' => 'Recurring meeting ID'] as $field => $label) {
    echo html_writer::tag('label', $label, ['for' => $field, 'class' => 'form-label mt-3']);
    echo html_writer::empty_tag('input', ['type' => $field === 'meeting_id' ? 'text' : 'password', 'id' => $field,
        'name' => $field, 'class' => 'form-control', 'autocomplete' => 'new-password',
        'value' => $field === 'meeting_id' ? ($config[$field] ?? '') : '', 'maxlength' => 512]);
}
echo html_writer::tag('p', html_writer::checkbox('enabled', 1, !empty($config['enabled']), 'Enable automatic attendance syncing'), ['class' => 'mt-3']);
echo html_writer::tag('button', 'Verify meeting and save', ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');
if ($config) {
    echo $OUTPUT->single_button(new moodle_url($url, ['action' => 'disable']), 'Pause syncing', 'post');
}
echo $OUTPUT->footer();
