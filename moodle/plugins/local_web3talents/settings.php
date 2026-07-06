<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Admin settings for local_web3talents.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$context = context_system::instance();
$canmanage = has_capability('local/web3talents:manage', $context);

if ($canmanage) {
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_web3talents',
        get_string('pluginname', 'local_web3talents'),
        new moodle_url('/local/web3talents/index.php'),
        'local/web3talents:manage'
    ));

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_web3talents_applicants',
        get_string('applicants', 'local_web3talents'),
        new moodle_url('/local/web3talents/applicants.php'),
        'local/web3talents:manageacceptedapplicants'
    ));

    $settings = new admin_settingpage(
        'local_web3talents_settings',
        get_string('settings', 'local_web3talents'),
        'local/web3talents:manage'
    );

    $settings->add(new admin_setting_configcheckbox(
        'local_web3talents/enabled',
        get_string('setting_enabled', 'local_web3talents'),
        get_string('setting_enabled_desc', 'local_web3talents'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'local_web3talents/fundamentals_course_shortname',
        get_string('setting_fundamentals_course_shortname', 'local_web3talents'),
        get_string('setting_fundamentals_course_shortname_desc', 'local_web3talents'),
        'W3T-FUNDAMENTALS-DEV',
        PARAM_TEXT
    ));

    $ADMIN->add('localplugins', $settings);
}
