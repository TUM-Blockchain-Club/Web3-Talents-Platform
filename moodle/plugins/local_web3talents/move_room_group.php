<?php
// This file is part of Moodle - http://moodle.org/
/**
 * Session-authenticated endpoint for in-place room moves.
 * @package local_web3talents
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/web3talents/lib.php');

use local_web3talents\local\room_assignment_service;
use local_web3talents\local\topic_round_service;
use local_web3talents\output\room_assignment_panel;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new moodle_exception('invalidrequest');
    }
    $course = topic_round_service::get_configured_course();
    require_login($course, false, null, false, true);
    require_sesskey();
    $context = context_course::instance($course->id);
    require_capability('local/web3talents:manage', $context);
    require_capability('local/web3talents:managerooms', $context);
    $PAGE->set_context($context);
    $resultid = required_param('resultid', PARAM_INT);
    $roundid = required_param('roundid', PARAM_INT);
    $groupid = required_param('pgroupid', PARAM_INT);
    $targetid = required_param('targetroomid', PARAM_INT);
    // Bind the supplied result to both this course and round; the service checks group/room ownership.
    if (!$DB->record_exists('local_w3t_room_result', ['id' => $resultid, 'courseid' => $course->id, 'roundid' => $roundid])) {
        throw new moodle_exception('error_unknown_room_result', 'local_web3talents');
    }
    room_assignment_service::move_group($resultid, $groupid, $targetid);
    echo json_encode(['success' => true, 'html' => room_assignment_panel::render(room_assignment_service::get_result_state($resultid)),
        'message' => get_string('room_group_moved', 'local_web3talents')]);
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => get_string('room_move_failed', 'local_web3talents')]);
}
