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

web3t_phase3_assert((bool)$pluginman->get_plugin_info('theme_web3talents'), 'theme_web3talents is installed');
web3t_phase3_assert($CFG->theme === 'web3talents', 'Web3 Talents theme is selected');
web3t_phase3_assert(trim($CFG->custommenuitems ?? '') === '', 'custom menu is empty');
web3t_phase3_assert(
    file_exists($CFG->dirroot . '/theme/web3talents/pix/login-hero.png'),
    'login hero image exists'
);

// The marketing pages moved to the front-facing project; nothing should serve them.
web3t_phase3_assert(
    !file_exists($CFG->dirroot . '/theme/web3talents/overview.php'),
    'no marketing pages remain in the theme'
);

$fs = get_file_storage();
$files = $fs->get_area_files(context_system::instance()->id, 'core_admin', 'logo', 0, 'filename', false);
web3t_phase3_assert(!empty($files), 'site logo is published');

echo 'Phase 3 Moodle configuration validation complete.' . PHP_EOL;
