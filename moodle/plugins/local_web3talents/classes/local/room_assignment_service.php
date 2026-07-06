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
 * Hidden room generation workflow for finalized topic rounds.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class room_assignment_service {
    /**
     * Generate and store the latest room assignment result for a round.
     *
     * @param int $roundid Topic round id.
     * @param int|null $roomcount Room count, or recommended count when null.
     * @param int|null $generatedby User id.
     * @return stdClass Result record.
     */
    public static function generate(int $roundid, ?int $roomcount = null, ?int $generatedby = null): stdClass {
        global $DB, $USER;

        $round = $DB->get_record('local_w3t_round', ['id' => $roundid], '*', MUST_EXIST);
        if ($round->status !== topic_round_service::STATUS_FINALIZED) {
            throw new moodle_exception('error_round_must_be_finalized', 'local_web3talents');
        }

        $topics = $DB->get_records('local_w3t_topic', ['roundid' => $roundid], 'sortorder ASC, id ASC');
        $groups = $DB->get_records('local_w3t_pgroup', ['partnersetid' => $round->partnersetid], 'sortorder ASC, id ASC');
        if (!$topics || !$groups) {
            throw new moodle_exception('error_room_generation_missing_data', 'local_web3talents');
        }

        $roomcount = $roomcount ?: self::recommended_room_count(count($groups), count($topics));
        if ($roomcount < 1 || count($groups) > $roomcount * count($topics)) {
            throw new moodle_exception('error_room_count_too_low', 'local_web3talents');
        }

        $assignments = self::assign_topics($round, $topics, $groups);
        $rooms = self::build_rooms($assignments, $topics, $roomcount);
        $warnings = self::build_warnings($rooms);

        $transaction = $DB->start_delegated_transaction();
        self::delete_result_for_round($roundid);
        $now = time();
        $resultid = $DB->insert_record('local_w3t_room_result', [
            'courseid' => $round->courseid,
            'roundid' => $roundid,
            'roomcount' => $roomcount,
            'warnings' => json_encode($warnings),
            'generatedby' => $generatedby ?? (int)$USER->id,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        foreach ($rooms as $index => $room) {
            $roomid = $DB->insert_record('local_w3t_room', [
                'resultid' => $resultid,
                'roomname' => $room['roomname'],
                'sortorder' => $index + 1,
            ]);
            foreach ($room['assignments'] as $sortorder => $assignment) {
                $DB->insert_record('local_w3t_room_group', [
                    'resultid' => $resultid,
                    'roomid' => $roomid,
                    'pgroupid' => $assignment['pgroup']->id,
                    'topicid' => $assignment['topic']->id,
                    'assignmentreason' => $assignment['reason'],
                    'sortorder' => $sortorder + 1,
                ]);
            }
        }

        self::log_event('rooms_generated', $generatedby ?? (int)$USER->id, (int)$round->courseid, [
            'roundid' => $roundid,
            'roomcount' => $roomcount,
            'warnings' => $warnings,
        ]);
        $transaction->allow_commit();

        return $DB->get_record('local_w3t_room_result', ['id' => $resultid], '*', MUST_EXIST);
    }

    /**
     * Move a partner group to another room in the latest result.
     *
     * @param int $resultid Result id.
     * @param int $pgroupid Partner group id.
     * @param int $targetroomid Target room id.
     * @return stdClass Updated result.
     */
    public static function move_group(int $resultid, int $pgroupid, int $targetroomid): stdClass {
        global $DB, $USER;

        $result = $DB->get_record('local_w3t_room_result', ['id' => $resultid], '*', MUST_EXIST);
        $targetroom = $DB->get_record('local_w3t_room', ['id' => $targetroomid, 'resultid' => $resultid], '*', MUST_EXIST);
        $placement = $DB->get_record('local_w3t_room_group', ['resultid' => $resultid, 'pgroupid' => $pgroupid], '*', MUST_EXIST);

        $placement->roomid = $targetroom->id;
        $placement->sortorder = $DB->count_records('local_w3t_room_group', ['roomid' => $targetroom->id]) + 1;
        $DB->update_record('local_w3t_room_group', $placement);

        $state = self::get_result_state($resultid);
        $warnings = self::build_warnings($state['rooms']);
        $result->warnings = json_encode($warnings);
        $result->timemodified = time();
        $DB->update_record('local_w3t_room_result', $result);

        self::log_event('room_group_moved', (int)$USER->id, (int)$result->courseid, [
            'resultid' => $resultid,
            'pgroupid' => $pgroupid,
            'targetroom' => $targetroom->roomname,
            'warnings' => $warnings,
        ]);

        return $DB->get_record('local_w3t_room_result', ['id' => $resultid], '*', MUST_EXIST);
    }

    /**
     * Return latest result for a round.
     *
     * @param int $roundid Round id.
     * @return stdClass|null
     */
    public static function get_latest_result(int $roundid): ?stdClass {
        global $DB;

        return $DB->get_record('local_w3t_room_result', ['roundid' => $roundid]) ?: null;
    }

    /**
     * Return display state for a stored result.
     *
     * @param int $resultid Result id.
     * @return array
     */
    public static function get_result_state(int $resultid): array {
        global $DB;

        $result = $DB->get_record('local_w3t_room_result', ['id' => $resultid], '*', MUST_EXIST);
        $round = $DB->get_record('local_w3t_round', ['id' => $result->roundid], '*', MUST_EXIST);
        $rooms = $DB->get_records('local_w3t_room', ['resultid' => $resultid], 'sortorder ASC, id ASC');
        $topics = $DB->get_records('local_w3t_topic', ['roundid' => $round->id], 'sortorder ASC, id ASC');
        $topicbyid = [];
        foreach ($topics as $topic) {
            $topicbyid[(int)$topic->id] = $topic;
        }

        $roomstate = [];
        foreach ($rooms as $room) {
            $placements = $DB->get_records('local_w3t_room_group', ['roomid' => $room->id], 'sortorder ASC, id ASC');
            $assignments = [];
            foreach ($placements as $placement) {
                $pgroup = $DB->get_record('local_w3t_pgroup', ['id' => $placement->pgroupid], '*', MUST_EXIST);
                $assignments[] = [
                    'placement' => $placement,
                    'pgroup' => $pgroup,
                    'members' => topic_round_service::get_partner_group_members((int)$pgroup->id),
                    'topic' => $topicbyid[(int)$placement->topicid] ?? null,
                    'reason' => $placement->assignmentreason,
                ];
            }
            $roomstate[] = [
                'room' => $room,
                'assignments' => $assignments,
            ];
        }

        return [
            'result' => $result,
            'round' => $round,
            'rooms' => $roomstate,
            'topics' => $topics,
            'warnings' => json_decode((string)$result->warnings, true) ?: [],
        ];
    }

    /**
     * Return recommended room count.
     *
     * @param int $groupcount Partner group count.
     * @param int $topiccount Topic count.
     * @return int
     */
    private static function recommended_room_count(int $groupcount, int $topiccount): int {
        return max(1, (int)ceil($groupcount / max(1, $topiccount)));
    }

    /**
     * Assign topics to partner groups.
     *
     * @param stdClass $round Round.
     * @param array $topics Topics.
     * @param array $groups Partner groups.
     * @return array
     */
    private static function assign_topics(stdClass $round, array $topics, array $groups): array {
        global $DB;

        $counts = [];
        foreach ($topics as $topic) {
            $counts[(int)$topic->id] = 0;
        }

        $choices = $DB->get_records('local_w3t_choice', ['roundid' => $round->id]);
        $choicebypgroup = [];
        foreach ($choices as $choice) {
            $choicebypgroup[(int)$choice->pgroupid] = $choice;
        }

        $assignments = [];
        foreach ($groups as $group) {
            $choice = $choicebypgroup[(int)$group->id] ?? null;
            if ($choice && isset($counts[(int)$choice->topicid])) {
                $topicid = (int)$choice->topicid;
                $reason = 'group-choice';
            } else {
                $topicid = self::choose_balanced_topic_id($counts, array_keys($counts));
                $reason = 'no-choice-balanced';
            }
            $counts[$topicid]++;
            $assignments[] = [
                'pgroup' => $group,
                'topic' => $DB->get_record('local_w3t_topic', ['id' => $topicid], '*', MUST_EXIST),
                'reason' => $reason,
                'membercount' => count(topic_round_service::get_partner_group_members((int)$group->id)),
            ];
        }

        usort($assignments, fn($a, $b) => strnatcasecmp($a['pgroup']->name, $b['pgroup']->name));
        return $assignments;
    }

    /**
     * Build room assignment buckets.
     *
     * @param array $assignments Partner-group assignments.
     * @param array $topics Topics.
     * @param int $roomcount Room count.
     * @return array
     */
    private static function build_rooms(array $assignments, array $topics, int $roomcount): array {
        $rooms = [];
        for ($i = 1; $i <= $roomcount; $i++) {
            $rooms[] = ['roomname' => 'Room' . $i, 'assignments' => []];
        }

        foreach ($topics as $topic) {
            $topicassignments = array_values(array_filter(
                $assignments,
                fn($assignment) => (int)$assignment['topic']->id === (int)$topic->id
            ));
            foreach ($topicassignments as $assignment) {
                $candidates = array_values(array_filter(
                    $rooms,
                    fn($room) => !self::room_has_topic($room, (int)$assignment['topic']->id)
                ));
                $roomindex = self::choose_best_room_index($rooms, $candidates ?: $rooms);
                $rooms[$roomindex]['assignments'][] = $assignment;
            }
        }

        return $rooms;
    }

    /**
     * Choose balanced topic.
     *
     * @param array $counts Current counts by topic id.
     * @param array $topicids Topic ids.
     * @return int
     */
    private static function choose_balanced_topic_id(array $counts, array $topicids): int {
        usort($topicids, fn($a, $b) => ($counts[(int)$a] <=> $counts[(int)$b]) ?: ((int)$a <=> (int)$b));
        return (int)$topicids[0];
    }

    /**
     * Whether room already has topic.
     *
     * @param array $room Room state.
     * @param int $topicid Topic id.
     * @return bool
     */
    private static function room_has_topic(array $room, int $topicid): bool {
        foreach ($room['assignments'] as $assignment) {
            if ((int)$assignment['topic']->id === $topicid) {
                return true;
            }
        }
        return false;
    }

    /**
     * Choose best room index from candidates.
     *
     * @param array $rooms All rooms.
     * @param array $candidates Candidate rooms.
     * @return int
     */
    private static function choose_best_room_index(array $rooms, array $candidates): int {
        usort($candidates, function($left, $right) {
            $leftparticipants = self::room_participant_count($left);
            $rightparticipants = self::room_participant_count($right);
            return ($leftparticipants <=> $rightparticipants)
                ?: (count($left['assignments']) <=> count($right['assignments']))
                ?: (self::room_number($left['roomname']) <=> self::room_number($right['roomname']));
        });
        $selected = $candidates[0]['roomname'];
        foreach ($rooms as $index => $room) {
            if ($room['roomname'] === $selected) {
                return $index;
            }
        }
        return 0;
    }

    /**
     * Count participants in a room.
     *
     * @param array $room Room state.
     * @return int
     */
    private static function room_participant_count(array $room): int {
        return array_sum(array_map(fn($assignment) => $assignment['membercount'] ?? count($assignment['members'] ?? []), $room['assignments']));
    }

    /**
     * Extract numeric room number.
     *
     * @param string $roomname Room name.
     * @return int
     */
    private static function room_number(string $roomname): int {
        return (int)str_replace('Room', '', $roomname);
    }

    /**
     * Build warnings for room state.
     *
     * @param array $rooms Room state.
     * @return array
     */
    private static function build_warnings(array $rooms): array {
        $warnings = [];
        foreach ($rooms as $room) {
            $roomname = $room['roomname'] ?? ($room['room']->roomname ?? 'Room');
            $topiccounts = [];
            foreach ($room['assignments'] as $assignment) {
                if (empty($assignment['topic'])) {
                    continue;
                }
                $topicid = (int)$assignment['topic']->id;
                $topiccounts[$topicid] = ($topiccounts[$topicid] ?? 0) + 1;
            }
            foreach ($topiccounts as $count) {
                if ($count > 1) {
                    $warnings[] = $roomname . ' has duplicate topics.';
                    break;
                }
            }
            if (!$room['assignments']) {
                $warnings[] = $roomname . ' has no partner groups.';
            }
        }
        return array_values(array_unique($warnings));
    }

    /**
     * Delete the current result for a round.
     *
     * @param int $roundid Round id.
     */
    private static function delete_result_for_round(int $roundid): void {
        global $DB;

        $result = $DB->get_record('local_w3t_room_result', ['roundid' => $roundid]);
        if (!$result) {
            return;
        }
        $DB->delete_records('local_w3t_room_group', ['resultid' => $result->id]);
        $DB->delete_records('local_w3t_room', ['resultid' => $result->id]);
        $DB->delete_records('local_w3t_room_result', ['id' => $result->id]);
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
