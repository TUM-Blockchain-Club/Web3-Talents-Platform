<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Public Web3 Talents "Community" page (Figma node 4567:7974).
 *
 * @package    theme_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Public marketing page — anonymous access is governed by the theme's
// `publicpages` setting rather than being an accident of never calling
// require_login() on a site running with $CFG->forcelogin.
theme_web3talents_guard_public_page();

$context = context_system::instance();
$url = new moodle_url('/theme/web3talents/community.php');

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('frontpage');
$PAGE->set_title('Community · Web3 Talents');
$PAGE->set_heading('Community');
$PAGE->add_body_class('web3t-page');
$PAGE->add_body_class('web3t-community-page');

$common = theme_web3talents_common_context($OUTPUT);
$img = function(string $name) use ($OUTPUT): string {
    return $OUTPUT->image_url('community/' . $name, 'theme_web3talents')->out(false);
};

$templatecontext = array_merge($common, [
    'galaxy' => $img('hero-galaxy'),
    'tumlogo' => $img('tum-logo'),
    'communityphoto' => $img('community-photo'),
    'herobody' => 'Web3Talents is a community where students learn, connect, and grow together. '
        . 'Members share ideas, support each other, and explore the world of Web3. Together with groups '
        . 'like the TUM Blockchain Club, we make blockchain learning fun and accessible.',

    'diversity' => [
        ['icon' => $img('icon-school'), 'title' => 'Students',
            'body' => 'Eager to specialize in blockchain and Web3 technologies'],
        ['icon' => $img('icon-briefcase'), 'title' => 'Industry Professionals',
            'body' => 'Eager to specialize in blockchain and Web3 technologies'],
        ['icon' => $img('icon-bulb'), 'title' => 'Interested Minds',
            'body' => 'Eager to specialize in blockchain and Web3 technologies'],
    ],

    'event' => [
        'date' => '25. Jun-2024 | 10.00 AM - 3.30 PM',
        'title' => 'Annual Alumni Meet-Up and QnA',
        'location' => '23 Blabla Street, Munich',
        'pin' => $img('events-pin'),
    ],

    'puzzle' => [
        ['pos' => 'lt', 'img' => $img('puzzle-lt'), 'title' => 'Club Conference',
            'body' => 'Join us at our annual club conference to connect with students, founders, and Web3 enthusiasts.'],
        ['pos' => 'rt', 'img' => $img('puzzle-rt'), 'title' => 'Q&A Sessions',
            'body' => 'Ask your questions and learn directly from industry experts during our interactive Q&A sessions.'],
        ['pos' => 'lb', 'img' => $img('puzzle-lb'), 'title' => 'Group Trips',
            'body' => 'Explore the blockchain ecosystem beyond campus through exciting group trips and company visits.'],
        ['pos' => 'rb', 'img' => $img('puzzle-rb'), 'title' => 'Hackathons',
            'body' => 'Build, innovate, and collaborate with fellow students at our hands-on Web3 hackathons.'],
    ],

    // NOTE: the live Figma FAQ bars have no answer text (collapsed). These
    // answers are drafts for the accordion and should be confirmed/replaced.
    'faqs' => [
        ['q' => 'Is the program free?', 'color' => '5c32f8',
            'a' => 'Yes — every Web3 Talents course is completely free, supported by our partners and the TUM Blockchain Club.'],
        ['q' => 'How can we apply to the courses?', 'color' => '4629fb',
            'a' => 'Applications open before each cohort. Head to the Courses page and hit Apply Now to register your interest.'],
        ['q' => 'How time consuming is each course program?', 'color' => '2b1eff',
            'a' => 'Expect a few hours a week across the 20-week cohort — a live session plus some group work and a short assignment.'],
        ['q' => "Can I still join the events even if I'm not a member?", 'color' => '2666ff',
            'a' => 'Absolutely — most community events are open to everyone. Just sign up and come along.'],
    ],
]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_web3talents/community', $templatecontext);
echo $OUTPUT->footer();
