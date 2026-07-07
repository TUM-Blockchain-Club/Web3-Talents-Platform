<?php
// Validates Phase 11 retention cleanup and task registration.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');
require_once($CFG->dirroot . '/local/web3talents/classes/local/applicant_service.php');
require_once($CFG->dirroot . '/local/web3talents/classes/local/retention_service.php');
require_once($CFG->dirroot . '/local/web3talents/classes/task/cleanup_retention.php');

use local_web3talents\local\applicant_service;
use local_web3talents\local\retention_service;

global $CFG, $DB;

\core\session\manager::set_user(get_admin());

function web3t_phase11_assert(bool $condition, string $message): void {
    if (!$condition) {
        throw new moodle_exception("Phase 11 validation failed: {$message}");
    }
    echo "OK: {$message}" . PHP_EOL;
}

$task = $DB->get_record('task_scheduled', ['classname' => '\local_web3talents\task\cleanup_retention']);
if (!$task) {
    $task = $DB->get_record('task_scheduled', ['classname' => 'local_web3talents\task\cleanup_retention']);
}
web3t_phase11_assert($task !== false, 'retention cleanup scheduled task is registered');
web3t_phase11_assert((int)$task->disabled === 0, 'retention cleanup scheduled task is enabled');

$exportdir = $CFG->tempdir . '/local_web3talents/exports';
$oldfile = $exportdir . '/phase11-old-export.xlsx';
$freshfile = $exportdir . '/phase11-fresh-export.xlsx';
web3t_phase11_assert(file_exists($oldfile), 'old export fixture exists before cleanup');
web3t_phase11_assert(file_exists($freshfile), 'fresh export fixture exists before cleanup');

$expired = $DB->get_record('local_web3talents_app', ['email' => 'w3t.phase11.expired@example.test'], '*', MUST_EXIST);
$active = $DB->get_record('local_web3talents_app', ['email' => 'w3t.phase11.active@example.test'], '*', MUST_EXIST);
web3t_phase11_assert($expired->status === applicant_service::STATUS_ACCOUNT_CREATED, 'expired applicant starts account-created');
web3t_phase11_assert($active->status === applicant_service::STATUS_ACCOUNT_CREATED, 'active applicant starts account-created');

$manualcounts = retention_service::cleanup();
web3t_phase11_assert($manualcounts['exportfiles'] >= 1, 'manual retention cleanup deletes old export files');
web3t_phase11_assert($manualcounts['applicants'] >= 1, 'manual retention cleanup marks expired applicants');

$scheduledtask = new \local_web3talents\task\cleanup_retention();
$scheduledtask->execute();

web3t_phase11_assert(!file_exists($oldfile), 'old export fixture is removed after cleanup');
web3t_phase11_assert(file_exists($freshfile), 'fresh export fixture is retained after cleanup');

$expired = $DB->get_record('local_web3talents_app', ['email' => 'w3t.phase11.expired@example.test'], '*', MUST_EXIST);
$active = $DB->get_record('local_web3talents_app', ['email' => 'w3t.phase11.active@example.test'], '*', MUST_EXIST);
web3t_phase11_assert($expired->status === applicant_service::STATUS_REMOVED, 'expired applicant is marked removed');
web3t_phase11_assert(str_contains((string)$expired->notes, 'Retention cleanup'), 'expired applicant keeps retention audit note');
web3t_phase11_assert($active->status === applicant_service::STATUS_ACCOUNT_CREATED, 'active applicant is retained');

web3t_phase11_assert($DB->record_exists('local_web3talents_log', ['eventtype' => 'retention_export_files_deleted']), 'export retention cleanup is logged');
web3t_phase11_assert($DB->record_exists('local_web3talents_log', ['eventtype' => 'retention_applicant_marked_removed']), 'applicant retention cleanup is logged');

echo 'Phase 11 retention cleanup validation complete.' . PHP_EOL;
