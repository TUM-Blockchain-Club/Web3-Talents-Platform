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

web3t_phase3_assert($CFG->theme === 'boost', 'stock Boost theme is selected');
web3t_phase3_assert(trim($CFG->custommenuitems ?? '') === '', 'custom menu is empty');
web3t_phase3_assert(
    !file_exists($CFG->dirroot . '/theme/web3talents'),
    'no custom theme is installed'
);

echo 'Phase 3 Moodle configuration validation complete.' . PHP_EOL;
