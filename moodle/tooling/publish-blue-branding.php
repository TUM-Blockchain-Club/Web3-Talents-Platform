<?php
// Publish only approved branding. Existing files are retained for rollback.
define('CLI_SCRIPT', true);
require '/var/www/html/config.php';
$apply = in_array('--apply', $argv, true);
$fs = get_file_storage();
$context = context_system::instance();
$files = ['logo' => 'logo-blue-transparent.png', 'logocompact' => 'logo-blue-transparent.png',
    'favicon' => 'favicon-blue.png'];
foreach ($files as $area => $filename) {
    $source = $CFG->dirroot . '/theme/web3talents/pix/' . $filename;
    if (!is_readable($source)) {
        throw new RuntimeException('Missing branding asset: ' . $filename);
    }
    echo $area . ': ' . get_config('core_admin', $area) . ' -> /' . $filename . PHP_EOL;
    if (!$apply) {
        continue;
    }
    if (!$fs->file_exists($context->id, 'core_admin', $area, 0, '/', $filename)) {
        $fs->create_file_from_pathname(['contextid' => $context->id, 'component' => 'core_admin',
            'filearea' => $area, 'itemid' => 0, 'filepath' => '/', 'filename' => $filename], $source);
    }
    set_config($area, '/' . $filename, 'core_admin');
}
if ($apply) {
    purge_all_caches();
    echo 'Branding published; original files preserved.' . PHP_EOL;
}
