<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_web3talents\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;

/**
 * Privacy metadata provider for local_web3talents.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements metadata_provider {
    /**
     * Describes metadata stored by the plugin scaffold.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_web3talents_log', [
            'userid' => 'privacy:metadata:local_web3talents_log:userid',
            'courseid' => 'privacy:metadata:local_web3talents_log:courseid',
            'eventtype' => 'privacy:metadata:local_web3talents_log:eventtype',
            'metadata' => 'privacy:metadata:local_web3talents_log:metadata',
            'timecreated' => 'privacy:metadata:local_web3talents_log:timecreated',
        ], 'privacy:metadata:local_web3talents_log');

        $collection->add_database_table('local_web3talents_app', [
            'firstname' => 'privacy:metadata:local_web3talents_app:firstname',
            'lastname' => 'privacy:metadata:local_web3talents_app:lastname',
            'email' => 'privacy:metadata:local_web3talents_app:email',
            'cohortid' => 'privacy:metadata:local_web3talents_app:cohortid',
            'status' => 'privacy:metadata:local_web3talents_app:status',
            'notes' => 'privacy:metadata:local_web3talents_app:notes',
            'userid' => 'privacy:metadata:local_web3talents_app:userid',
        ], 'privacy:metadata:local_web3talents_app');

        $collection->add_database_table('local_web3talents_agree', [
            'userid' => 'privacy:metadata:local_web3talents_agree:userid',
            'policyversion' => 'privacy:metadata:local_web3talents_agree:policyversion',
            'agreedtime' => 'privacy:metadata:local_web3talents_agree:agreedtime',
            'ipaddress' => 'privacy:metadata:local_web3talents_agree:ipaddress',
            'useragent' => 'privacy:metadata:local_web3talents_agree:useragent',
        ], 'privacy:metadata:local_web3talents_agree');

        $collection->add_database_table('local_w3t_pmember', [
            'userid' => 'privacy:metadata:local_w3t_pmember:userid',
            'pgroupid' => 'privacy:metadata:local_w3t_pmember:pgroupid',
        ], 'privacy:metadata:local_w3t_pmember');

        $collection->add_database_table('local_w3t_choice', [
            'selectedby' => 'privacy:metadata:local_w3t_choice:selectedby',
            'pgroupid' => 'privacy:metadata:local_w3t_choice:pgroupid',
            'topicid' => 'privacy:metadata:local_w3t_choice:topicid',
        ], 'privacy:metadata:local_w3t_choice');

        return $collection;
    }
}
