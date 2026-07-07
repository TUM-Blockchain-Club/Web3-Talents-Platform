<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Install hook for block_web3talents.
 *
 * @package    block_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add the block to dashboards on install.
 */
function xmldb_block_web3talents_install(): void {
    global $CFG;

    require_once($CFG->dirroot . '/blocks/web3talents/lib.php');
    block_web3talents_ensure_dashboard_blocks();
}
