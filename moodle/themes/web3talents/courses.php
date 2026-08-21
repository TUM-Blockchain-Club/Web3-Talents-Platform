<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Public Web3 Talents "Courses" overview page (Figma node 4567:7910).
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
$url = new moodle_url('/theme/web3talents/courses.php');

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('frontpage');
$PAGE->set_title('Courses · Web3 Talents');
$PAGE->set_heading('Courses');
$PAGE->add_body_class('web3t-page');
$PAGE->add_body_class('web3t-courses-page');

$common = theme_web3talents_common_context($OUTPUT);

$templatecontext = array_merge($common, [
    'cards' => [
        [
            'modifier' => 'lilac', 'eyebrow' => 'BEGINNER · 20 WEEKS',
            'title' => 'Blockchain Fundamentals', 'sub' => 'Your first Web3 course',
            'date' => 'June, 13 - August, 30',
        ],
        [
            'modifier' => 'blue', 'eyebrow' => 'BEGINNER · 20 WEEKS',
            'title' => 'Web3 Applications', 'sub' => 'Your first Web3 course',
            'date' => 'June, 13 - August, 30',
        ],
        [
            'modifier' => 'cyan', 'eyebrow' => 'BEGINNER · 20 WEEKS',
            'title' => 'Blockchain and AI', 'sub' => 'Your first Web3 course',
            'date' => 'June, 13 - August, 30',
        ],
    ],
    // Step 1 (stands alone) then the Progressive Learning Cycle (steps 2 + 3).
    'step1' => [
        'num' => '1', 'variant' => 'foundation',
        'tag' => 'Foundation', 'label' => 'AT THE START',
        'icon' => $OUTPUT->image_url('courses/icon-lecture', 'theme_web3talents')->out(false),
        'heading' => 'Lecture + Assignment',
        'body' => 'Build your conceptual foundation through a lecture from expert speakers, then '
            . 'apply it with a hands-on assignment in one subtopic.',
    ],
    'cyclecaption' => "Research and teaching alternate, repeating until you've covered the whole topic.",
    'cyclesteps' => [
        [
            'num' => '2', 'variant' => 'research',
            'tag' => 'Research', 'label' => 'IN SPECIALIST GROUPS',
            'icon' => $OUTPUT->image_url('courses/icon-research', 'theme_web3talents')->out(false),
            'heading' => 'Processing in Specialist Groups',
            'body' => 'Meet peers in small specialist subgroups. Everyone researches the same subtopic '
                . 'from the lecture, becomes an expert on it, and prepares a presentation.',
        ],
        [
            'num' => '3', 'variant' => 'teach',
            'tag' => 'Teach', 'label' => 'PEER-TO-PEER, THEN A NEW LECTURE',
            'icon' => $OUTPUT->image_url('courses/icon-teach', 'theme_web3talents')->out(false),
            'heading' => 'Group Teaching + New Lecture',
            'list' => ['items' => [
                ['text' => 'Specialist groups teach their subtopics to each other — you present your '
                    . 'findings and learn theirs, piecing together the full picture through peer-to-peer teaching.'],
                ['text' => 'Then a new lecture from an expert speaker adds fresh input — kicking off the '
                    . 'next round of the cycle.'],
            ]],
        ],
    ],
    'loopbackicon' => $OUTPUT->image_url('courses/icon-loopback', 'theme_web3talents')->out(false),
    'calendaricon' => $OUTPUT->image_url('courses/icon-calendar', 'theme_web3talents')->out(false),
]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_web3talents/courses', $templatecontext);
echo $OUTPUT->footer();
