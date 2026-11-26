<?php
namespace local_web3talents\local;

defined('MOODLE_INTERNAL') || die();

/** Keep the navigation-only staff group aligned with current course permissions. */
class mentor_tools_access {
    public const GROUP_IDNUMBER = 'w3t-mentor-tools-access';

    public static function eligible(\context_course $context, int $userid): bool {
        return has_capability('local/web3talents:manage', $context, $userid)
            || (has_capability('local/web3talents:gradeassignedroom', $context, $userid)
                && has_capability('local/web3talents:manageparticipation', $context, $userid)
                && has_capability('local/web3talents:manageownavailability', $context, $userid));
    }

    public static function sync(int $courseid): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/group/lib.php');
        $group = $DB->get_record('groups', ['courseid' => $courseid, 'idnumber' => self::GROUP_IDNUMBER]);
        if (!$group) {
            return;
        }
        $context = \context_course::instance($courseid);
        $members = $DB->get_records('groups_members', ['groupid' => $group->id], '', 'userid,id');
        $users = get_enrolled_users($context, '', 0, 'u.id,u.deleted,u.suspended');
        foreach ($users as $user) {
            if (!$user->deleted && !$user->suspended && is_enrolled($context, $user, '', true)
                    && self::eligible($context, (int)$user->id)) {
                if (!isset($members[$user->id]) && !groups_add_member($group, $user)) {
                    throw new \coding_exception('Could not grant mentor-tools navigation access.');
                }
                unset($members[$user->id]);
            }
        }
        foreach ($members as $member) {
            groups_remove_member($group, $member->userid);
        }
    }

    /** Run after role/enrolment changes commit, including removals. */
    public static function access_changed(\core\event\base $event): void {
        global $DB;
        foreach ($DB->get_records('groups', ['idnumber' => self::GROUP_IDNUMBER], '', 'id,courseid') as $group) {
            self::sync((int)$group->courseid);
        }
    }
}
