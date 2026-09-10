<?php
// Read-only by default. --exercise-move tests a move in a rolled-back transaction on localhost only.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/local/web3talents/lib.php');

use local_web3talents\local\room_assignment_service;
use local_web3talents\output\room_assignment_panel;

\core\session\manager::set_user(get_admin());
function room_check(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: $message\n";
}
$builder = new ReflectionMethod(room_assignment_service::class, 'build_warnings');
$topics = [(object)['id' => 1, 'name' => 'Topic A'], (object)['id' => 2, 'name' => 'Topic B']];
$one = ['topic' => $topics[0]];
$two = ['topic' => $topics[1]];
$warnings = $builder->invoke(null, [['roomname' => 'Room1', 'assignments' => [$one]]], $topics);
room_check(count($warnings) === 1 && str_contains($warnings[0], 'Topic B'), 'Missing topic is named');
room_check($builder->invoke(null, [['roomname' => 'Room1', 'assignments' => [$one, $two]]], $topics) === [],
    'Complete topic coverage has no warning');
$warnings = $builder->invoke(null, [['roomname' => 'Room1', 'assignments' => [$one, $one]]], $topics);
room_check(count($warnings) === 2, 'Duplicate and missing topics are both reported');
$warnings = $builder->invoke(null, [['roomname' => 'Room1', 'assignments' => []]], $topics);
room_check(count($warnings) === 2, 'Empty room and missing coverage are reported');

$course = local_web3talents_get_configured_course();
$result = room_assignment_service::get_latest_result_for_course((int)$course->id);
room_check((bool)$result, 'Existing room result available for rendering checks');
$state = room_assignment_service::get_result_state((int)$result->id);
$html = room_assignment_panel::render($state);
room_check(!str_contains($html, '[['), 'Panel has no missing translations');
$dom = new DOMDocument();
@$dom->loadHTML($html);
$xpath = new DOMXPath($dom);
room_check($xpath->query('//*[@data-region="room-checks"]')->length === 1, 'Panel contains live room checks');
$forms = $xpath->query('//form[@data-room-move]');
room_check($forms->length > 0, 'Move forms remain available to admins');
foreach ($forms as $form) {
    room_check($xpath->query('.//input[@name="sesskey"]', $form)->length === 1, 'Move form includes CSRF token');
}
// Dynamic names in warning text must not become executable HTML.
$state['warnings'] = ['Room <script>alert(1)</script> is missing Topic A'];
$html = room_assignment_panel::render($state);
room_check(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'), 'Warning text is escaped');
\core\session\manager::set_user($DB->get_record('user', ['username' => 'guest', 'mnethostid' => $CFG->mnet_localhost_id], '*', MUST_EXIST));
room_check(!str_contains(room_assignment_panel::render($state), 'data-room-move'), 'Unauthorized users get no move controls');
\core\session\manager::set_user(get_admin());

if (in_array('--exercise-move', $argv, true)) {
    room_check(in_array(parse_url($CFG->wwwroot, PHP_URL_HOST), ['localhost', '127.0.0.1'], true),
        'Transactional move test restricted to localhost');
    $state = room_assignment_service::get_result_state((int)$result->id);
    $source = null;
    foreach ($state['rooms'] as $room) {
        if ($room['assignments']) {
            $source = $room;
            break;
        }
    }
    $target = null;
    foreach ($state['rooms'] as $room) {
        if ($source && $room['room']->id != $source['room']->id) {
            $target = $room;
            break;
        }
    }
    room_check((bool)$target, 'Two rooms available for move test');
    $groupid = (int)$source['assignments'][0]['pgroup']->id;
    $transaction = $DB->start_delegated_transaction();
    $rollback = new RuntimeException('Roll back test move');
    try {
        room_assignment_service::move_group((int)$result->id, $groupid, (int)$target['room']->id);
        $placement = $DB->get_record('local_w3t_room_group', ['resultid' => $result->id, 'pgroupid' => $groupid]);
        room_check((int)$placement->roomid === (int)$target['room']->id, 'Move saves to the target room');
        $moved = room_assignment_service::get_result_state((int)$result->id);
        room_check(json_decode($moved['result']->warnings, true) === $moved['warnings'], 'Saved warnings match current room coverage');
        $transaction->rollback($rollback);
    } catch (Throwable $exception) {
        if ($DB->is_transaction_started()) {
            $transaction->rollback($exception);
        }
        if ($exception !== $rollback) {
            throw $exception;
        }
    }
    $placement = $DB->get_record('local_w3t_room_group', ['resultid' => $result->id, 'pgroupid' => $groupid]);
    room_check((int)$placement->roomid === (int)$source['room']->id, 'Test move rolled back; original room restored');
}
