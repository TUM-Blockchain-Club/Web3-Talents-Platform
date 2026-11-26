<?php
/** Put weekly tools first and clarify communication. Dry-run unless --apply is supplied. */
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/course/lib.php');
[$options, $unknown] = cli_get_params(['apply' => false, 'courseid' => 0], []);
if ($unknown || !$options['courseid']) { cli_error('Use --courseid=N [--apply].'); }
$course = \local_web3talents\local\participation_service::get_configured_course();
if ((int)$course->id !== (int)$options['courseid']) { cli_error('Configured course differs.'); }
$overview = $DB->get_record('course_sections', ['course'=>$course->id, 'section'=>0], '*', MUST_EXIST);
$page = $DB->get_record('page', ['course'=>$course->id, 'name'=>'Course Home And Weekly Workflow'], '*', MUST_EXIST);
$url = $DB->get_record('url', ['course'=>$course->id, 'name'=>'Choose Weekly Topic'], '*', MUST_EXIST);
$topiccm = get_coursemodule_from_instance('url', $url->id, $course->id, false, MUST_EXIST);
$folder = $DB->get_record('folder', ['course'=>$course->id, 'name'=>'Recordings']);
$content = '<h3>How to use this course</h3>' .
    '<p><strong>Our cohort WhatsApp community is the main communication channel.</strong> ' .
    'Join it for weekly updates, questions, resource sharing, and coordination with peers, mentors, and the team.</p>' .
    '<p><a href="https://chat.whatsapp.com/LAYZdrkfg5tAxZVVyILj9e">Join the cohort WhatsApp community</a></p>' .
    '<p>Use Moodle for course materials, worksheets, assignments, topic selection, and session recordings.</p>' .
    '<ul><li>Check WhatsApp regularly for session updates and reminders.</li>' .
    '<li>Use <strong>Choose Weekly Topic</strong> at the top of Overview when topic selection is open.</li>' .
    '<li>Read the current topic materials and your group worksheet before each live session.</li>' .
    '<li>Coordinate your group research and presentation through WhatsApp.</li>' .
    '<li>Find uploaded session recordings in <strong>Recordings</strong> at the top of Overview.</li></ul>';
echo json_encode(['courseid'=>$course->id, 'workflow_page'=>$page->id,
    'top_order'=>['Choose Weekly Topic', 'Recordings'], 'create_folder'=>!$folder,
    'apply'=>(bool)$options['apply']], JSON_PRETTY_PRINT) . PHP_EOL;
if (!$options['apply']) { exit; }
$CFG->noemailever = true;
\core\session\manager::set_user(get_admin());
$backupdir = $CFG->dataroot . '/web3talents-backups';
if (!is_dir($backupdir) && !mkdir($backupdir, 0700, true)) { cli_error('Cannot create backup directory.'); }
$before = ['page'=>$page, 'sections'=>$DB->get_records('course_sections', ['course'=>$course->id]),
    'modules'=>$DB->get_records('course_modules', ['course'=>$course->id])];
$backup = $backupdir . '/weekly-workflow-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.json';
if (file_put_contents($backup, json_encode($before), LOCK_EX) === false) { cli_error('Backup failed.'); }
chmod($backup, 0600);
$transaction = $DB->start_delegated_transaction();
try {
    $DB->update_record('page', (object)['id'=>$page->id, 'content'=>$content,
        'contentformat'=>FORMAT_HTML, 'timemodified'=>time()]);
    if (!$folder) {
        $added = add_moduleinfo((object)[
            'modulename'=>'folder', 'module'=>$DB->get_field('modules', 'id', ['name'=>'folder'], MUST_EXIST),
            'section'=>0, 'name'=>'Recordings', 'cmidnumber'=>'w3t-session-recordings',
            'visible'=>1, 'visibleoncoursepage'=>1, 'groupmode'=>0, 'groupingid'=>0,
            'completion'=>0, 'intro'=>'<p>Session recordings will be uploaded here after the live sessions.</p>',
            'introformat'=>FORMAT_HTML, 'showdescription'=>1, 'display'=>0,
            'showexpanded'=>1, 'showdownloadfolder'=>0, 'forcedownload'=>0,
        ], $course);
        $foldercmid = (int)$added->coursemodule;
    } else {
        $foldercmid = (int)get_coursemodule_from_instance('folder', $folder->id, $course->id, false, MUST_EXIST)->id;
    }
    $current = $DB->get_record('course_sections', ['id'=>$overview->id], '*', MUST_EXIST);
    $others = array_values(array_filter(array_map('intval', explode(',', $current->sequence)),
        static fn($id) => $id && $id !== (int)$topiccm->id && $id !== $foldercmid));
    $actions = \core_courseformat\formatactions::cm($course);
    if ($others) { $actions->move_before($foldercmid, $others[0]); }
    else { $actions->move_end_section($foldercmid, (int)$overview->id); }
    $actions->move_before((int)$topiccm->id, $foldercmid);
    foreach ($before['modules'] as $id=>$original) {
        $current = $DB->get_record('course_modules', ['id'=>$id], '*', MUST_EXIST);
        foreach (['course','module','instance','visible','availability','completion','groupmode','groupingid'] as $field) {
            if ($current->$field != $original->$field) { throw new RuntimeException('Unrelated activity changed.'); }
        }
        if ((int)$id !== (int)$topiccm->id && (int)$id !== $foldercmid && $current->section != $original->section) {
            throw new RuntimeException('Unrelated activity moved.');
        }
    }
    $sequence = explode(',', $DB->get_field('course_sections', 'sequence', ['id'=>$overview->id]));
    if (array_map('intval', array_slice($sequence, 0, 2)) !== [(int)$topiccm->id, $foldercmid]) {
        throw new RuntimeException('Top order verification failed.');
    }
    $transaction->allow_commit();
} catch (Throwable $e) { $transaction->rollback($e); }
rebuild_course_cache($course->id, true);
echo json_encode(['verified'=>true, 'backup'=>$backup, 'recordings_cmid'=>$foldercmid, 'emails'=>0], JSON_PRETTY_PRINT) . PHP_EOL;
