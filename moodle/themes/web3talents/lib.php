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

    $base = $CFG->dirroot . '/theme/web3talents/scss';
    $scss = theme_boost_get_main_scss_content($theme);
    $scss .= "\n";

    // Shared design system (tokens, fonts, buttons, nav, footer, base layout).
    $scss .= file_get_contents($base . '/web3talents.scss') . "\n";

    // Per-page styles. Each landing surface ships its own partial under scss/pages/.
    foreach (glob($base . '/pages/*.scss') as $page) {
        $scss .= file_get_contents($page) . "\n";
    }

    return $scss;
}

/**
 * Shared template context for every Web3 Talents public page (header + footer).
 *
 * @param renderer_base $output The page output renderer.
 * @return array
 */
function theme_web3talents_common_context($output): array {
    $loginurl = (new moodle_url('/login/index.php'))->out(false);
    $img = function(string $name) use ($output): string {
        return $output->image_url('home/' . $name, 'theme_web3talents')->out(false);
    };
    $page = function(string $file): string {
        return (new moodle_url('/theme/web3talents/' . $file))->out(false);
    };

    return [
        'loginurl' => $loginurl,
        'homeurl' => $page('overview.php'),
        'coursesurl' => $page('courses.php'),
        'courseurl' => $page('course.php'),
        'communityurl' => $page('community.php'),
        'dashboardurl' => $page('dashboard.php'),
        'logourl' => $img('logo'),
        'linkedinurl' => $img('social-linkedin'),
        'tumurl' => 'https://www.tum-blockchain.com',
        'nav' => [
            ['label' => 'Courses', 'url' => $page('courses.php')],
            ['label' => 'Community', 'url' => $page('community.php')],
            ['label' => 'Dashboard', 'url' => $page('dashboard.php')],
        ],
        'footernav1' => [
            ['label' => 'Home', 'url' => $page('overview.php')],
            ['label' => 'Courses', 'url' => $page('courses.php')],
            ['label' => 'Community', 'url' => $page('community.php')],
            ['label' => 'Speakers', 'url' => $page('overview.php') . '#speakers'],
        ],
        'footernav2' => [
            ['label' => 'About Us', 'url' => '#'],
            ['label' => 'TUM Blockchain Club', 'url' => 'https://www.tum-blockchain.com'],
            ['label' => 'FAQ', 'url' => '#'],
        ],
    ];
}
