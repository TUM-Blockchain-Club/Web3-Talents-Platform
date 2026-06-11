<?php
// Applies Phase 3 theme configuration.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');

global $CFG, $DB;

\core\session\manager::set_user(get_admin());

$pluginman = \core_plugin_manager::instance();
$themeinfo = $pluginman->get_plugin_info('theme_web3talents');
if (!$themeinfo) {
    throw new moodle_exception('theme_web3talents is not installed');
}

set_config('theme', 'web3talents');
set_config('allowthemechangeonurl', 0);
set_config('custommenuitems', "Overview|{$CFG->wwwroot}/theme/web3talents/overview.php\nStudent login|{$CFG->wwwroot}/login/index.php");

purge_all_caches();

echo 'Phase 3 theme configuration complete.' . PHP_EOL;
