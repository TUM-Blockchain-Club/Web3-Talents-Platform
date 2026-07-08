<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_web3talents\local;

use moodle_exception;
use stdClass;

/**
 * Presentation-week mentor room grading workflow.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mentor_grading_service {
    public const MIN_GRADE = 0;
    public const MAX_GRADE = 7;

    /**
     * Get room mentor assignments keyed by room id.
     *
     * @param int $sessionid Session id.
     * @param int $resultid Room result id.
     * @return array
     */
    public static function get_assignments_by_room(int $sessionid, int $resultid): array {
        global $DB;

        $records = $DB->get_records('local_w3t_room_mentor', [
            'sessionid' => $sessionid,
            'resultid' => $resultid,
        ]);
        $byroom = [];
        foreach ($records as $record) {
            $byroom[(int)$record->roomid] = $record;
        }
        return $byroom;
    }

    /**
     * Get grade records keyed by student user id.
     *
     * @param int $sessionid Session id.
     * @param int $resultid Room result id.
     * @return array
     */
    public static function get_grades_by_user(int $sessionid, int $resultid): array {
        global $DB;

        $records = $DB->get_records('local_w3t_room_grade', [
            'sessionid' => $sessionid,
            'resultid' => $resultid,
        ]);
        $byuser = [];
        foreach ($records as $record) {
            $byuser[(int)$record->userid] = $record;
        }
        return $byuser;
    }

    /**
     * Auto-assign available mentors to currently unassigned rooms.
     *
     * Available mentors are used once. Extra available mentors are left unassigned.
     *
     * @param int $sessionid Session id.
     * @param int $resultid Room result id.
     * @param int $actorid Actor id.
     * @return array Counts.
     */
    public static function auto_assign_available_mentors(int $sessionid, int $resultid, int $actorid): array {
        global $DB;

        $state = room_assignment_service::get_result_state($resultid);
        $course = $DB->get_record('course', ['id' => $state['result']->courseid], '*', MUST_EXIST);
        $availablementors = self::get_available_mentors($course, $sessionid);
        $assignments = self::get_assignments_by_room($sessionid, $resultid);
        $assignedmentorids = [];
        foreach ($assignments as $assignment) {
            $assignedmentorids[(int)$assignment->mentorid] = true;
        }

        $created = 0;
        foreach ($state['rooms'] as $roomstate) {
            $roomid = (int)$roomstate['room']->id;
            if (isset($assignments[$roomid])) {
                continue;
            }

            $mentor = null;
            foreach ($availablementors as $candidate) {
                if (empty($assignedmentorids[(int)$candidate->id])) {
                    $mentor = $candidate;
                    break;
                }
            }
            if (!$mentor) {
                break;
            }

            self::assign_mentor($sessionid, $resultid, $roomid, (int)$mentor->id, $actorid);
            $assignedmentorids[(int)$mentor->id] = true;
            $created++;
        }

        self::log_event('room_mentors_auto_assigned', $actorid, (int)$state['result']->courseid, [
            'sessionid' => $sessionid,
            'resultid' => $resultid,
            'created' => $created,
        ]);

        return [
            'created' => $created,
            'availablementors' => count($availablementors),
            'roomcount' => count($state['rooms']),
        ];
    }

    /**
     * Assign one official mentor to a room.
     *
     * @param int $sessionid Session id.
     * @param int $resultid Room result id.
     * @param int $roomid Room id.
     * @param int $mentorid Mentor user id, or zero to clear room assignment.
     * @param int $actorid Actor id.
     */
    public static function assign_mentor(int $sessionid, int $resultid, int $roomid, int $mentorid, int $actorid): void {
        global $DB;

        $state = room_assignment_service::get_result_state($resultid);
        if (!self::state_has_room($state, $roomid)) {
            throw new moodle_exception('error_room_missing', 'local_web3talents');
        }

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('local_w3t_room_mentor', [
            'sessionid' => $sessionid,
            'resultid' => $resultid,
            'roomid' => $roomid,
        ]);

        if ($mentorid > 0) {
            $course = $DB->get_record('course', ['id' => $state['result']->courseid], '*', MUST_EXIST);
            $mentorids = array_map('intval', array_keys(participation_service::get_mentors($course)));
            if (!in_array($mentorid, $mentorids, true)) {
                throw new moodle_exception('error_invalid_room_mentor', 'local_web3talents');
            }

            $DB->delete_records('local_w3t_room_mentor', [
                'sessionid' => $sessionid,
                'resultid' => $resultid,
                'mentorid' => $mentorid,
            ]);
            $now = time();
            $DB->insert_record('local_w3t_room_mentor', [
                'sessionid' => $sessionid,
                'resultid' => $resultid,
                'roomid' => $roomid,
                'mentorid' => $mentorid,
                'assignedby' => $actorid,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
        $transaction->allow_commit();

        self::log_event('room_mentor_assigned', $actorid, (int)$state['result']->courseid, [
            'sessionid' => $sessionid,
            'resultid' => $resultid,
            'roomid' => $roomid,
            'mentorid' => $mentorid,
        ]);
    }

    /**
     * Save or clear one student presentation grade.
     *
     * @param int $sessionid Session id.
     * @param int $resultid Room result id.
     * @param int $roomid Room id.
     * @param int $userid Student user id.
     * @param int|null $grade Grade 0-7, or null to clear.
     * @param string $notes Notes.
     * @param int $gradedby Actor id.
     */
    public static function save_grade(
        int $sessionid,
        int $resultid,
        int $roomid,
        int $userid,
        ?int $grade,
        string $notes,
        int $gradedby
    ): void {
        global $DB;

        $state = room_assignment_service::get_result_state($resultid);
        if (!self::room_has_student($state, $roomid, $userid)) {
            throw new moodle_exception('error_grade_student_not_in_room', 'local_web3talents');
        }

        $existing = $DB->get_record('local_w3t_room_grade', [
            'sessionid' => $sessionid,
            'resultid' => $resultid,
            'userid' => $userid,
        ]);

        if ($grade === null) {
            if ($existing) {
                $DB->delete_records('local_w3t_room_grade', ['id' => $existing->id]);
            }
            return;
        }

        if ($grade < self::MIN_GRADE || $grade > self::MAX_GRADE) {
            throw new moodle_exception('error_invalid_room_grade', 'local_web3talents');
        }

        $now = time();
        $record = (object)[
            'sessionid' => $sessionid,
            'resultid' => $resultid,
            'roomid' => $roomid,
            'userid' => $userid,
            'grade' => $grade,
            'notes' => trim($notes),
            'gradedby' => $gradedby,
            'timemodified' => $now,
        ];

        if ($existing) {
            $record->id = $existing->id;
            $record->timecreated = $existing->timecreated;
            $DB->update_record('local_w3t_room_grade', $record);
            return;
        }

        $record->timecreated = $now;
        $DB->insert_record('local_w3t_room_grade', $record);
    }

    /**
     * Return rooms assigned to one mentor.
     *
     * @param array $state Room result state.
     * @param array $assignments Assignments keyed by room id.
     * @param int $mentorid Mentor id.
     * @return array
     */
    public static function rooms_for_mentor(array $state, array $assignments, int $mentorid): array {
        return array_values(array_filter($state['rooms'], function(array $roomstate) use ($assignments, $mentorid): bool {
            $assignment = $assignments[(int)$roomstate['room']->id] ?? null;
            return $assignment && (int)$assignment->mentorid === $mentorid;
        }));
    }

    /**
     * Return available enrolled mentors for a session.
     *
     * @param stdClass $course Course.
     * @param int $sessionid Session id.
     * @return array
     */
    public static function get_available_mentors(stdClass $course, int $sessionid): array {
        $mentors = participation_service::get_mentors($course);
        $availability = participation_service::get_availability_by_user($sessionid);

        return array_values(array_filter($mentors, function(stdClass $mentor) use ($availability): bool {
            $record = $availability[(int)$mentor->id] ?? null;
            return $record && $record->availability === participation_service::AVAILABILITY_AVAILABLE;
        }));
    }

    /**
     * Return whether a room belongs to a result state.
     *
     * @param array $state Room result state.
     * @param int $roomid Room id.
     * @return bool
     */
    private static function state_has_room(array $state, int $roomid): bool {
        foreach ($state['rooms'] as $roomstate) {
            if ((int)$roomstate['room']->id === $roomid) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return whether a student is in a room.
     *
     * @param array $state Room result state.
     * @param int $roomid Room id.
     * @param int $userid Student id.
     * @return bool
     */
    private static function room_has_student(array $state, int $roomid, int $userid): bool {
        foreach ($state['rooms'] as $roomstate) {
            if ((int)$roomstate['room']->id !== $roomid) {
                continue;
            }
            foreach ($roomstate['assignments'] as $assignment) {
                foreach ($assignment['members'] as $member) {
                    if ((int)$member->id === $userid) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Write an operational event.
     *
     * @param string $eventtype Event type.
     * @param int $userid Actor id.
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
