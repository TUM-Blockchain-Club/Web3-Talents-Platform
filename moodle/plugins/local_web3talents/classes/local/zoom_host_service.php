<?php
namespace local_web3talents\local;

defined('MOODLE_INTERNAL') || die();

/** Delegation to one configured host, disabled unless explicitly provisioned. */
class zoom_host_service {
    public static function configuration(): array {
        $config = [];
        foreach (['account_id', 'client_id', 'client_secret', 'host_id', 'meeting_id'] as $key) {
            $config[$key] = trim((string)getenv('WEB3T_ZOOM_' . strtoupper($key)));
        }
        return $config;
    }

    public static function authorised(): bool {
        global $USER;
        $ids = array_filter(array_map('trim', explode(',', (string)getenv('WEB3T_ZOOM_ORGANISER_IDS'))),
            static fn($id) => ctype_digit($id) && (int)$id > 0);
        // Site admins also need explicit allowlisting. Email domains are not an authorisation boundary.
        return isloggedin() && !isguestuser() && empty($USER->suspended) && empty($USER->deleted) &&
            in_array((string)$USER->id, $ids, true) &&
            has_capability('local/web3talents:manage', \local_web3talents_admin_context());
    }

    /** A safe UI string key, never credentials or upstream response details. */
    public static function blocker(): ?string {
        global $CFG;
        $secure = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') || !empty($CFG->sslproxy);
        if (!is_https() || !$secure || empty($CFG->cookiesecure)) {
            return 'zoom_httpsrequired';
        }
        if (getenv('WEB3T_ZOOM_HOST_ENABLED') !== '1') {
            return 'zoom_disabled';
        }
        foreach (self::configuration() as $value) {
            if ($value === '') {
                return 'zoom_notconfigured';
            }
        }
        if (!preg_match('/^[0-9]{9,11}$/D', self::configuration()['meeting_id'])) {
            return 'zoom_notconfigured';
        }
        return null;
    }

    /** Per-user throttle across browser sessions, plus a lock against parallel requests. */
    public static function launch(?zoom_client $client = null): string {
        global $USER, $DB;
        if (!self::authorised()) {
            throw new \moodle_exception('zoom_forbidden', 'local_web3talents');
        }
        if ($blocker = self::blocker()) {
            throw new \moodle_exception($blocker, 'local_web3talents');
        }
        $lock = \core\lock\lock_config::get_lock_factory('local_web3talents')->get_lock('zoomhost:' . $USER->id, 0);
        if (!$lock) {
            throw new \moodle_exception('zoom_wait', 'local_web3talents');
        }
        try {
            $now = time();
            if ((int)get_user_preferences('local_web3talents_zoom_lastlaunch', 0) > $now - 30) {
                throw new \moodle_exception('zoom_wait', 'local_web3talents');
            }
            set_user_preference('local_web3talents_zoom_lastlaunch', $now);
            $course = \local_web3talents_get_configured_course();
            // Only audit the request. Never store the host URL, credentials or API response.
            $DB->insert_record('local_web3talents_log', (object)[
                'userid' => $USER->id, 'courseid' => $course ? $course->id : null,
                'eventtype' => 'zoom_host_link_requested', 'metadata' => '{}', 'timecreated' => $now,
            ]);
            try {
                return ($client ?? new zoom_client(self::configuration()))->start_url();
            } catch (\RuntimeException $e) {
                $code = in_array($e->getMessage(), ['zoom_wronghost', 'zoom_notconfigured'], true)
                    ? $e->getMessage() : 'zoom_unavailable';
                throw new \moodle_exception($code, 'local_web3talents');
            }
        } finally {
            $lock->release();
        }
    }
}
