<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Web3 Talents dashboard shortcut block.
 *
 * @package    block_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Dashboard shortcuts for Web3 Talents roles.
 */
class block_web3talents extends block_base {
    /**
     * Initialise the block.
     */
    public function init(): void {
        $this->title = get_string('pluginname', 'block_web3talents');
    }

    /**
     * Allow this block on the Moodle dashboard.
     *
     * @return array
     */
    public function applicable_formats(): array {
        return ['my' => true];
    }

    /**
     * Keep one Web3 Talents block per dashboard.
     *
     * @return bool
     */
    public function instance_allow_multiple(): bool {
        return false;
    }

    /**
     * Render role-aware Web3 Talents shortcuts.
     *
     * @return stdClass|null
     */
    public function get_content(): ?stdClass {
        global $CFG;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        $lib = $CFG->dirroot . '/local/web3talents/lib.php';
        if (!file_exists($lib)) {
            return $this->content;
        }
        require_once($lib);

        $links = $this->shortcut_links();
        if (!$links) {
            return $this->content;
        }

        $items = [];
        foreach ($links as $link) {
            $items[] = html_writer::tag(
                'li',
                html_writer::link($link['url'], $link['label'], ['class' => $link['primary'] ? 'fw-bold' : ''])
            );
        }

        $this->content->text = html_writer::tag('ul', implode('', $items), ['class' => 'list-unstyled mb-0']);
        return $this->content;
    }

    /**
     * Build shortcuts for the current user's permissions.
     *
     * @return array
     */
    private function shortcut_links(): array {
        $systemcontext = context_system::instance();
        $course = local_web3talents_get_configured_course();
        $coursecontext = $course ? context_course::instance($course->id) : null;
        $links = [];

        if (has_capability('local/web3talents:manage', $systemcontext)) {
            $links[] = [
                'url' => new moodle_url('/local/web3talents/index.php'),
                'label' => get_string('block_open_dashboard', 'block_web3talents'),
                'primary' => true,
            ];
            if ($course) {
                $links[] = [
                    'url' => new moodle_url('/user/index.php', ['id' => $course->id]),
                    'label' => get_string('block_participants', 'block_web3talents'),
                    'primary' => false,
                ];
            }
            $links[] = [
                'url' => new moodle_url('/local/web3talents/applicants.php'),
                'label' => get_string('block_applicants', 'block_web3talents'),
                'primary' => false,
            ];
            $links[] = [
                'url' => new moodle_url('/local/web3talents/topic_rounds.php'),
                'label' => get_string('block_topic_rounds', 'block_web3talents'),
                'primary' => false,
            ];
            $links[] = [
                'url' => new moodle_url('/local/web3talents/room_assignments.php'),
                'label' => get_string('block_room_assignments', 'block_web3talents'),
                'primary' => false,
            ];
            $links[] = [
                'url' => new moodle_url('/local/web3talents/participation.php'),
                'label' => get_string('block_participation', 'block_web3talents'),
                'primary' => false,
            ];
            $links[] = [
                'url' => new moodle_url('/local/web3talents/mentor_availability.php'),
                'label' => get_string('block_mentor_availability', 'block_web3talents'),
                'primary' => false,
            ];
            return $links;
        }

        if ($coursecontext && has_capability('local/web3talents:viewmentorrooms', $coursecontext)) {
            $links[] = [
                'url' => new moodle_url('/local/web3talents/mentor_rooms.php'),
                'label' => get_string('block_mentor_rooms', 'block_web3talents'),
                'primary' => true,
            ];
            $links[] = [
                'url' => new moodle_url('/local/web3talents/participation.php'),
                'label' => get_string('block_participation', 'block_web3talents'),
                'primary' => false,
            ];
            $links[] = [
                'url' => new moodle_url('/local/web3talents/mentor_availability.php'),
                'label' => get_string('block_mentor_availability', 'block_web3talents'),
                'primary' => false,
            ];
        }

        if ($coursecontext && has_capability('local/web3talents:viewstudentrooms', $coursecontext)) {
            $links[] = [
                'url' => new moodle_url('/local/web3talents/choose_topic.php'),
                'label' => get_string('block_choose_topic', 'block_web3talents'),
                'primary' => true,
            ];
            $links[] = [
                'url' => new moodle_url('/local/web3talents/my_room.php'),
                'label' => get_string('block_my_room', 'block_web3talents'),
                'primary' => false,
            ];
        }

        return $links;
    }
}
