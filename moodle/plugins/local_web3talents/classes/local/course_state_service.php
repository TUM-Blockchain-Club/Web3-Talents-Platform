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
 * Reads Moodle-native source data used by Web3 Talents room generation.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_state_service {
    public const CHOICE_IDNUMBER = 'w3t_topic_choice';
    public const MIN_PARTNER_GROUP_SIZE = 2;
    public const MAX_PARTNER_GROUP_SIZE = 6;

    /**
     * First-release topic options used by the room-generation workflow.
     *
     * @return array
     */
    public static function launch_topics(): array {
        return [
            'Blockchain Foundations',
            'Wallets And Transactions',
            'Smart Contracts',
            'Applications And Protocols',
        ];
    }

    /**
     * Return the configured fundamentals course.
     *
     * @return stdClass
     */
    public static function get_configured_course(): stdClass {
        global $DB;

        $shortname = get_config('local_web3talents', 'fundamentals_course_shortname') ?: 'W3T-FUNDAMENTALS-DEV';
        $course = $DB->get_record('course', ['shortname' => $shortname]);
        if (!$course) {
            throw new moodle_exception('error_course_missing', 'local_web3talents');
        }
        return $course;
    }

    /**
     * Read all state needed to validate Moodle groups and Choice responses.
     *
     * @return array
     */
    public static function get_state(): array {
        $course = self::get_configured_course();
        $students = self::get_enrolled_students($course);
        $groups = self::get_groups($course, $students);
        $choice = self::get_choice($course);
        $responses = $choice ? self::get_choice_responses($choice->choice) : [];
        $studentrows = self::build_student_rows($students, $groups, $responses);
        $groupwarnings = self::build_group_warnings($groups, $responses);

        return [
            'course' => $course,
            'students' => $students,
            'groups' => $groups,
            'choice' => $choice,
            'responses' => $responses,
            'studentrows' => $studentrows,
            'groupwarnings' => $groupwarnings,
            'summary' => [
                'studentcount' => count($students),
                'groupcount' => count($groups),
                'responsecount' => count($responses),
                'studentwarningcount' => count(array_filter($studentrows, fn($row) => !empty($row['warnings']))),
                'groupwarningcount' => count($groupwarnings),
            ],
        ];
    }

    /**
     * Read active enrolled student-role users.
     *
     * @param stdClass $course
     * @return array
     */
    public static function get_enrolled_students(stdClass $course): array {
        global $DB;

        $context = context_course::instance($course->id);
        $sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.username
                  FROM {user} u
                  JOIN {role_assignments} ra ON ra.userid = u.id
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE ra.contextid = :contextid
                   AND r.shortname = :roleshortname
                   AND u.deleted = 0
              ORDER BY u.lastname, u.firstname, u.id";
        $users = $DB->get_records_sql($sql, [
            'contextid' => $context->id,
            'roleshortname' => 'student',
        ]);

        return array_filter($users, fn($user) => is_enrolled($context, $user, '', true));
    }

    /**
     * Read Moodle groups and enrolled student members.
     *
     * @param stdClass $course
     * @param array $students
     * @return array
     */
    public static function get_groups(stdClass $course, array $students): array {
        global $CFG;

        require_once($CFG->libdir . '/grouplib.php');

        $studentids = array_map('intval', array_keys($students));
        $studentlookup = array_fill_keys($studentids, true);
        $groups = groups_get_all_groups($course->id, 0, 0, 'g.id, g.courseid, g.name, g.idnumber') ?: [];

        foreach ($groups as $group) {
            $members = groups_get_members($group->id, 'u.id, u.firstname, u.lastname, u.email, u.username') ?: [];
            $group->studentmembers = array_filter($members, fn($member) => isset($studentlookup[(int)$member->id]));
        }

        uasort($groups, fn($a, $b) => strnatcasecmp($a->name, $b->name));
        return $groups;
    }

    /**
     * Read the configured Choice activity.
     *
     * @param stdClass $course
     * @return stdClass|null
     */
    public static function get_choice(stdClass $course): ?stdClass {
        global $DB;

        $sql = "SELECT cm.*, m.name AS modulename
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.course = :courseid
                   AND cm.idnumber = :idnumber
                   AND cm.deletioninprogress = 0";
        $cm = $DB->get_record_sql($sql, [
            'courseid' => $course->id,
            'idnumber' => self::CHOICE_IDNUMBER,
        ]);
        if (!$cm || $cm->modulename !== 'choice') {
            return null;
        }

        return (object)[
            'cm' => $cm,
            'choice' => $DB->get_record('choice', ['id' => $cm->instance], '*', MUST_EXIST),
            'options' => $DB->get_records('choice_options', ['choiceid' => $cm->instance], 'id', '*'),
        ];
    }

    /**
     * Read Choice answers by user id.
     *
     * @param stdClass $choice
     * @return array
     */
    public static function get_choice_responses(stdClass $choice): array {
        global $DB;

        $sql = "SELECT ca.id, ca.userid, ca.optionid, ca.timemodified, co.text
                  FROM {choice_answers} ca
                  JOIN {choice_options} co ON co.id = ca.optionid
                 WHERE ca.choiceid = :choiceid
              ORDER BY ca.userid, co.text";
        $records = $DB->get_records_sql($sql, ['choiceid' => $choice->id]);

        $responses = [];
        foreach ($records as $record) {
            $responses[(int)$record->userid][] = $record;
        }
        return $responses;
    }

    /**
     * Build per-student source-data rows.
     *
     * @param array $students
     * @param array $groups
     * @param array $responses
     * @return array
     */
    private static function build_student_rows(array $students, array $groups, array $responses): array {
        $rows = [];
        foreach ($students as $student) {
            $studentgroups = [];
            foreach ($groups as $group) {
                if (isset($group->studentmembers[(int)$student->id])) {
                    $studentgroups[] = $group->name;
                }
            }

            $choices = array_map(fn($response) => $response->text, $responses[(int)$student->id] ?? []);
            $warnings = [];
            if (!$studentgroups) {
                $warnings[] = 'missing_group';
            }
            if (!$choices) {
                $warnings[] = 'missing_choice';
            }

            $rows[(int)$student->id] = [
                'user' => $student,
                'groups' => $studentgroups,
                'choices' => $choices,
                'warnings' => $warnings,
            ];
        }
        return $rows;
    }

    /**
     * Build per-group warnings for source-data review.
     *
     * @param array $groups
     * @param array $responses
     * @return array
     */
    private static function build_group_warnings(array $groups, array $responses): array {
        $warnings = [];
        foreach ($groups as $group) {
            $memberids = array_map('intval', array_keys($group->studentmembers));
            $membercount = count($memberids);
            $groupchoices = [];
            foreach ($memberids as $userid) {
                foreach ($responses[$userid] ?? [] as $response) {
                    $groupchoices[] = $response->text;
                }
            }
            $uniquechoices = array_values(array_unique($groupchoices));
            $codes = [];
            if ($membercount < self::MIN_PARTNER_GROUP_SIZE || $membercount > self::MAX_PARTNER_GROUP_SIZE) {
                $codes[] = 'invalid_group_size';
            }
            if (!$uniquechoices) {
                $codes[] = 'no_group_responses';
            } else if (count($uniquechoices) > 1) {
                $codes[] = 'split_choices';
            }

            if ($codes) {
                $warnings[(int)$group->id] = [
                    'group' => $group,
                    'membercount' => $membercount,
                    'choices' => $uniquechoices,
                    'warnings' => $codes,
                ];
            }
        }
        return $warnings;
    }
}
