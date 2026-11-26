<?php
/** Approved 2026–27 calendar: worksheet access after each lecture. Dry-run by default; never sends mail. */
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/course/lib.php');
$CFG->noemailever = true;
$CFG->nosmsever = true;
\core\session\manager::set_user(get_admin());
$course = get_course(2);
if ($course->shortname !== 'Blockchain Fundamentals 1' || empty($CFG->enableavailability)) {
    throw new RuntimeException('Unexpected course or disabled availability.');
}
$dates = [1=>'2026-12-02', 2=>'2026-12-16', 3=>'2027-01-13', 4=>'2027-01-27',
    5=>'2027-02-10', 6=>'2027-02-24', 7=>'2027-03-10', 8=>'2027-03-24', 9=>'2027-04-07', 10=>'2027-04-21'];
$counts = [1=>5, 2=>5, 3=>5, 4=>4, 5=>5, 6=>5, 7=>5, 8=>5, 9=>4, 10=>5];
$cms = $DB->get_records_sql('SELECT cm.* FROM {course_modules} cm JOIN {modules} m ON m.id=cm.module
    WHERE cm.course=2 AND cm.deletioninprogress=0 AND m.name=? AND cm.idnumber LIKE ?',
    ['resource', 'w3t-worksheet-%']);
if (count($cms) !== 48) { throw new RuntimeException('Expected 48 current worksheet PDFs.'); }
$plans = []; $schedule = [];
foreach ($dates as $topic=>$day) {
    $date = new DateTimeImmutable($day . ' 19:30:00', new DateTimeZone('Europe/Berlin'));
    if ($date->format('N') !== '3') { throw new RuntimeException('Lecture must be Wednesday.'); }
    $tree = ['op'=>'&', 'c'=>[['type'=>'date','d'=>'>=','t'=>$date->getTimestamp()]], 'showc'=>[false]];
    $json = json_encode($tree); new \core_availability\tree(json_decode($json));
    $condition = new \availability_date\condition((object)$tree['c'][0]);
    try {
        \availability_date\condition::set_current_time_for_test($date->getTimestamp()-1);
        if ($condition->is_available_for_all()) { throw new RuntimeException('Premature release.'); }
        \availability_date\condition::set_current_time_for_test($date->getTimestamp());
        if (!$condition->is_available_for_all()) { throw new RuntimeException('Release boundary failed.'); }
    } finally { \availability_date\condition::set_current_time_for_test(); }
    $matching = array_filter($cms, fn($cm)=>preg_match('/^w3t-worksheet-topic'.$topic.'-(general|group[1-4])$/', $cm->idnumber));
    if (count($matching) !== $counts[$topic]) { throw new RuntimeException('Unexpected topic worksheet count.'); }
    foreach ($matching as $cm) {
        if ($cm->availability && $cm->availability !== $json) { throw new RuntimeException('Existing restriction differs; review first.'); }
        $plans[$cm->id] = $json;
    }
    $schedule[$topic] = $date->format('Y-m-d H:i T');
}
$apply = in_array('--apply', $argv, true);
if ($apply) {
    $path = $CFG->dataroot . '/web3talents-backups/worksheet-calendar-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.json';
    if (file_put_contents($path, json_encode($cms), LOCK_EX) === false) { throw new RuntimeException('Backup failed.'); }
    chmod($path, 0600);
    $tx = $DB->start_delegated_transaction();
    foreach ($plans as $id=>$json) {
        $DB->set_field('course_modules', 'availability', $json, ['id'=>$id]);
        set_coursemodule_visible($id, 1, 1, false);
    }
    $tx->allow_commit(); rebuild_course_cache(2, true);
}
$students = \local_web3talents\local\participation_service::get_students($course);
$access = 0;
foreach ($students as $u) {
    $info = get_fast_modinfo($course, $u->id);
    foreach ($plans as $id=>$json) { if ($info->get_cm($id)->uservisible) { $access++; } }
}
echo json_encode(['applied'=>$apply, 'scheduled_pdfs'=>count($plans), 'release_times_berlin'=>$schedule,
    'boundary_tests'=>20, 'student_accounts_checked'=>count($students), 'student_accesses_now'=>$access,
    'emails_paused'=>!\local_web3talents\local\applicant_service::activation_emails_enabled()], JSON_PRETTY_PRINT) . PHP_EOL;
