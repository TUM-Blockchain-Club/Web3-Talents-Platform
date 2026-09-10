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
 * The pages below used to sit as flat siblings under Local plugins, which gave a program
 * admin no clue which are touched once per cohort and which belong to the weekly routine.
 * They are now grouped by when they are used rather than by what they act on, so the
 * running order of a week is readable straight off the menu.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/web3talents/lib.php');

// Program admins hold their manager role on the fundamentals course, so each page
// below is gated on its own capability in that context rather than on one shared
// system-context check.
$context = local_web3talents_admin_context();

/**
 * Build one externalpage entry with this plugin's shared capability context.
 *
 * @param string $key Admin page identifier.
 * @param string $stringid Language string for the visible name.
 * @param string $file Page filename inside /local/web3talents/.
 * @param string $capability Capability required to see and open the page.
 * @return admin_externalpage
 */
$page = function (string $key, string $stringid, string $file, string $capability) use ($context): admin_externalpage {
    return new admin_externalpage(
        $key,
        get_string($stringid, 'local_web3talents'),
        new moodle_url('/local/web3talents/' . $file),
        $capability,
        false,
        $context
    );
};

$ADMIN->add('localplugins', new admin_category(
    'local_web3talents_cat',
    get_string('pluginname', 'local_web3talents')
));

// Landing page first: it is the only entry that summarises the others.
$ADMIN->add('local_web3talents_cat', $page(
    'local_web3talents',
    'pluginname',
    'index.php',
    'local/web3talents:manage'
));

// Touched while a cohort is being set up, and rarely afterwards.
$ADMIN->add('local_web3talents_cat', new admin_category(
    'local_web3talents_cat_setup',
    get_string('nav_setup', 'local_web3talents')
));
$ADMIN->add('local_web3talents_cat_setup', $page(
    'local_web3talents_applicants',
    'applicants',
    'applicants.php',
    'local/web3talents:manageacceptedapplicants'
));
$ADMIN->add('local_web3talents_cat_setup', $page(
    'local_web3talents_course_state',
    'course_state',
    'course_state.php',
    'local/web3talents:manage'
));

// The weekly cycle, listed in the order it actually runs: open a round, generate rooms
// from the finalised result, then record who turned up.
$ADMIN->add('local_web3talents_cat', new admin_category(
    'local_web3talents_cat_weekly',
    get_string('nav_weekly', 'local_web3talents')
));
$ADMIN->add('local_web3talents_cat_weekly', $page(
    'local_web3talents_topic_rounds',
    'topic_rounds',
    'topic_rounds.php',
    'local/web3talents:manage'
));
$ADMIN->add('local_web3talents_cat_weekly', $page(
    'local_web3talents_room_assignments',
    'room_assignments',
    'room_assignments.php',
    'local/web3talents:manage'
));
$ADMIN->add('local_web3talents_cat_weekly', $page(
    'local_web3talents_participation',
    'participation',
    'participation.php',
    'local/web3talents:manageparticipation'
));

// Mentor-facing pages. Grouped separately because mentors reach them through the course
// navigation rather than through this menu.
$ADMIN->add('local_web3talents_cat', new admin_category(
    'local_web3talents_cat_mentors',
    get_string('nav_mentors', 'local_web3talents')
));
$ADMIN->add('local_web3talents_cat_mentors', $page(
    'local_web3talents_mentor_availability',
    'mentor_availability',
    'mentor_availability.php',
    'local/web3talents:manageownavailability'
));
$ADMIN->add('local_web3talents_cat_mentors', $page(
    'local_web3talents_mentor_grading',
    'mentor_grading',
    'mentor_grading.php',
    'local/web3talents:gradeassignedroom'
));

$settings = new admin_settingpage(
    'local_web3talents_settings',
    get_string('settings', 'local_web3talents'),
    'local/web3talents:manage',
    false,
    $context
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

$settings->add(new admin_setting_configtext(
    'local_web3talents/policy_version',
    get_string('setting_policy_version', 'local_web3talents'),
    get_string('setting_policy_version_desc', 'local_web3talents'),
    '2026-07',
    PARAM_TEXT
));

$settings->add(new admin_setting_configtextarea(
    'local_web3talents/policy_text',
    get_string('setting_policy_text', 'local_web3talents'),
    get_string('setting_policy_text_desc', 'local_web3talents'),
    get_string('default_policy_text', 'local_web3talents'),
    PARAM_TEXT
));

$ADMIN->add('local_web3talents_cat_setup', $settings);
