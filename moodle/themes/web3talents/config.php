<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Web3 Talents Boost child theme configuration.
 *
 * @package    theme_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/lib.php');

$THEME->name = 'web3talents';
$THEME->sheets = [];
$THEME->editor_sheets = [];
// Dark styles for the inside of the editor iframe; without this the compiler
// falls back to theme/boost/scss/editor.scss and the editing surface is white.
$THEME->editor_scss = ['editor'];
$THEME->parents = ['boost'];
$THEME->enable_dock = false;
$THEME->usefallback = true;
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->iconsystem = \core\output\icon_system::FONTAWESOME;
$THEME->haseditswitch = true;
$THEME->usescourseindex = true;
$THEME->activityheaderconfig = [
    'notitle' => true,
];

$THEME->scss = function($theme) {
    return theme_web3talents_get_main_scss_content($theme);
};

// Re-theme Bootstrap/Boost variables to the dark Web3 Talents palette globally.
$THEME->prescsscallback = 'theme_web3talents_get_pre_scss';

$boostlayouts = [
    'base',
    'standard',
    'course',
    'coursecategory',
    'incourse',
    'frontpage',
    'admin',
    'mycourses',
    'mydashboard',
    'mypublic',
    'login',
    'popup',
    'frametop',
    'embedded',
    'maintenance',
    'print',
    'redirect',
    'report',
    'secure',
];

$THEME->layouts = [];
foreach ($boostlayouts as $layout) {
    $THEME->layouts[$layout] = [
        'theme' => 'boost',
        'file' => $layout === 'login' ? 'login.php' : 'drawers.php',
        'regions' => $layout === 'base' ? [] : ['side-pre'],
        'defaultregion' => 'side-pre',
    ];
}

$THEME->layouts['login']['regions'] = [];
$THEME->layouts['popup']['regions'] = [];
$THEME->layouts['embedded']['regions'] = [];
$THEME->layouts['maintenance']['regions'] = [];
$THEME->layouts['redirect']['regions'] = [];
$THEME->layouts['frontpage']['options'] = ['nonavbar' => true];
