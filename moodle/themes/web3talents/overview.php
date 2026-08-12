<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Public Web3 Talents overview (landing) page.
 *
 * High-fidelity implementation of the TUM Blockchain Club "Web3 Talents"
 * Figma home page. The dark visual system lives in scss/web3talents.scss and
 * is scoped to the `web3t-page` body class so the rest of Moodle keeps Boost.
 *
 * @package    theme_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$context = context_system::instance();
$url = new moodle_url('/theme/web3talents/overview.php');

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('frontpage');
$PAGE->set_title(get_string('overviewtitle', 'theme_web3talents'));
$PAGE->set_heading(get_string('overviewtitle', 'theme_web3talents'));
$PAGE->add_body_class('web3t-page');
$PAGE->add_body_class('web3t-overview-page');

$common = theme_web3talents_common_context($OUTPUT);
$loginurl = $common['loginurl'];
$img = function(string $name): string {
    global $OUTPUT;
    return $OUTPUT->image_url('home/' . $name, 'theme_web3talents')->out(false);
};

$templatecontext = array_merge($common, [
    'overviewurl' => $url->out(false),

    // Program carousel: two stacked course cards, the arrow swaps them
    // (mirrors the Figma "2 Cards" component: Fundamentals front, Web3 behind).
    'courses' => [
        [
            'title' => 'Blockchain Fundamentals',
            'body' => 'Learn the foundations of blockchain technology and build the skills to '
                . 'understand, evaluate, and work with decentralized systems.',
            'front' => true,
            'info' => [
                ['label' => 'DURATION', 'value' => '20 WEEKS'],
                ['label' => 'FORMAT', 'value' => 'PEER-LEED · LIVE'],
                ['label' => 'START DATE', 'value' => 'Jan 2027'],
                ['label' => 'COST', 'value' => 'FREE'],
            ],
        ],
        [
            'title' => 'Web3 Applications',
            'body' => 'Explore how decentralized technologies are used to build products, '
                . 'communities, and real-world solutions.',
            'front' => false,
            'info' => [
                ['label' => 'DURATION', 'value' => '20 WEEKS'],
                ['label' => 'FORMAT', 'value' => 'PEER-LEED · LIVE'],
                ['label' => 'START DATE', 'value' => 'JULY 2026'],
                ['label' => 'COST', 'value' => 'FREE'],
            ],
        ],
    ],

    'steps' => [
        ['num' => '1', 'title' => 'Expert Input',
            'bodyhtml' => 'An <strong>industry expert</strong> introduces the topic through a live lecture.'],
        ['num' => '2', 'title' => 'Become a Specialist',
            'bodyhtml' => 'In small <strong>peer groups</strong>, you research, discuss and prepare a presentation on a subtopic.'],
        ['num' => '3', 'title' => 'Teach your Peers',
            'bodyhtml' => 'You <strong>present your findings</strong> to other groups and they teach you theirs.'],
        ['num' => '4', 'title' => 'Expert Validation',
            'bodyhtml' => 'A <strong>second expert lecture</strong> connects all the pieces and deepens your understanding.'],
    ],

    'speakers' => [
        ['photo' => $img('speaker-1'), 'name' => 'Dr. David An', 'role' => 'Partner',
            'org' => '@Dracoon Ventures', 'topic' => 'Topic: "Proof of Work, Mining, and Immutability"'],
        ['photo' => $img('speaker-2'), 'name' => 'Jonas Gebele', 'role' => 'Research Associate',
            'org' => '@Technical University of Munich', 'topic' => 'Topic: "Cryptography and Hashing"'],
        ['photo' => $img('speaker-3'), 'name' => 'David Kurz', 'role' => 'Business Development',
            'org' => '@Bitvavo', 'topic' => 'Topic: "Ethereum: The World Computer (Architecture)"'],
    ],

    // NOTE: the live Figma value cards contain only a title + line-art icon in
    // every variant (no body copy). These descriptions are drafts for the
    // hover-reveal and should be confirmed/replaced with real copy.
    'valuecards' => [
        ['title' => 'Certification', 'modifier' => 'certification',
            'desc' => 'Earn a recognised certificate on completion to showcase your Web3 skills.'],
        ['title' => 'Top Tier Speakers', 'modifier' => 'speakers',
            'desc' => 'Learn directly from industry experts and founders shaping the Web3 ecosystem.'],
        ['title' => 'Authentic Learning', 'modifier' => 'authentic',
            'desc' => 'Hands-on, peer-led sessions built around real understanding, not memorisation.'],
        ['title' => 'Fast & Entrepreneurial', 'modifier' => 'entrepreneurial',
            'desc' => 'Move quickly from fundamentals to building and shipping your own ideas.'],
    ],

    'testimonials' => [
        ['avatar' => $img('testimonial-avatar-1'),
            'quote' => 'I joined the course with almost no prior knowledge of Web3, but the structure made it '
                . 'easy to follow. The sessions were beginner-friendly, and the community helped me feel more '
                . 'confident asking questions and exploring the topic further.',
            'attribution' => 'Course Participant, Cohort 1'],
        ['avatar' => $img('testimonial-avatar-2'),
            'quote' => 'The group research format was useful because it made me go deeper into one topic instead '
                . 'of only listening passively. Presenting it to others also helped me understand where I still had gaps.',
            'attribution' => 'Course Participant, Cohort 2'],
        ['avatar' => $img('testimonial-avatar-3'),
            'quote' => 'The group research format was useful because it made me go deeper into one topic instead '
                . 'of only listening passively. Presenting it to others also helped me understand where I still had gaps.',
            'attribution' => 'Course Participant, Cohort 2'],
        ['avatar' => $img('testimonial-avatar-4'),
            'quote' => 'I joined the course with almost no prior knowledge of Web3, but the structure made it '
                . 'easy to follow. The sessions were beginner-friendly, and the community helped me feel more '
                . 'confident asking questions and exploring the topic further.',
            'attribution' => 'Course Participant, Cohort 1'],
        ['avatar' => $img('testimonial-avatar-5'),
            'quote' => 'I joined the course with almost no prior knowledge of Web3, but the structure made it '
                . 'easy to follow. The sessions were beginner-friendly, and the community helped me feel more '
                . 'confident asking questions and exploring the topic further.',
            'attribution' => 'Course Participant, Cohort 1'],
    ],
]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_web3talents/overview', $templatecontext);
echo $OUTPUT->footer();
