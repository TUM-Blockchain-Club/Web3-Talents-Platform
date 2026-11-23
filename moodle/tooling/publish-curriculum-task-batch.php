<?php
/** Publish a checked worksheet manifest. Dry-run by default; never sends email. */
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/filelib.php');
[$options, $unknown] = cli_get_params(['apply'=>false, 'courseid'=>0, 'source'=>''], []);
if ($unknown || !$options['courseid'] || !$options['source']) {
    cli_error('Use --courseid=N --source=/absolute/batch-directory [--apply].');
}
$CFG->noemailever = true;
$course = $DB->get_record('course', ['id'=>(int)$options['courseid']], '*', MUST_EXIST);
if ($course->shortname !== 'Blockchain Fundamentals 1' ||
        get_config('local_web3talents', 'fundamentals_course_shortname') !== $course->shortname) {
    cli_error('Course identity differs.');
}
$source = realpath($options['source']);
if (!$source || !is_readable($source . '/manifest.json')) { cli_error('Manifest missing.'); }
$entries = json_decode(file_get_contents($source . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
if (count($entries) !== 37) { cli_error('Expected 37 approved worksheets.'); }
$resourceid = $DB->get_field('modules', 'id', ['name'=>'resource'], MUST_EXIST);
$subsectionid = $DB->get_field('modules', 'id', ['name'=>'subsection'], MUST_EXIST);
$fs = get_file_storage();
$plan = [];
$seen = [];
foreach ($entries as $entry) {
    $number = (int)$entry['topic'];
    $group = (int)$entry['group'];
    if (!in_array($number, [2,4,5,6,7,8,9,10], true) || $group < 0 || $group > 4 ||
            (!$group && !in_array($number, [5,6,7,8,10], true))) { cli_error('Unexpected task.'); }
    $filename = sprintf('Topic_%02d_', $number) . ($group ? sprintf('Group_%02d', $group) : 'General') . '_Task.pdf';
    if ($entry['filename'] !== $filename || !is_readable($source . '/' . $filename) ||
            sha1_file($source . '/' . $filename) !== $entry['sha1']) { cli_error('PDF integrity failed.'); }
    $parent = $DB->get_record('course_sections', ['course'=>$course->id, 'section'=>$number], '*', MUST_EXIST);
    $subtopics = [];
    $firstsubtopiccm = null;
    foreach (array_filter(explode(',', $parent->sequence)) as $cmid) {
        $cm = $DB->get_record('course_modules', ['id'=>$cmid, 'course'=>$course->id], '*', MUST_EXIST);
        if ((int)$cm->module !== (int)$subsectionid) { continue; }
        $firstsubtopiccm = $firstsubtopiccm ?? (int)$cm->id;
        $subtopics[] = $DB->get_record('course_sections', ['course'=>$course->id,
            'component'=>'mod_subsection', 'itemid'=>$cm->instance], '*', MUST_EXIST);
    }
    if (count($subtopics) !== 4) { cli_error('Expected four subtopics for topic ' . $number); }
    $section = $group ? $subtopics[$group - 1] : $parent;
    if (!$parent->visible || !$section->visible) { cli_error('Target topic or subtopic is hidden.'); }
    $title = preg_replace('/^Group\s+\d+:\s*/i', '', trim($entry['title']));
    $sectionname = $group ? $group . ': ' . $title : $section->name;
    if ($group && $section->name !== 'Subtopic ' . $group && $section->name !== $sectionname) {
        cli_error('Independent section name found: ' . $section->name . '; expected ' . $sectionname);
    }
    $idnumber = 'w3t-worksheet-topic' . $number . ($group ? '-group' . $group : '-general');
    if (isset($seen[$idnumber])) { cli_error('Duplicate manifest task.'); }
    $seen[$idnumber] = true;
    $existing = $DB->get_record('course_modules', ['course'=>$course->id, 'idnumber'=>$idnumber]);
    if ($existing) {
        $file = $fs->get_file(context_module::instance($existing->id)->id,
            'mod_resource', 'content', 0, '/', $filename);
        if ((int)$existing->module !== (int)$resourceid || (int)$existing->section !== (int)$section->id ||
                !$file || $file->get_contenthash() !== $entry['sha1'] || !$existing->visible) {
            cli_error('Existing resource differs; refusing overwrite: ' . $idnumber);
        }
    }
    $plan[] = compact('entry','number','group','filename','section','sectionname','title','idnumber','existing','firstsubtopiccm');
}
echo json_encode(array_map(static fn($p) => ['idnumber'=>$p['idnumber'], 'sectionid'=>$p['section']->id,
    'section'=>$p['sectionname'], 'action'=>$p['existing'] ? 'verify' : 'create'], $plan), JSON_PRETTY_PRINT) . PHP_EOL;
if (!$options['apply']) { exit; }
\core\session\manager::set_user(get_admin());
$before = ['sections'=>$DB->get_records('course_sections', ['course'=>$course->id]),
    'modules'=>$DB->get_records('course_modules', ['course'=>$course->id]),
    'subsections'=>$DB->get_records('subsection', ['course'=>$course->id])];
$backupdir = $CFG->dataroot . '/web3talents-backups';
if (!is_dir($backupdir) && !mkdir($backupdir, 0700, true)) { cli_error('Backup directory unavailable.'); }
$backup = $backupdir . '/curriculum-task-batch-' . $course->id . '-' . gmdate('Ymd-His') . '.json';
if (file_put_contents($backup, json_encode($before), LOCK_EX) === false) { cli_error('Backup failed.'); }
chmod($backup, 0600);
$transaction = $DB->start_delegated_transaction();
$created = [];
try {
    foreach ($plan as $p) {
        if ($p['group'] && $p['section']->name !== $p['sectionname']) {
            course_update_section($course, $p['section'], ['name'=>$p['sectionname']]);
        }
        if ($p['existing']) { continue; }
        $draftid = file_get_unused_draft_itemid();
        $fs->create_file_from_pathname(['contextid'=>context_user::instance($USER->id)->id,
            'component'=>'user', 'filearea'=>'draft', 'itemid'=>$draftid, 'filepath'=>'/',
            'filename'=>$p['filename']], $source . '/' . $p['filename']);
        $data = (object)['modulename'=>'resource', 'module'=>$resourceid,
            'section'=>$p['section']->section,
            'name'=>$p['group'] ? 'Group ' . $p['group'] . ' worksheet: ' . $p['title'] : 'General worksheet: Topic ' . $p['number'],
            'cmidnumber'=>$p['idnumber'], 'visible'=>1, 'visibleoncoursepage'=>1,
            'groupmode'=>0, 'groupingid'=>0, 'completion'=>0, 'files'=>$draftid,
            'introformat'=>FORMAT_HTML, 'intro'=>'', 'showdescription'=>0, 'display'=>0,
            'printintro'=>0, 'showsize'=>1, 'showtype'=>1, 'showdate'=>0,
            'popupwidth'=>620, 'popupheight'=>450];
        $added = add_moduleinfo($data, $course);
        if (!$p['group']) {
            \core_courseformat\formatactions::cm($course)->move_before((int)$added->coursemodule, $p['firstsubtopiccm']);
        }
        $stored = $fs->get_file(context_module::instance($added->coursemodule)->id,
            'mod_resource', 'content', 0, '/', $p['filename']);
        $cm = $DB->get_record('course_modules', ['id'=>$added->coursemodule], '*', MUST_EXIST);
        if (!$stored || $stored->get_contenthash() !== $p['entry']['sha1'] ||
                (int)$cm->section !== (int)$p['section']->id || !$cm->visible) {
            throw new RuntimeException('Published worksheet verification failed.');
        }
        $created[$p['idnumber']] = $cm->id;
    }
    foreach ($before['modules'] as $id=>$original) {
        $current = $DB->get_record('course_modules', ['id'=>$id], '*', MUST_EXIST);
        foreach (['course','module','instance','section','visible','availability','completion','groupmode','groupingid'] as $field) {
            if ($current->$field != $original->$field) { throw new RuntimeException('Existing activity changed: ' . $id); }
        }
    }
    $transaction->allow_commit();
} catch (Throwable $e) { $transaction->rollback($e); }
rebuild_course_cache($course->id, true);
echo json_encode(['created'=>$created, 'verified'=>count($plan), 'backup'=>$backup,
    'emails'=>0, 'enrolment_changes'=>0, 'grade_changes'=>0], JSON_PRETTY_PRINT) . PHP_EOL;
