<?php
/** Add approved PDFs to the configured course. Dry-run by default, never replaces files. */
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/filelib.php');
[$options, $unknown] = cli_get_params(['apply'=>false, 'source'=>'', 'courseid'=>0], []);
if ($unknown || !$options['source'] || !$options['courseid']) {
    cli_error('Use --source=/directory --courseid=N [--apply].');
}
$course = $DB->get_record('course', ['id'=>(int)$options['courseid']], '*', MUST_EXIST);
if ($course->shortname !== 'Blockchain Fundamentals 1' ||
        get_config('local_web3talents', 'fundamentals_course_shortname') !== $course->shortname) {
    cli_error('Course identity differs.');
}
$plan = [
    [1, 'general', 'General worksheet: Digital Trust & Decentralization', ['Topic_01_General_Task.pdf']],
    [1, 'groups', 'Group worksheets: Digital Trust & Decentralization', [
        'Topic_01_Group_01_Task.pdf', 'Topic_01_Group_02_Task.pdf',
        'Topic_01_Group_03_Task.pdf', 'Topic_01_Group_04_Task.pdf']],
    [2, 'general', 'General worksheet: Cryptographic Hashing & Data Integrity', ['Week_02_General_Task.pdf']],
    [3, 'general', 'General worksheet: Wallets & Peer-to-Peer Networks', ['Week_03_General_Task.pdf']],
    [3, 'groups', 'Group worksheets: Wallets & Peer-to-Peer Networks', [
        'Week_03_Group_01_Task.pdf', 'Week_03_Group_02_Task.pdf',
        'Week_03_Group_03_Task.pdf', 'Week_03_Group_04_Task.pdf']],
];
$fs = get_file_storage();
$prepared = [];
foreach ($plan as [$sectionnum, $kind, $name, $files]) {
    $section = $DB->get_record('course_sections', ['course'=>$course->id, 'section'=>$sectionnum], '*', MUST_EXIST);
    if (strpos($section->name, $sectionnum . '. ') !== 0) { cli_error('Unexpected section name.'); }
    $idnumber = 'w3t-worksheet-topic' . $sectionnum . '-' . $kind;
    $cm = $DB->get_record('course_modules', ['course'=>$course->id, 'idnumber'=>$idnumber]);
    $module = $kind === 'groups' ? 'folder' : 'resource';
    if ($cm && (int)$cm->module !== (int)$DB->get_field('modules', 'id', ['name'=>$module])) {
        cli_error('Existing resource type differs.');
    }
    foreach ($files as $filename) {
        $path = $options['source'] . '/' . $filename;
        if (!is_readable($path) || file_get_contents($path, false, null, 0, 5) !== '%PDF-') {
            cli_error('Missing or invalid PDF: ' . $filename);
        }
        if ($cm) {
            $file = $fs->get_file(context_module::instance($cm->id)->id, 'mod_' . $module, 'content', 0, '/', $filename);
            if (!$file || $file->get_contenthash() !== sha1_file($path)) {
                cli_error('Existing PDF differs; refusing overwrite: ' . $filename);
            }
        }
    }
    $prepared[] = compact('section', 'idnumber', 'cm', 'module', 'name', 'files', 'kind');
}
echo json_encode(array_map(static fn($p) => ['topic'=>$p['section']->name, 'resource'=>$p['name'],
    'files'=>$p['files'], 'action'=>$p['cm'] ? 'unchanged' : 'create'], $prepared), JSON_PRETTY_PRINT) . PHP_EOL;
if (!$options['apply']) { exit; }
$CFG->noemailever = true;
\core\session\manager::set_user(get_admin());
$backupdir = $CFG->dataroot . '/web3talents-backups';
if (!is_dir($backupdir) && !mkdir($backupdir, 0700, true)) { cli_error('Backup directory unavailable.'); }
$backup = $backupdir . '/worksheets-' . $course->id . '-' . gmdate('Ymd-His') . '.json';
$before = ['sections'=>$DB->get_records('course_sections', ['course'=>$course->id]),
    'modules'=>$DB->get_records('course_modules', ['course'=>$course->id])];
if (file_put_contents($backup, json_encode($before), LOCK_EX) === false) { cli_error('Backup failed.'); }
chmod($backup, 0600);
rebuild_course_cache($course->id, true);
$transaction = $DB->start_delegated_transaction();
$created = [];
try {
    foreach ($prepared as $p) {
        if ($p['cm']) { continue; }
        $draftid = file_get_unused_draft_itemid();
        foreach ($p['files'] as $filename) {
            $fs->create_file_from_pathname(['contextid'=>context_user::instance($USER->id)->id,
                'component'=>'user', 'filearea'=>'draft', 'itemid'=>$draftid, 'filepath'=>'/',
                'filename'=>$filename], $options['source'] . '/' . $filename);
        }
        $data = (object)['modulename'=>$p['module'],
            'module'=>$DB->get_field('modules', 'id', ['name'=>$p['module']], MUST_EXIST),
            'section'=>$p['section']->section, 'name'=>$p['name'], 'cmidnumber'=>$p['idnumber'],
            'visible'=>1, 'visibleoncoursepage'=>1, 'groupmode'=>0, 'groupingid'=>0,
            'completion'=>0, 'files'=>$draftid, 'introformat'=>FORMAT_HTML,
            'intro'=>$p['kind'] === 'groups' ? '<p>Download the worksheet for your assigned group. Each group has its own PDF.</p>' : '',
            'showdescription'=>$p['kind'] === 'groups' ? 1 : 0,
            'display'=>$p['module'] === 'folder' ? 1 : 0,
            'showexpanded'=>1, 'showdownloadfolder'=>1, 'forcedownload'=>0,
            'printintro'=>0, 'showsize'=>1, 'showtype'=>1, 'showdate'=>0,
            'popupwidth'=>620, 'popupheight'=>450];
        $added = add_moduleinfo($data, $course);
        $cm = $DB->get_record('course_modules', ['id'=>$added->coursemodule], '*', MUST_EXIST);
        // Put worksheets before existing subtopics, preserving every existing activity.
        $first = (int)strtok($p['section']->sequence, ',');
        if ($first) { \core_courseformat\formatactions::cm($course)->move_before((int)$cm->id, $first); }
        foreach ($p['files'] as $filename) {
            $file = $fs->get_file(context_module::instance($cm->id)->id, 'mod_' . $p['module'], 'content', 0, '/', $filename);
            if (!$file || $file->get_contenthash() !== sha1_file($options['source'] . '/' . $filename)) {
                throw new RuntimeException('Upload verification failed: ' . $filename);
            }
        }
        $created[] = ['cmid'=>$cm->id, 'name'=>$p['name']];
    }
    foreach ($before['modules'] as $id=>$original) {
        $current = $DB->get_record('course_modules', ['id'=>$id], '*', MUST_EXIST);
        foreach (['course','module','instance','section','visible','availability','completion'] as $field) {
            if ($current->$field != $original->$field) { throw new RuntimeException('Existing activity changed.'); }
        }
    }
    $transaction->allow_commit();
} catch (Throwable $e) { $transaction->rollback($e); }
rebuild_course_cache($course->id, true);
echo json_encode(['created'=>$created, 'backup'=>$backup, 'emails'=>0, 'enrolment_changes'=>0], JSON_PRETTY_PRINT) . PHP_EOL;
