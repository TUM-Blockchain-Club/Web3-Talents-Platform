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
    /** Applies to automatic invitations and explicit resend actions, not core password resets. */
    public static function activation_emails_enabled(): bool {
        return get_config('local_web3talents', 'activation_emails_enabled') === '1';
    }

    public const STATUS_PENDING = 'pending';
    public const STATUS_DISCUSSION = 'discussion';
    public const STATUS_REJECTED = 'rejected';
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
            self::STATUS_REJECTED => get_string('applicant_status_rejected', 'local_web3talents'),
            self::STATUS_ACCEPTED => get_string('applicant_status_accepted', 'local_web3talents'),
            self::STATUS_ACCOUNT_CREATED => get_string('applicant_status_accountcreated', 'local_web3talents'),
            self::STATUS_ACCOUNT_ACTIVATED => get_string('applicant_status_accountactivated', 'local_web3talents'),
            self::STATUS_DEFERRED => get_string('applicant_status_deferred', 'local_web3talents'),
            self::STATUS_REMOVED => get_string('applicant_status_removed', 'local_web3talents'),
        ];
    }

    /** Queue filters are separate from persisted workflow statuses. */
    public static function review_filters(): array {
        return [
            'toreview' => get_string('review_filter_toreview', 'local_web3talents'),
            'allaccepted' => get_string('review_filter_allaccepted', 'local_web3talents'),
            'setupactivated' => get_string('setup_activated', 'local_web3talents'),
            'setuppending' => get_string('setup_pending', 'local_web3talents'),
        ] + self::statuses();
    }

    /** Use the same status membership for overview totals and aggregate filters. */
    private static function review_status_groups(): array {
        return [
            'allaccepted' => [self::STATUS_ACCEPTED, self::STATUS_ACCOUNT_CREATED, self::STATUS_ACCOUNT_ACTIVATED],
            'toreview' => [self::STATUS_PENDING, self::STATUS_DISCUSSION],
        ];
    }

    /** Global queue totals, independent of search, pagination and selected filter. */
    public static function applicant_overview(): array {
        global $DB;
        $totals = ['total' => 0, 'accepted' => 0, 'rejected' => 0, 'toreview' => 0, 'other' => 0];
        $groups = self::review_status_groups();
        $counts = $DB->get_records_sql('SELECT status, COUNT(1) AS applicantcount
            FROM {local_web3talents_app} GROUP BY status');
        foreach ($counts as $row) {
            $count = (int)$row->applicantcount;
            $bucket = match (true) {
                in_array($row->status, $groups['allaccepted'], true) => 'accepted',
                in_array($row->status, $groups['toreview'], true) => 'toreview',
                $row->status === self::STATUS_REJECTED => 'rejected',
                default => 'other',
            };
            $totals['total'] += $count;
            $totals[$bucket] += $count;
        }
        return $totals;
    }

    /** Current participant applicants only; no mentors, deleted accounts or non-applicant users. */
    public static function account_setup_rows(): array {
        global $DB;
        $course = participation_service::get_configured_course();
        $context = \context_course::instance($course->id);
        $students = array_filter(participation_service::get_students($course),
            fn($u) => !$u->suspended && !is_siteadmin($u)
                && !has_capability('local/web3talents:viewmentorrooms', $context, $u->id)
                && !has_capability('local/web3talents:manage', $context, $u->id));
        if (!$students) { return []; }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($students), SQL_PARAMS_NAMED, 'setupuser');
        $params['setupcreated'] = self::STATUS_ACCOUNT_CREATED;
        $params['setupactivated'] = self::STATUS_ACCOUNT_ACTIVATED;
        $rows = $DB->get_records_sql("SELECT id, userid, status FROM {local_web3talents_app}
            WHERE userid $insql AND status IN (:setupcreated, :setupactivated) ORDER BY id ASC", $params);
        // Match the first applicant record used by the agreement workflow, avoiding duplicate counts.
        $seen = [];
        foreach ($rows as $id => $row) {
            if (isset($seen[$row->userid])) { unset($rows[$id]); }
            $seen[$row->userid] = true;
        }
        return $rows;
    }

    /** Use the recorded first agreement time, never the editable application modification date. */
    public static function activation_times(array $userids): array {
        global $DB;
        $userids = array_values(array_unique(array_filter(array_map('intval', $userids))));
        if (!$userids) { return []; }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'activationuser');
        return $DB->get_records_sql("SELECT userid, MIN(agreedtime) AS activatedtime
            FROM {local_web3talents_agree} WHERE userid $insql GROUP BY userid", $params);
    }

    public static function account_setup_overview(): array {
        $rows = self::account_setup_rows();
        $activated = array_filter($rows, fn($row) => $row->status === self::STATUS_ACCOUNT_ACTIVATED);
        $times = self::activation_times(array_column($activated, 'userid'));
        return ['activated' => count($activated), 'pending' => count($rows) - count($activated),
            'total' => count($rows), 'lastactivation' => $times ? max(array_column($times, 'activatedtime')) : 0];
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
    public static function search_applicants(string $query = '', int $limit = 100, int $offset = 0, string $status = ''): array {
        global $DB;
        [$where, $params] = self::applicant_search_filter($query, $status);
        $records = $DB->get_records_select(
            'local_web3talents_app',
            $where,
            $params,
            'id ASC', // Stable queue: reviews never reorder entries; new applications append.
            '*',
            $offset,
            $limit
        );
        $times = self::activation_times(array_column($records, 'userid'));
        foreach ($records as $record) {
            $record->activatedtime = $record->status === self::STATUS_ACCOUNT_ACTIVATED
                ? (int)($times[$record->userid]->activatedtime ?? 0) : 0;
        }
        return $records;
    }

    /** Count the same filtered records as search_applicants, without loading responses. */
    public static function count_applicants(string $query = '', string $status = ''): int {
        global $DB;
        [$where, $params] = self::applicant_search_filter($query, $status);
        return $DB->count_records_select('local_web3talents_app', $where, $params);
    }

    private static function applicant_search_filter(string $query, string $status = ''): array {
        global $DB;
        if ($status !== '' && !array_key_exists($status, self::review_filters())) {
            throw new \invalid_parameter_exception('Unknown applicant status.');
        }
        $statuswhere = $status === '' ? '' : 'status = :filterstatus';
        $statusparams = $status === '' ? [] : ['filterstatus' => $status];
        if (in_array($status, ['setupactivated', 'setuppending'], true)) {
            $target = $status === 'setupactivated' ? self::STATUS_ACCOUNT_ACTIVATED : self::STATUS_ACCOUNT_CREATED;
            $ids = array_keys(array_filter(self::account_setup_rows(), fn($row) => $row->status === $target));
            if ($ids) {
                [$insql, $statusparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'setupapp');
                $statuswhere = 'id ' . $insql;
            } else {
                $statuswhere = '1 = 0';
                $statusparams = [];
            }
        }
        $groups = self::review_status_groups();
        if (isset($groups[$status])) {
            $statusparams = [];
            foreach ($groups[$status] as $index => $member) {
                $statusparams['filterstatus' . $index] = $member;
            }
            $statuswhere = 'status IN (:' . implode(', :', array_keys($statusparams)) . ')';
        }
        $query = trim($query);
        if ($query === '') {
            return [$statuswhere, $statusparams];
        }
        $like = '%' . $DB->sql_like_escape($query) . '%';
        $conditions = [
            $DB->sql_like('firstname', ':firstname', false),
            $DB->sql_like('lastname', ':lastname', false),
            $DB->sql_like('email', ':email', false),
        ];
        return ['(' . implode(' OR ', $conditions) . ')' . ($statuswhere ? ' AND ' . $statuswhere : ''),
            ['firstname' => $like, 'lastname' => $like, 'email' => $like] + $statusparams];
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

            if (!self::enrol_reviewed_student($course->id, $userid, $roleid)) {
                throw new moodle_exception('error_enrol_failed', 'local_web3talents');
            }

            $applicant->userid = $userid;
            $applicant->status = self::STATUS_ACCOUNT_CREATED;
            $applicant->accountcreatedtime = $now;
            // Do not expire an account while its invitation is deliberately held.
            $applicant->retentionuntil = self::activation_emails_enabled() ? $now + 30 * DAYSECS : null;
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

    /** The branded invitation is the only welcome email sent by this workflow. */
    private static function enrol_reviewed_student(int $courseid, int $userid, int $roleid): bool {
        global $DB;
        if (!enrol_is_enabled('manual') || !($plugin = enrol_get_plugin('manual')) ||
                !$DB->record_exists('role', ['id' => $roleid])) {
            return false;
        }
        $instances = $DB->get_records('enrol',
            ['enrol' => 'manual', 'courseid' => $courseid, 'status' => ENROL_INSTANCE_ENABLED], 'sortorder,id ASC');
        if (!$instances) {
            return false;
        }
        // The welcome-message hook receives this instance. Do not alter the stored course settings.
        $instance = clone reset($instances);
        $instance->customint1 = ENROL_DO_NOT_SEND_EMAIL;
        $plugin->enrol_user($instance, $userid, $roleid);
        return true;
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

        if (!self::activation_emails_enabled()) {
            // Check before generating a token or calling the mailer.
            self::log_event('student_activation_email_paused', $actorid, null, [
                'applicantid' => (int)$applicant->id, 'userid' => (int)$user->id,
            ]);
            return false;
        }
        $sent = false;
        try {
            // Moodle's noemailever mode returns success without sending. Do not mark that as delivery.
            if (empty($CFG->noemailever)) {
                $sent = invitation_mailer::send($user);
            }
        } catch (\Throwable $exception) {
            $sent = false;
        }

        if ($sent) {
            $DB->set_field('local_web3talents_app', 'activationemailsenttime', time(), ['id' => $applicant->id]);
            if (empty($applicant->retentionuntil)) {
                $DB->set_field('local_web3talents_app', 'retentionuntil', time() + 30 * DAYSECS,
                    ['id' => $applicant->id]);
            }
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

        $shortname = get_config('local_web3talents', 'fundamentals_course_shortname') ?: 'Blockchain Fundamentals 1';
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
