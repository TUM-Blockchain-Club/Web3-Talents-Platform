<?php
namespace local_web3talents\local;

defined('MOODLE_INTERNAL') || die();

/** Human application reviews. No decision is derived from applicant answers. */
class application_review_service {
    public static function context(): \context {
        global $CFG;
        require_once($CFG->dirroot . '/local/web3talents/lib.php');
        return local_web3talents_admin_context();
    }

    public static function require_reviewer(bool $approve = false): void {
        global $USER;
        require_login();
        if (isguestuser() || empty($USER->id)) {
            throw new \moodle_exception('nopermissions', 'error');
        }
        require_capability('local/web3talents:manageacceptedapplicants', self::context());
        if ($approve) {
            require_capability('local/web3talents:createstudentaccounts', self::context());
        }
    }

    /** Shared lock serializes reviews, imports and account creation for this email. */
    public static function lock(string $email): \core\lock\lock {
        $factory = \core\lock\lock_config::get_lock_factory('local_web3talents_applications');
        $lock = $factory->get_lock(hash('sha256', strtolower(trim($email))), 10);
        if (!$lock) {
            throw new \moodle_exception('review_busy', 'local_web3talents');
        }
        return $lock;
    }

    public static function summary(int $id): array {
        global $DB;
        $approvals = 0;
        $objections = 0;
        foreach ($DB->get_records('local_w3t_app_review', ['applicantid' => $id]) as $review) {
            $user = $DB->get_record('user', ['id' => $review->reviewerid, 'deleted' => 0, 'suspended' => 0]);
            if (!$user || !has_capability('local/web3talents:manageacceptedapplicants', self::context(), $user)) {
                continue;
            }
            if ($review->decision === 'approve' &&
                    has_capability('local/web3talents:createstudentaccounts', self::context(), $user)) {
                $approvals++;
            } else if ($review->decision === 'reject') {
                $objections++;
            }
        }
        return ['approvals' => $approvals, 'objections' => $objections,
            'ready' => $approvals >= 2 && $objections === 0];
    }

    /** Only the signed-in reviewer can cast or withdraw their own decision. */
    public static function submit(int $id, string $decision, string $comment = ''): array {
        global $DB, $USER;
        self::require_reviewer($decision === 'approve');
        if (!in_array($decision, ['approve', 'reject', 'withdraw', 'comment'], true)) {
            throw new \invalid_parameter_exception('Unknown review action.');
        }
        $comment = trim($comment);
        if (mb_strlen($comment) > 10000 || (in_array($decision, ['reject', 'comment']) && $comment === '')) {
            throw new \moodle_exception('review_comment_required', 'local_web3talents');
        }
        $applicant = $DB->get_record('local_web3talents_app', ['id' => $id], '*', MUST_EXIST);
        $lock = self::lock($applicant->email);
        try {
            $transaction = $DB->start_delegated_transaction();
            try {
                $applicant = $DB->get_record('local_web3talents_app', ['id' => $id], '*', MUST_EXIST);
                if ($decision !== 'comment' && (!empty($applicant->userid) ||
                        in_array($applicant->status, ['removed', 'deferred'], true))) {
                    throw new \moodle_exception('review_closed', 'local_web3talents');
                }
                if ($decision !== 'comment') {
                    $existing = $DB->get_record('local_w3t_app_review', ['applicantid' => $id, 'reviewerid' => $USER->id]);
                    if ($decision === 'withdraw') {
                        $DB->delete_records('local_w3t_app_review', ['applicantid' => $id, 'reviewerid' => $USER->id]);
                    } else {
                        $record = (object)['applicantid' => $id, 'reviewerid' => $USER->id,
                            'decision' => $decision, 'timemodified' => time()];
                        if ($existing) {
                            $record->id = $existing->id;
                            $DB->update_record('local_w3t_app_review', $record);
                        } else {
                            $DB->insert_record('local_w3t_app_review', $record);
                        }
                    }
                }
                $DB->insert_record('local_w3t_app_comment', (object)[
                    'applicantid' => $id, 'reviewerid' => $USER->id, 'action' => $decision,
                    'body' => $comment, 'timecreated' => time(),
                ]);
                $summary = self::summary($id);
                if (empty($applicant->userid) && !in_array($applicant->status, ['removed', 'deferred'], true)) {
                    $applicant->status = $summary['objections'] ? 'discussion' : ($summary['ready'] ? 'accepted' : 'pending');
                    $applicant->timemodified = time();
                    $DB->update_record('local_web3talents_app', $applicant);
                }
                $transaction->allow_commit();
            } catch (\Throwable $e) {
                $transaction->rollback($e);
            }
        } finally {
            $lock->release();
        }
        // Recheck under the same lock in the account service. Mail runs after its DB commit.
        // Only an explicit approval triggers provisioning; comments must never create accounts.
        if ($decision === 'approve' && $summary['ready'] && empty($applicant->userid)) {
            try {
                $user = applicant_service::create_student_account($id);
                return ['status' => empty($user->activationemailsent) ? 'emailpending' : 'created'];
            } catch (\Throwable $e) {
                // Another simultaneous approval may have already completed provisioning.
                $latest = $DB->get_record('local_web3talents_app', ['id' => $id], '*', MUST_EXIST);
                if (!empty($latest->userid)) {
                    return ['status' => empty($latest->activationemailsenttime) ? 'emailpending' : 'created'];
                }
                // The decisions remain saved; the UI offers an explicit, safe provisioning retry.
                return ['status' => 'provisionpending'];
            }
        }
        return ['status' => 'saved'];
    }

