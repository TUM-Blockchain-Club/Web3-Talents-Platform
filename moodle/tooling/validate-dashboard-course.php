<?php
// Isolated dashboard visibility tests. No Moodle config, live users, database or email.
namespace core\hook\output {
    class before_http_headers {}
}
namespace {
define('MOODLE_INTERNAL', true);
class context_course { public static function instance($id) { return (object)['id' => $id]; } }
class block_contents { public array $attributes = []; public string $title = ''; public string $content = ''; }
class moodle_url {
    public function __construct(public string $path, public array $params) {}
    public function __toString() { return $this->path . '?' . http_build_query($this->params); }
}
class html_writer {
    public static function tag($tag, $content, $attributes = []) { return "<$tag>$content</$tag>"; }
    public static function link($url, $label, $attributes = []) { return '<a href="' . $url . '">' . $label . '</a>'; }
}
$CFG = (object)['dirroot' => __DIR__ . '/fixtures/dashboard'];
$course = (object)['id' => 42, 'fullname' => 'Blockchain Fundamentals 1'];
$loggedin = true; $guest = false; $manage = true; $access = true;
function isloggedin() { global $loggedin; return $loggedin; }
function isguestuser() { global $guest; return $guest; }
function local_web3talents_get_configured_course() { global $course; return $course; }
function has_capability($cap, $context) { global $manage; return $manage; }
function can_access_course($course) { global $access; return $access; }
function get_string($key, $component) { return $key; }
function format_string($value, ...$args) { return htmlspecialchars($value); }
require(__DIR__ . '/../plugins/local_web3talents/classes/dashboard_hooks.php');
function check($ok, $label) {
    if (!$ok) { throw new \RuntimeException('FAIL: ' . $label); }
    echo 'PASS: ' . $label . PHP_EOL;
}
function render($pagetype = 'my-index') {
    global $PAGE;
    $PAGE = (object)['pagetype' => $pagetype, 'blocks' => new class {
        public array $added = [];
        public function add_fake_block($block, $region) { $this->added[] = [$block, $region]; }
    }];
    \local_web3talents\dashboard_hooks::add_course_shortcut(new \core\hook\output\before_http_headers());
    return $PAGE->blocks->added;
}
$blocks = render();
check(count($blocks) === 1 && $blocks[0][1] === 'content', 'Admin receives a main dashboard course card');
check(str_contains($blocks[0][0]->content, '/course/view.php?id=42'), 'Link opens the configured course');
check(str_contains($blocks[0][0]->content, 'Blockchain Fundamentals 1'), 'Card shows real course name');
check(render('course-view-topics') === [], 'No card on course pages');
check(render('my-index-system') === [], 'Default dashboard configuration is untouched');
$manage = false;
check(render() === [], 'Students, mentors and switched roles without manage capability get no admin card');
$manage = true; $access = false;
check(render() === [], 'No inaccessible-course link');
$access = true; $guest = true;
check(render() === [], 'Guests get no card');
$guest = false; $loggedin = false;
check(render() === [], 'Logged-out visitors get no card');
$loggedin = true; $course = null;
check(render() === [], 'Missing configured course is safe');
}
