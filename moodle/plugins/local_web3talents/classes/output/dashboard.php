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
        $enabled = (bool)get_config('local_web3talents', 'enabled');
        $courseshortname = get_config('local_web3talents', 'fundamentals_course_shortname') ?: 'W3T-FUNDAMENTALS-DEV';

        return [
            'intro' => get_string('dashboard_intro', 'local_web3talents'),
            'enabled' => $enabled,
            'applicantsurl' => (new \moodle_url('/local/web3talents/applicants.php'))->out(false),
            'applicantslabel' => get_string('applicants', 'local_web3talents'),
            'coursestateurl' => (new \moodle_url('/local/web3talents/course_state.php'))->out(false),
            'coursestatelabel' => get_string('course_state', 'local_web3talents'),
            'topicroundsurl' => (new \moodle_url('/local/web3talents/topic_rounds.php'))->out(false),
            'topicroundslabel' => get_string('topic_rounds', 'local_web3talents'),
            'choosetopicurl' => (new \moodle_url('/local/web3talents/choose_topic.php'))->out(false),
            'choosetopiclabel' => get_string('choose_weekly_topic', 'local_web3talents'),
            'status' => get_string(
                $enabled ? 'dashboard_status_enabled' : 'dashboard_status_disabled',
                'local_web3talents'
            ),
            'courselabel' => get_string('dashboard_course_shortname', 'local_web3talents'),
            'courseshortname' => $courseshortname,
            'nextstepslabel' => get_string('dashboard_next_steps', 'local_web3talents'),
            'nextsteps' => [
                ['label' => get_string('dashboard_next_applicants', 'local_web3talents')],
                ['label' => get_string('dashboard_next_policy', 'local_web3talents')],
                ['label' => get_string('dashboard_next_rooms', 'local_web3talents')],
            ],
        ];
    }
}
