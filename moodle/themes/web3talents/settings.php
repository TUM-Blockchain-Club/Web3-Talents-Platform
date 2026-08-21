<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Web3 Talents theme settings.
 *
 * @package    theme_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // The four marketing pages (overview/courses/course/community) are public by
    // default. Sites that run with $CFG->forcelogin can turn this off so those
    // pages require a login like everything else, instead of silently serving
    // content anonymously.
    $settings->add(new admin_setting_configcheckbox(
        'theme_web3talents/publicpages',
        get_string('publicpages', 'theme_web3talents'),
        get_string('publicpages_desc', 'theme_web3talents'),
        1
    ));
}
