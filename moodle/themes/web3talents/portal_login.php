<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Branded login entry point.
 *
 * Sending users through Moodle's dashboard gives the role-aware login hook a
 * predictable place to route students to the branded Home while leaving
 * mentors and admins in standard Moodle.
 *
 * @package    theme_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$dashboardurl = new moodle_url('/my/');

if (isloggedin() && !isguestuser()) {
    redirect($dashboardurl);
}

$SESSION->wantsurl = $dashboardurl->out(false);
redirect(new moodle_url('/login/index.php'));
