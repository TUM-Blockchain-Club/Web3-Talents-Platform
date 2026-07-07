<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Helper functions for block_web3talents.
 *
 * @package    block_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add the Web3 Talents block to the default dashboard and existing user dashboards.
 *
 * @return int Number of created block instances.
 */
function block_web3talents_ensure_dashboard_blocks(): int {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/my/lib.php');
    require_once($CFG->libdir . '/blocklib.php');

    $created = 0;
    $systempage = my_get_page(null, MY_PAGE_PRIVATE);
    if ($systempage) {
        $created += block_web3talents_ensure_dashboard_block($systempage, context_system::instance());
    }

    $pages = $DB->get_records('my_pages', [
        'private' => MY_PAGE_PRIVATE,
        'name' => MY_PAGE_DEFAULT,
    ]);
    foreach ($pages as $page) {
        if (empty($page->userid)) {
            continue;
        }
        if (!$DB->record_exists('user', ['id' => $page->userid, 'deleted' => 0])) {
            continue;
        }
        $created += block_web3talents_ensure_dashboard_block($page, context_user::instance($page->userid));
    }

    return $created;
}

/**
 * Add the block to a single dashboard page if missing.
 *
 * @param stdClass $page Dashboard page record.
 * @param context $context Parent context.
 * @return int One if created, zero if already present.
 */
function block_web3talents_ensure_dashboard_block(stdClass $page, context $context): int {
    global $DB;

    $params = [
        'blockname' => 'web3talents',
        'parentcontextid' => $context->id,
        'pagetypepattern' => 'my-index',
        'subpagepattern' => (string)$page->id,
    ];
    if ($DB->record_exists('block_instances', $params)) {
        return 0;
    }

    $weight = (int)$DB->get_field_sql(
        "SELECT COALESCE(MIN(defaultweight), 0) - 1
           FROM {block_instances}
          WHERE parentcontextid = :parentcontextid
            AND pagetypepattern = :pagetypepattern
            AND subpagepattern = :subpagepattern
            AND defaultregion = :defaultregion",
        [
            'parentcontextid' => $context->id,
            'pagetypepattern' => 'my-index',
            'subpagepattern' => (string)$page->id,
            'defaultregion' => 'content',
        ]
    );

    $now = time();
    $record = (object)[
        'blockname' => 'web3talents',
        'parentcontextid' => $context->id,
        'showinsubcontexts' => 0,
        'pagetypepattern' => 'my-index',
        'subpagepattern' => (string)$page->id,
        'defaultregion' => 'content',
        'defaultweight' => $weight,
        'configdata' => '',
        'timecreated' => $now,
        'timemodified' => $now,
    ];

    $id = $DB->insert_record('block_instances', $record);
    context_block::instance($id);

    return 1;
}
