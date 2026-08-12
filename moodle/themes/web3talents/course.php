<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Public Web3 Talents course detail page (Blockchain Fundamentals).
 *
 * High-fidelity implementation of the TUM Blockchain Club "Course Page"
 * Figma frame (4567:6935, 1440x3573). Page styles live in
 * scss/pages/course.scss scoped to `.web3t-course`.
 *
 * @package    theme_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$context = context_system::instance();
$url = new moodle_url('/theme/web3talents/course.php');

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('frontpage');
$PAGE->set_title('Blockchain Fundamentals');
$PAGE->set_heading('Blockchain Fundamentals');
$PAGE->add_body_class('web3t-page');
$PAGE->add_body_class('web3t-course-page');

$common = theme_web3talents_common_context($OUTPUT);
$img = function(string $path): string {
    global $OUTPUT;
    return $OUTPUT->image_url($path, 'theme_web3talents')->out(false);
};

$templatecontext = array_merge($common, [
    'courseurl' => $url->out(false),

    'hero' => [
        'dates' => 'January 15th - March 26th',
        'badge' => '17 days to apply',
        'description' => 'Build a strong foundation in blockchain, smart contracts, and Web3 '
            . 'through a structured 10-week learning experience.',
    ],

    'tags' => [
        ['label' => 'Smart Contracts', 'bright' => true],
        ['label' => 'Bitcoin Transactions', 'bright' => true],
        ['label' => 'Decentralization & Trust', 'bright' => false],
        ['label' => 'Crypto Wallets', 'bright' => false],
        ['label' => 'Cryptography Basics', 'bright' => false],
        ['label' => 'Ethereum Architecture', 'bright' => false],
    ],

    // NOTE: the live Figma phases/description/FAQ are collapsed with no body
    // copy. The description, phase bodies and FAQ answers below are drafts for
    // the tabs/accordion and should be confirmed/replaced with real copy.
    'coursedescription' => 'This 10-week cohort takes you from the fundamentals of cryptography and '
        . 'consensus through to smart contracts and the wider Web3 economy. Each phase pairs an '
        . 'expert-led lecture with hands-on group work, so you learn by building — not just watching.',

    'phases' => [
        ['num' => 'Phase 1', 'title' => 'Cryptography / Keys & Hashing', 'arrow' => $img('course/icon-arrow-down-1'), 'big' => false,
            'body' => 'Hashing, keys and digital signatures — the cryptographic primitives that make blockchains trustworthy.'],
        ['num' => 'Phase 2', 'title' => 'The Ledger Architecture', 'arrow' => $img('course/icon-arrow-down-2'), 'big' => false,
            'body' => 'How blocks, chains and consensus keep a distributed ledger consistent without a central authority.'],
        ['num' => 'Phase 3', 'title' => 'Securing the State', 'arrow' => $img('course/icon-arrow-down-3'), 'big' => false,
            'body' => 'Wallets, transactions and the security practices that protect on-chain state and user funds.'],
        ['num' => 'Phase 4', 'title' => 'The Programmable Layer', 'arrow' => $img('course/icon-arrow-down-1'), 'big' => false,
            'body' => 'Smart contracts and the programmable layer — how applications run logic directly on-chain.'],
        ['num' => 'Phase 5', 'title' => 'The New Economy & Future Outlook', 'arrow' => $img('course/icon-arrow-down-4'), 'big' => true,
            'body' => 'Tokens, DeFi and where the ecosystem is heading — from real-world assets to decentralised identity.'],
    ],

    'faqs' => [
        ['q' => 'Do I need prior experience?',
            'a' => 'No — the course starts from first principles and is designed for complete beginners.'],
        ['q' => 'How much time per week should I expect?',
            'a' => 'Around 3–4 hours: one live session plus some group work and a short assignment.'],
        ['q' => 'Is the course really free?',
            'a' => 'Yes — every cohort is free, thanks to the TUM Blockchain Club and our partners.'],
    ],

    'speakers' => [
        ['photo' => $img('course/speaker-david-an'), 'name' => 'Dr. David An',
            'role' => 'Partner', 'org' => '@Dragon Ventures',
            'topic' => 'Topic: "Blockchain Fundamentals"'],
        ['photo' => $img('home/speaker-2'), 'name' => 'Jonas Gebele',
            'role' => 'Research Associate', 'org' => '@Technical University of Munich',
            'topic' => 'Topic: "Cryptography & Hashing"'],
        ['photo' => $img('course/speaker-placeholder'), 'name' => 'Andi Schmitt',
            'role' => 'Co-founder', 'org' => '@LightUpKryptos',
            'topic' => 'Topic: "Bitcoin Data Structure and Transactions"'],
        ['photo' => $img('course/speaker-placeholder'), 'name' => 'Profesor Dr. Philip Maume',
            'role' => 'Professor of Law', 'org' => '@Technical University of Munich',
            'topic' => 'Topic: "The Financial Layer: Stablecoins, RWA"'],
        ['photo' => $img('course/speaker-placeholder'), 'name' => 'Dr. Christian Ziegler',
            'role' => 'CTO', 'org' => '@Stealth Startup',
            'topic' => 'Topic: "Future Outlook: Beyond Finance DePIN Identity & DAO"'],
        ['photo' => $img('home/speaker-3'), 'name' => 'David Kurz',
            'role' => 'Business Development', 'org' => '@Bitvavo',
            'topic' => 'Topic: "Ethereum: The World Computer (Architecture)"'],
    ],
]);

