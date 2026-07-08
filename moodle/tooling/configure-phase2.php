<?php
// Applies the repeatable Moodle base configuration for Phase 2.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');
require_once($CFG->dirroot . '/user/lib.php');

global $CFG, $DB, $USER;

\core\session\manager::set_user(get_admin());

$testpassword = getenv('WEB3T_PHASE2_TEST_PASSWORD') ?: 'ChangeMe123!';
$courseShortname = 'W3T-FUNDAMENTALS-DEV';
$courseFullname = 'Web3 Talents Fundamentals Cohort';

function web3t_log(string $message): void {
    echo $message . PHP_EOL;
}

function web3t_role_id(string $shortname): int {
    global $DB;

    return (int)$DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
}

function web3t_ensure_user(string $username, string $firstname, string $lastname, string $email, string $password): stdClass {
    global $CFG, $DB;

    $username = core_text::strtolower(trim($username));
    $user = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);

    if (!$user) {
        $user = new stdClass();
        $user->auth = 'manual';
        $user->confirmed = 1;
        $user->mnethostid = $CFG->mnet_localhost_id;
        $user->username = $username;
        $user->firstname = $firstname;
        $user->lastname = $lastname;
        $user->email = $email;
        $user->lang = $CFG->lang ?? 'en';
        $user->id = user_create_user($user, false, false);
        $user = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
        update_internal_user_password($user, $password, true);
        web3t_log("Created user: {$username}");
        return $user;
    }

    $user->firstname = $firstname;
    $user->lastname = $lastname;
    $user->email = $email;
    $user->auth = 'manual';
    $user->confirmed = 1;
    $user->timemodified = time();
    user_update_user($user, false, false);
    $user = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
    update_internal_user_password($user, $password, true);
    web3t_log("Updated user: {$username}");
    return $user;
}

function web3t_ensure_course(string $shortname, string $fullname): stdClass {
    global $DB;

    $course = $DB->get_record('course', ['shortname' => $shortname]);
    if ($course) {
        web3t_log("Course already exists: {$shortname}");
        return $course;
    }

    $course = new stdClass();
    $course->fullname = $fullname;
    $course->shortname = $shortname;
    $course->category = 1;
    $course->format = 'topics';
    $course->visible = 1;
    $course->summary = 'Development baseline for the Web3 Talents fundamentals cohort.';
    $course->summaryformat = FORMAT_HTML;
    $course->startdate = strtotime('2026-07-01 00:00:00 UTC');
    $course->enddate = 0;
    $course->newsitems = 5;
    $course->numsections = 11;
    $course->groupmode = NOGROUPS;
    $course->groupmodeforce = 0;
    $course->enablecompletion = 0;
    $course->showgrades = 0;

    $course = create_course($course);
    web3t_log("Created course: {$shortname}");
    return $course;
}

function web3t_configure_sections(stdClass $course): void {
    global $DB;

    $sections = [
        0 => ['Overview', 'Start here: program orientation, announcements, and general discussion.'],
    ];
    for ($topic = 1; $topic <= 10; $topic++) {
        $sections[$topic] = ["Topic {$topic}", "Week {$topic} materials, speaker notes, and presentation resources."];
    }
    $sections[11] = ['Topic Selection', 'Student topic selection for live learning sessions.'];

    course_get_format($course)->update_course_format_options((object)['id' => $course->id, 'numsections' => 11]);
    course_create_sections_if_missing($course, array_keys($sections));

    foreach ($sections as $sectionnum => [$name, $summary]) {
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $sectionnum], '*', MUST_EXIST);
        $section->name = $name;
        $section->summary = $summary;
        $section->summaryformat = FORMAT_HTML;
        $section->visible = 1;
        $DB->update_record('course_sections', $section);
    }

    rebuild_course_cache($course->id, true);
    web3t_log('Configured topic sections.');
}

