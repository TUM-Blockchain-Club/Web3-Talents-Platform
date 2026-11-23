<?php
namespace local_web3talents;

defined('MOODLE_INTERNAL') || die();

/** Start the course index closed, then preserve the user's choices during the session. */
final class course_hooks {
    public static function initialise_index(\core\hook\output\before_http_headers $hook): void {
        global $CFG, $PAGE, $SESSION;
        if (strpos($PAGE->pagetype, 'course-view-') !== 0 || !isloggedin() || isguestuser() ||
                $PAGE->user_is_editing()) {
            return;
        }
        require_once($CFG->dirroot . '/local/web3talents/lib.php');
        $course = local_web3talents_get_configured_course();
        if (!$course || (int)$PAGE->course->id !== (int)$course->id || !can_access_course($course)) {
            return;
        }
        $key = 'web3talents_index_started_' . $course->id;
        if (!empty($SESSION->$key)) {
            return;
        }
        $ids = array_map(static fn($section) => (int)$section->id,
            array_values(get_fast_modinfo($course)->get_section_info_all()));
        // Moodle's API preserves contentcollapsed and invalidates the index-state cache.
        course_get_format($course)->set_sections_preference('indexcollapsed', $ids);
        $SESSION->$key = true;
    }
}
