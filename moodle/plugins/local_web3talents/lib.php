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
