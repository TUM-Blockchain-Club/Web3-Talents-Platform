<?php
/** Place each published group worksheet in its delegated subtopic. Dry-run by default. */
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/filelib.php');
[$options, $unknown] = cli_get_params(['apply'=>false, 'courseid'=>0], []);
if ($unknown || !$options['courseid']) { cli_error('Use --courseid=N [--apply].'); }
$course = $DB->get_record('course', ['id'=>(int)$options['courseid']], '*', MUST_EXIST);
if ($course->shortname !== 'Blockchain Fundamentals 1' ||
        get_config('local_web3talents', 'fundamentals_course_shortname') !== $course->shortname) {
    cli_error('Course identity differs.');
}
$topics = [
    1 => ['prefix'=>'Topic_01', 'titles'=>[
        'The Consensus Problem (Byzantine Generals)',
        'Centralized vs. Decentralized (Who Is in Charge?)',
        'The Double-Spending Problem & Digital Cash',
        'The History of Pre-Bitcoin (Cypherpunks)']],
    3 => ['prefix'=>'Week_03', 'titles'=>[
        'Wallets & Key Management (Custody)',
        'Gossip & Transaction Propagation',
        'Node Roles & Trust',
        'Network Security & Attacks']],
];
$archive = $DB->get_record('course_sections', ['course'=>$course->id, 'name'=>'Supplementary resources'], '*', MUST_EXIST);
$fs = get_file_storage();
$plan = [];
$folders = [];
foreach ($topics as $number=>$topic) {
    $parent = $DB->get_record('course_sections', ['course'=>$course->id, 'section'=>$number], '*', MUST_EXIST);
    $folder = $DB->get_record('course_modules', ['course'=>$course->id,
        'idnumber'=>'w3t-worksheet-topic' . $number . '-groups'], '*', MUST_EXIST);
    if ((int)$folder->module !== (int)$DB->get_field('modules', 'id', ['name'=>'folder'])) {
        cli_error('Unexpected source module type.');
    }
    $folders[$folder->id] = $folder;
    // Find subtopics by their actual parent activity order, not assumed section numbers.
    $subtopics = [];
    foreach (array_filter(explode(',', $parent->sequence)) as $cmid) {
        $cm = $DB->get_record('course_modules', ['id'=>$cmid], '*', MUST_EXIST);
        if ((int)$cm->module !== (int)$DB->get_field('modules', 'id', ['name'=>'subsection'])) { continue; }
        $subtopics[] = $DB->get_record('course_sections', ['course'=>$course->id,
            'component'=>'mod_subsection', 'itemid'=>$cm->instance], '*', MUST_EXIST);
    }
    if (count($subtopics) !== 4) { cli_error('Expected four subtopics for topic ' . $number); }
    foreach ($topic['titles'] as $index=>$title) {
        $group = $index + 1;
        $section = $subtopics[$index];
        $name = $group . ': ' . $title;
        if ($section->name !== 'Subtopic ' . $group && $section->name !== $name) {
            cli_error('Subtopic was renamed independently; refusing overwrite.');
        }
        $filename = sprintf('%s_Group_%02d_Task.pdf', $topic['prefix'], $group);
        $source = $fs->get_file(context_module::instance($folder->id)->id,
            'mod_folder', 'content', 0, '/', $filename);
        if (!$source) { cli_error('Missing source PDF: ' . $filename); }
        $idnumber = 'w3t-worksheet-topic' . $number . '-group' . $group;
        $existing = $DB->get_record('course_modules', ['course'=>$course->id, 'idnumber'=>$idnumber]);
        if ($existing) {
            $file = $fs->get_file(context_module::instance($existing->id)->id,
                'mod_resource', 'content', 0, '/', $filename);
            if ((int)$existing->section !== (int)$section->id || !$file ||
                    $file->get_contenthash() !== $source->get_contenthash()) {
                cli_error('Existing worksheet differs; refusing overwrite.');
            }
        }
        $plan[] = compact('number', 'group', 'section', 'name', 'title', 'filename', 'source', 'idnumber', 'existing');
    }
}
echo json_encode(array_map(static fn($p) => ['topic'=>$p['number'], 'subtopic'=>$p['name'],
    'sectionid'=>$p['section']->id, 'file'=>$p['filename'],
    'action'=>$p['existing'] ? 'verify' : 'create'], $plan), JSON_PRETTY_PRINT) . PHP_EOL;