    /** Existing records are never overwritten by re-import, even if answers changed. */
    public static function preview(array $rows): array {
        global $DB;
        $seen = [];
        $submissions = [];
        foreach ($rows as &$row) {
            $row['outcome'] = 'new';
            if ($row['error']) {
                $row['outcome'] = 'invalid';
            } else if (isset($seen[$row['email']]) || ($row['submissionid'] !== '' && isset($submissions[$row['submissionid']]))) {
                $row['outcome'] = 'duplicate';
            } else {
                $existing = $DB->get_record('local_web3talents_app', ['email' => $row['email']]);
                if (!$existing && $row['submissionid'] !== '') {
                    $existing = $DB->get_record('local_web3talents_app', ['submissionid' => $row['submissionid']]);
                }
                if ($existing) {
                    $row['outcome'] = (string)$existing->responses === $row['responses'] &&
                        $existing->email === $row['email'] && $existing->cohortid === $row['cohortid'] ? 'duplicate' : 'conflict';
                } else if ($DB->record_exists('user', ['email' => $row['email'], 'deleted' => 0])) {
                    $row['outcome'] = 'existinguser';
                }
            }
            $seen[$row['email']] = true;
            if ($row['submissionid'] !== '') {
                $submissions[$row['submissionid']] = true;
            }
        }
        unset($row);
        return $rows;
    }

    public static function import(array $rows): array {
        global $DB;
        self::require_reviewer();
        $result = ['created' => 0, 'skipped' => 0];
        // A global import lock also protects submission IDs shared by two different emails.
        $lock = self::lock('tally-import');
        try {
            $transaction = $DB->start_delegated_transaction();
            try {
                foreach (self::preview($rows) as $row) {
                    if ($row['outcome'] !== 'new') {
                        $result['skipped']++;
                        continue;
                    }
                    $applicant = applicant_service::upsert_applicant((object)$row, 'tally');
                    $result[$applicant->wasinserted ? 'created' : 'skipped']++;
                }
                $transaction->allow_commit();
            } catch (\Throwable $e) {
                $transaction->rollback($e);
            }
        } finally {
            $lock->release();
        }
        return $result;
    }
}
