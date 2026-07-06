<?php
// Validates Phase 6 first-login agreement workflow.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');

use local_web3talents\local\agreement_service;
use local_web3talents\local\applicant_service;

global $DB;

function web3t_phase6_assert(bool $condition, string $message): void {
    if (!$condition) {
        throw new moodle_exception("Phase 6 validation failed: {$message}");
    }
    echo "OK: {$message}" . PHP_EOL;
}

\core\session\manager::set_user(get_admin());

web3t_phase6_assert($DB->get_manager()->table_exists('local_web3talents_agree'), 'agreement table exists');
web3t_phase6_assert(agreement_service::current_version() === '2026-07-phase6', 'policy version setting is configured');
web3t_phase6_assert(agreement_service::current_text() !== '', 'policy text setting is configured');

$applicant = $DB->get_record('local_web3talents_app', ['email' => 'phase6.student@example.test'], '*', MUST_EXIST);
$user = $DB->get_record('user', ['id' => $applicant->userid, 'deleted' => 0], '*', MUST_EXIST);

web3t_phase6_assert($applicant->status === applicant_service::STATUS_ACCOUNT_CREATED, 'phase 6 student starts pending agreement');
web3t_phase6_assert(agreement_service::requires_agreement((int)$user->id), 'phase 6 student requires current agreement before accepting');

$acceptance = agreement_service::accept_current((int)$user->id);
web3t_phase6_assert(!empty($acceptance->agreedtime), 'agreement acceptance timestamp is stored');
web3t_phase6_assert($acceptance->policyversion === agreement_service::current_version(), 'agreement stores current policy version');
web3t_phase6_assert(!agreement_service::requires_agreement((int)$user->id), 'student does not see current agreement repeatedly');

$updated = $DB->get_record('local_web3talents_app', ['id' => $applicant->id], '*', MUST_EXIST);
web3t_phase6_assert($updated->status === applicant_service::STATUS_ACCOUNT_ACTIVATED, 'accepted applicant is marked activated after agreement');

$sameacceptance = agreement_service::accept_current((int)$user->id);
web3t_phase6_assert((int)$sameacceptance->id === (int)$acceptance->id, 'agreement acceptance is idempotent for same version');

echo 'Phase 6 first-login agreement validation complete.' . PHP_EOL;
