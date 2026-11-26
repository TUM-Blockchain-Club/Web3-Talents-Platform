<?php
/** Add a restricted native subsection without changing existing curriculum or sending mail. */
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/group/lib.php');
use local_web3talents\local\mentor_tools_access as access;

[$options, $unknown] = cli_get_params(['apply' => false, 'courseid' => 0], []);
$course = \local_web3talents\local\participation_service::get_configured_course();
if ($unknown || (int)$options['courseid'] !== (int)$course->id) {
    cli_error('Specify the configured --courseid=N [--apply].');
}
$CFG->noemailever = true;
$CFG->nosmsever = true;
\core\session\manager::set_user(get_admin());
$context = context_course::instance($course->id);
$links = [
    'grading' => ['Grading and feedback', 'mentor_grading.php', 'Grade presentations and leave feedback for your assigned rooms.'],
    'attendance' => ['Attendance and participation', 'participation.php', 'Review session attendance and record participation.'],
    'availability' => ['My mentor availability', 'mentor_availability.php', 'Set your availability for upcoming sessions.'],
    'rooms' => ['My mentor room assignments', 'mentor_rooms.php', 'See your assigned rooms and students after allocations are published.'],
];
$users = get_enrolled_users($context, '', 0, 'u.id,u.deleted,u.suspended');
$eligible = array_filter($users, fn($u) => !$u->suspended && !$u->deleted
    && is_enrolled($context, $u, '', true) && access::eligible($context, (int)$u->id));
echo json_encode(['courseid' => $course->id, 'eligible_enrolled_staff' => count($eligible),
    'links' => array_column($links, 0), 'emails' => 0, 'apply' => (bool)$options['apply']], JSON_PRETTY_PRINT) . PHP_EOL;
if (!$options['apply']) { exit; }
$before = ['sections' => $DB->get_records('course_sections', ['course' => $course->id]),
    'modules' => $DB->get_records('course_modules', ['course' => $course->id]),
    'groups' => $DB->get_records('groups', ['courseid' => $course->id])];
$backupdir = $CFG->dataroot . '/web3talents-backups';
if (!is_dir($backupdir) && !mkdir($backupdir, 0700, true)) { cli_error('Cannot create backup directory.'); }
$backup = $backupdir . '/mentor-tools-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.json';
if (file_put_contents($backup, json_encode($before), LOCK_EX) === false) { cli_error('Backup failed.'); }
chmod($backup, 0600);
$transaction = $DB->start_delegated_transaction();
try {
    $group = $DB->get_record('groups', ['courseid' => $course->id, 'idnumber' => access::GROUP_IDNUMBER]);
    if (!$group) {
        $groupid = groups_create_group((object)['courseid' => $course->id, 'idnumber' => access::GROUP_IDNUMBER,
            'name' => 'Mentor tools access', 'description' => 'Automatically managed staff navigation access. Not a student team.',
            'descriptionformat' => FORMAT_HTML, 'visibility' => GROUPS_VISIBILITY_NONE,
            'participation' => 0, 'enablemessaging' => 0]);
    } else { $groupid = (int)$group->id; }
    access::sync((int)$course->id);
    $availability = json_encode(['op' => '&', 'c' => [['type' => 'group', 'id' => (int)$groupid]], 'showc' => [false]]);
    new \core_availability\tree(json_decode($availability));
    $overview = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 0], '*', MUST_EXIST);
    $cm = $DB->get_record('course_modules', ['course' => $course->id, 'idnumber' => 'w3t-mentor-tools']);
    if (!$cm) {
        $added = add_moduleinfo((object)['modulename' => 'subsection',
            'module' => $DB->get_field('modules', 'id', ['name' => 'subsection'], MUST_EXIST),
            'section' => 0, 'name' => 'Mentor tools', 'cmidnumber' => 'w3t-mentor-tools',
            'visible' => 1, 'visibleoncoursepage' => 1, 'availability' => $availability,
            'completion' => 0, 'groupmode' => 0, 'groupingid' => 0,
            'intro' => '', 'introformat' => FORMAT_HTML], $course);
        $cm = $DB->get_record('course_modules', ['id' => $added->coursemodule], '*', MUST_EXIST);
    }
    if ($cm->availability !== $availability) { throw new RuntimeException('Existing restriction differs.'); }
    $section = $DB->get_record('course_sections', ['course' => $course->id, 'component' => 'mod_subsection',
        'itemid' => $cm->instance], '*', MUST_EXIST);
    $ids = ['section' => (int)$cm->id];
    foreach ($links as $key => [$name, $path, $description]) {
        $idnumber = 'w3t-mentor-tool-' . $key;
        $child = $DB->get_record('course_modules', ['course' => $course->id, 'idnumber' => $idnumber]);
        if (!$child) {
            $added = add_moduleinfo((object)['modulename' => 'url',
                'module' => $DB->get_field('modules', 'id', ['name' => 'url'], MUST_EXIST),
                'section' => $section->section, 'name' => $name, 'cmidnumber' => $idnumber,
                'externalurl' => $CFG->wwwroot . '/local/web3talents/' . $path,
                'visible' => 1, 'visibleoncoursepage' => 1, 'availability' => $availability,
                'completion' => 0, 'groupmode' => 0, 'groupingid' => 0,
                'intro' => '<p>' . $description . '</p>', 'introformat' => FORMAT_HTML,
                'showdescription' => 1, 'display' => 0, 'parameters' => []], $course);
            $child = $DB->get_record('course_modules', ['id' => $added->coursemodule], '*', MUST_EXIST);
        }
        if ($child->availability !== $availability || $child->section != $section->id) {
            throw new RuntimeException('Unexpected child location/restriction.');
        }
        $ids[$key] = (int)$child->id;
    }
    $first = (int)explode(',', $overview->sequence)[0];
    if ($first && $first !== (int)$cm->id) {
        \core_courseformat\formatactions::cm($course)->move_before((int)$cm->id, $first);
    }
    foreach ($before['modules'] as $id => $original) {
        $current = $DB->get_record('course_modules', ['id' => $id], '*', MUST_EXIST);
        foreach (['course', 'module', 'instance', 'section', 'visible', 'availability', 'completion', 'groupmode', 'groupingid'] as $field) {
            if ($current->$field != $original->$field) { throw new RuntimeException('Existing activity changed: ' . $id . '/' . $field); }
        }
    }
    foreach ($before['sections'] as $id => $original) {
        $current = $DB->get_record('course_sections', ['id' => $id], '*', MUST_EXIST);
        foreach (['section', 'name', 'availability', 'visible', 'summary'] as $field) {
            if ($current->$field != $original->$field) { throw new RuntimeException('Existing section changed: ' . $id . '/' . $field); }
        }
    }
    $transaction->allow_commit();
} catch (Throwable $e) { $transaction->rollback($e); }
rebuild_course_cache($course->id, true);
echo json_encode(['created' => $ids, 'private_group' => $groupid, 'backup' => $backup,
    'existing_course_content_preserved' => true, 'emails' => 0], JSON_PRETTY_PRINT) . PHP_EOL;