if (!$options['apply']) { exit; }
$CFG->noemailever = true;
\core\session\manager::set_user(get_admin());
$backupdir = $CFG->dataroot . '/web3talents-backups';
if (!is_dir($backupdir) && !mkdir($backupdir, 0700, true)) { cli_error('Backup directory unavailable.'); }
$before = ['sections'=>$DB->get_records('course_sections', ['course'=>$course->id]),
    'modules'=>$DB->get_records('course_modules', ['course'=>$course->id]),
    'subsections'=>$DB->get_records('subsection', ['course'=>$course->id])];
$backup = $backupdir . '/group-worksheet-placement-' . $course->id . '-' . gmdate('Ymd-His') . '.json';
if (file_put_contents($backup, json_encode($before), LOCK_EX) === false) { cli_error('Backup failed.'); }
chmod($backup, 0600);
rebuild_course_cache($course->id, true);
$transaction = $DB->start_delegated_transaction();
try {
    foreach ($plan as $p) {
        course_update_section($course, $p['section'], ['name'=>$p['name']]);
        if ($p['existing']) { continue; }
        $draftid = file_get_unused_draft_itemid();
        $fs->create_file_from_storedfile(['contextid'=>context_user::instance($USER->id)->id,
            'component'=>'user', 'filearea'=>'draft', 'itemid'=>$draftid, 'filepath'=>'/',
            'filename'=>$p['filename']], $p['source']);
        $data = (object)['modulename'=>'resource',
            'module'=>$DB->get_field('modules', 'id', ['name'=>'resource'], MUST_EXIST),
            'section'=>$p['section']->section, 'name'=>'Group ' . $p['group'] . ' worksheet: ' . $p['title'],
            'cmidnumber'=>$p['idnumber'], 'visible'=>1, 'visibleoncoursepage'=>1,
            'groupmode'=>0, 'groupingid'=>0, 'completion'=>0, 'files'=>$draftid,
            'introformat'=>FORMAT_HTML, 'intro'=>'', 'showdescription'=>0, 'display'=>0,
            'printintro'=>0, 'showsize'=>1, 'showtype'=>1, 'showdate'=>0,
            'popupwidth'=>620, 'popupheight'=>450];
        $added = add_moduleinfo($data, $course);
        $stored = $fs->get_file(context_module::instance($added->coursemodule)->id,
            'mod_resource', 'content', 0, '/', $p['filename']);
        $cm = $DB->get_record('course_modules', ['id'=>$added->coursemodule], '*', MUST_EXIST);
        if (!$stored || $stored->get_contenthash() !== $p['source']->get_contenthash() ||
                (int)$cm->section !== (int)$p['section']->id) {
            throw new RuntimeException('Worksheet placement or content verification failed.');
        }
    }
    foreach ($folders as $folder) {
        set_coursemodule_visible($folder->id, 0);
        \core_courseformat\formatactions::cm($course)->move_end_section((int)$folder->id, (int)$archive->id);
    }
    foreach ($before['modules'] as $id=>$original) {
        if (isset($folders[$id])) { continue; }
        $current = $DB->get_record('course_modules', ['id'=>$id], '*', MUST_EXIST);
        foreach (['course','module','instance','section','visible','availability','completion','groupmode','groupingid'] as $field) {
            if ($current->$field != $original->$field) { throw new RuntimeException('Unrelated activity changed.'); }
        }
    }
    $transaction->allow_commit();
} catch (Throwable $e) { $transaction->rollback($e); }
rebuild_course_cache($course->id, true);
echo json_encode(['verified_worksheets'=>count($plan), 'archived_folders'=>array_keys($folders),
    'backup'=>$backup, 'emails'=>0, 'enrolment_changes'=>0], JSON_PRETTY_PRINT) . PHP_EOL;
