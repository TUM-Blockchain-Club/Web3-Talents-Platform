<?php
// Machine-to-machine endpoint: no login/session/CSRF token. Authentication is an HMAC signature.
define('NO_MOODLE_COOKIES', true);
define('NO_DEBUG_DISPLAY', true);
require_once(__DIR__ . '/../../config.php');

use local_web3talents\local\tally_webhook;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo '{"error":"POST required"}';
    exit;
}
$body = file_get_contents('php://input', false, null, 0, tally_webhook::MAX_BYTES + 1);
try {
    $config = get_config('local_web3talents');
    $result = tally_webhook::receive($body === false ? '' : $body, $_SERVER['HTTP_TALLY_SIGNATURE'] ?? '', $config);
    set_config('tally_last_received', time(), 'local_web3talents');
    set_config('tally_last_result', $result, 'local_web3talents');
    echo '{"ok":true}';
} catch (Throwable $e) {
    $status = (int)$e->getCode();
    if (!in_array($status, [400, 401, 403, 413, 503], true)) {
        $status = 503; // Retry transient database/lock failures; never leak answers or DB details.
    }
    http_response_code($status);
    echo json_encode(['error'=>$status === 503 ? 'Temporarily unavailable' : 'Request rejected']);
}
