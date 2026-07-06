<?php
// Applies Phase 5 accepted-applicant workflow sample data.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');

use local_web3talents\local\applicant_service;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

global $DB;

\core\session\manager::set_user(get_admin());

$pluginman = \core_plugin_manager::instance();
$plugininfo = $pluginman->get_plugin_info('local_web3talents');
if (!$plugininfo || !$plugininfo->is_installed_and_upgraded()) {
    throw new moodle_exception('local_web3talents is not installed and upgraded');
}

$manual = applicant_service::upsert_applicant((object)[
    'firstname' => 'Phase',
    'lastname' => 'Five',
    'email' => 'w3t.phase5.student@example.test',
    'cohortid' => 'fundamentals',
    'status' => applicant_service::STATUS_ACCEPTED,
    'notes' => 'Created by Phase 5 smoke setup.',
], 'manual', get_admin()->id);

$csv = "firstname,lastname,email,cohortid,status,notes\n";
$csv .= "Csv,Applicant,w3t.phase5.csv@example.test,fundamentals,accepted,Imported from CSV\n";
applicant_service::import_csv($csv, 'UTF-8', 'comma', get_admin()->id);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->fromArray([
    ['firstname', 'lastname', 'email', 'cohortid', 'status', 'notes'],
    ['Excel', 'Applicant', 'w3t.phase5.excel@example.test', 'fundamentals', 'accepted', 'Imported from Excel'],
], null, 'A1');
$excelpath = make_temp_directory('web3talents') . '/phase5-applicants.xlsx';
(new Xlsx($spreadsheet))->save($excelpath);
applicant_service::import_excel($excelpath, get_admin()->id);
@unlink($excelpath);

applicant_service::upsert_applicant((object)[
    'firstname' => 'Blocked',
    'lastname' => 'Applicant',
    'email' => 'w3t.phase5.blocked@example.test',
    'cohortid' => 'fundamentals',
    'status' => applicant_service::STATUS_REMOVED,
    'notes' => 'Used to validate blocked account creation.',
], 'manual', get_admin()->id);

$manual = $DB->get_record('local_web3talents_app', ['email' => 'w3t.phase5.student@example.test'], '*', MUST_EXIST);
if (empty($manual->userid)) {
    applicant_service::create_student_account((int)$manual->id, get_admin()->id);
}

purge_all_caches();

echo 'Phase 5 accepted-applicant workflow configuration complete.' . PHP_EOL;
