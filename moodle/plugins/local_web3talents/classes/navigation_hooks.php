<?php
// This file is part of Moodle - http://moodle.org/

namespace local_web3talents;

defined('MOODLE_INTERNAL') || die();

/** Capability-aware navigation for everyday cohort administration. */
final class navigation_hooks {
    public static function extend_primary(\core\hook\navigation\primary_extend $hook): void {
        global $CFG, $PAGE;

        if (!isloggedin() || isguestuser()) {
            return;
        }
        require_once($CFG->dirroot . '/local/web3talents/lib.php');
        if (!has_capability('local/web3talents:manage', local_web3talents_admin_context())) {
            return;
        }

        $navigation = $hook->get_primaryview();
        if ($settings = $navigation->get('siteadminnode')) {
            $settings->text = get_string('advanced_settings', 'local_web3talents');
            $settings->shorttext = $settings->text;
        }
        // Keep the personal Moodle dashboard available, but distinguish it from cohort management.
        if ($personal = $navigation->get('myhome')) {
            $personal->text = get_string('personal_dashboard', 'local_web3talents');
            $personal->shorttext = $personal->text;
        }
        if (!$navigation->get('local_web3talents_admin')) {
            $keys = $navigation->get_children_key_list();
            $navigation->add_node(\navigation_node::create(
                get_string('admin_dashboard', 'local_web3talents'),
                new \moodle_url('/local/web3talents/index.php'),
                \navigation_node::TYPE_CUSTOM,
                null,
                'local_web3talents_admin',
                new \pix_icon('i/dashboard', '')
            ), $keys[0] ?? null);
        }

        // These management screens otherwise highlight Moodle's technical settings tab.
        $managementpages = [
            'index.php', 'applicants.php', 'application_import.php', 'application_review.php',
            'course_state.php', 'topic_rounds.php', 'room_assignments.php', 'participation.php',
            'mentor_availability.php', 'mentor_grading.php',
        ];
        foreach ($managementpages as $page) {
            if ($PAGE->url->compare(new \moodle_url('/local/web3talents/' . $page), URL_MATCH_BASE)) {
                $PAGE->set_primary_active_tab('local_web3talents_admin');
                break;
            }
        }
    }
}