function web3t_add_topic_subsections(stdClass $course): void {
    global $DB;

    if (!$DB->record_exists('modules', ['name' => 'subsection', 'visible' => 1])) {
        throw new moodle_exception('The Moodle Subsection activity must be enabled for the Web3 Talents topic structure.');
    }

    for ($topic = 1; $topic <= 10; $topic++) {
        for ($subtopic = 1; $subtopic <= 4; $subtopic++) {
            $idnumber = sprintf('w3t_topic_%02d_subtopic_%02d', $topic, $subtopic);
            $name = "Subtopic {$subtopic}";
            $cm = $DB->get_record('course_modules', [
                'course' => $course->id,
                'idnumber' => $idnumber,
                'deletioninprogress' => 0,
            ]);

            if ($cm) {
                $DB->set_field('subsection', 'name', $name, ['id' => $cm->instance]);
                $DB->set_field('course_sections', 'name', $name, [
                    'course' => $course->id,
                    'component' => 'mod_subsection',
                    'itemid' => $cm->instance,
                ]);
                continue;
            }

            [, , , , $moduleinfo] = prepare_new_moduleinfo_data($course, 'subsection', $topic);
            $moduleinfo->name = $name;
            $moduleinfo->cmidnumber = $idnumber;
            add_moduleinfo($moduleinfo, $course);
        }
    }

    rebuild_course_cache($course->id, true);
    web3t_log('Configured topic subsections.');
}

function web3t_module_exists(stdClass $course, string $idnumber): bool {
    global $DB;

    return $DB->record_exists('course_modules', [
        'course' => $course->id,
        'idnumber' => $idnumber,
        'deletioninprogress' => 0,
    ]);
}

function web3t_add_forum(stdClass $course, int $section, string $name, string $intro, string $type, string $idnumber): void {
    if (web3t_module_exists($course, $idnumber)) {
        web3t_log("Forum already exists: {$name}");
        return;
    }

    [, , , , $moduleinfo] = prepare_new_moduleinfo_data($course, 'forum', $section);
    $moduleinfo->name = $name;
    $moduleinfo->introeditor = ['text' => $intro, 'format' => FORMAT_HTML, 'itemid' => 0];
    $moduleinfo->type = $type;
    $moduleinfo->assessed = 0;
    $moduleinfo->scale = 0;
    $moduleinfo->grade_forum = 0;
    $moduleinfo->forcesubscribe = FORUM_CHOOSESUBSCRIBE;
    $moduleinfo->trackingtype = FORUM_TRACKING_OPTIONAL;
    $moduleinfo->cmidnumber = $idnumber;

    add_moduleinfo($moduleinfo, $course);
    web3t_log("Created forum: {$name}");
}

function web3t_ensure_announcements_forum(stdClass $course): void {
    global $DB;

    $idnumber = 'w3t_announcements';
    $existing = $DB->get_record_sql(
        "SELECT cm.*, f.id AS forumid
           FROM {course_modules} cm
           JOIN {modules} m ON m.id = cm.module
           JOIN {forum} f ON f.id = cm.instance
          WHERE cm.course = :courseid
            AND cm.idnumber = :idnumber
            AND cm.deletioninprogress = 0
            AND m.name = 'forum'",
        ['courseid' => $course->id, 'idnumber' => $idnumber]
    );

    $candidates = $DB->get_records_sql(
        "SELECT cm.id, cm.instance, cm.idnumber, f.name, f.type
           FROM {course_modules} cm
           JOIN {modules} m ON m.id = cm.module
           JOIN {forum} f ON f.id = cm.instance
          WHERE cm.course = :courseid
            AND cm.deletioninprogress = 0
            AND m.name = 'forum'
            AND " . $DB->sql_compare_text('f.name') . " = :name
            AND f.type = 'news'
          ORDER BY cm.id ASC",
        ['courseid' => $course->id, 'name' => 'Announcements']
    );

    if (!$existing && $candidates) {
        $existing = reset($candidates);
        $DB->set_field('course_modules', 'idnumber', $idnumber, ['id' => $existing->id]);
        $existing->forumid = $existing->instance;
        web3t_log('Reused default Announcements forum.');
    }

    if ($existing) {
        $forum = $DB->get_record('forum', ['id' => $existing->forumid], '*', MUST_EXIST);
        $forum->name = 'Announcements';
        $forum->intro = 'Official program announcements for the cohort.';
        $forum->introformat = FORMAT_HTML;
        $forum->type = 'news';
        $DB->update_record('forum', $forum);

        foreach ($candidates as $candidate) {
            if ((int)$candidate->id === (int)$existing->id) {
                continue;
            }
            \core_courseformat\formatactions::cm($course->id)->delete((int)$candidate->id);
            web3t_log('Removed duplicate Announcements forum.');
        }
        return;
    }

    web3t_add_forum($course, 0, 'Announcements', 'Official program announcements for the cohort.', 'news', $idnumber);
}

