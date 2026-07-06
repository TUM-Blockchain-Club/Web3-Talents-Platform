<?php
// Applies Phase 4 local plugin scaffold configuration.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');

global $DB;

\core\session\manager::set_user(get_admin());

$pluginman = \core_plugin_manager::instance();
$plugininfo = $pluginman->get_plugin_info('local_web3talents');
if (!$plugininfo) {
    throw new moodle_exception('local_web3talents is not installed');
}

set_config('enabled', 1, 'local_web3talents');
set_config('fundamentals_course_shortname', 'W3T-FUNDAMENTALS-DEV', 'local_web3talents');

$DB->insert_record('local_web3talents_log', [
    'eventtype' => 'phase4_configured',
    'userid' => get_admin()->id,
    'courseid' => null,
    'metadata' => json_encode(['source' => 'configure-phase4']),
    'timecreated' => time(),
]);

purge_all_caches();

echo 'Phase 4 local plugin configuration complete.' . PHP_EOL;
