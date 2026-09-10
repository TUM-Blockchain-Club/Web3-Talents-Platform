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
 * Task-oriented landing page for Web3 Talents administrators.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard implements renderable, templatable {
    /**
     * Build a link without changing the destination's permissions or workflow.
     *
     * @param string $path Moodle-relative path.
     * @param string $label Language string key.
     * @param string $description Optional explanatory string key.
     * @param array $params URL parameters.
     * @return array
     */
    private function link(string $path, string $label, string $description = '', array $params = []): array {
        return [
            'url' => (new \moodle_url($path, $params))->out(false),
            'label' => get_string($label, 'local_web3talents'),
            'description' => $description ? get_string($description, 'local_web3talents') : '',
        ];
    }

    /**
     * Export real configuration and task links; step numbers are guidance, not progress.
     *
     * @param renderer_base $output Renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $DB;

        $enabled = (bool)get_config('local_web3talents', 'enabled');
        $shortname = get_config('local_web3talents', 'fundamentals_course_shortname') ?: 'W3T-FUNDAMENTALS-DEV';
        $course = $DB->get_record('course', ['shortname' => $shortname], 'id, fullname, shortname, category');

        $weeklylinks = [
            $this->link('/local/web3talents/topic_rounds.php', 'dashboard_topics_title', 'dashboard_topics_help'),
            $this->link('/local/web3talents/room_assignments.php', 'dashboard_rooms_title', 'dashboard_rooms_help'),
            $this->link('/local/web3talents/participation.php', 'dashboard_attendance_title', 'dashboard_attendance_help'),
        ];
        foreach ($weeklylinks as $index => &$link) {
            $link['step'] = $index + 1;
        }
        unset($link);

        $setuplinks = [
            $this->link('/local/web3talents/applicants.php', 'applicants', 'dashboard_applicants_help'),
        ];
        $advancedlinks = [
            $this->link('/local/web3talents/course_state.php', 'dashboard_check_data', 'dashboard_check_data_help'),
            $this->link('/admin/settings.php', 'settings', 'dashboard_settings_help',
                ['section' => 'local_web3talents_settings']),
            $this->link('/admin/tool/task/scheduledtasks.php', 'dashboard_scheduled_tasks', 'dashboard_tasks_help'),
        ];
        if ($course) {
            $setuplinks[] = $this->link('/user/index.php', 'dashboard_course_participants',
                'dashboard_participants_help', ['id' => $course->id]);
            $setuplinks[] = $this->link('/course/edit.php', 'dashboard_course_settings',
                'dashboard_course_settings_help', ['id' => $course->id]);
            $advancedlinks[] = $this->link('/group/index.php', 'review_groups',
                'dashboard_groups_help', ['id' => $course->id]);
            $advancedlinks[] = $this->link('/course/management.php', 'dashboard_manage_course_category',
                'dashboard_category_help', ['categoryid' => $course->category]);
        }

        return [
            'enabled' => $enabled,
            'status' => get_string($enabled ? 'dashboard_status_enabled' : 'dashboard_status_disabled', 'local_web3talents'),
            'coursefound' => (bool)$course,
            'coursename' => $course ? format_string($course->fullname) : '',
            'courseurl' => $course ? (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false) : '',
            'settingsurl' => (new \moodle_url('/admin/settings.php', ['section' => 'local_web3talents_settings']))->out(false),
            'coursenotfound' => get_string('dashboard_course_not_found', 'local_web3talents', $shortname),
            'weeklylinks' => $weeklylinks,
            'setuplinks' => $setuplinks,
            'mentorlinks' => [
                $this->link('/local/web3talents/mentor_availability.php', 'mentor_availability', 'dashboard_availability_help'),
                $this->link('/local/web3talents/mentor_grading.php', 'mentor_grading', 'dashboard_grading_help'),
            ],
            'advancedlinks' => $advancedlinks,
            'viewlinks' => [
                $this->link('/local/web3talents/choose_topic.php', 'choose_weekly_topic'),
                $this->link('/local/web3talents/my_room.php', 'my_room_assignment'),
                $this->link('/local/web3talents/mentor_rooms.php', 'mentor_room_assignments'),
            ],
        ];
    }
}
