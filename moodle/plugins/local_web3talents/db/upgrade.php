<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Upgrade code for local_web3talents.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade steps for local_web3talents.
 *
 * @param int $oldversion Installed plugin version.
 * @return bool
 */
function xmldb_local_web3talents_upgrade($oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026061200) {
        $table = new xmldb_table('local_web3talents_app');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('firstname', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('lastname', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('email', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_field('cohortid', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('status', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, 'accepted');
        $table->add_field('notes', XMLDB_TYPE_TEXT);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('source', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, 'manual');
        $table->add_field('retentionuntil', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('accountcreatedtime', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('activationemailsenttime', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('createdby', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('importedby', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('email', XMLDB_INDEX_UNIQUE, ['email']);
        $table->add_index('cohortid', XMLDB_INDEX_NOTUNIQUE, ['cohortid']);
        $table->add_index('status', XMLDB_INDEX_NOTUNIQUE, ['status']);
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026061200, 'local', 'web3talents');
    }

    if ($oldversion < 2026061300) {
        $table = new xmldb_table('local_web3talents_agree');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('policyversion', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL);
        $table->add_field('agreedtime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('ipaddress', XMLDB_TYPE_CHAR, '45');
        $table->add_field('useragent', XMLDB_TYPE_CHAR, '255');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('useridversion', XMLDB_INDEX_UNIQUE, ['userid', 'policyversion']);
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026061300, 'local', 'web3talents');
    }

    return true;
}
