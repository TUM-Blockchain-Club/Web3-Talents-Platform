<?php
// This file is part of Moodle - http://moodle.org/

namespace local_web3talents;

defined('MOODLE_INTERNAL') || die();

/** Course access on the personal dashboard without enrolling administrators as students. */
final class dashboard_hooks {
    public static function add_course_shortcut(\core\hook\output\before_http_headers $hook): void {
        global $CFG, $PAGE;

        if ($PAGE->pagetype !== 'my-index' || !isloggedin() || isguestuser()) {
            return;
        }
        require_once($CFG->dirroot . '/local/web3talents/lib.php');
        $course = local_web3talents_get_configured_course();
        if (!$course || !has_capability('local/web3talents:manage', \context_course::instance($course->id)) ||
                !can_access_course($course)) {
            return;
        }

        $block = new \block_contents();
        $block->attributes['id'] = 'local-web3talents-dashboard-course';
        $block->title = get_string('personal_dashboard_course', 'local_web3talents');
        $block->content = \html_writer::tag('h3', format_string($course->fullname, true, [
            'context' => \context_course::instance($course->id),
        ]), ['class' => 'h5 mb-2']);
        $block->content .= \html_writer::tag('p', get_string('personal_dashboard_course_help', 'local_web3talents'));
        $block->content .= \html_writer::link(new \moodle_url('/course/view.php', ['id' => $course->id]),
            get_string('dashboard_open_course', 'local_web3talents'), ['class' => 'btn btn-primary']);
        // Transient content appears before the user's blocks; no dashboard reset or DB write.
        $PAGE->blocks->add_fake_block($block, 'content');
    }
}
