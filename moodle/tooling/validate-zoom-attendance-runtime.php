<?php
/** Isolated tests: no live Zoom, outbound mail, production database or persistent fixtures. */
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
if ($CFG->dbname !== 'reviewtest' || empty($CFG->noemailever) || getenv('W3T_SECURITY_TEST') !== '1') {
    throw new RuntimeException('Requires isolated reviewtest with mail disabled.');
}
use local_web3talents\local\zoom_attendance_math as maths;
use local_web3talents\local\zoom_attendance_service as sync;
use local_web3talents\local\zoom_attendance_client as client;
use local_web3talents\local\participation_service as participation;

function za_check(bool $condition, string $label): void {
    if (!$condition) { throw new RuntimeException('FAIL: ' . $label); }
    echo 'PASS: ' . $label . PHP_EOL;
}
function za_reject(callable $fn, string $label): void {
    try { $fn(); } catch (Throwable $e) { za_check(true, $label); return; }
    throw new RuntimeException('FAIL: ' . $label);
}
class fixture_zoom_client extends client {
    public array $urls = [];
    public function __construct(private array $responses) {
        parent::__construct(['client_id' => 'test', 'client_secret' => 'test', 'account_id' => 'test', 'meeting_id' => '12345678901']);
    }
    protected function request(string $url, array $headers, ?string $body = null): array {
        $this->urls[] = $url;
        if (!$this->responses) { throw new RuntimeException('Unexpected network request prevented'); }
        return array_shift($this->responses);
    }
}
$c = new fixture_zoom_client([['access_token' => 'test'],
    ['participants' => [['name' => 'A']], 'next_page_token' => 'next', 'total_records' => 2],
    ['participants' => [['name' => 'B']], 'next_page_token' => '', 'total_records' => 2]]);
za_check(count($c->participants('/test/uuid==')) === 2 && str_contains($c->urls[1], '%252Ftest%252Fuuid%253D%253D')
    && str_contains($c->urls[2], 'next_page_token=next'), 'All pages fetched using double-encoded occurrence UUID');
$c = new fixture_zoom_client([['access_token' => 'test'], ['participants' => [], 'next_page_token' => '', 'total_records' => 1]]);
za_reject(fn() => $c->participants('uuid'), 'Truncated participant report rejected');
$c = new fixture_zoom_client([['access_token' => 'test'], ['participants' => [], 'next_page_token' => 'repeat'],
    ['participants' => [], 'next_page_token' => 'repeat']]);
za_reject(fn() => $c->participants('uuid'), 'Repeated pagination token rejected');
za_check(maths::seconds([[0, 100], [50, 150], [200, 220], [0, 100]]) === 170, 'Reconnect overlap and duplicates not double-counted');
$fakeusers = [(object)['id' => 11, 'email' => 'one@example.test'], (object)['id' => 12, 'email' => 'two@example.test']];
za_check(maths::match(' ONE@example.test ', $fakeusers) === 11 && maths::match('', $fakeusers) === 0,
    'Exact normalised email only, no empty-email matching');
$fakeusers[] = (object)['id' => 13, 'email' => 'ONE@example.test'];
za_check(maths::match('one@example.test', $fakeusers) === 0, 'Duplicate Moodle email requires review');
za_check(maths::session_for(10000, [(object)['id' => 1, 'sessiondate' => 10000]]) === 1
    && maths::session_for(10000, [(object)['id' => 1, 'sessiondate' => 10000], (object)['id' => 2, 'sessiondate' => 10001]]) === 0
    && maths::session_for(10000, [(object)['id' => 1, 'sessiondate' => 20000]]) === 0,
    'Session association rejects ambiguous and out-of-window starts');
$start = time() - 18000;
function za_participant(string $email, string $id, int $join, int $leave, string $name = 'Test participant'): array {
    return ['user_email' => $email, 'id' => $id, 'name' => $name, 'user_id' => $id ?: (string)$join,
        'join_time' => gmdate('c', $join), 'leave_time' => gmdate('c', $leave),
        'duration' => $leave - $join, 'status' => 'in_meeting'];
}
$anonymous = [za_participant('', '', $start, $start + 100, 'Same name'),
    za_participant('', '', $start + 200, $start + 300, 'Same name')];
za_check(count(maths::group($anonymous, $start, time())) === 2, 'Same-name anonymous users never merged');
$guest = maths::group([za_participant('one@example.test', '', $start, $start + 100)], $start, time());
za_check(!reset($guest)['authenticated'], 'Guest-supplied email is not treated as an authenticated identity');
$waiting = za_participant('one@example.test', 'one', $start, $start + 100);
$waiting['status'] = 'in_waiting_room';
za_check(maths::group([$waiting], $start, time()) === [], 'Waiting-room time excluded');
$zero = za_participant('one@example.test', 'one', $start, $start);
za_reject(fn() => maths::group([$zero], $start, time()), 'Unprocessed zero-duration snapshot rejected');

