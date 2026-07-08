<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Local plugin callbacks.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Return the configured fundamentals course, when available.
 *
 * @return stdClass|null
 */
function local_web3talents_get_configured_course(): ?stdClass {
    global $DB;

    $shortname = get_config('local_web3talents', 'fundamentals_course_shortname') ?: 'W3T-FUNDAMENTALS-DEV';
    $course = $DB->get_record('course', ['shortname' => $shortname], 'id, fullname, shortname, category');

    return $course ?: null;
}

/**
 * Pick the best Web3 Talents landing URL for the current user.
 *
 * @return moodle_url|null
 */
function local_web3talents_navigation_url(): ?moodle_url {
    if (!isloggedin() || isguestuser()) {
        return null;
    }

    $systemcontext = context_system::instance();
    if (has_capability('local/web3talents:manage', $systemcontext)) {
        return new moodle_url('/local/web3talents/index.php');
    }

    $course = local_web3talents_get_configured_course();
    if (!$course) {
        return null;
    }

    $coursecontext = context_course::instance($course->id);
    if (has_capability('local/web3talents:viewmentorrooms', $coursecontext)) {
        return new moodle_url('/local/web3talents/mentor_rooms.php');
    }
    if (has_capability('local/web3talents:viewstudentrooms', $coursecontext)) {
        return new moodle_url('/local/web3talents/choose_topic.php');
    }

    return null;
}

/**
 * Add Web3 Talents to the main Moodle navigation for users with relevant access.
 *
 * @param global_navigation $navigation Main navigation tree.
 */
function local_web3talents_extend_navigation(global_navigation $navigation): void {
    $url = local_web3talents_navigation_url();
    if (!$url) {
        return;
    }

    $node = $navigation->add(
        get_string('pluginname', 'local_web3talents'),
        $url,
        navigation_node::TYPE_CUSTOM,
        null,
        'local_web3talents',
        new pix_icon('i/settings', '')
    );
    $node->showinflatnavigation = true;
}

/**
 * Add Web3 Talents shortcuts to the configured course navigation.
 *
 * @param navigation_node $navigation Course navigation node.
 * @param stdClass $course Course record.
 * @param context $context Course context.
 */
function local_web3talents_extend_navigation_course(navigation_node $navigation, stdClass $course, context $context): void {
    $configuredcourse = local_web3talents_get_configured_course();
    if (!$configuredcourse || (int)$configuredcourse->id !== (int)$course->id) {
        return;
    }

    $systemcontext = context_system::instance();
    $hasadminaccess = has_capability('local/web3talents:manage', $systemcontext);
    $hasmentoraccess = !$hasadminaccess && has_capability('local/web3talents:viewmentorrooms', $context);
    $hasstudentaccess = !$hasadminaccess && has_capability('local/web3talents:viewstudentrooms', $context);

    if (!$hasadminaccess && !$hasmentoraccess && !$hasstudentaccess) {
        return;
    }

    $root = $navigation->add(
        get_string('pluginname', 'local_web3talents'),
        null,
        navigation_node::TYPE_CONTAINER,
        null,
        'local_web3talents_course',
        new pix_icon('i/settings', '')
    );

    if ($hasadminaccess) {
        $root->add(
            get_string('pluginname', 'local_web3talents'),
            new moodle_url('/local/web3talents/index.php'),
            navigation_node::TYPE_SETTING,
            null,
            'local_web3talents_admin'
        );
        $root->add(
            get_string('applicants', 'local_web3talents'),
            new moodle_url('/local/web3talents/applicants.php'),
            navigation_node::TYPE_SETTING,
            null,
            'local_web3talents_applicants'
        );
        $root->add(
            get_string('topic_rounds', 'local_web3talents'),
            new moodle_url('/local/web3talents/topic_rounds.php'),
            navigation_node::TYPE_SETTING,
            null,
            'local_web3talents_topic_rounds'
        );
        $root->add(
            get_string('room_assignments', 'local_web3talents'),
            new moodle_url('/local/web3talents/room_assignments.php'),
            navigation_node::TYPE_SETTING,
            null,
            'local_web3talents_room_assignments'
        );
        $root->add(
            get_string('participation', 'local_web3talents'),
            new moodle_url('/local/web3talents/participation.php'),
            navigation_node::TYPE_SETTING,
            null,
            'local_web3talents_participation'
        );
        $root->add(
            get_string('mentor_availability', 'local_web3talents'),
            new moodle_url('/local/web3talents/mentor_availability.php'),
            navigation_node::TYPE_SETTING,
            null,
            'local_web3talents_mentor_availability_admin'
        );
    }

    if ($hasmentoraccess) {
        $root->add(
            get_string('mentor_room_assignments', 'local_web3talents'),
            new moodle_url('/local/web3talents/mentor_rooms.php'),
            navigation_node::TYPE_SETTING,
            null,
            'local_web3talents_mentor_rooms'
        );
        $root->add(
            get_string('participation', 'local_web3talents'),
            new moodle_url('/local/web3talents/participation.php'),
            navigation_node::TYPE_SETTING,
            null,
            'local_web3talents_participation_mentor'
        );
        $root->add(
            get_string('mentor_availability', 'local_web3talents'),
            new moodle_url('/local/web3talents/mentor_availability.php'),
            navigation_node::TYPE_SETTING,
            null,
            'local_web3talents_mentor_availability'
        );
    }

    if ($hasstudentaccess) {
        $root->add(
            get_string('choose_weekly_topic', 'local_web3talents'),
            new moodle_url('/local/web3talents/choose_topic.php'),
            navigation_node::TYPE_SETTING,
            null,
            'local_web3talents_choose_topic'
        );
        $root->add(
            get_string('my_room_assignment', 'local_web3talents'),
            new moodle_url('/local/web3talents/my_room.php'),
            navigation_node::TYPE_SETTING,
            null,
            'local_web3talents_my_room'
        );
    }
}

/**
 * Redirect accepted student accounts to the agreement page until the current policy is accepted.
 *
 * @param mixed $courseorid Course object/id.
 * @param bool $autologinguest Whether guests may be logged in automatically.
 * @param mixed $cm Course module.
 * @param bool $setwantsurltome Whether Moodle should store the wanted URL.
 * @param bool $preventredirect Whether redirects should be prevented.
 */
function local_web3talents_after_require_login($courseorid, $autologinguest, $cm, $setwantsurltome, $preventredirect): void {
    global $SCRIPT, $SESSION, $USER;

    if (CLI_SCRIPT || AJAX_SCRIPT || WS_SERVER || $preventredirect || !isloggedin() || isguestuser() || is_siteadmin()) {
        return;
    }

    $exempt = [
        '/local/web3talents/agreement.php',
        '/local/web3talents/index.php',
        '/local/web3talents/applicants.php',
        '/local/web3talents/course_state.php',
        '/local/web3talents/topic_rounds.php',
        '/local/web3talents/room_assignments.php',
        '/local/web3talents/participation.php',
        '/local/web3talents/mentor_availability.php',
        '/login/logout.php',
        '/login/change_password.php',
        '/user/edit.php',
    ];
    if (in_array($SCRIPT, $exempt, true)) {
        return;
    }

    if (!\local_web3talents\local\agreement_service::requires_agreement((int)$USER->id)) {
        return;
    }

    if ($setwantsurltome) {
        $SESSION->wantsurl = qualified_me();
    }
    redirect(new moodle_url('/local/web3talents/agreement.php'));
}
