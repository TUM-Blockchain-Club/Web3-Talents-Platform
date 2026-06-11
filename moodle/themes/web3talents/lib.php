<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Web3 Talents theme callbacks.
 *
 * @package    theme_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/theme/boost/lib.php');

/**
 * Returns the main SCSS content for the Boost child theme.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_web3talents_get_main_scss_content($theme): string {
    global $CFG;

    $scss = theme_boost_get_main_scss_content($theme);
    $scss .= "\n";
    $scss .= file_get_contents($CFG->dirroot . '/theme/web3talents/scss/web3talents.scss');

    return $scss;
}
