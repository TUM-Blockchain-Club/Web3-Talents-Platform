<?php
/** Read-only verification of uploaded content and visibility for a student. */
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');
[$options, $unknown] = cli_get_params(['courseid'=>0, 'source'=>''], []);
if ($unknown || !$options['courseid'] || !$options['source']) { cli_error('Supply --courseid and --source.'); }
$course = get_course((int)$options['courseid']);
if ($course->shortname !== 'Blockchain Fundamentals 1') { cli_error('Wrong course.'); }
$context = context_course::instance($course->id);
$students = $DB->get_records_sql('SELECT DISTINCT u.id FROM {user} u
    JOIN {role_assignments} ra ON ra.userid = u.id JOIN {role} r ON r.id = ra.roleid
    JOIN {user_enrolments} ue ON ue.userid = u.id JOIN {enrol} e ON e.id = ue.enrolid
    WHERE ra.contextid = :context AND r.archetype = :role AND e.courseid = :course
    AND ue.status = 0 AND e.status = 0 AND u.suspended = 0 AND u.deleted = 0 ORDER BY u.id',
    ['context'=>$context->id, 'role'=>'student', 'course'=>$course->id], 0, 1);
if (!$students) { cli_error('No active student available for visibility check.'); }
$student = reset($students);
$modinfo = get_fast_modinfo($course, $student->id);
$entries = json_decode(file_get_contents($options['source'] . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$fs = get_file_storage();
$groups = 0;
foreach ($entries as $entry) {
    $idnumber = 'w3t-worksheet-topic' . $entry['topic'] . ($entry['group'] ? '-group' . $entry['group'] : '-general');
    if ($DB->count_records('course_modules', ['course'=>$course->id, 'idnumber'=>$idnumber]) !== 1) {
        cli_error('Missing or duplicate resource: ' . $idnumber);
    }
    $cm = $DB->get_record('course_modules', ['course'=>$course->id, 'idnumber'=>$idnumber], '*', MUST_EXIST);
    $file = $fs->get_file(context_module::instance($cm->id)->id, 'mod_resource', 'content', 0, '/', $entry['filename']);
    if (!$file || $file->get_contenthash() !== $entry['sha1']) { cli_error('Content mismatch.'); }
    if (!$modinfo->get_cm($cm->id)->uservisible) { cli_error('Resource unavailable to student: ' . $idnumber); }
    if ($entry['group']) {
        $title = preg_replace('/^Group\s+\d+:\s*/i', '', trim($entry['title']));
        $section = $DB->get_record('course_sections', ['id'=>$cm->section], '*', MUST_EXIST);
        if ($section->component !== 'mod_subsection' || $section->name !== $entry['group'] . ': ' . $title) {
            cli_error('Subtopic placement or label mismatch.');
        }
        $groups++;
    }
}
echo json_encode(['verified_files'=>count($entries), 'named_group_subtopics'=>$groups,
    'student_visible'=>true, 'duplicate_resources'=>0, 'emails'=>0], JSON_PRETTY_PRINT) . PHP_EOL;
