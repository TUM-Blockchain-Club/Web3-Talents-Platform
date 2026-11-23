<?php
/** Publish supplied Topic 4–10 lecture outlines, not generated worksheets. Dry-run by default. */
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/course/lib.php');
[$options, $unknown] = cli_get_params(['apply'=>false, 'courseid'=>0, 'source'=>''], []);
if ($unknown || !$options['courseid'] || !is_readable($options['source'])) {
    cli_error('Use --courseid=N --source=/path/to/approved-curriculum.txt [--apply].');
}
$course = $DB->get_record('course', ['id'=>(int)$options['courseid']], '*', MUST_EXIST);
if ($course->shortname !== 'Blockchain Fundamentals 1' ||
        get_config('local_web3talents', 'fundamentals_course_shortname') !== $course->shortname) {
    cli_error('Course identity differs.');
}
$source = file_get_contents($options['source']);
preg_match_all('/^## (\d+)\. ([^\r\n]+)\R(.*?)(?=^#{1,2} |\z)/ms', $source, $matches, PREG_SET_ORDER);
$plan = [];
$moduleid = $DB->get_field('modules', 'id', ['name'=>'page'], MUST_EXIST);
foreach ($matches as $match) {
    $number = (int)$match[1];
    if ($number < 4 || $number > 10) { continue; }
    if (isset($plan[$number])) { cli_error('Duplicate topic in source.'); }
    $section = $DB->get_record('course_sections', ['course'=>$course->id, 'section'=>$number], '*', MUST_EXIST);
    if (strpos($section->name, $number . '. ') !== 0 || $section->component) {
        cli_error('Unexpected topic section.');
    }
    $body = trim($match[3]);
    $body = preg_replace('/\s*---\s*$/', '', $body);
    // Staff-only speaker selection guidance is not student course content.
    $body = preg_replace('/^For this week it is very important to have a speaker[^\r\n]*\R*/m', '', $body);
    $markdown = '## ' . $match[2] . "\n\n" . trim($body);
    $content = '<p><strong>Lecture outline</strong>: topics covered in the live lecture. This is not a worksheet or a slide deck.</p>' .
        format_text($markdown, FORMAT_MARKDOWN, ['context'=>context_course::instance($course->id), 'filter'=>false]);
    $name = 'Lecture outline: ' . preg_replace('/^\d+\. /', '', $section->name);
    $idnumber = 'w3t-lecture-outline-topic' . $number;
    $existing = $DB->get_record('course_modules', ['course'=>$course->id, 'idnumber'=>$idnumber]);
    if ($existing) {
        $page = $DB->get_record('page', ['id'=>$existing->instance], '*', MUST_EXIST);
        if ((int)$existing->module !== (int)$moduleid || (int)$existing->section !== (int)$section->id ||
                $page->content !== $content || $page->name !== $name) {
            cli_error('Existing outline differs; refusing overwrite.');
        }
    }
    $plan[$number] = compact('section', 'name', 'idnumber', 'existing', 'content');
}
if (array_keys($plan) !== range(4, 10)) { cli_error('Expected exactly Topics 4–10 in source order.'); }
echo json_encode(array_map(static fn($p) => ['topic'=>$p['section']->name,
    'name'=>$p['name'], 'action'=>$p['existing'] ? 'verified unchanged' : 'create'], $plan), JSON_PRETTY_PRINT) . PHP_EOL;
if (!$options['apply']) { exit; }
$CFG->noemailever = true;
\core\session\manager::set_user(get_admin());
$before = ['sections'=>$DB->get_records('course_sections', ['course'=>$course->id]),
    'modules'=>$DB->get_records('course_modules', ['course'=>$course->id]), 'sourcehash'=>hash('sha256', $source)];
$backupdir = $CFG->dataroot . '/web3talents-backups';
if (!is_dir($backupdir) && !mkdir($backupdir, 0700, true)) { cli_error('Backup directory unavailable.'); }
$backup = $backupdir . '/lecture-outlines-' . $course->id . '-' . gmdate('Ymd-His') . '.json';
if (file_put_contents($backup, json_encode($before), LOCK_EX) === false) { cli_error('Backup failed.'); }
chmod($backup, 0600);
rebuild_course_cache($course->id, true);
$transaction = $DB->start_delegated_transaction();
$created = [];
try {
    foreach ($plan as $p) {
        if ($p['existing']) { continue; }
        $data = (object)['modulename'=>'page', 'module'=>$moduleid,
            'section'=>$p['section']->section, 'name'=>$p['name'], 'cmidnumber'=>$p['idnumber'],
            'visible'=>1, 'visibleoncoursepage'=>1, 'groupmode'=>0, 'groupingid'=>0, 'completion'=>0,
            'intro'=>'', 'introformat'=>FORMAT_HTML, 'showdescription'=>0,
            'content'=>$p['content'], 'contentformat'=>FORMAT_HTML, 'display'=>0,
            'printintro'=>0, 'printlastmodified'=>1, 'popupwidth'=>620, 'popupheight'=>450];
        $added = add_moduleinfo($data, $course);
        $first = (int)strtok($p['section']->sequence, ',');
        if ($first) { \core_courseformat\formatactions::cm($course)->move_before((int)$added->coursemodule, $first); }
        $cm = $DB->get_record('course_modules', ['id'=>$added->coursemodule], '*', MUST_EXIST);
        $page = $DB->get_record('page', ['id'=>$cm->instance], '*', MUST_EXIST);
        if ($page->content !== $p['content'] || (int)$cm->section !== (int)$p['section']->id || !$cm->visible) {
            throw new RuntimeException('Published outline verification failed.');
        }
        $created[] = ['cmid'=>$cm->id, 'name'=>$page->name];
    }
    foreach ($before['modules'] as $id=>$original) {
        $current = $DB->get_record('course_modules', ['id'=>$id], '*', MUST_EXIST);
        foreach (['course','module','instance','section','visible','availability','completion','groupmode','groupingid'] as $field) {
            if ($current->$field != $original->$field) { throw new RuntimeException('Existing activity changed.'); }
        }
    }
    $transaction->allow_commit();
} catch (Throwable $e) { $transaction->rollback($e); }
rebuild_course_cache($course->id, true);
echo json_encode(['created'=>$created, 'backup'=>$backup, 'emails'=>0, 'enrolment_changes'=>0], JSON_PRETTY_PRINT) . PHP_EOL;
