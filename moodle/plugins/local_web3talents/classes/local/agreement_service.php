<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_web3talents\local;

use stdClass;

/**
 * First-login agreement workflow service.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class agreement_service {
    /**
     * Get the current policy version.
     *
     * @return string
     */
    public static function current_version(): string {
        return trim((string)(get_config('local_web3talents', 'policy_version') ?: '2026-07'));
    }

    /**
     * Get the current policy text.
     *
     * @return string
     */
    public static function current_text(): string {
        return trim((string)(get_config('local_web3talents', 'policy_text') ?: get_string('default_policy_text', 'local_web3talents')));
    }

    /**
     * Determine whether the user needs to accept the current agreement.
     *
     * @param int $userid User id.
     * @return bool
     */
    public static function requires_agreement(int $userid): bool {
        global $DB;

        $applicant = self::get_applicant_for_user($userid);
        if (!$applicant) {
            return false;
        }

        if (!in_array($applicant->status, [applicant_service::STATUS_ACCOUNT_CREATED, applicant_service::STATUS_ACCOUNT_ACTIVATED], true)) {
            return false;
        }

        return !$DB->record_exists('local_web3talents_agree', [
            'userid' => $userid,
            'policyversion' => self::current_version(),
        ]);
    }

    /**
     * Store an acceptance for the current policy version.
     *
     * @param int $userid User id.
     * @return stdClass Acceptance record.
     */
    public static function accept_current(int $userid): stdClass {
        global $DB;

        $version = self::current_version();
        $existing = $DB->get_record('local_web3talents_agree', [
            'userid' => $userid,
            'policyversion' => $version,
        ]);
        if ($existing) {
            return $existing;
        }

        $now = time();
        $record = (object)[
            'userid' => $userid,
            'policyversion' => $version,
            'agreedtime' => $now,
            'ipaddress' => getremoteaddr(),
            'useragent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ];
        $record->id = $DB->insert_record('local_web3talents_agree', $record);

        $applicant = self::get_applicant_for_user($userid);
        if ($applicant && $applicant->status === applicant_service::STATUS_ACCOUNT_CREATED) {
            $applicant->status = applicant_service::STATUS_ACCOUNT_ACTIVATED;
            $applicant->timemodified = $now;
            $DB->update_record('local_web3talents_app', $applicant);
        }

        $courseid = null;
        $course = $DB->get_record('course', ['shortname' => get_config('local_web3talents', 'fundamentals_course_shortname') ?: 'W3T-FUNDAMENTALS-DEV']);
        if ($course) {
            $courseid = (int)$course->id;
        }
        $DB->insert_record('local_web3talents_log', [
            'eventtype' => 'policy_agreed',
            'userid' => $userid,
            'courseid' => $courseid,
            'metadata' => json_encode(['policyversion' => $version]),
            'timecreated' => $now,
        ]);

        return $record;
    }

    /**
     * Get the current user's accepted-applicant record.
     *
     * @param int $userid User id.
     * @return stdClass|null
     */
    public static function get_applicant_for_user(int $userid): ?stdClass {
        global $DB;

        $record = $DB->get_record('local_web3talents_app', ['userid' => $userid]);
        return $record ?: null;
    }

    /**
     * Return current agreement for a user.
     *
     * @param int $userid User id.
     * @return stdClass|null
     */
    public static function get_current_acceptance(int $userid): ?stdClass {
        global $DB;

        $record = $DB->get_record('local_web3talents_agree', [
            'userid' => $userid,
            'policyversion' => self::current_version(),
        ]);
        return $record ?: null;
    }
}
