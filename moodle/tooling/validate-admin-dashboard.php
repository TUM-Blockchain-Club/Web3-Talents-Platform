<?php
// Read-only rendering checks. Run inside the Moodle container with PHP CLI.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/local/web3talents/lib.php');
require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
require_once($CFG->dirroot . '/blocks/web3talents/block_web3talents.php');

\core\session\manager::set_user(get_admin());
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/web3talents/index.php'));
$OUTPUT = $PAGE->get_renderer('core');

function dashboard_check(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: $message\n";
}

$dashboard = new \local_web3talents\output\dashboard();
$block = new block_web3talents();
$shortcuts = $block->get_content()->text;
dashboard_check(substr_count($shortcuts, '<a ') === 1 && str_contains($shortcuts, '/local/web3talents/index.php'),
    'Admin homepage block contains only Open dashboard');
$data = $dashboard->export_for_template($OUTPUT);
dashboard_check(array_column($data['weeklylinks'], 'step') === [1, 2, 3], 'Weekly steps stay in order');
$urls = [];
foreach (['weeklylinks', 'setuplinks', 'mentorlinks', 'advancedlinks', 'viewlinks'] as $group) {
    foreach ($data[$group] as $link) {
        dashboard_check(!str_contains($link['label'], '[['), 'Translated label: ' . $link['label']);
        $path = parse_url($link['url'], PHP_URL_PATH);
        dashboard_check(is_file($CFG->dirroot . $path), 'Destination exists: ' . $path);
        $urls[] = $link['url'];
    }
}
dashboard_check(count($urls) === count(array_unique($urls)), 'No duplicate task links');
$html = $OUTPUT->render_from_template('local_web3talents/dashboard', $data);
dashboard_check(!str_contains($html, '[['), 'Template has no missing language strings');
$dom = new DOMDocument();
@$dom->loadHTML($html);
$xpath = new DOMXPath($dom);
dashboard_check($xpath->query('//*[contains(@class, "w3t-admin-context") or contains(@class, "w3t-admin-status")]')->length === 0,
    'Course/status strip is removed');
dashboard_check($xpath->query('//details[contains(@class, "w3t-admin-advanced") and not(@open)]')->length === 1,
    'Advanced tools are collapsed initially');
if ($data['coursefound']) {
    dashboard_check($xpath->query('//details[@open]')->length === 0, 'Optional sections start collapsed');
    dashboard_check($xpath->query('//li[contains(@class, "w3t-admin-step")]')->length === 3, 'Three weekly cards render');
}

// Exercise warning/empty-state rendering without changing configuration or data.
$data['coursefound'] = false;
$data['enabled'] = false;
$data['status'] = get_string('dashboard_status_disabled', 'local_web3talents');
$html = $OUTPUT->render_from_template('local_web3talents/dashboard', $data);
@$dom->loadHTML($html);
$xpath = new DOMXPath($dom);
dashboard_check($xpath->query('//details[@id="w3t-admin-setup" and @open]')->length === 1,
    'Setup opens when the configured course is missing');
dashboard_check($xpath->query('//section[contains(@class, "w3t-admin-weekly")]')->length === 0,
    'Weekly workflow is hidden without a course');
dashboard_check($xpath->query('//div[contains(@class, "alert-warning")]')->length === 2,
    'Missing-course and disabled-workflow warnings render');
$links = [
    ['url' => new moodle_url('/local/web3talents/index.php'), 'label' => 'Dashboard',
        'capability' => 'local/web3talents:manage', 'context' => local_web3talents_admin_context()],
    ['url' => new moodle_url('/local/web3talents/topic_rounds.php'), 'label' => 'Topics',
        'capability' => 'local/web3talents:manage', 'context' => local_web3talents_admin_context()],
];
$bar = local_web3talents_action_bar($links);
dashboard_check(substr_count($bar, '<a ') === 1 && str_contains($bar, 'Back to admin dashboard')
    && !str_contains($bar, 'topic_rounds.php'), 'Admin navigation contains only the Back link');
dashboard_check(local_web3talents_action_bar([]) === '', 'Empty action bars stay empty');

// A visitor without admin access must not gain the return link or lose allowed links.
\core\session\manager::set_user($DB->get_record('user', ['username' => 'guest', 'mnethostid' => $CFG->mnet_localhost_id], '*', MUST_EXIST));
$links[] = ['url' => new moodle_url('/'), 'label' => 'Public home'];
$bar = local_web3talents_action_bar($links);
dashboard_check(substr_count($bar, '<a ') === 1 && str_contains($bar, 'Public home')
    && !str_contains($bar, 'Back to admin dashboard'), 'Capability checks still protect admin navigation');
\core\session\manager::set_user(get_admin());
echo "Admin dashboard rendering checks complete. No course data changed.\n";
