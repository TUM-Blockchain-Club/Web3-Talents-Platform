<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_web3talents\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;

/**
 * Dashboard renderable for the plugin landing page.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard implements renderable, templatable {
    /**
     * Export dashboard data for Mustache.
     *
     * @param renderer_base $output Renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $DB;

        $enabled = (bool)get_config('local_web3talents', 'enabled');
        $courseshortname = get_config('local_web3talents', 'fundamentals_course_shortname') ?: 'W3T-FUNDAMENTALS-DEV';
        $course = $DB->get_record('course', ['shortname' => $courseshortname], 'id, fullname, shortname, category');

        $workflowlinks = [
            [
                'url' => (new \moodle_url('/local/web3talents/applicants.php'))->out(false),
                'label' => get_string('applicants', 'local_web3talents'),
                'primary' => true,
            ],
            [
                'url' => (new \moodle_url('/local/web3talents/topic_rounds.php'))->out(false),
                'label' => get_string('topic_rounds', 'local_web3talents'),
            ],
            [
                'url' => (new \moodle_url('/local/web3talents/room_assignments.php'))->out(false),
                'label' => get_string('room_assignments', 'local_web3talents'),
            ],
            [
                'url' => (new \moodle_url('/local/web3talents/course_state.php'))->out(false),
                'label' => get_string('course_state', 'local_web3talents'),
            ],
        ];

        $courselinks = [];
        if ($course) {
            $courselinks = [
                [
                    'url' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                    'label' => get_string('dashboard_open_course', 'local_web3talents'),
                    'primary' => true,
                ],
                [
                    'url' => (new \moodle_url('/user/index.php', ['id' => $course->id]))->out(false),
                    'label' => get_string('dashboard_course_participants', 'local_web3talents'),
                ],
                [
                    'url' => (new \moodle_url('/group/index.php', ['id' => $course->id]))->out(false),
                    'label' => get_string('review_groups', 'local_web3talents'),
                ],
                [
                    'url' => (new \moodle_url('/course/edit.php', ['id' => $course->id]))->out(false),
                    'label' => get_string('dashboard_course_settings', 'local_web3talents'),
                ],
                [
                    'url' => (new \moodle_url('/course/management.php', ['categoryid' => $course->category]))->out(false),
                    'label' => get_string('dashboard_manage_course_category', 'local_web3talents'),
                ],
            ];
        }

        $systemlinks = [
            [
                'url' => (new \moodle_url('/admin/settings.php', ['section' => 'local_web3talents_settings']))->out(false),
                'label' => get_string('settings', 'local_web3talents'),
            ],
            [
                'url' => (new \moodle_url('/admin/tool/task/scheduledtasks.php'))->out(false),
                'label' => get_string('dashboard_scheduled_tasks', 'local_web3talents'),
            ],
        ];

        $viewlinks = [
            [
                'url' => (new \moodle_url('/local/web3talents/choose_topic.php'))->out(false),
                'label' => get_string('choose_weekly_topic', 'local_web3talents'),
            ],
            [
                'url' => (new \moodle_url('/local/web3talents/my_room.php'))->out(false),
                'label' => get_string('my_room_assignment', 'local_web3talents'),
            ],
            [
                'url' => (new \moodle_url('/local/web3talents/mentor_rooms.php'))->out(false),
                'label' => get_string('mentor_room_assignments', 'local_web3talents'),
            ],
        ];

        return [
            'intro' => get_string('dashboard_intro', 'local_web3talents'),
            'enabled' => $enabled,
            'status' => get_string(
                $enabled ? 'dashboard_status_enabled' : 'dashboard_status_disabled',
                'local_web3talents'
            ),
            'courselabel' => get_string('dashboard_course_shortname', 'local_web3talents'),
            'courseshortname' => $courseshortname,
            'coursefound' => (bool)$course,
            'coursename' => $course ? format_string($course->fullname) : '',
            'workflowlabel' => get_string('dashboard_workflows', 'local_web3talents'),
            'workflowlinks' => $workflowlinks,
            'courseadminlabel' => get_string('dashboard_course_administration', 'local_web3talents'),
            'courselinks' => $courselinks,
            'systemlabel' => get_string('dashboard_system_administration', 'local_web3talents'),
            'systemlinks' => $systemlinks,
            'viewlinkslabel' => get_string('dashboard_role_views', 'local_web3talents'),
            'viewlinks' => $viewlinks,
            'coursenotfound' => get_string('dashboard_course_not_found', 'local_web3talents', $courseshortname),
        ];
    }
}
