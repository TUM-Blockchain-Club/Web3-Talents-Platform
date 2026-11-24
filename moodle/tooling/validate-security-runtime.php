<?php
// Run only against a restored, isolated test DB. All synthetic records roll back.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
if ($CFG->dbname !== 'reviewtest' || empty($CFG->noemailever) || getenv('W3T_SECURITY_TEST') !== '1') {
    throw new RuntimeException('Requires isolated reviewtest DB, noemailever and W3T_SECURITY_TEST=1.');
}
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/login/lib.php');
function security_check(bool $ok, string $label): void {
    if (!$ok) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    echo 'PASS: ' . $label . "\n";
}
security_check(!get_config('local_web3talents', 'activation_emails_enabled'), 'Applicant emails remain paused');
security_check((int)get_config('core', 'debug') === 0 && !get_config('core', 'debugdisplay'), 'Production debug output disabled');
security_check((int)get_config('core', 'lockoutthreshold') === 10
    && (int)get_config('core', 'lockoutduration') === 900, 'Temporary login lockout configured');
security_check(!ini_get('display_errors') && ini_get('log_errors') && !ini_get('expose_php'), 'PHP production INI loaded');
$transaction = $DB->start_delegated_transaction();
$rollback = new RuntimeException('Intentional synthetic-fixture rollback');
try {
    $password = bin2hex(random_bytes(24));
    $user = (object)[
        'username' => 'security-fixture-' . bin2hex(random_bytes(8)),
        'email' => 'security-fixture@example.invalid',
        'firstname' => 'Security', 'lastname' => 'Fixture',
        'auth' => 'manual', 'confirmed' => 1, 'mnethostid' => $CFG->mnet_localhost_id,
        'password' => $password,
    ];
    $id = user_create_user($user, true, false);
    $stored = $DB->get_record('user', ['id' => $id], '*', MUST_EXIST);
    security_check($stored->password !== $password && validate_internal_user_password($stored, $password),
        'Real Moodle account API stores a working password hash, not plaintext');
    $old = core_login_generate_password_reset($stored);
    $new = core_login_generate_password_reset($stored);
    security_check(!$DB->record_exists('user_password_resets', ['id' => $old->id])
        && $DB->record_exists('user_password_resets', ['id' => $new->id]), 'Core supersedes earlier reset tokens');
    security_check(strlen($new->token) >= 32 && $old->token !== $new->token, 'Reset tokens are distinct and sufficiently long');
    $before = $DB->count_records('user_password_resets');
    security_check(!\local_web3talents\local\invitation_mailer::send($stored)
        && $DB->count_records('user_password_resets') === $before, 'Paused mailer creates no new setup tokens');
} finally {
    try {
        $transaction->rollback($rollback);
    } catch (RuntimeException $error) {
        if ($error !== $rollback) {
            throw $error;
        }
    }
}
security_check(!$DB->record_exists('user', ['username' => $user->username]), 'Synthetic account and tokens rolled back');
