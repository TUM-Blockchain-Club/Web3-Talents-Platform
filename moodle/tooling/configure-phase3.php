<?php
// Applies Phase 3 theme configuration.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');

global $CFG, $DB;

\core\session\manager::set_user(get_admin());

// The site runs Moodle's stock Boost theme on purpose. The custom theme carried the
// marketing pages and a full visual system, which made it hard to reason about the
// structure underneath; that work now lives in the separate front-facing project.
// A Moodle theme can be reintroduced once the structure is settled.
set_config('theme', 'boost');
set_config('allowthemechangeonurl', 0);

// Nothing to link to from the custom menu now that the marketing pages have left.
set_config('custommenuitems', '');

purge_all_caches();

echo 'Phase 3 theme configuration complete.' . PHP_EOL;
