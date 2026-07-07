<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_web3talents\local;

/**
 * Retention cleanup for Web3 Talents operational data.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class retention_service {
    /** Temporary export file retention. */
    public const EXPORT_RETENTION_SECONDS = 14 * DAYSECS;

    /**
     * Run all retention cleanup steps.
     *
     * @param int|null $now Current timestamp.
     * @return array Cleanup counts.
     */
    public static function cleanup(?int $now = null): array {
        $now = $now ?? time();

        return [
            'exportfiles' => self::cleanup_export_files($now),
            'applicants' => self::mark_expired_unactivated_applicants($now),
        ];
    }

    /**
     * Delete temporary export files older than the configured retention window.
     *
     * @param int|null $now Current timestamp.
     * @return int Deleted file count.
     */
    public static function cleanup_export_files(?int $now = null): int {
        global $CFG;

        $now = $now ?? time();
        $dir = $CFG->tempdir . '/local_web3talents/exports';
        if (!is_dir($dir)) {
            return 0;
        }

        $deleted = 0;
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (!is_file($path)) {
                continue;
            }

            $modified = filemtime($path);
            if ($modified !== false && $modified <= ($now - self::EXPORT_RETENTION_SECONDS) && @unlink($path)) {
                $deleted++;
            }
        }

        if ($deleted > 0) {
            self::log_event('retention_export_files_deleted', null, null, ['count' => $deleted]);
        }

        return $deleted;
    }

    /**
     * Mark expired accepted/account-created applicants as removed.
     *
     * @param int|null $now Current timestamp.
     * @return int Updated applicant count.
     */
    public static function mark_expired_unactivated_applicants(?int $now = null): int {
        global $DB;

        $now = $now ?? time();
        [$statussql, $params] = $DB->get_in_or_equal([
            applicant_service::STATUS_ACCEPTED,
            applicant_service::STATUS_ACCOUNT_CREATED,
        ], SQL_PARAMS_NAMED, 'status');
        $params['now'] = $now;

        $records = $DB->get_records_select(
            'local_web3talents_app',
            "retentionuntil IS NOT NULL AND retentionuntil > 0 AND retentionuntil <= :now AND status {$statussql}",
            $params
        );

        $updated = 0;
        foreach ($records as $record) {
            $record->status = applicant_service::STATUS_REMOVED;
            $record->notes = self::append_retention_note((string)$record->notes, $now);
            $record->timemodified = $now;
            $DB->update_record('local_web3talents_app', $record);
            $updated++;

            self::log_event('retention_applicant_marked_removed', null, null, [
                'applicantid' => (int)$record->id,
                'email' => $record->email,
                'retentionuntil' => (int)$record->retentionuntil,
            ]);
        }

        return $updated;
    }

    /**
     * Append a retention note without clobbering existing admin notes.
     *
     * @param string $notes Existing notes.
     * @param int $now Current timestamp.
     * @return string Updated notes.
     */
    private static function append_retention_note(string $notes, int $now): string {
        $note = '[Retention cleanup ' . userdate($now, '%Y-%m-%d') . '] Marked removed after retention date.';
        return trim($notes) === '' ? $note : trim($notes) . "\n" . $note;
    }

    /**
     * Write an operational log record.
     *
     * @param string $eventtype Event type.
     * @param int|null $userid User id.
     * @param int|null $courseid Course id.
     * @param array $metadata Metadata.
     */
    private static function log_event(string $eventtype, ?int $userid, ?int $courseid, array $metadata): void {
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