function web3t_add_choice(stdClass $course, int $section): void {
    global $DB;

    $idnumber = 'w3t_topic_choice';
    $existing = $DB->get_record('course_modules', [
        'course' => $course->id,
        'idnumber' => $idnumber,
        'deletioninprogress' => 0,
    ]);
    if ($existing) {
        $sectionrecord = $DB->get_record('course_sections', [
            'course' => $course->id,
            'section' => $section,
        ], '*', MUST_EXIST);
        \core_courseformat\formatactions::cm($course->id)->move_end_section((int)$existing->id, (int)$sectionrecord->id);
        web3t_log('Choice already exists: Fundamentals Topic Selection');
        return;
    }

    [, , , , $moduleinfo] = prepare_new_moduleinfo_data($course, 'choice', $section);
    $moduleinfo->name = 'Fundamentals Topic Selection';
    $moduleinfo->introeditor = [
        'text' => 'Select the topic you want to focus on for the next live session.',
        'format' => FORMAT_HTML,
        'itemid' => 0,
    ];
    $moduleinfo->option = [
        'Blockchain Foundations',
        'Wallets And Transactions',
        'Smart Contracts',
        'Applications And Protocols',
        'Security And Responsible Participation',
    ];
    $moduleinfo->limit = [0, 0, 0, 0, 0];
    $moduleinfo->allowupdate = 1;
    $moduleinfo->showresults = 0;
    $moduleinfo->publish = 0;
    $moduleinfo->display = 0;
    $moduleinfo->allowmultiple = 0;
    $moduleinfo->showunanswered = 1;
    $moduleinfo->includeinactive = 0;
    $moduleinfo->cmidnumber = $idnumber;

    add_moduleinfo($moduleinfo, $course);
    web3t_log('Created Choice: Fundamentals Topic Selection');
}

function web3t_enrol(stdClass $course, stdClass $user, string $roleshortname): void {
    $roleid = web3t_role_id($roleshortname);
    if (!enrol_try_internal_enrol($course->id, $user->id, $roleid)) {
        throw new moodle_exception("Could not enrol {$user->username} as {$roleshortname}");
    }
    web3t_log("Enrolled {$user->username} as {$roleshortname}");
}

function web3t_assign_course_role(stdClass $course, stdClass $user, string $roleshortname): void {
    $context = context_course::instance($course->id);
    role_assign(web3t_role_id($roleshortname), $user->id, $context->id);
    web3t_log("Assigned {$roleshortname} role to {$user->username}");
}

set_config('fullname', 'Web3 Talents Moodle');
set_config('shortname', 'Web3 Talents');
set_config('registerauth', '');
set_config('messaging', 1);
set_config('messagingallusers', 0);
set_config('noreplyaddress', 'noreply@example.test');
set_config('supportemail', 'support@example.test');
set_config('enablecompletion', 0);
set_config('enablebadges', 0);

$course = web3t_ensure_course($courseShortname, $courseFullname);
web3t_configure_sections($course);
web3t_add_topic_subsections($course);

web3t_ensure_announcements_forum($course);
web3t_add_forum($course, 0, 'Course Forum', 'General course discussion for students and mentors.', 'general', 'w3t_course_forum');
web3t_add_choice($course, 11);

$student1 = web3t_ensure_user('w3t.student1', 'Student', 'One', 'w3t.student1@example.test', $testpassword);
$student2 = web3t_ensure_user('w3t.student2', 'Student', 'Two', 'w3t.student2@example.test', $testpassword);
$mentor = web3t_ensure_user('w3t.mentor1', 'Mentor', 'One', 'w3t.mentor1@example.test', $testpassword);
$programadmin = web3t_ensure_user('w3t.programadmin', 'Program', 'Admin', 'w3t.programadmin@example.test', $testpassword);

web3t_enrol($course, $student1, 'student');
web3t_enrol($course, $student2, 'student');
web3t_enrol($course, $mentor, 'editingteacher');
web3t_enrol($course, $programadmin, 'manager');
web3t_assign_course_role($course, $programadmin, 'manager');

rebuild_course_cache($course->id, true);
purge_all_caches();

web3t_log('Phase 2 base Moodle configuration complete.');
