<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_web3talents\local;

use csv_import_reader;
use moodle_exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use stdClass;

/**
 * Accepted-applicant workflow service.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class applicant_service {
    public const STATUS_PENDING = 'pending';
    public const STATUS_DISCUSSION = 'discussion';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_ACCOUNT_CREATED = 'accountcreated';
    public const STATUS_ACCOUNT_ACTIVATED = 'accountactivated';
    public const STATUS_DEFERRED = 'deferred';
    public const STATUS_REMOVED = 'removed';

    /**
     * Return supported applicant statuses.
     *
     * @return array
     */
    public static function statuses(): array {
        return [
            self::STATUS_PENDING => get_string('applicant_status_pending', 'local_web3talents'),
            self::STATUS_DISCUSSION => get_string('applicant_status_discussion', 'local_web3talents'),
            self::STATUS_ACCEPTED => get_string('applicant_status_accepted', 'local_web3talents'),
            self::STATUS_ACCOUNT_CREATED => get_string('applicant_status_accountcreated', 'local_web3talents'),
            self::STATUS_ACCOUNT_ACTIVATED => get_string('applicant_status_accountactivated', 'local_web3talents'),
            self::STATUS_DEFERRED => get_string('applicant_status_deferred', 'local_web3talents'),
            self::STATUS_REMOVED => get_string('applicant_status_removed', 'local_web3talents'),
        ];
    }

    /**
     * Insert a pending application, preserving any existing record with the same email.
     *
     * @param stdClass|array $data Applicant data.
     * @param string $source Source label.
     * @param int|null $actorid User making the change.
     * @return stdClass
     */
    public static function upsert_applicant($data, string $source = 'manual', ?int $actorid = null): stdClass {
        $data = (object)$data;
        $lock = application_review_service::lock((string)($data->email ?? ''));
        try {
            return self::insert_applicant_if_new($data, $source, $actorid);
        } finally {
            $lock->release();
        }
    }

    /** Re-import never changes answers, identity, comments or decisions already under review. */
    private static function insert_applicant_if_new($data, string $source, ?int $actorid): stdClass {
        global $DB, $USER;

        $data = (object)$data;
        $actorid = $actorid ?? (int)$USER->id;
        $now = time();
        $email = self::normalise_email($data->email ?? '');
        if (!validate_email($email)) {
            throw new moodle_exception('error_invalid_email', 'local_web3talents', '', $email);
        }

        $record = $DB->get_record('local_web3talents_app', ['email' => $email]);
        if ($record) {
            $record->wasinserted = false;
            return $record;
        }
        // No imported or manually supplied status can bypass independent review.
        $status = self::STATUS_PENDING;

        $values = [
            'firstname' => self::required_text($data->firstname ?? ''),
            'lastname' => self::required_text($data->lastname ?? ''),
            'email' => $email,
            'cohortid' => self::required_text($data->cohortid ?? 'fundamentals'),
            'status' => $status,
            'notes' => trim((string)($data->notes ?? '')),
            'responses' => $data->responses ?? null,
            'submissionid' => !empty($data->submissionid) ? $data->submissionid : null,
            'source' => clean_param($source, PARAM_ALPHANUMEXT),
            // The activation window starts when the account is created, not while awaiting review.
            'retentionuntil' => null,
            'timemodified' => $now,
        ];

        $values['createdby'] = $actorid;
        $values['importedby'] = $source === 'manual' ? null : $actorid;
        $values['timecreated'] = $now;
        $id = $DB->insert_record('local_web3talents_app', (object)$values);
        self::log_event('applicant_created', $actorid, null, ['email' => $email, 'source' => $source]);
        $record = $DB->get_record('local_web3talents_app', ['id' => $id], '*', MUST_EXIST);
        $record->wasinserted = true;
        return $record;
    }

    /**
     * Import applicant rows from CSV content.
     *
     * @param string $content CSV content.
     * @param string $encoding Source encoding.
     * @param string $delimiter Delimiter name.
     * @param int|null $actorid User making the change.
     * @return array
     */
    public static function import_csv(string $content, string $encoding = 'UTF-8', string $delimiter = 'comma', ?int $actorid = null): array {
        global $CFG;

        require_once($CFG->libdir . '/csvlib.class.php');

        $iid = csv_import_reader::get_new_iid('local_web3talents_applicants');
        $reader = new csv_import_reader($iid, 'local_web3talents_applicants');
        $reader->load_csv_content($content, $encoding, $delimiter);
        if ($reader->get_error()) {
            throw new moodle_exception('csvloaderror', '', '', $reader->get_error());
        }

        $headers = self::normalise_headers($reader->get_columns() ?: []);
        self::validate_headers($headers);

        $rows = [];
        $reader->init();
        while ($line = $reader->next()) {
            $rows[] = self::row_from_line($headers, $line);
        }
        $reader->close();

        return self::import_rows($rows, 'csv', $actorid);
    }

    /**
     * Import applicant rows from an Excel workbook.
     *
     * @param string $filepath Uploaded file path.
     * @param int|null $actorid User making the change.
     * @return array
     */
    public static function import_excel(string $filepath, ?int $actorid = null): array {
        $spreadsheet = IOFactory::load($filepath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        if (!$rows) {
            return ['created' => 0, 'updated' => 0, 'errors' => []];
        }

        $headers = self::normalise_headers(array_shift($rows));
        self::validate_headers($headers);

        $mapped = [];
        foreach ($rows as $row) {
            $mapped[] = self::row_from_line($headers, $row);
        }

        return self::import_rows($mapped, 'excel', $actorid);
    }

    /**
     * Search accepted-applicant records.
     *
     * @param string $query Search text.
     * @param int $limit Result limit.
     * @return array
     */
    public static function search_applicants(string $query = '', int $limit = 100, int $offset = 0): array {
        global $DB;

        $query = trim($query);
        if ($query === '') {
            return $DB->get_records('local_web3talents_app', null, 'timemodified DESC, id DESC', '*', $offset, $limit);
        }

        $like = '%' . $DB->sql_like_escape($query) . '%';
        $conditions = [
            $DB->sql_like('firstname', ':firstname', false),
            $DB->sql_like('lastname', ':lastname', false),
            $DB->sql_like('email', ':email', false),
        ];
        $params = ['firstname' => $like, 'lastname' => $like, 'email' => $like];
        return $DB->get_records_select(
            'local_web3talents_app',
            implode(' OR ', $conditions),
            $params,
            'timemodified DESC, id DESC',
            '*',
            $offset,
            $limit
        );
    }

    /**
     * Create a Moodle student account for an accepted applicant and enrol it.
     *
     * @param int $applicantid Accepted-applicant record id.
     * @param int|null $actorid User making the change.
     * @return stdClass Created Moodle user.
     */
    public static function create_student_account(int $applicantid, ?int $actorid = null): stdClass {
        global $DB;
        application_review_service::require_reviewer(true);
        $applicant = $DB->get_record('local_web3talents_app', ['id' => $applicantid], '*', MUST_EXIST);
        $lock = application_review_service::lock($applicant->email);
        try {
            return self::create_reviewed_account($applicantid);
        } finally {
            $lock->release();
        }
    }

    private static function create_reviewed_account(int $applicantid): stdClass {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->libdir . '/enrollib.php');

        $actorid = (int)$USER->id;
        $applicant = $DB->get_record('local_web3talents_app', ['id' => $applicantid], '*', MUST_EXIST);
        if (!application_review_service::summary($applicantid)['ready']) {
            throw new moodle_exception('review_two_required', 'local_web3talents');
        }
        if ($applicant->status !== self::STATUS_ACCEPTED) {
            throw new moodle_exception('error_applicant_not_accepted', 'local_web3talents');
        }
        if (!empty($applicant->userid)) {
            throw new moodle_exception('error_applicant_account_exists', 'local_web3talents');
        }
        if ($DB->record_exists('user', ['email' => $applicant->email, 'deleted' => 0])) {
            throw new moodle_exception('error_user_email_exists', 'local_web3talents');
        }

        $course = self::get_configured_course();
        $roleid = self::student_role_id();
        $user = (object)[
            'auth' => 'manual',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'username' => self::generate_username($applicant),
            'password' => generate_password(),
            'firstname' => $applicant->firstname,
            'lastname' => $applicant->lastname,
            'email' => $applicant->email,
            'lang' => current_language(),
        ];

        // Account, enrolment, and applicant link land together: a failure part-way through
        // used to leave an orphan account that permanently blocked any retry.
        $now = time();
        $transaction = $DB->start_delegated_transaction();
        try {
            $userid = user_create_user($user, true, true);
            $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
            set_user_preference('auth_forcepasswordchange', 1, $user);

            if (!enrol_try_internal_enrol($course->id, $userid, $roleid)) {
                throw new moodle_exception('error_enrol_failed', 'local_web3talents');
            }

            $applicant->userid = $userid;
            $applicant->status = self::STATUS_ACCOUNT_CREATED;
            $applicant->accountcreatedtime = $now;
            $applicant->retentionuntil = $now + 30 * DAYSECS;
            $applicant->timemodified = $now;
            $DB->update_record('local_web3talents_app', $applicant);

            self::log_event('student_account_created', $actorid, $course->id, [
                'applicantid' => $applicant->id,
                'userid' => $userid,
                'email' => $applicant->email,
                'cohortid' => $applicant->cohortid,
            ]);
            $transaction->allow_commit();
        } catch (\Throwable $exception) {
            $transaction->rollback($exception);
        }

        // Mail delivery happens after the commit: a bounced activation email is a warning,
        // not a reason to throw away a valid account. Use resend_activation_email() to retry.
        $user->activationemailsent = self::send_activation_email($applicant, $user, $actorid);

        return $user;
    }

    /**
     * Send the activation email for an applicant account and record the send time.
     *
     * @param stdClass $applicant Applicant record.
     * @param stdClass $user Moodle user.
     * @param int $actorid User making the change.
     * @return bool Whether the email was accepted for delivery.
     */
    private static function send_activation_email(stdClass $applicant, stdClass $user, int $actorid): bool {
        global $DB, $CFG;

        $sent = false;
        try {
            // Moodle's noemailever mode returns success without sending. Do not mark that as delivery.
            if (empty($CFG->noemailever)) {
                $sent = (bool)setnew_password_and_mail($user);
            }
        } catch (\Throwable $exception) {
            $sent = false;
        }

        if ($sent) {
            $DB->set_field('local_web3talents_app', 'activationemailsenttime', time(), ['id' => $applicant->id]);
            return true;
        }

        self::log_event('student_activation_email_failed', $actorid, null, [
            'applicantid' => (int)$applicant->id,
            'userid' => (int)$user->id,
        ]);
        return false;
    }

    /**
     * Resend the activation email for an applicant whose account already exists.
     *
     * @param int $applicantid Accepted-applicant record id.
     * @param int|null $actorid User making the change.
     * @return bool Whether the email was accepted for delivery.
     */
    public static function resend_activation_email(int $applicantid, ?int $actorid = null): bool {
        global $CFG, $DB, $USER;

        application_review_service::require_reviewer(true);
        require_once($CFG->dirroot . '/user/lib.php');

        $actorid = (int)$USER->id;
        $applicant = $DB->get_record('local_web3talents_app', ['id' => $applicantid], '*', MUST_EXIST);
        $lock = application_review_service::lock($applicant->email);
        try {
            $applicant = $DB->get_record('local_web3talents_app', ['id' => $applicantid], '*', MUST_EXIST);
            if (empty($applicant->userid)) {
                throw new moodle_exception('error_applicant_has_no_account', 'local_web3talents');
            }
            $user = $DB->get_record('user', ['id' => $applicant->userid, 'deleted' => 0], '*', MUST_EXIST);

            return self::send_activation_email($applicant, $user, $actorid);
        } finally {
            $lock->release();
        }
    }

    /**
     * Import mapped rows.
     *
     * @param array $rows Applicant rows.
     * @param string $source Source label.
     * @param int|null $actorid User making the change.
     * @return array
     */
    private static function import_rows(array $rows, string $source, ?int $actorid): array {
        global $DB;

        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            if (self::row_is_empty($row)) {
                continue;
            }

            try {
                $email = self::normalise_email($row['email'] ?? '');
                $exists = $DB->record_exists('local_web3talents_app', ['email' => $email]);
                self::upsert_applicant((object)$row, $source, $actorid);
                $exists ? $updated++ : $created++;
            } catch (\Throwable $exception) {
                $errors[] = 'Row ' . ($index + 2) . ': ' . $exception->getMessage();
            }
        }

        return ['created' => $created, 'updated' => $updated, 'errors' => $errors];
    }

    /**
     * Normalise import headers.
     *
     * @param array $headers Raw headers.
     * @return array
     */
    private static function normalise_headers(array $headers): array {
        $normalised = [];
        foreach ($headers as $index => $header) {
            $key = strtolower(trim((string)$header));
            $key = preg_replace('/[^a-z0-9]+/', '', $key);
            $normalised[$index] = match ($key) {
                'first', 'firstname', 'givenname' => 'firstname',
                'last', 'lastname', 'surname', 'familyname' => 'lastname',
                'mail', 'email', 'emailaddress' => 'email',
                'cohort', 'cohortid', 'cohortidentifier' => 'cohortid',
                'state', 'status' => 'status',
                'note', 'notes' => 'notes',
                default => $key,
            };
        }
        return $normalised;
    }

    /**
     * Validate required import headers.
     *
     * @param array $headers Normalised headers.
     */
    private static function validate_headers(array $headers): void {
        foreach (['firstname', 'lastname', 'email'] as $required) {
            if (!in_array($required, $headers, true)) {
                throw new moodle_exception('error_missing_required_columns', 'local_web3talents');
            }
        }
    }

    /**
     * Convert a data line to an associative row.
     *
     * @param array $headers Headers.
     * @param array $line Values.
     * @return array
     */
    private static function row_from_line(array $headers, array $line): array {
        $row = [];
        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }
            $row[$header] = trim((string)($line[$index] ?? ''));
        }
        if (empty($row['cohortid'])) {
            $row['cohortid'] = 'fundamentals';
        }
        if (empty($row['status'])) {
            $row['status'] = self::STATUS_ACCEPTED;
        }
        return $row;
    }

    /**
     * Check whether an imported row is empty.
     *
     * @param array $row Row.
     * @return bool
     */
    private static function row_is_empty(array $row): bool {
        return trim(implode('', array_map('strval', $row))) === '';
    }

    /**
     * Get the configured fundamentals course.
     *
     * @return stdClass
     */
    private static function get_configured_course(): stdClass {
        global $DB;

        $shortname = get_config('local_web3talents', 'fundamentals_course_shortname') ?: 'W3T-FUNDAMENTALS-DEV';
        $course = $DB->get_record('course', ['shortname' => $shortname]);
        if (!$course) {
            throw new moodle_exception('error_course_missing', 'local_web3talents');
        }
        return $course;
    }

    /**
     * Get the Moodle student role id.
     *
     * @return int
     */
    private static function student_role_id(): int {
        global $DB;

        return (int)$DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
    }

    /**
     * Generate a unique Moodle username from applicant email.
     *
     * @param stdClass $applicant Applicant.
     * @return string
     */
    private static function generate_username(stdClass $applicant): string {
        global $DB;

        $localpart = strstr($applicant->email, '@', true) ?: 'student';
        $base = strtolower(preg_replace('/[^a-z0-9._-]+/', '.', $localpart));
        $base = trim($base, '._-') ?: 'student';
        $base = 'w3t.' . $base;
        $username = substr($base, 0, 80);
        $suffix = 1;
        while ($DB->record_exists('user', ['username' => $username, 'deleted' => 0])) {
            $tail = '.' . $suffix++;
            $username = substr($base, 0, 80 - strlen($tail)) . $tail;
        }
        return $username;
    }

    /**
     * Normalise an email address.
     *
     * @param string $email Email.
     * @return string
     */
    private static function normalise_email(string $email): string {
        return strtolower(trim($email));
    }

    /**
     * Normalise a status value.
     *
     * @param string $status Status.
     * @return string
     */
    private static function normalise_status(string $status): string {
        $status = strtolower(trim($status));
        $status = str_replace([' ', '-', '_'], '', $status);
        return array_key_exists($status, self::statuses()) ? $status : self::STATUS_ACCEPTED;
    }

    /**
     * Require a non-empty text field.
     *
     * @param string $value Value.
     * @return string
     */
    private static function required_text(string $value): string {
        $value = trim($value);
        if ($value === '') {
            throw new moodle_exception('required');
        }
        return $value;
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
