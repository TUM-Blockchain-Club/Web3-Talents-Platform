<?php
/** Read actual per-user Moodle availability, plus rollback-only group-sync regression checks. */
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/group/lib.php');
use local_web3talents\local\mentor_tools_access as access;

$CFG->noemailever = true;
$CFG->nosmsever = true;
\core\session\manager::set_user(get_admin());
$course = \local_web3talents\local\participation_service::get_configured_course();
$context = context_course::instance($course->id);
$group = $DB->get_record('groups', ['courseid' => $course->id, 'idnumber' => access::GROUP_IDNUMBER], '*', MUST_EXIST);
$modules = $DB->get_records_select('course_modules', 'course = ? AND (idnumber = ? OR idnumber LIKE ?)',
    [$course->id, 'w3t-mentor-tools', 'w3t-mentor-tool-%']);
if (count($modules) !== 5) { throw new RuntimeException('Expected one subsection and four links.'); }
$students = \local_web3talents\local\participation_service::get_students($course);
$mentors = \local_web3talents\local\participation_service::get_mentors($course);
$admins = get_admins();
$checked = [];
foreach (['students' => $students, 'mentors' => $mentors, 'admins' => $admins] as $kind => $users) {
    $checked[$kind] = 0;
    foreach ($users as $user) {
        $info = get_fast_modinfo($course, $user->id);
        $expected = $kind !== 'students';
        foreach ($modules as $module) {
            if ((bool)$info->get_cm($module->id)->uservisible !== $expected) {
                throw new RuntimeException('Visibility mismatch: ' . $kind . '/' . $user->id . '/' . $module->id);
            }
        }
        if ($kind === 'students') {
            foreach (['gradeassignedroom', 'manageparticipation', 'manageownavailability', 'viewmentorrooms'] as $cap) {
                if (has_capability('local/web3talents:' . $cap, $context, $user->id)) {
                    throw new RuntimeException('Unexpected student tool permission.');
                }
            }
        }
        $checked[$kind]++;
    }
}
$sectioncm = $DB->get_record('course_modules', ['course' => $course->id, 'idnumber' => 'w3t-mentor-tools'], '*', MUST_EXIST);
$delegated = $DB->get_record('course_sections', ['component' => 'mod_subsection', 'itemid' => $sectioncm->instance], '*', MUST_EXIST);
if ($delegated->availability !== $sectioncm->availability) { throw new RuntimeException('Subsection restrictions differ.'); }
$overview = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 0], '*', MUST_EXIST);
if ((int)explode(',', $overview->sequence)[0] !== (int)$sectioncm->id) { throw new RuntimeException('Not first in Overview.'); }
$teams = \local_web3talents\local\course_state_service::get_groups($course, $students);
if (isset($teams[$group->id])) { throw new RuntimeException('Access group leaked into student teams.'); }
if ($group->participation || (int)$group->visibility !== GROUPS_VISIBILITY_NONE) { throw new RuntimeException('Group is not private.'); }

// Exercise the actual sync against deliberately stale navigation memberships, then roll back.
$membersbefore = $DB->get_records('groups_members', ['groupid' => $group->id], 'userid');
$transaction = $DB->start_delegated_transaction();
try {
    $student = reset($students);
    $mentor = reset($mentors);
    groups_add_member($group, $student);
    groups_remove_member($group, $mentor);
    access::sync((int)$course->id);
    if (groups_is_member($group->id, $student->id) || !groups_is_member($group->id, $mentor->id)) {
        throw new RuntimeException('Sync failed to correct stale memberships.');
    }
    $transaction->rollback(new RuntimeException('EXPECTED_TEST_ROLLBACK'));
} catch (RuntimeException $e) {
    if ($e->getMessage() !== 'EXPECTED_TEST_ROLLBACK') { throw $e; }
}
$membersafter = $DB->get_records('groups_members', ['groupid' => $group->id], 'userid');
if ($membersbefore != $membersafter) { throw new RuntimeException('Rollback changed actual memberships.'); }
$observers = \core\event\manager::get_all_observers();
foreach (['role_assigned', 'role_unassigned', 'role_capabilities_updated',
        'user_enrolment_created', 'user_enrolment_updated', 'user_enrolment_deleted', 'user_updated'] as $event) {
    $found = false;
    foreach ($observers['\\core\\event\\' . $event] ?? [] as $observer) {
        if (ltrim($observer->callable, '\\') === 'local_web3talents\\local\\mentor_tools_access::access_changed') {
            $found = true;
        }
    }
    if (!$found) { throw new RuntimeException('Missing access-sync observer: ' . $event); }
}
echo json_encode(['passed' => true, 'visibility_checks' => $checked,
    'student_direct_tool_permissions' => 'denied', 'top_of_overview' => true,
    'excluded_from_student_teams' => true, 'stale_access_sync_test' => 'passed and rolled back',
    'access_sync_observers_registered' => true, 'emails' => 0], JSON_PRETTY_PRINT) . PHP_EOL;
