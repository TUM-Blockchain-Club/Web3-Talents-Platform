<?php
/** Topic-round-only scheduler. Never drains Moodle's general task/email backlog. */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');

// Finalisation creates assignment messages. Honour the pre-launch email hold
// before making any changes, rather than generating and discarding notifications.
if (!\local_web3talents\local\applicant_service::activation_emails_enabled()) {
    echo "Topic rounds: paused while participant invitations are disabled.\n";
    exit(0);
}

try {
    $count = \local_web3talents\local\topic_round_service::finalize_due_rounds();
    echo 'Topic rounds finalised: ' . $count . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, "Topic-round finalisation failed; inspect Moodle's round status before retrying.\n");
    exit(1);
}
