<?php
// CLI-only, idempotent configuration hardening. Does not send email or run tasks.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');

if (!in_array('--apply', $argv, true)) {
    fwrite(STDERR, "Requires --apply after a verified backup and recovery-access check.\n");
    exit(1);
}
if (parse_url($CFG->wwwroot, PHP_URL_SCHEME) !== 'https' && empty($CFG->noemailever)) {
    throw new RuntimeException('Requires HTTPS production or a no-email isolated test instance.');
}
// No forced password changes, MFA enforcement, cron execution, or mail-state changes.
$settings = [
    'debug' => 0,
    'debugdisplay' => 0,
    'lockoutthreshold' => 10,
    'lockoutwindow' => 900,
    'lockoutduration' => 900,
    'disableupdateautodeploy' => 1,
];
foreach ($settings as $name => $value) {
    set_config($name, $value);
}
echo "Production hardening settings applied. Applicant mail and scheduled tasks unchanged.\n";
