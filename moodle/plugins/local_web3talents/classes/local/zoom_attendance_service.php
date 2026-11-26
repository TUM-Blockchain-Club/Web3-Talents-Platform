<?php
namespace local_web3talents\local;

defined('MOODLE_INTERNAL') || die();

/** Ended-meeting imports. No email, absence inference, name matching or participation scores. */
class zoom_attendance_service {
    public static function session_lock(int $sessionid): \core\lock\lock {
        $lock = \core\lock\lock_config::get_lock_factory('local_web3talents')->get_lock('attendance:' . $sessionid, 10);
        if (!$lock) {
            throw new \moodle_exception('zoom_attendance_busy', 'local_web3talents');
        }
        return $lock;
    }

    public static function evidence(int $sessionid): array {
        global $DB;
        return $DB->get_records_sql('SELECT a.*, r.starttime FROM {local_w3t_zatt} a
            JOIN {local_w3t_zrun} r ON r.id = a.runid WHERE r.sessionid = ? ORDER BY a.displayname, a.id', [$sessionid]);
    }

    public static function totals(int $sessionid): array {
        $intervals = [];
        foreach (self::evidence($sessionid) as $row) {
            if ($row->userid && in_array($row->decision, ['auto', 'manual'], true)) {
                $intervals[$row->userid] = array_merge($intervals[$row->userid] ?? [], json_decode($row->intervals, true));
            }
        }
        return array_map([zoom_attendance_math::class, 'seconds'], $intervals);
    }

    /** Caller holds the session lock and a transaction. Manual records are never overwritten. */
    private static function apply(int $sessionid, array $students): void {
        global $DB;
        $totals = self::totals($sessionid);
        // Recompute only machine-owned records; fixes reviewer corrections without stale presence.
        $DB->delete_records('local_w3t_attendance', ['sessionid' => $sessionid, 'source' => 'zoom']);
        foreach ($students as $student) {
            if (empty($totals[$student->id]) || !empty($student->suspended) || !empty($student->deleted) ||
                    $DB->record_exists('local_w3t_attendance', ['sessionid' => $sessionid, 'userid' => $student->id])) {
                continue;
            }
            $DB->insert_record('local_w3t_attendance', (object)[
                'sessionid' => $sessionid, 'userid' => $student->id, 'status' => 'present', 'source' => 'zoom',
                'participation' => 0, 'notes' => '', 'markedby' => 0, 'timecreated' => time(), 'timemodified' => time(),
            ]);
        }
    }

    /** Atomic, repeatable complete snapshot. Never persist raw responses, IPs or access tokens. */
    public static function import(\stdClass $session, string $meetingid, string $uuid, int $starttime,
            array $participants, array $students): void {
        global $DB;
        $groups = zoom_attendance_math::group($participants, $starttime, time());
        if (!$groups) {
            throw new \RuntimeException('zoom_attendance_incomplete');
        }
        $lock = self::session_lock((int)$session->id);
        try {
            $transaction = $DB->start_delegated_transaction();
            $hash = hash('sha256', $uuid);
            $run = $DB->get_record('local_w3t_zrun', ['uuidhash' => $hash]);
            if ($run && ((int)$run->sessionid !== (int)$session->id || $run->meetingid !== $meetingid)) {
                throw new \RuntimeException('zoom_attendance_session_error');
            }
            if (!$run) {
                $run = (object)['sessionid' => $session->id, 'meetingid' => $meetingid, 'uuidhash' => $hash,
                    'starttime' => $starttime, 'syncedat' => time()];
                $run->id = $DB->insert_record('local_w3t_zrun', $run);
            }
            $keep = [];
            foreach ($groups as $key => $group) {
                $row = $DB->get_record('local_w3t_zatt', ['runid' => $run->id, 'identityhash' => $key]);
                $userid = $group['authenticated'] ? zoom_attendance_math::match($group['email'], $students) : 0;
                $values = (object)['runid' => $run->id, 'identityhash' => $key,
                    'displayname' => \core_text::substr($group['name'], 0, 255), 'email' => $group['email'],
                    'intervals' => json_encode($group['intervals']), 'userid' => $userid,
                    'decision' => $userid ? 'auto' : 'review', 'reviewedby' => 0, 'timemodified' => time()];
                if ($row) {
                    $values->id = $row->id;
                    if ($row->reviewedby || in_array($row->decision, ['manual', 'ignored'], true)) {
                        $values->userid = $row->userid;
                        $values->decision = $row->decision;
                        $values->reviewedby = $row->reviewedby;
                    }
                    $DB->update_record('local_w3t_zatt', $values);
                    $keep[] = (int)$row->id;
                } else {
                    $keep[] = $DB->insert_record('local_w3t_zatt', $values);
                }
            }
            // Only a fully fetched/validated report can replace prior evidence.
            foreach ($DB->get_records('local_w3t_zatt', ['runid' => $run->id]) as $row) {
                if (!in_array((int)$row->id, $keep, true)) {
                    $DB->delete_records('local_w3t_zatt', ['id' => $row->id]);
                }
            }
            $DB->set_field('local_w3t_zrun', 'syncedat', time(), ['id' => $run->id]);
            self::apply((int)$session->id, $students);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            if (isset($transaction)) {
                $transaction->rollback($e);
            }
            throw $e;
        } finally {
            $lock->release();
        }
    }

    /** Explicit reviewer match is occurrence-specific, never a reusable name-based identity. */
    public static function review(int $sessionid, int $rowid, int $userid, int $actorid): void {
        global $DB;
        $session = participation_service::get_session($sessionid);
        require_capability('local/web3talents:manageparticipation', \context_course::instance($session->courseid));
        $course = get_course($session->courseid);
        $students = participation_service::get_students($course);
        if ($userid > 0 && !isset($students[$userid])) {
            throw new \moodle_exception('zoom_attendance_invalid_match', 'local_web3talents');
        }
        if ($userid < -1) {
            throw new \moodle_exception('zoom_attendance_invalid_match', 'local_web3talents');
        }
        $lock = self::session_lock($sessionid);
        try {
            $transaction = $DB->start_delegated_transaction();
            $rows = self::evidence($sessionid);
            if (!isset($rows[$rowid])) {
                throw new \moodle_exception('zoom_attendance_invalid_match', 'local_web3talents');
            }
            $row = $rows[$rowid];
            $row->userid = max(0, $userid);
            $row->decision = $userid === -1 ? 'ignored' : ($userid > 0 ? 'manual' : 'review');
            $row->reviewedby = $actorid;
            $row->timemodified = time();
            $DB->update_record('local_w3t_zatt', $row);
            self::apply($sessionid, $students);
            $DB->insert_record('local_web3talents_log', (object)['userid' => $actorid, 'courseid' => $session->courseid,
                'eventtype' => 'zoom_attendance_reviewed', 'metadata' => json_encode([
                    'sessionid' => $sessionid, 'evidenceid' => $rowid, 'userid' => $row->userid, 'decision' => $row->decision]),
                'timecreated' => time()]);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            if (isset($transaction)) {
                $transaction->rollback($e);
            }
            throw $e;
        } finally {
            $lock->release();
        }
    }

    /** Called only by dedicated CLI/task or a CSRF-protected administrator action. */
    public static function sync(?zoom_attendance_client $client = null): array {
        global $DB;
        $config = zoom_attendance_config::read();
        if (empty($config['enabled'])) {
            return ['state' => 'disabled', 'imported' => 0, 'review' => 0];
        }
        $lock = \core\lock\lock_config::get_lock_factory('local_web3talents')->get_lock('zoomattendance-sync', 0);
        if (!$lock) {
            throw new \RuntimeException('zoom_attendance_busy');
        }
        $summary = ['state' => 'ready', 'imported' => 0, 'review' => 0];
        try {
            $course = participation_service::get_configured_course();
            $sessions = array_filter(participation_service::get_sessions((int)$course->id),
                static fn($s) => $s->sessiondate >= time() - 7 * DAYSECS && $s->sessiondate <= time() - 2 * HOURSECS);
            if (!$sessions) {
                $summary['state'] = 'waiting';
            } else {
                $client ??= new zoom_attendance_client($config);
                $client->meeting(); // Refuse silently moved/reassigned host or wrong recurring meeting.
                $instances = $client->instances(time() - 7 * DAYSECS, time());
                $students = participation_service::get_students($course);
                foreach ($instances as $instance) {
                    $start = strtotime($instance['start_time'] ?? '');
                    if (!$start || $start < time() - 7 * DAYSECS || $start > time()) {
                        continue;
                    }
                    $sessionid = zoom_attendance_math::session_for($start, $sessions);
                    if (!$sessionid) {
                        // Not our teaching session or ambiguous: never put data in a guessed session.
                        $summary['review']++;
                        continue;
                    }
                    $uuid = (string)($instance['uuid'] ?? '');
                    $run = $DB->get_record('local_w3t_zrun', ['uuidhash' => hash('sha256', $uuid)]);
                    // Refresh reports for 48h to catch Zoom processing delays; failures retry for 7d.
                    if ($run && $run->syncedat > $start + 2 * DAYSECS) {
                        continue;
                    }
                    try {
                        self::import($sessions[$sessionid], $config['meeting_id'], $uuid, $start,
                            $client->participants($uuid), $students);
                        $summary['imported']++;
                    } catch (\RuntimeException $e) {
                        if ($e->getMessage() !== 'zoom_attendance_incomplete') {
                            throw $e;
                        }
                        $summary['state'] = 'pending';
                    }
                }
            }
            set_config('zoom_attendance_lastcheck', time(), 'local_web3talents');
            set_config('zoom_attendance_state', $summary['state'], 'local_web3talents');
            set_config('zoom_attendance_unmapped', $summary['review'], 'local_web3talents');
            return $summary;
        } catch (\Throwable $e) {
            set_config('zoom_attendance_lastcheck', time(), 'local_web3talents');
            set_config('zoom_attendance_state', 'error', 'local_web3talents');
            // Do not expose arbitrary API, SQL or filesystem exceptions in logs/UI.
            throw new \RuntimeException('zoom_attendance_api_error');
        } finally {
            $lock->release();
        }
    }

    /** Short-lived evidence only. Keep course attendance and manual decisions in its normal table. */
    public static function cleanup(): void {
        global $DB;
        foreach ($DB->get_records_select('local_w3t_zrun', 'starttime < ?', [time() - 90 * DAYSECS]) as $run) {
            $lock = self::session_lock((int)$run->sessionid);
            try {
                $transaction = $DB->start_delegated_transaction();
                $DB->delete_records('local_w3t_zatt', ['runid' => $run->id]);
                $DB->delete_records('local_w3t_zrun', ['id' => $run->id]);
                $transaction->allow_commit();
            } finally {
                $lock->release();
            }
        }
    }
}