$course = participation::get_configured_course();
$students = participation::get_students($course);
if (count($students) < 2) { throw new RuntimeException('Need two existing isolated student fixtures'); }
$list = array_values($students);
[$first, $second] = [$list[0], $list[1]];
$USER = get_admin();
$counts = array_map(fn($table) => $DB->count_records($table), ['local_w3t_attendance', 'local_w3t_zrun', 'local_w3t_zatt', 'local_w3t_session']);
$transaction = $DB->start_delegated_transaction();
$rollback = new RuntimeException('Intentional fixture rollback');
try {
    $session = participation::create_session((int)$course->id, 'ISOLATED Zoom fixture ' . bin2hex(random_bytes(4)), $start, '', (int)$USER->id);
    $participants = [za_participant(strtoupper($first->email), 'first', $start, $start + 600),
        za_participant($first->email, 'first', $start + 300, $start + 900),
        za_participant('', 'unknown', $start, $start + 400, fullname($second))];
    sync::import($session, '12345678901', 'fixture-uuid', $start, $participants, $students);
    $records = participation::get_attendance_by_user((int)$session->id);
    za_check(count($records) === 1 && $records[$first->id]->status === 'present' && $records[$first->id]->source === 'zoom'
        && (int)$records[$first->id]->participation === 0 && sync::totals((int)$session->id)[$first->id] === 900,
        'Verified identity creates present only, combines duration, does not invent participation or absences');
    $rows = sync::evidence((int)$session->id);
    $unknown = array_values(array_filter($rows, fn($r) => $r->decision === 'review'))[0];
    za_check($unknown->displayname === fullname($second) && !$unknown->userid, 'Matching display name alone still requires review');
    sync::import($session, '12345678901', 'fixture-uuid', $start, $participants, $students);
    za_check(count(sync::evidence((int)$session->id)) === 2 && count(participation::get_attendance_by_user((int)$session->id)) === 1,
        'Retry is idempotent with no duplicated records');
    sync::review((int)$session->id, (int)$unknown->id, (int)$second->id, (int)$USER->id);
    za_check(isset(participation::get_attendance_by_user((int)$session->id)[$second->id]), 'Reviewer match records attendance');
    sync::import($session, '12345678901', 'fixture-uuid', $start, $participants, $students);
    za_check(sync::evidence((int)$session->id)[$unknown->id]->decision === 'manual', 'Reviewer match survives refresh');
    sync::review((int)$session->id, (int)$unknown->id, -1, (int)$USER->id);
    za_check(!isset(participation::get_attendance_by_user((int)$session->id)[$second->id]), 'Ignoring mistaken match removes only automatic attendance');
    participation::save_attendance_bulk((int)$session->id, [$first->id => ['status' => 'excused', 'participation' => 4, 'notes' => 'Manual decision']], (int)$USER->id);
    sync::import($session, '12345678901', 'fixture-uuid', $start, $participants, $students);
    $record = participation::get_attendance_by_user((int)$session->id)[$first->id];
    za_check($record->source === 'manual' && $record->status === 'excused' && (int)$record->participation === 4
        && $record->notes === 'Manual decision', 'Manual status, participation, notes and source preserved');
    za_reject(fn() => sync::import($session, '12345678901', 'fixture-uuid', $start, [$zero], $students), 'Incomplete reimport fails before writes');
    za_check(count(sync::evidence((int)$session->id)) === 2, 'Incomplete report does not destroy existing evidence');
    za_reject(fn() => sync::review((int)$session->id, (int)$unknown->id, 99999999, (int)$USER->id), 'Non-enrolled reviewer match rejected');
    // Exercise access control with a real enrolled student, without authenticating any live account.
    $USER = $first;
    za_reject(fn() => sync::review((int)$session->id, (int)$unknown->id, (int)$first->id, (int)$first->id), 'Student cannot review attendance');
    $USER = get_admin();
} finally {
    try { $transaction->rollback($rollback); } catch (RuntimeException $e) { if ($e !== $rollback) { throw $e; } }
}
za_check($counts === array_map(fn($table) => $DB->count_records($table), ['local_w3t_attendance', 'local_w3t_zrun', 'local_w3t_zatt', 'local_w3t_session']),
    'All isolated fixtures rolled back');
