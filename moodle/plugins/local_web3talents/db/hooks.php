<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\output\before_http_headers::class,
        'callback' => [\local_web3talents\course_hooks::class, 'initialise_index'],
    ],
    [
        'hook' => \core\hook\output\before_http_headers::class,
        'callback' => [\local_web3talents\dashboard_hooks::class, 'add_course_shortcut'],
    ],
    [
        'hook' => \core\hook\navigation\primary_extend::class,
        'callback' => [\local_web3talents\navigation_hooks::class, 'extend_primary'],
    ],
];
