<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Web3 Talents dashboard block version metadata.
 *
 * @package    block_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version = 2026061801;
$plugin->requires = 2026041000;
$plugin->component = 'block_web3talents';
$plugin->dependencies = [
    'local_web3talents' => ANY_VERSION,
];
