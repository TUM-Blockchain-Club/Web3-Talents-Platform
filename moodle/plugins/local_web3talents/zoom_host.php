<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/web3talents/lib.php');

use local_web3talents\local\zoom_host_service;

require_login();
require_capability('local/web3talents:manage', local_web3talents_admin_context());
$PAGE->set_context(local_web3talents_admin_context());
$PAGE->set_url(new moodle_url('/local/web3talents/zoom_host.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('zoom_host', 'local_web3talents'));
$PAGE->set_heading(get_string('zoom_host', 'local_web3talents'));
$PAGE->set_cacheable(false);
header('Cache-Control: no-store, private, max-age=0');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $url = zoom_host_service::launch();
    // Moodle redirect() can embed the sensitive URL in fallback HTML; use a header only.
    \core\session\manager::write_close();
    header('Location: ' . $url, true, 303);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET, POST');
    exit;
}
echo $OUTPUT->header();
echo local_web3talents_action_bar([[
    'url' => new moodle_url('/local/web3talents/index.php'),
    'label' => get_string('dashboard_back', 'local_web3talents'),
]]);
echo html_writer::tag('p', get_string('zoom_host_help', 'local_web3talents'));
$blocker = zoom_host_service::blocker();
if ($blocker) {
    echo $OUTPUT->notification(get_string($blocker, 'local_web3talents'), 'warning');
}
if (!zoom_host_service::authorised()) {
    echo $OUTPUT->notification(get_string('zoom_forbidden', 'local_web3talents'), 'info');
} else if (!$blocker) {
    echo $OUTPUT->single_button(new moodle_url('/local/web3talents/zoom_host.php'),
        get_string('zoom_host', 'local_web3talents'), 'post');
}
echo $OUTPUT->footer();