// Testimonial quotes (verbatim from the Figma frame).
$quotea = 'I joined the course with almost no prior knowledge of Web3, but the structure made it '
    . 'easy to follow. The sessions were beginner-friendly, and the community helped me feel more '
    . 'confident asking questions and exploring the topic further.';

$templatecontext['studentcolumns'] = [
    ['cards' => [
        ['avatar' => $img('course/testimonial-avatar-1'), 'hasavatar' => true, 'accent' => 'blue',
            'quote' => $quotea, 'attribution' => 'Course Participant, Cohort 1'],
        ['avatar' => $img('course/testimonial-avatar-2'), 'hasavatar' => true, 'accent' => 'purple',
            'quote' => 'It was a good starting point if you\'re curious about blockchain but don\'t know '
                . 'where to begin. Some topics were challenging, but the structure made them manageable. '
                . 'Before joining, I had heard about Bitcoin, Ethereum, and DeFi, but I didn\'t really '
                . 'understand how they connected. The course helped me build a clearer mental map of the '
                . 'Web3 ecosystem.',
            'attribution' => 'Course Participant, Cohort 2'],
        ['avatar' => $img('course/testimonial-avatar-3'), 'hasavatar' => true, 'accent' => 'blue',
            'quote' => $quotea, 'attribution' => 'Course Participant, Cohort 1'],
        ['avatar' => $img('course/testimonial-avatar-6'), 'hasavatar' => true, 'accent' => 'violet',
            'quote' => $quotea, 'attribution' => 'Course Participant, Cohort 1'],
    ]],
    ['cards' => [
        ['hasavatar' => false, 'accent' => 'blue',
            'quote' => 'I liked that the course didn\'t assume everyone already knew the terminology. '
                . 'It started with the basics and then slowly connected the topics, which made the more '
                . 'technical parts easier to understand.',
            'attribution' => 'Course Participant, Cohort 1'],
        ['avatar' => $img('course/testimonial-avatar-4'), 'hasavatar' => true, 'accent' => 'purple',
            'quote' => $quotea, 'attribution' => 'Course Participant, Cohort 1'],
        ['avatar' => $img('course/testimonial-avatar-5'), 'hasavatar' => true, 'accent' => 'violet',
            'quote' => 'The group research format was useful because it made me go deeper into one topic '
                . 'instead of only listening passively. Presenting it to others also helped me understand '
                . 'where I still had gaps.',
            'attribution' => 'Course Participant, Cohort 3'],
        ['avatar' => $img('course/testimonial-avatar-1'), 'hasavatar' => true, 'accent' => 'blue',
            'quote' => $quotea, 'attribution' => 'Course Participant, Cohort 1'],
    ]],
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_web3talents/course', $templatecontext);
echo $OUTPUT->footer();
