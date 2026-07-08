<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_web3talents\local;

use context_course;
use moodle_exception;
use stdClass;

/**
 * Attendance, participation, and mentor availability workflow service.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class participation_service {
    public const ATTENDANCE_PRESENT = 'present';
    public const ATTENDANCE_LATE = 'late';
    public const ATTENDANCE_ABSENT = 'absent';
    public const ATTENDANCE_EXCUSED = 'excused';

    public const AVAILABILITY_AVAILABLE = 'available';
    public const AVAILABILITY_TENTATIVE = 'tentative';
    public const AVAILABILITY_UNAVAILABLE = 'unavailable';

    /**
     * Supported attendance statuses.
     *
     * @return array
     */
    public static function attendance_statuses(): array {
        return [
            self::ATTENDANCE_PRESENT => get_string('attendance_status_present', 'local_web3talents'),
            self::ATTENDANCE_LATE => get_string('attendance_status_late', 'local_web3talents'),
            self::ATTENDANCE_ABSENT => get_string('attendance_status_absent', 'local_web3talents'),
            self::ATTENDANCE_EXCUSED => get_string('attendance_status_excused', 'local_web3talents'),
        ];
    }

    /**
     * Supported mentor availability states.
     *
     * @return array
     */
    public static function availability_statuses(): array {
        return [
            self::AVAILABILITY_AVAILABLE => get_string('availability_status_available', 'local_web3talents'),
            self::AVAILABILITY_TENTATIVE => get_string('availability_status_tentative', 'local_web3talents'),
            self::AVAILABILITY_UNAVAILABLE => get_string('availability_status_unavailable', 'local_web3talents'),
        ];
    }

    /**
     * Return configured course.
     *
     * @return stdClass
     */
    public static function get_configured_course(): stdClass {
        return course_state_service::get_configured_course();
    }

    /**
     * Create or update a live session by course/name.
     *
     * @param int $courseid Course id.
     * @param string $name Session name.
     * @param int $sessiondate Session timestamp.
     * @param string $notes Admin notes.
     * @param int $actorid User id.
     * @return stdClass
     */
    public static function upsert_session(int $courseid, string $name, int $sessiondate, string $notes, int $actorid): stdClass {
        global $DB;

        $name = trim($name);
        if ($name === '') {
            throw new moodle_exception('error_session_name_required', 'local_web3talents');
        }
        if ($sessiondate <= 0) {
            throw new moodle_exception('error_session_date_required', 'local_web3talents');
        }

        $now = time();
        $record = $DB->get_record('local_w3t_session', ['courseid' => $courseid, 'name' => $name]);
        $values = (object)[
            'courseid' => $courseid,
            'name' => $name,
            'sessiondate' => $sessiondate,
            'notes' => trim($notes),
            'createdby' => $actorid,
            'timemodified' => $now,
        ];

        if ($record) {
            $values->id = $record->id;
            $values->createdby = $record->createdby;
            $values->timecreated = $record->timecreated;
            $DB->update_record('local_w3t_session', $values);
            self::log_event('participation_session_updated', $actorid, $courseid, ['sessionid' => (int)$record->id]);
            return $DB->get_record('local_w3t_session', ['id' => $record->id], '*', MUST_EXIST);
        }

        $values->timecreated = $now;
        $id = $DB->insert_record('local_w3t_session', $values);
        self::log_event('participation_session_created', $actorid, $courseid, ['sessionid' => (int)$id]);
        return $DB->get_record('local_w3t_session', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Return sessions for a course.
     *
     * @param int $courseid Course id.
     * @return array
     */
    public static function get_sessions(int $courseid): array {
        global $DB;

        return $DB->get_records('local_w3t_session', ['courseid' => $courseid], 'sessiondate DESC, id DESC');
    }

    /**
     * Return one session.
     *
     * @param int $sessionid Session id.
     * @return stdClass
     */
    public static function get_session(int $sessionid): stdClass {
        global $DB;

        return $DB->get_record('local_w3t_session', ['id' => $sessionid], '*', MUST_EXIST);
    }

    /**
     * Save attendance and participation for a student.
     *
     * @param int $sessionid Session id.
     * @param int $userid Student user id.
     * @param string $status Attendance status.
     * @param int $participation Participation score, 0-5.
     * @param string $notes Notes.
     * @param int $actorid Marker user id.
     * @return stdClass
     */
    public static function save_attendance(int $sessionid, int $userid, string $status, int $participation, string $notes, int $actorid): stdClass {
        global $DB;

        if (!array_key_exists($status, self::attendance_statuses())) {
            throw new moodle_exception('error_invalid_attendance_status', 'local_web3talents');
        }
        $participation = max(0, min(5, $participation));
        $now = time();
        $record = $DB->get_record('local_w3t_attendance', ['sessionid' => $sessionid, 'userid' => $userid]);
        $values = (object)[
            'sessionid' => $sessionid,
            'userid' => $userid,
            'status' => $status,
            'participation' => $participation,
            'notes' => trim($notes),
            'markedby' => $actorid,
            'timemodified' => $now,
        ];

        if ($record) {
            $values->id = $record->id;
            $values->timecreated = $record->timecreated;
            $DB->update_record('local_w3t_attendance', $values);
            return $DB->get_record('local_w3t_attendance', ['id' => $record->id], '*', MUST_EXIST);
        }

        $values->timecreated = $now;
        $id = $DB->insert_record('local_w3t_attendance', $values);
        return $DB->get_record('local_w3t_attendance', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Save mentor availability for one session.
     *
     * @param int $sessionid Session id.
     * @param int $userid Mentor user id.
     * @param string $availability Availability status.
     * @param string $notes Notes.
     * @return stdClass
     */
    public static function save_availability(int $sessionid, int $userid, string $availability, string $notes): stdClass {
        global $DB;

        if (!array_key_exists($availability, self::availability_statuses())) {
            throw new moodle_exception('error_invalid_availability_status', 'local_web3talents');
        }

        $now = time();
        $record = $DB->get_record('local_w3t_mavail', ['sessionid' => $sessionid, 'userid' => $userid]);
        $values = (object)[
            'sessionid' => $sessionid,
            'userid' => $userid,
            'availability' => $availability,
            'notes' => trim($notes),
            'timemodified' => $now,
        ];

        if ($record) {
            $values->id = $record->id;
            $values->timecreated = $record->timecreated;
            $DB->update_record('local_w3t_mavail', $values);
            return $DB->get_record('local_w3t_mavail', ['id' => $record->id], '*', MUST_EXIST);
        }

        $values->timecreated = $now;
        $id = $DB->insert_record('local_w3t_mavail', $values);
        return $DB->get_record('local_w3t_mavail', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Return attendance records keyed by user id.
     *
     * @param int $sessionid Session id.
     * @return array
     */
    public static function get_attendance_by_user(int $sessionid): array {
        global $DB;

        $records = $DB->get_records('local_w3t_attendance', ['sessionid' => $sessionid]);
        $byuser = [];
        foreach ($records as $record) {
            $byuser[(int)$record->userid] = $record;
        }
        return $byuser;
    }

    /**
     * Return availability records keyed by user id.
     *
     * @param int $sessionid Session id.
     * @return array
     */
    public static function get_availability_by_user(int $sessionid): array {
        global $DB;

        $records = $DB->get_records('local_w3t_mavail', ['sessionid' => $sessionid]);
        $byuser = [];
        foreach ($records as $record) {
            $byuser[(int)$record->userid] = $record;
        }
        return $byuser;
    }

    /**
     * Return enrolled students.
     *
     * @param stdClass $course Course.
     * @return array
     */
    public static function get_students(stdClass $course): array {
        return course_state_service::get_enrolled_students($course);
    }

    /**
     * Return enrolled mentors.
     *
     * @param stdClass $course Course.
     * @return array
     */
    public static function get_mentors(stdClass $course): array {
        global $DB;

        $context = context_course::instance($course->id);
        $sql = "SELECT u.*
                  FROM {user} u
                  JOIN {role_assignments} ra ON ra.userid = u.id
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE ra.contextid = :contextid
                   AND r.shortname = :roleshortname
                   AND u.deleted = 0
              ORDER BY u.lastname, u.firstname, u.id";
        $users = $DB->get_records_sql($sql, [
            'contextid' => $context->id,
            'roleshortname' => 'editingteacher',
        ]);

        return array_filter($users, fn($user) => is_enrolled($context, $user, '', true));
    }

    /**
     * Write an operational log event.
     *
     * @param string $eventtype Event type.
     * @param int $userid Actor.
     * @param int $courseid Course id.
     * @param array $metadata Metadata.
     */
    private static function log_event(string $eventtype, int $userid, int $courseid, array $metadata): void {
        global $DB;

        $DB->insert_record('local_web3talents_log', [
            'eventtype' => $eventtype,
            'userid' => $userid,
            'courseid' => $courseid,
            'metadata' => json_encode($metadata),
            'timecreated' => time(),
        ]);
    }
}
