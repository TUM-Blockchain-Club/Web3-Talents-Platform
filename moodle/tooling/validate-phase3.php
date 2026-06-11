<?php
// Validates Phase 3 theme configuration.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');

global $CFG;

function web3t_phase3_assert(bool $condition, string $message): void {
    if (!$condition) {
        throw new moodle_exception("Phase 3 validation failed: {$message}");
    }
    echo "OK: {$message}" . PHP_EOL;
}

$pluginman = \core_plugin_manager::instance();
$themeinfo = $pluginman->get_plugin_info('theme_web3talents');

web3t_phase3_assert((bool)$themeinfo, 'theme_web3talents is installed');
web3t_phase3_assert($CFG->theme === 'web3talents', 'Web3 Talents theme is selected');
web3t_phase3_assert(file_exists($CFG->dirroot . '/theme/web3talents/overview.php'), 'overview page file exists');
web3t_phase3_assert(file_exists($CFG->dirroot . '/theme/web3talents/pix/overview-hero.png'), 'overview hero image exists');
web3t_phase3_assert(str_contains($CFG->custommenuitems ?? '', '/theme/web3talents/overview.php'), 'overview link is in the custom menu');

echo 'Phase 3 Moodle configuration validation complete.' . PHP_EOL;
