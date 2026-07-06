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
 * Weekly group-slot topic selection workflow.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class topic_round_service {
    public const STATUS_DRAFT = 'draft';
    public const STATUS_OPEN = 'open';
    public const STATUS_FINALIZED = 'finalized';

    /**
     * Default four topics for first-release weekly rounds.
     *
     * @return array
     */
    public static function default_topics(): array {
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
        return course_state_service::get_configured_course();
    }

    /**
     * Create a partner set and mark it active for the course.
     *
     * @param int $courseid Course id.
     * @param string $name Partner set name.
     * @return stdClass
     */
    public static function create_partner_set(int $courseid, string $name): stdClass {
        global $DB;

        $now = time();
        $transaction = $DB->start_delegated_transaction();
        $DB->set_field('local_w3t_pset', 'active', 0, ['courseid' => $courseid]);
        $id = $DB->insert_record('local_w3t_pset', [
            'courseid' => $courseid,
            'name' => trim($name),
            'active' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $transaction->allow_commit();

        return $DB->get_record('local_w3t_pset', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Return the active partner set for a course, if any.
     *
     * @param int $courseid Course id.
     * @return stdClass|null
     */
    public static function get_active_partner_set(int $courseid): ?stdClass {
        global $DB;

        $records = $DB->get_records('local_w3t_pset', ['courseid' => $courseid, 'active' => 1], 'timemodified DESC, id DESC', '*', 0, 1);
        return $records ? reset($records) : null;
    }

    /**
     * Add a partner group to a partner set.
     *
     * @param int $partnersetid Partner set id.
     * @param string $name Group name.
     * @param array $userids Student user ids.
     * @return stdClass
     */
    public static function create_partner_group(int $partnersetid, string $name, array $userids): stdClass {
        global $DB;

        $partnerset = $DB->get_record('local_w3t_pset', ['id' => $partnersetid], '*', MUST_EXIST);
        $now = time();
        $transaction = $DB->start_delegated_transaction();
        $pgroupid = $DB->insert_record('local_w3t_pgroup', [
            'partnersetid' => $partnersetid,
            'name' => trim($name),
            'sortorder' => $DB->count_records('local_w3t_pgroup', ['partnersetid' => $partnersetid]) + 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $seen = [];
        foreach ($userids as $userid) {
            $userid = (int)$userid;
            if ($userid <= 0 || isset($seen[$userid])) {
                continue;
            }
            self::ensure_user_available_for_partner_set($partnerset, $userid);
            $DB->insert_record('local_w3t_pmember', [
                'pgroupid' => $pgroupid,
                'userid' => $userid,
                'timecreated' => $now,
            ]);
            $seen[$userid] = true;
        }

        $transaction->allow_commit();
        return $DB->get_record('local_w3t_pgroup', ['id' => $pgroupid], '*', MUST_EXIST);
    }

    /**
     * Create a weekly round with four default topics.
     *
     * @param int $courseid Course id.
     * @param int $partnersetid Partner set id.
     * @param string $name Round name.
     * @param int $opentime Open timestamp.
     * @param int $closetime Close timestamp.
     * @param int $defaultslots Default group slots per topic.
     * @param array|null $topics Topic names.
     * @return stdClass
     */
    public static function create_round(int $courseid, int $partnersetid, string $name, int $opentime, int $closetime, int $defaultslots = 5, ?array $topics = null): stdClass {
        global $DB;

        if ($closetime <= $opentime) {
            throw new moodle_exception('error_invalid_round_window', 'local_web3talents');
        }
        if (self::has_open_round($courseid)) {
            throw new moodle_exception('error_open_round_exists', 'local_web3talents');
        }

        $topics = self::normalise_topic_names($topics ?? self::default_topics());

        $now = time();
        $roundid = $DB->insert_record('local_w3t_round', [
            'courseid' => $courseid,
            'partnersetid' => $partnersetid,
            'name' => trim($name),
            'opentime' => $opentime,
            'closetime' => $closetime,
            'status' => self::STATUS_OPEN,
            'defaultslots' => max(1, $defaultslots),
            'finalizedtime' => null,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $sortorder = 1;
        foreach ($topics as $topic) {
            $DB->insert_record('local_w3t_topic', [
                'roundid' => $roundid,
                'name' => $topic,
                'slotlimit' => max(1, $defaultslots),
                'sortorder' => $sortorder++,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }

        return $DB->get_record('local_w3t_round', ['id' => $roundid], '*', MUST_EXIST);
    }

    /**
     * Check whether a course already has an open round.
     *
     * @param int $courseid Course id.
     * @return bool
     */
    public static function has_open_round(int $courseid): bool {
        global $DB;

        return $DB->record_exists('local_w3t_round', [
            'courseid' => $courseid,
            'status' => self::STATUS_OPEN,
        ]);
    }

    /**
     * Clean topic names and ensure exactly four non-empty topics.
     *
     * @param array $topics Topic names.
     * @return array
     */
    private static function normalise_topic_names(array $topics): array {
        $cleaned = [];
        foreach ($topics as $topic) {
            $topic = trim((string)$topic);
            if ($topic !== '') {
                $cleaned[] = $topic;
            }
        }

        if (count($cleaned) !== 4) {
            throw new moodle_exception('error_four_topics_required', 'local_web3talents');
        }
        return array_values($cleaned);
    }

    /**
     * Return the current round visible to students.
     *
     * @param int $courseid Course id.
     * @return stdClass|null
     */
    public static function get_current_round(int $courseid): ?stdClass {
        global $DB;

        $now = time();
        $sql = "SELECT *
                  FROM {local_w3t_round}
                 WHERE courseid = :courseid
                   AND status = :status
                   AND opentime <= :nowopen
                   AND closetime > :nowclose
              ORDER BY opentime DESC, id DESC";
        $records = $DB->get_records_sql($sql, [
            'courseid' => $courseid,
            'status' => self::STATUS_OPEN,
            'nowopen' => $now,
            'nowclose' => $now,
        ], 0, 1);

        if ($records) {
            return reset($records);
        }

        $records = $DB->get_records('local_w3t_round', ['courseid' => $courseid], 'id DESC', '*', 0, 1);
        return $records ? reset($records) : null;
    }

    /**
     * Return admin-facing state for partner sets and rounds.
     *
     * @param int $courseid Course id.
     * @return array
     */
    public static function get_admin_state(int $courseid): array {
        global $DB;

        $sets = $DB->get_records('local_w3t_pset', ['courseid' => $courseid], 'active DESC, id DESC');
        $rounds = $DB->get_records('local_w3t_round', ['courseid' => $courseid], 'id DESC');

        return [
            'sets' => $sets,
            'groups' => self::get_partner_groups_for_sets(array_keys($sets)),
            'rounds' => $rounds,
            'topics' => self::get_topics_for_rounds(array_keys($rounds)),
            'choices' => self::get_choices_for_rounds(array_keys($rounds)),
        ];
    }

    /**
     * Return student-facing state for a round.
     *
     * @param int $roundid Round id.
     * @param int $userid User id.
     * @return array
     */
    public static function get_student_state(int $roundid, int $userid): array {
        global $DB;

        $round = $DB->get_record('local_w3t_round', ['id' => $roundid], '*', MUST_EXIST);
        $pgroup = self::get_partner_group_for_user($round->partnersetid, $userid);
        $members = $pgroup ? self::get_partner_group_members($pgroup->id) : [];
        $topics = self::topics_with_capacity($roundid);
        $choice = $pgroup ? $DB->get_record('local_w3t_choice', ['roundid' => $roundid, 'pgroupid' => $pgroup->id]) : null;

        return [
            'round' => $round,
            'pgroup' => $pgroup,
            'members' => $members,
            'topics' => $topics,
            'choice' => $choice,
            'isopen' => self::is_round_open($round),
        ];
    }

    /**
     * Select or change the topic for the user's whole partner group.
     *
     * @param int $roundid Round id.
     * @param int $userid Acting user id.
     * @param int $topicid Topic id.
     * @return stdClass Choice record.
     */
    public static function select_topic(int $roundid, int $userid, int $topicid): stdClass {
        global $DB;

        $lockfactory = \core\lock\lock_config::get_lock_factory('local_web3talents_topic_choice');
        $lock = $lockfactory->get_lock("round:{$roundid}", 10);
        if (!$lock) {
            throw new moodle_exception('error_choice_locked', 'local_web3talents');
        }

        try {
            $transaction = null;
            try {
                $transaction = $DB->start_delegated_transaction();
                $round = $DB->get_record('local_w3t_round', ['id' => $roundid], '*', MUST_EXIST);
                if (!self::is_round_open($round)) {
                    throw new moodle_exception('error_round_not_open', 'local_web3talents');
                }

                $topic = $DB->get_record('local_w3t_topic', ['id' => $topicid, 'roundid' => $roundid], '*', MUST_EXIST);
                $pgroup = self::get_partner_group_for_user((int)$round->partnersetid, $userid);
                if (!$pgroup) {
                    throw new moodle_exception('error_no_partner_group', 'local_web3talents');
                }

                $choice = $DB->get_record('local_w3t_choice', ['roundid' => $roundid, 'pgroupid' => $pgroup->id]);
                $usedslots = self::count_topic_choices($roundid, $topicid, $choice ? (int)$pgroup->id : null);
                if ($usedslots >= (int)$topic->slotlimit && (!$choice || (int)$choice->topicid !== (int)$topicid)) {
                    throw new moodle_exception('error_topic_full', 'local_web3talents', '', $topic->name);
                }

                $now = time();
                if ($choice) {
                    $choice->topicid = $topicid;
                    $choice->selectedby = $userid;
                    $choice->timemodified = $now;
                    $DB->update_record('local_w3t_choice', $choice);
                } else {
                    $choice = (object)[
                        'roundid' => $roundid,
                        'topicid' => $topicid,
                        'pgroupid' => $pgroup->id,
                        'selectedby' => $userid,
                        'timeselected' => $now,
                        'timemodified' => $now,
                    ];
                    $choice->id = $DB->insert_record('local_w3t_choice', $choice);
                }

                self::log_event('topic_choice_saved', $userid, (int)$round->courseid, [
                    'roundid' => $roundid,
                    'topicid' => $topicid,
                    'pgroupid' => $pgroup->id,
                ]);
                $transaction->allow_commit();
                return $DB->get_record('local_w3t_choice', ['id' => $choice->id], '*', MUST_EXIST);
            } catch (\Throwable $exception) {
                if ($transaction) {
                    $transaction->rollback($exception);
                }
                throw $exception;
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Finalize any open rounds whose close time has passed.
     *
     * @return int Number of finalized rounds.
     */
    public static function finalize_due_rounds(): int {
        global $DB;

        $rounds = $DB->get_records_select(
            'local_w3t_round',
            'status = :status AND closetime <= :now',
            ['status' => self::STATUS_OPEN, 'now' => time()],
            'closetime ASC, id ASC'
        );

        $count = 0;
        foreach ($rounds as $round) {
            self::finalize_round((int)$round->id);
            $count++;
        }
        return $count;
    }

    /**
     * Finalize one round by creating Moodle topic groups and informing students.
     *
     * @param int $roundid Round id.
     * @return stdClass Finalized round.
     */
    public static function finalize_round(int $roundid): stdClass {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/group/lib.php');
        require_once($CFG->libdir . '/messagelib.php');

        $lockfactory = \core\lock\lock_config::get_lock_factory('local_web3talents_round_finalize');
        $lock = $lockfactory->get_lock("round:{$roundid}", 30);
        if (!$lock) {
            throw new moodle_exception('error_finalize_locked', 'local_web3talents');
        }

        try {
            $transaction = $DB->start_delegated_transaction();
            $round = $DB->get_record('local_w3t_round', ['id' => $roundid], '*', MUST_EXIST);
            if ($round->status === self::STATUS_FINALIZED) {
                $transaction->allow_commit();
                return $round;
            }

            $course = $DB->get_record('course', ['id' => $round->courseid], '*', MUST_EXIST);
            $topics = $DB->get_records('local_w3t_topic', ['roundid' => $roundid], 'sortorder ASC, id ASC');
            foreach ($topics as $topic) {
                $moodlegroup = self::ensure_moodle_topic_group($course, $round, $topic);
                $members = self::get_selected_members_for_topic($roundid, (int)$topic->id);
                foreach ($members as $member) {
                    if (!$DB->record_exists('groups_members', ['groupid' => $moodlegroup->id, 'userid' => $member->id])) {
                        groups_add_member($moodlegroup, $member);
                    }
                    self::notify_final_assignment($member, $round, $topic);
                }
            }

            $round->status = self::STATUS_FINALIZED;
            $round->finalizedtime = time();
            $round->timemodified = time();
            $DB->update_record('local_w3t_round', $round);
            self::log_event('topic_round_finalized', get_admin()->id, (int)$round->courseid, ['roundid' => $roundid]);
            $transaction->allow_commit();

            return $DB->get_record('local_w3t_round', ['id' => $roundid], '*', MUST_EXIST);
        } finally {
            $lock->release();
        }
    }

    /**
     * Return all topics with used and remaining group slots.
     *
     * @param int $roundid Round id.
     * @return array
     */
    public static function topics_with_capacity(int $roundid): array {
        global $DB;

        $topics = $DB->get_records('local_w3t_topic', ['roundid' => $roundid], 'sortorder ASC, id ASC');
        foreach ($topics as $topic) {
            $topic->usedslots = self::count_topic_choices($roundid, (int)$topic->id);
            $topic->slotsleft = max(0, (int)$topic->slotlimit - (int)$topic->usedslots);
        }
        return $topics;
    }

    /**
     * Whether a round is currently selectable.
     *
     * @param stdClass $round Round record.
     * @return bool
     */
    public static function is_round_open(stdClass $round): bool {
        $now = time();
        return $round->status === self::STATUS_OPEN && (int)$round->opentime <= $now && (int)$round->closetime > $now;
    }

    /**
     * Return selected topic for a user in a finalized/current round.
     *
     * @param int $roundid Round id.
     * @param int $userid User id.
     * @return stdClass|null
     */
    public static function get_user_choice_topic(int $roundid, int $userid): ?stdClass {
        global $DB;

        $round = $DB->get_record('local_w3t_round', ['id' => $roundid], '*', MUST_EXIST);
        $pgroup = self::get_partner_group_for_user((int)$round->partnersetid, $userid);
        if (!$pgroup) {
            return null;
        }
        $choice = $DB->get_record('local_w3t_choice', ['roundid' => $roundid, 'pgroupid' => $pgroup->id]);
        return $choice ? $DB->get_record('local_w3t_topic', ['id' => $choice->topicid]) : null;
    }

    /**
     * Get a user's partner group in a set.
     *
     * @param int $partnersetid Partner set id.
     * @param int $userid User id.
     * @return stdClass|null
     */
    public static function get_partner_group_for_user(int $partnersetid, int $userid): ?stdClass {
        global $DB;

        $sql = "SELECT pg.*
                  FROM {local_w3t_pgroup} pg
                  JOIN {local_w3t_pmember} pm ON pm.pgroupid = pg.id
                 WHERE pg.partnersetid = :partnersetid
                   AND pm.userid = :userid";
        return $DB->get_record_sql($sql, ['partnersetid' => $partnersetid, 'userid' => $userid]) ?: null;
    }

    /**
     * Get members for a partner group.
     *
     * @param int $pgroupid Partner group id.
     * @return array
     */
    public static function get_partner_group_members(int $pgroupid): array {
        global $DB;

        $sql = "SELECT u.*
                  FROM {user} u
                  JOIN {local_w3t_pmember} pm ON pm.userid = u.id
                 WHERE pm.pgroupid = :pgroupid
                   AND u.deleted = 0
              ORDER BY u.lastname, u.firstname, u.id";
        return $DB->get_records_sql($sql, ['pgroupid' => $pgroupid]);
    }

    /**
     * Ensure a user is not already assigned inside a partner set.
     *
     * @param stdClass $partnerset Partner set.
     * @param int $userid User id.
     */
    private static function ensure_user_available_for_partner_set(stdClass $partnerset, int $userid): void {
        global $DB;

        $sql = "SELECT pm.id
                  FROM {local_w3t_pmember} pm
                  JOIN {local_w3t_pgroup} pg ON pg.id = pm.pgroupid
                 WHERE pg.partnersetid = :partnersetid
                   AND pm.userid = :userid";
        if ($DB->record_exists_sql($sql, ['partnersetid' => $partnerset->id, 'userid' => $userid])) {
            throw new moodle_exception('error_partner_duplicate_user', 'local_web3talents');
        }
    }

    /**
     * Count topic choices, optionally excluding a partner group.
     *
     * @param int $roundid Round id.
     * @param int $topicid Topic id.
     * @param int|null $excludepgroupid Partner group id to exclude.
     * @return int
     */
    private static function count_topic_choices(int $roundid, int $topicid, ?int $excludepgroupid = null): int {
        global $DB;

        $select = 'roundid = :roundid AND topicid = :topicid';
        $params = ['roundid' => $roundid, 'topicid' => $topicid];
        if ($excludepgroupid !== null) {
            $select .= ' AND pgroupid <> :excludepgroupid';
            $params['excludepgroupid'] = $excludepgroupid;
        }
        return $DB->count_records_select('local_w3t_choice', $select, $params);
    }

    /**
     * Get grouped partner groups for partner sets.
     *
     * @param array $partnersetids Partner set ids.
     * @return array
     */
    private static function get_partner_groups_for_sets(array $partnersetids): array {
        global $DB;

        if (!$partnersetids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($partnersetids, SQL_PARAMS_NAMED);
        $records = $DB->get_records_select('local_w3t_pgroup', "partnersetid {$insql}", $params, 'partnersetid, sortorder, id');
        $groups = [];
        foreach ($records as $record) {
            $record->members = self::get_partner_group_members((int)$record->id);
            $groups[(int)$record->partnersetid][] = $record;
        }
        return $groups;
    }

    /**
     * Get grouped topics for rounds.
     *
     * @param array $roundids Round ids.
     * @return array
     */
    private static function get_topics_for_rounds(array $roundids): array {
        global $DB;

        if (!$roundids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($roundids, SQL_PARAMS_NAMED);
        $records = $DB->get_records_select('local_w3t_topic', "roundid {$insql}", $params, 'roundid, sortorder, id');
        $topics = [];
        foreach ($records as $record) {
            $record->usedslots = self::count_topic_choices((int)$record->roundid, (int)$record->id);
            $record->slotsleft = max(0, (int)$record->slotlimit - (int)$record->usedslots);
            $topics[(int)$record->roundid][] = $record;
        }
        return $topics;
    }

    /**
     * Get grouped choices for rounds.
     *
     * @param array $roundids Round ids.
     * @return array
     */
    private static function get_choices_for_rounds(array $roundids): array {
        global $DB;

        if (!$roundids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($roundids, SQL_PARAMS_NAMED);
        $records = $DB->get_records_select('local_w3t_choice', "roundid {$insql}", $params, 'roundid, id');
        $choices = [];
        foreach ($records as $record) {
            $choices[(int)$record->roundid][] = $record;
        }
        return $choices;
    }

    /**
     * Ensure generated Moodle group exists for a topic.
     *
     * @param stdClass $course Course record.
     * @param stdClass $round Round record.
     * @param stdClass $topic Topic record.
     * @return stdClass
     */
    private static function ensure_moodle_topic_group(stdClass $course, stdClass $round, stdClass $topic): stdClass {
        global $DB;

        $existing = $DB->get_record('local_w3t_fgroup', ['roundid' => $round->id, 'topicid' => $topic->id]);
        if ($existing) {
            return $DB->get_record('groups', ['id' => $existing->moodlegroupid], '*', MUST_EXIST);
        }

        $idnumber = 'w3t_round_' . $round->id . '_topic_' . $topic->id;
        $group = $DB->get_record('groups', ['courseid' => $course->id, 'idnumber' => $idnumber]);
        if (!$group) {
            $groupid = groups_create_group((object)[
                'courseid' => $course->id,
                'name' => $round->name . ' - ' . $topic->name,
                'idnumber' => $idnumber,
                'description' => 'Generated by Web3 Talents topic selection finalization.',
                'descriptionformat' => FORMAT_HTML,
            ]);
            $group = $DB->get_record('groups', ['id' => $groupid], '*', MUST_EXIST);
        }

        $DB->insert_record('local_w3t_fgroup', [
            'roundid' => $round->id,
            'topicid' => $topic->id,
            'moodlegroupid' => $group->id,
            'timecreated' => time(),
        ]);
        return $group;
    }

    /**
     * Return selected student members for a topic.
     *
     * @param int $roundid Round id.
     * @param int $topicid Topic id.
     * @return array
     */
    private static function get_selected_members_for_topic(int $roundid, int $topicid): array {
        global $DB;

        $sql = "SELECT DISTINCT u.*
                  FROM {user} u
                  JOIN {local_w3t_pmember} pm ON pm.userid = u.id
                  JOIN {local_w3t_choice} c ON c.pgroupid = pm.pgroupid
                 WHERE c.roundid = :roundid
                   AND c.topicid = :topicid
                   AND u.deleted = 0
              ORDER BY u.lastname, u.firstname, u.id";
        return $DB->get_records_sql($sql, ['roundid' => $roundid, 'topicid' => $topicid]);
    }

    /**
     * Send a Moodle direct message for final assignment.
     *
     * @param stdClass $user User.
     * @param stdClass $round Round.
     * @param stdClass $topic Topic.
     */
    private static function notify_final_assignment(stdClass $user, stdClass $round, stdClass $topic): void {
        global $DB;

        $admin = get_admin();
        $user = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
        $message = get_string('topic_assignment_message', 'local_web3talents', (object)[
            'round' => $round->name,
            'topic' => $topic->name,
        ]);
        message_post_message($admin, $user, $message, FORMAT_PLAIN);
        self::log_event('topic_assignment_notified', $user->id, (int)$round->courseid, [
            'roundid' => $round->id,
            'topicid' => $topic->id,
        ]);
    }

    /**
     * Store operational log event.
     *
     * @param string $eventtype Event type.
     * @param int|null $userid User id.
     * @param int|null $courseid Course id.
     * @param array $metadata Metadata.
     */
    private static function log_event(string $eventtype, ?int $userid, ?int $courseid, array $metadata = []): void {
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
