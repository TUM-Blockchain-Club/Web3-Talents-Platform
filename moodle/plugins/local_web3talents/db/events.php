<?php
defined('MOODLE_INTERNAL') || die();

$observers = [];
foreach (['role_assigned', 'role_unassigned', 'role_capabilities_updated',
        'user_enrolment_created', 'user_enrolment_updated', 'user_enrolment_deleted', 'user_updated'] as $eventname) {
    $observers[] = [
        'eventname' => '\\core\\event\\' . $eventname,
        'callback' => '\\local_web3talents\\local\\mentor_tools_access::access_changed',
        'internal' => false,
    ];
}
