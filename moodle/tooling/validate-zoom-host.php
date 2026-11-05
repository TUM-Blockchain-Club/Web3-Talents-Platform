<?php
// Synthetic-only checks. No network requests or real Zoom credentials are used.
define('CLI_SCRIPT', true);
require '/var/www/html/config.php';
require_once $CFG->dirroot . '/local/web3talents/lib.php';
if ($CFG->dbname !== 'reviewtest' || getenv('W3T_ZOOM_TEST') !== '1' || empty($CFG->noemailever)) {
    throw new RuntimeException('Requires isolated reviewtest database, disabled email, and W3T_ZOOM_TEST=1.');
}
use local_web3talents\local\zoom_client;
use local_web3talents\local\zoom_host_service as service;

function check(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException('FAIL: ' . $label); }
    echo 'PASS: ' . $label . "\n";
}
function blocked(callable $fn, string $label, ?string $expected = null): void {
    try { $fn(); } catch (Throwable $e) {
        check($expected === null || ($e->errorcode ?? $e->getMessage()) === $expected, $label);
        return;
    }
    check(false, $label);
}
class fake_zoom extends zoom_client {
    public array $requests = [];
    public array $responses = [];
    protected function request(string $url, array $headers, ?string $body = null): array {
        $this->requests[] = ['url' => $url, 'body' => $body];
        $next = array_shift($this->responses);
        if ($next instanceof Throwable) { throw $next; }
        return $next;
    }
}
$config = ['account_id' => 'fixture-account', 'client_id' => 'fixture-client', 'client_secret' => 'fixture-secret',
    'meeting_id' => '12345678901', 'host_id' => 'fixture-host'];
$url = 'https://eu01web.zoom.us/s/12345678901?zak=fixture-not-a-real-token';
$meeting = ['id' => 12345678901, 'host_id' => 'fixture-host', 'start_url' => $url];
foreach ($config as $key => $value) { putenv('WEB3T_ZOOM_' . strtoupper($key) . '=' . $value); }
$fake = new fake_zoom($config);
$fake->responses = [['access_token' => 'fixture-access-token'], $meeting];
check($fake->start_url() === $url, 'Fresh token and start URL retrieved for pinned meeting/host');
check(count($fake->requests) === 2 && $fake->requests[0]['url'] === 'https://zoom.us/oauth/token' &&
    $fake->requests[1]['url'] === 'https://api.zoom.us/v2/meetings/12345678901', 'Only fixed Zoom endpoints used');
foreach ([
    'http://zoom.us/s/12345678901?zak=x', 'https://zoom.us.evil.test/s/12345678901?zak=x',
    'https://evilzoom.us/s/12345678901?zak=x', 'https://evil.test/s/12345678901?zak=x',
    'https://user@zoom.us/s/12345678901?zak=x', 'https://zoom.us:444/s/12345678901?zak=x',
    'https://zoom.us/j/12345678901?zak=x', 'https://zoom.us/s/99999999999?zak=x',
    'https://zoom.us/s/12345678901', 'https://zoom.us/s/12345678901?zak[]=x',
    "https://zoom.us/s/12345678901?zak=x\r\nLocation: https://evil.test", null,
] as $index => $badurl) {
    blocked(fn() => zoom_client::validate_start_url($badurl, $config['meeting_id']), 'Unsafe URL rejected ' . $index);
}
foreach (['host_id' => 'other-host', 'id' => 99999999999] as $key => $value) {
    $bad = $meeting;
    $bad[$key] = $value;
    $fake->responses = [['access_token' => 'fixture'], $bad];
    blocked(fn() => $fake->start_url(), 'Mismatched ' . $key . ' rejected', 'zoom_wronghost');
}
$fake->responses = [['access_token' => "fixture\r\ninjected"]];
blocked(fn() => $fake->start_url(), 'Malformed bearer token rejected', 'zoom_unavailable');

$organiser = $DB->get_record('user', ['username' => 'reviewerone'], '*', MUST_EXIST);
$student = $DB->get_record('user', ['username' => 'nonreviewer'], '*', MUST_EXIST);
\core\session\manager::set_user($organiser);
putenv('WEB3T_ZOOM_ORGANISER_IDS=');
blocked(fn() => service::launch($fake), 'Manager without explicit approval denied', 'zoom_forbidden');
\core\session\manager::set_user(get_admin());
check(!service::authorised(), 'Site administrator is not implicitly allowlisted');
\core\session\manager::set_user($student);
putenv('WEB3T_ZOOM_ORGANISER_IDS=' . $student->id);
blocked(fn() => service::launch($fake), 'Allowlisted student still denied', 'zoom_forbidden');
\core\session\manager::set_user($organiser);
putenv('WEB3T_ZOOM_ORGANISER_IDS=' . $organiser->id);
check(service::authorised(), 'Explicitly allowlisted course manager allowed');
blocked(fn() => service::launch($fake), 'HTTP denied before upstream request', 'zoom_httpsrequired');
$CFG->wwwroot = 'https://fixture.invalid';
$CFG->cookiesecure = true;
$_SERVER['HTTPS'] = 'on';
putenv('WEB3T_ZOOM_HOST_ENABLED=0');
check(service::blocker() === 'zoom_disabled', 'Feature disabled by default');
putenv('WEB3T_ZOOM_HOST_ENABLED=1');
putenv('WEB3T_ZOOM_CLIENT_SECRET=');
check(service::blocker() === 'zoom_notconfigured', 'Missing credentials denied');
putenv('WEB3T_ZOOM_CLIENT_SECRET=fixture-secret');
$CFG->cookiesecure = false;
check(service::blocker() === 'zoom_httpsrequired', 'Insecure session cookies denied');
$CFG->cookiesecure = true;
$_SERVER['HTTPS'] = 'off';
check(service::blocker() === 'zoom_httpsrequired', 'Unsecured transport denied');
$_SERVER['HTTPS'] = 'on';
unset_user_preference('local_web3talents_zoom_lastlaunch');
$fake->responses = [['access_token' => 'fixture'], $meeting];
check(service::launch($fake) === $url, 'Authorised secure launch succeeds with synthetic response');
blocked(fn() => service::launch($fake), 'Repeated request throttled', 'zoom_wait');
$audit = $DB->get_records('local_web3talents_log', ['eventtype' => 'zoom_host_link_requested']);
check(count($audit) > 0 && !str_contains(json_encode($audit), 'zak') &&
    !str_contains(json_encode($audit), 'fixture-secret'), 'Audit has no URL/token/secret');
unset_user_preference('local_web3talents_zoom_lastlaunch');
$fake->responses = [new RuntimeException('sensitive-upstream-details')];
blocked(fn() => service::launch($fake), 'Upstream exception is sanitised', 'zoom_unavailable');
unset_user_preference('local_web3talents_zoom_lastlaunch');
echo "All synthetic Zoom checks passed; no external API requests were made.\n";
