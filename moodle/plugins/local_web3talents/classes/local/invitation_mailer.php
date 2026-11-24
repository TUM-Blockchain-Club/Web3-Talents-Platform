<?php
// This file is part of Moodle - http://moodle.org/

namespace local_web3talents\local;

use stdClass;

/** Send an invitation without replacing the user's current password. */
final class invitation_mailer {
    public static function send(stdClass $user): bool {
        global $CFG, $DB;

        // Fail closed even if called outside the approval/resend controller.
        if (!applicant_service::activation_emails_enabled() || !empty($CFG->noemailever) ||
                !empty($CFG->divertallemailsto) || !empty($CFG->forgottenpasswordurl) ||
                parse_url($CFG->wwwroot, PHP_URL_SCHEME) !== 'https' ||
                empty($user->id) || $user->id <= 0 || empty($user->confirmed) ||
                !empty($user->deleted) || !empty($user->suspended) || !empty($user->emailstop) ||
                ($user->auth ?? '') !== 'manual' || !validate_email($user->email ?? '') ||
                !validate_email($CFG->noreplyaddress ?? '') || (int)($CFG->pwresettime ?? 0) <= 0) {
            return false;
        }
        require_once($CFG->dirroot . '/login/lib.php');

        $reset = null;
        $sent = false;
        try {
            // Moodle 5.2.5 invalidates earlier links when a new token is issued.
            // Never restore superseded tokens after an SMTP failure.
            $reset = core_login_generate_password_reset($user);
            $url = new \moodle_url('/login/forgot_password.php', ['token' => $reset->token]);
            $fallback = new \moodle_url('/login/forgot_password.php');
            $message = invitation_template::render($user, $url->out(false), $fallback->out(false), (int)$CFG->pwresettime);
            $sent = (bool)email_to_user($user, 'Web3 Talents', $message['subject'], $message['text'],
                $message['html'], '', '', false, $CFG->noreplyaddress, 'Web3 Talents');
        } finally {
            // A failed attempt must not leave an unseen usable token behind.
            if (!$sent && $reset) {
                $DB->delete_records('user_password_resets', ['id' => $reset->id, 'userid' => $user->id]);
            }
        }
        if ($sent) {
            $DB->delete_records_select('user_password_resets', 'userid = :userid AND id <> :id',
                ['userid' => $user->id, 'id' => $reset->id]);
        }
        return $sent;
    }
}
