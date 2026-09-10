<?php
// Applies Phase 3 theme configuration.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');

global $CFG, $DB;

\core\session\manager::set_user(get_admin());

$pluginman = \core_plugin_manager::instance();
if (!$pluginman->get_plugin_info('theme_web3talents')) {
    throw new moodle_exception('theme_web3talents is not installed');
}

set_config('theme', 'web3talents');
set_config('allowthemechangeonurl', 0);

// The public marketing pages live in the separate front-facing project now, so there
// is nothing for a custom menu to point at and Moodle already provides its own login
// control.
set_config('custommenuitems', '');

/**
 * Publish a theme image into one of core_admin's logo file areas.
 *
 * Moodle serves the site logo from the file API rather than from a path, so the file
 * has to be copied into core_admin's area for $OUTPUT->get_logo_url() to find it. The
 * area is cleared first so re-running this script replaces the logo instead of
 * colliding with the copy already stored there.
 *
 * @param string $filearea Either 'logo' or 'logocompact'.
 * @param string $source Absolute path to the image to publish.
 * @return void
 */
function web3t_publish_logo(string $filearea, string $source): void {
    $syscontext = context_system::instance();
    $fs = get_file_storage();

    $fs->delete_area_files($syscontext->id, 'core_admin', $filearea, 0);
    $fs->create_file_from_pathname([
        'contextid' => $syscontext->id,
        'component' => 'core_admin',
        'filearea' => $filearea,
        'itemid' => 0,
        'filepath' => '/',
        'filename' => 'logo.png',
    ], $source);

    set_config($filearea, '/logo.png', 'core_admin');
}

$logo = $CFG->dirroot . '/theme/web3talents/pix/logo.png';
if (file_exists($logo)) {
    web3t_publish_logo('logo', $logo);
    web3t_publish_logo('logocompact', $logo);
    echo 'Published the Web3 Talents logo.' . PHP_EOL;
}

purge_all_caches();

echo 'Phase 3 theme configuration complete.' . PHP_EOL;
