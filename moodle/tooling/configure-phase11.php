<?php
// Applies Phase 11 retention cleanup fixtures.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');
require_once($CFG->dirroot . '/local/web3talents/classes/local/applicant_service.php');

use local_web3talents\local\applicant_service;

global $CFG, $DB;

\core\session\manager::set_user(get_admin());

function web3t_phase11_touch_export_file(string $name, int $modified): void {
    global $CFG;

    $dir = $CFG->tempdir . '/local_web3talents/exports';
    if (!is_dir($dir) && !mkdir($dir, $CFG->directorypermissions, true) && !is_dir($dir)) {
        throw new moodle_exception('Could not create Phase 11 export fixture directory.');
    }

    $path = $dir . '/' . $name;
    file_put_contents($path, 'phase11 export fixture');
    touch($path, $modified);
}

function web3t_phase11_upsert_applicant(string $email, string $status, int $retentionuntil): void {
    global $DB;

    $record = applicant_service::upsert_applicant((object)[
        'firstname' => 'Phase Eleven',
        'lastname' => str_contains($email, 'expired') ? 'Expired' : 'Active',
        'email' => $email,
        'cohortid' => 'fundamentals',
        'status' => applicant_service::STATUS_ACCEPTED,
        'notes' => 'Phase 11 retention fixture',
    ], 'manual', get_admin()->id);

    $record->status = $status;
    $record->retentionuntil = $retentionuntil;
    $record->notes = 'Phase 11 retention fixture';
    $record->timemodified = time();
    $DB->update_record('local_web3talents_app', $record);
}

$now = time();
web3t_phase11_touch_export_file('phase11-old-export.xlsx', $now - 15 * DAYSECS);
web3t_phase11_touch_export_file('phase11-fresh-export.xlsx', $now);

web3t_phase11_upsert_applicant(
    'w3t.phase11.expired@example.test',
    applicant_service::STATUS_ACCOUNT_CREATED,
    $now - DAYSECS
);
web3t_phase11_upsert_applicant(
    'w3t.phase11.active@example.test',
    applicant_service::STATUS_ACCOUNT_CREATED,
    $now + 30 * DAYSECS
);

echo 'Phase 11 retention cleanup fixtures configured.' . PHP_EOL;
