<?php
/** Dedicated attendance-only runner; deliberately does not invoke general Moodle cron. */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
$CFG->noemailever = true;
$CFG->nosmsever = true;
try {
    \local_web3talents\local\zoom_attendance_service::cleanup();
    $result = \local_web3talents\local\zoom_attendance_service::sync();
    echo 'Zoom attendance: ' . $result['state'] . '; imported occurrences: ' . $result['imported']
        . '; unmatched occurrences: ' . $result['review'] . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, "Zoom attendance check failed; review the connection settings and read-only API scopes. No email was sent.\n");
    exit(1);
}
