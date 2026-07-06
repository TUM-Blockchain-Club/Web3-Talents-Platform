<?php
// Applies Phase 6 first-login agreement sample data.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');
require_once($CFG->libdir . '/moodlelib.php');

use local_web3talents\local\agreement_service;
use local_web3talents\local\applicant_service;

global $DB;

\core\session\manager::set_user(get_admin());

$pluginman = \core_plugin_manager::instance();
$plugininfo = $pluginman->get_plugin_info('local_web3talents');
if (!$plugininfo || !$plugininfo->is_installed_and_upgraded()) {
    throw new moodle_exception('local_web3talents is not installed and upgraded');
}

set_config('policy_version', '2026-07-phase6', 'local_web3talents');
set_config('policy_text', get_string('default_policy_text', 'local_web3talents'), 'local_web3talents');

applicant_service::upsert_applicant((object)[
    'firstname' => 'Policy',
    'lastname' => 'Student',
    'email' => 'phase6.student@example.test',
    'cohortid' => 'fundamentals',
    'status' => applicant_service::STATUS_ACCEPTED,
    'notes' => 'Created by Phase 6 smoke setup.',
], 'manual', get_admin()->id);

$applicant = $DB->get_record('local_web3talents_app', ['email' => 'phase6.student@example.test'], '*', MUST_EXIST);
if (empty($applicant->userid)) {
    applicant_service::create_student_account((int)$applicant->id, get_admin()->id);
    $applicant = $DB->get_record('local_web3talents_app', ['email' => 'phase6.student@example.test'], '*', MUST_EXIST);
}

$user = $DB->get_record('user', ['id' => $applicant->userid], '*', MUST_EXIST);
update_internal_user_password($user, 'ChangeMe123!', false);
set_user_preference('auth_forcepasswordchange', 0, $user);

$DB->delete_records('local_web3talents_agree', [
    'userid' => $user->id,
    'policyversion' => agreement_service::current_version(),
]);
$applicant->status = applicant_service::STATUS_ACCOUNT_CREATED;
$applicant->timemodified = time();
$DB->update_record('local_web3talents_app', $applicant);

purge_all_caches();

echo 'Phase 6 first-login agreement configuration complete.' . PHP_EOL;
