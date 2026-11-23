<?php
namespace core\hook\output { class before_http_headers {} }
namespace {
define('MOODLE_INTERNAL', true);
require(__DIR__ . '/../plugins/local_web3talents/classes/course_hooks.php');
$CFG = (object)['dirroot'=>__DIR__ . '/fixtures/dashboard'];
$SESSION = new stdClass();
$PAGE = new class {
    public $pagetype = 'course-view-topics';
    public $course;
    public $editing = false;
    public function user_is_editing() { return $this->editing; }
};
$PAGE->course = (object)['id'=>2];
$loggedin = true; $guest = false; $access = true;
$configuredcourse = (object)['id'=>2, 'fullname'=>'Blockchain Fundamentals 1'];
function isloggedin() { global $loggedin; return $loggedin; }
function isguestuser() { global $guest; return $guest; }
function can_access_course($course) { global $access; return $access; }
function local_web3talents_get_configured_course() { global $configuredcourse; return $configuredcourse; }
function get_fast_modinfo($course) { return new class {
    public function get_section_info_all() { return [(object)['id'=>1], (object)['id'=>13]]; }
}; }
$writes = [];
function course_get_format($course) { return new class {
    public function set_sections_preference($key, $value) { global $writes; $writes[] = [$key, $value]; }
}; }
function check($condition, $label) { if (!$condition) throw new RuntimeException($label); echo "PASS $label\n"; }
$hook = new \core\hook\output\before_http_headers();
\local_web3talents\course_hooks::initialise_index($hook);
check($writes === [['indexcollapsed', [1,13]]], 'Only index preference changed; all topics/subtopics collapsed');
\local_web3talents\course_hooks::initialise_index($hook);
check(count($writes) === 1, 'Does not override choices on later navigation');
foreach (['editing','guest','loggedout','othercourse','otherpage','denied'] as $case) {
    $SESSION = new stdClass();
    $PAGE->editing = $case === 'editing'; $guest = $case === 'guest';
    $loggedin = $case !== 'loggedout'; $access = $case !== 'denied';
    $PAGE->course->id = $case === 'othercourse' ? 9 : 2;
    $PAGE->pagetype = $case === 'otherpage' ? 'mod-resource-view' : 'course-view-topics';
    \local_web3talents\course_hooks::initialise_index($hook);
    check(count($writes) === 1, $case . ' does not alter preferences');
}
}
