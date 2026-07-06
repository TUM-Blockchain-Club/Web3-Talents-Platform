<?php
// Validates Phase 4 local plugin scaffold.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');

global $CFG, $DB;

function web3t_phase4_assert(bool $condition, string $message): void {
    if (!$condition) {
        throw new moodle_exception("Phase 4 validation failed: {$message}");
    }
    echo "OK: {$message}" . PHP_EOL;
}

$pluginman = \core_plugin_manager::instance();
$plugininfo = $pluginman->get_plugin_info('local_web3talents');

web3t_phase4_assert((bool)$plugininfo, 'local_web3talents is installed');
web3t_phase4_assert($plugininfo->is_installed_and_upgraded(), 'local_web3talents is installed and upgraded');
web3t_phase4_assert($DB->get_manager()->table_exists('local_web3talents_log'), 'plugin event log table exists');
web3t_phase4_assert(get_config('local_web3talents', 'enabled') === '1', 'plugin enabled setting is on');
web3t_phase4_assert(
    get_config('local_web3talents', 'fundamentals_course_shortname') === 'W3T-FUNDAMENTALS-DEV',
    'fundamentals course shortname setting is configured'
);

$systemcontext = context_system::instance();
$course = $DB->get_record('course', ['shortname' => 'W3T-FUNDAMENTALS-DEV'], '*', MUST_EXIST);
$coursecontext = context_course::instance($course->id);
$admin = get_admin();
$student = $DB->get_record('user', ['username' => 'w3t.student1', 'deleted' => 0], '*', MUST_EXIST);
$mentor = $DB->get_record('user', ['username' => 'w3t.mentor1', 'deleted' => 0], '*', MUST_EXIST);

\core\session\manager::set_user($admin);
web3t_phase4_assert(has_capability('local/web3talents:manage', $systemcontext), 'admin can manage plugin');
web3t_phase4_assert(has_capability('local/web3talents:manageacceptedapplicants', $systemcontext), 'admin can manage accepted applicants');
web3t_phase4_assert(has_capability('local/web3talents:createstudentaccounts', $systemcontext), 'admin can create student accounts');
web3t_phase4_assert(has_capability('local/web3talents:managerooms', $coursecontext), 'admin can manage room generation');
web3t_phase4_assert(has_capability('local/web3talents:downloadzoomcsv', $coursecontext), 'admin can download Zoom CSV');

\core\session\manager::set_user($student);
web3t_phase4_assert(!has_capability('local/web3talents:manage', $systemcontext), 'student cannot manage plugin');
web3t_phase4_assert(has_capability('local/web3talents:viewstudentrooms', $coursecontext), 'student has future room-view capability');

\core\session\manager::set_user($mentor);
web3t_phase4_assert(!has_capability('local/web3talents:manage', $systemcontext), 'mentor cannot manage plugin');
web3t_phase4_assert(has_capability('local/web3talents:viewmentorrooms', $coursecontext), 'mentor has future room-view capability');

web3t_phase4_assert(file_exists($CFG->dirroot . '/local/web3talents/index.php'), 'plugin landing page exists');
web3t_phase4_assert(file_exists($CFG->dirroot . '/local/web3talents/templates/dashboard.mustache'), 'plugin dashboard template exists');

$PAGE->set_context($systemcontext);
$renderer = $PAGE->get_renderer('core');
$dashboard = new \local_web3talents\output\dashboard();
$dashboarddata = $dashboard->export_for_template($renderer);
web3t_phase4_assert($dashboarddata['status'] === get_string('dashboard_status_enabled', 'local_web3talents'), 'plugin dashboard renders enabled state');
web3t_phase4_assert($dashboarddata['courseshortname'] === 'W3T-FUNDAMENTALS-DEV', 'plugin dashboard renders fundamentals course setting');

set_config('enabled', 0, 'local_web3talents');
$disableddata = $dashboard->export_for_template($renderer);
web3t_phase4_assert($disableddata['enabled'] === false, 'plugin enabled setting can be disabled');
set_config('enabled', 1, 'local_web3talents');
$enableddata = $dashboard->export_for_template($renderer);
web3t_phase4_assert($enableddata['enabled'] === true, 'plugin enabled setting can be re-enabled');

echo 'Phase 4 Moodle plugin validation complete.' . PHP_EOL;
