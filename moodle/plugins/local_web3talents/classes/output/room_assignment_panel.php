<?php
// This file is part of Moodle - http://moodle.org/
namespace local_web3talents\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Shared room tables and checks for full-page and asynchronous responses.
 *
 * @package local_web3talents
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class room_assignment_panel {
    /** Render the current saved state. @param array $state Result state. @return string */
    public static function render(array $state): string {
        $url = new \moodle_url('/local/web3talents/room_assignments.php');
        $canmove = has_capability('local/web3talents:managerooms',
            \context_course::instance($state['round']->courseid));
        $html = \html_writer::start_div('mb-4', ['data-region' => 'room-checks']);
        $html .= \html_writer::tag('h3', get_string('room_assignment_checks', 'local_web3talents'));
        if ($state['warnings']) {
            $html .= \html_writer::tag('p', get_string('room_assignment_checks_help', 'local_web3talents'));
            $html .= \html_writer::div(\html_writer::alist(array_map('s', $state['warnings'])), 'alert alert-warning');
        } else {
            $html .= \html_writer::div(get_string('room_assignment_checks_ok', 'local_web3talents'), 'alert alert-success');
        }
        $html .= \html_writer::end_div();
        $options = [];
        foreach ($state['rooms'] as $roomstate) {
            $options[(int)$roomstate['room']->id] = $roomstate['room']->roomname;
        }
        foreach ($state['rooms'] as $roomstate) {
            $html .= \html_writer::start_div('generalbox mb-3', ['data-room-id' => $roomstate['room']->id]);
            $html .= \html_writer::tag('h3', s($roomstate['room']->roomname));
            $table = new \html_table();
            $table->head = [get_string('group', 'local_web3talents'), get_string('members', 'local_web3talents'),
                get_string('topic', 'local_web3talents'), get_string('assignment_reason', 'local_web3talents')];
            if ($canmove) {
                $table->head[] = get_string('move_to_room', 'local_web3talents');
            }
            foreach ($roomstate['assignments'] as $assignment) {
                $row = [s($assignment['pgroup']->name), s(implode(', ', array_map('fullname', $assignment['members']))),
                    $assignment['topic'] ? format_string($assignment['topic']->name) : get_string('none'),
                    s($assignment['reason'])];
                if ($canmove) {
                    $groupid = (int)$assignment['pgroup']->id;
                    $form = \html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false),
                        'class' => 'd-flex gap-2', 'data-room-move' => $groupid]);
                    foreach (['action' => 'move', 'roundid' => $state['round']->id, 'resultid' => $state['result']->id,
                        'pgroupid' => $groupid, 'sesskey' => sesskey()] as $name => $value) {
                        $form .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
                    }
                    $form .= \html_writer::select($options, 'targetroomid', $roomstate['room']->id, false,
                        ['id' => 'move-room-' . $groupid, 'class' => 'form-select',
                            'aria-label' => get_string('room_move_label', 'local_web3talents', $assignment['pgroup']->name)]);
                    $form .= \html_writer::tag('button', get_string('move', 'local_web3talents'),
                        ['type' => 'submit', 'class' => 'btn btn-secondary btn-sm']);
                    $form .= \html_writer::end_tag('form');
                    $row[] = $form;
                }
                $table->data[] = $row;
            }
            $html .= \html_writer::table($table) . \html_writer::end_div();
        }
        return $html;
    }
}
