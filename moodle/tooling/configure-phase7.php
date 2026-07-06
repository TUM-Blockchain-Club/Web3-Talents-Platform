<?php
// Applies Phase 7 fundamentals course materials and communication setup.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');
require_once($CFG->dirroot . '/mod/folder/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/messagelib.php');
require_once($CFG->libdir . '/resourcelib.php');

global $CFG, $DB;

\core\session\manager::set_user(get_admin());

$course = $DB->get_record('course', ['shortname' => 'W3T-FUNDAMENTALS-DEV'], '*', MUST_EXIST);
$testpassword = getenv('WEB3T_PHASE2_TEST_PASSWORD') ?: 'ChangeMe123!';

function web3t_phase7_log(string $message): void {
    echo $message . PHP_EOL;
}

function web3t_phase7_role_id(string $shortname): int {
    global $DB;

    return (int)$DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
}

function web3t_phase7_ensure_user(string $username, string $firstname, string $lastname, string $email, string $password): stdClass {
    global $CFG, $DB;

    $username = core_text::strtolower(trim($username));
    $user = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
    if (!$user) {
        $user = (object)[
            'auth' => 'manual',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'username' => $username,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email,
            'lang' => $CFG->lang ?? 'en',
            'city' => 'Munich',
            'country' => 'DE',
        ];
        $user->id = user_create_user($user, false, false);
        $user = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
    } else {
        $user->firstname = $firstname;
        $user->lastname = $lastname;
        $user->email = $email;
        $user->auth = 'manual';
        $user->confirmed = 1;
        $user->timemodified = time();
        user_update_user($user, false, false);
        $user = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
    }

    update_internal_user_password($user, $password, true);
    set_user_preference('auth_forcepasswordchange', 0, $user);
    return $user;
}

function web3t_phase7_enrol(stdClass $course, stdClass $user, string $roleshortname): void {
    if (!enrol_try_internal_enrol($course->id, $user->id, web3t_phase7_role_id($roleshortname))) {
        throw new moodle_exception("Could not enrol {$user->username} as {$roleshortname}");
    }
}

function web3t_phase7_module_exists(stdClass $course, string $idnumber): bool {
    global $DB;

    return $DB->record_exists('course_modules', [
        'course' => $course->id,
        'idnumber' => $idnumber,
        'deletioninprogress' => 0,
    ]);
}

function web3t_phase7_add_page(stdClass $course, int $section, string $idnumber, string $name, string $intro, string $content): void {
    if (web3t_phase7_module_exists($course, $idnumber)) {
        web3t_phase7_log("Page already exists: {$name}");
        return;
    }

    [, , , , $moduleinfo] = prepare_new_moduleinfo_data($course, 'page', $section);
    $moduleinfo->name = $name;
    $moduleinfo->introeditor = ['text' => $intro, 'format' => FORMAT_HTML, 'itemid' => 0];
    $moduleinfo->content = $content;
    $moduleinfo->contentformat = FORMAT_HTML;
    $moduleinfo->display = RESOURCELIB_DISPLAY_OPEN;
    $moduleinfo->printintro = 1;
    $moduleinfo->printlastmodified = 1;
    $moduleinfo->popupwidth = 620;
    $moduleinfo->popupheight = 450;
    $moduleinfo->cmidnumber = $idnumber;

    add_moduleinfo($moduleinfo, $course);
    web3t_phase7_log("Created page: {$name}");
}

function web3t_phase7_add_url(stdClass $course, int $section, string $idnumber, string $name, string $intro, string $url): void {
    if (web3t_phase7_module_exists($course, $idnumber)) {
        web3t_phase7_log("URL already exists: {$name}");
        return;
    }

    [, , , , $moduleinfo] = prepare_new_moduleinfo_data($course, 'url', $section);
    $moduleinfo->name = $name;
    $moduleinfo->introeditor = ['text' => $intro, 'format' => FORMAT_HTML, 'itemid' => 0];
    $moduleinfo->externalurl = $url;
    $moduleinfo->display = RESOURCELIB_DISPLAY_AUTO;
    $moduleinfo->printintro = 1;
    $moduleinfo->popupwidth = 620;
    $moduleinfo->popupheight = 450;
    $moduleinfo->cmidnumber = $idnumber;

    add_moduleinfo($moduleinfo, $course);
    web3t_phase7_log("Created URL: {$name}");
}

function web3t_phase7_add_folder(stdClass $course): void {
    global $DB, $USER;

    $idnumber = 'w3t_session_handouts_folder';
    if (web3t_phase7_module_exists($course, $idnumber)) {
        web3t_phase7_log('Folder already exists: Session Handouts');
        return;
    }

    [, , , , $moduleinfo] = prepare_new_moduleinfo_data($course, 'folder', 0);
    $moduleinfo->name = 'Session Handouts';
    $moduleinfo->introeditor = [
        'text' => 'Moodle-hosted handouts and reference notes for live sessions.',
        'format' => FORMAT_HTML,
        'itemid' => 0,
    ];
    $moduleinfo->files = 0;
    $moduleinfo->display = FOLDER_DISPLAY_PAGE;
    $moduleinfo->showexpanded = 1;
    $moduleinfo->showdownloadfolder = 1;
    $moduleinfo->forcedownload = 0;
    $moduleinfo->cmidnumber = $idnumber;

    $created = add_moduleinfo($moduleinfo, $course);
    $context = context_module::instance($created->coursemodule);
    $fs = get_file_storage();
    $filerecord = [
        'contextid' => $context->id,
        'component' => 'mod_folder',
        'filearea' => 'content',
        'itemid' => 0,
        'filepath' => '/',
        'filename' => 'web3-talents-session-handouts.txt',
        'userid' => $USER->id,
    ];
    if (!$fs->file_exists($context->id, 'mod_folder', 'content', 0, '/', 'web3-talents-session-handouts.txt')) {
        $fs->create_file_from_string($filerecord, "Web3 Talents session handouts\n\nUse this folder for approved Moodle-hosted materials that replace ad-hoc Google Drive links for launch.\n");
    }
    $DB->set_field('folder', 'revision', 2, ['id' => $created->instance]);
    web3t_phase7_log('Created folder: Session Handouts');
}

function web3t_phase7_ensure_discussion(string $forumidnumber, string $subject, string $message, stdClass $user): void {
    global $DB;

    $cm = $DB->get_record('course_modules', ['idnumber' => $forumidnumber, 'deletioninprogress' => 0], '*', MUST_EXIST);
    $forum = $DB->get_record('forum', ['id' => $cm->instance], '*', MUST_EXIST);
    if ($DB->record_exists('forum_discussions', ['forum' => $forum->id, 'name' => $subject])) {
        web3t_phase7_log("Forum discussion already exists: {$subject}");
        return;
    }

    \core\session\manager::set_user($user);
    $discussion = (object)[
        'course' => $forum->course,
        'forum' => $forum->id,
        'message' => $message,
        'messageformat' => FORMAT_HTML,
        'messagetrust' => 0,
        'groupid' => 0,
        'mailnow' => 0,
        'subject' => $subject,
        'name' => $subject,
        'timestart' => 0,
        'timeend' => 0,
        'timelocked' => 0,
        'attachments' => 0,
        'pinned' => FORUM_DISCUSSION_UNPINNED,
    ];
    forum_add_discussion($discussion, null, null, $user->id);
    \core\session\manager::set_user(get_admin());
    web3t_phase7_log("Created forum discussion: {$subject}");
}

web3t_phase7_add_page(
    $course,
    0,
    'w3t_course_home',
    'Course Home And Weekly Workflow',
    'Start here for current Moodle-hosted materials, communication channels, and live-session preparation.',
    '<h3>How to use this course</h3><p>Use Moodle as the primary home for materials, announcements, topic selection, and cohort discussion.</p><ul><li>Read the current topic page before each live session.</li><li>Use Announcements for program updates.</li><li>Use Course Forum for questions and resource sharing.</li><li>Use direct messages for short student, mentor, or admin coordination.</li></ul>'
);

$materials = [
    [1, 'w3t_material_blockchain_foundations', 'Blockchain Foundations Starter Notes', 'Core blockchain concepts and terminology.', '<h3>Blockchain foundations</h3><p>This Moodle page replaces the first shared-drive handout for launch. It covers blocks, transactions, consensus, validators, and why decentralization matters.</p><p>Before the session, review the vocabulary and write down one question for the course forum.</p>'],
    [2, 'w3t_material_wallets_transactions', 'Wallets And Transactions Starter Notes', 'Wallet usage, transaction flow, and operational safety.', '<h3>Wallets and transactions</h3><p>Review account addresses, private-key safety, transaction fees, confirmations, and common wallet mistakes.</p><p>Bring one example of a safe wallet habit to discuss.</p>'],
    [3, 'w3t_material_smart_contracts', 'Smart Contracts Starter Notes', 'Smart contract concepts, use cases, and risks.', '<h3>Smart contracts</h3><p>Review contract state, function calls, events, upgrades, and audit mindset. The goal is conceptual literacy before technical depth.</p>'],
    [4, 'w3t_material_applications_protocols', 'Applications And Protocols Starter Notes', 'How Web3 applications and protocols fit together.', '<h3>Applications and protocols</h3><p>Review how frontends, wallets, RPC nodes, smart contracts, protocols, and governance fit into a user-facing Web3 application.</p>'],
    [5, 'w3t_material_security_responsible', 'Security And Responsible Participation Starter Notes', 'Security habits, responsible participation, and live-session preparation.', '<h3>Security and responsible participation</h3><p>Review phishing patterns, seed phrase handling, transaction review, and respectful cohort communication norms.</p>'],
];

foreach ($materials as [$section, $idnumber, $name, $intro, $content]) {
    web3t_phase7_add_page($course, $section, $idnumber, $name, $intro, $content);
}

web3t_phase7_add_url(
    $course,
    1,
    'w3t_external_ethereum_learn',
    'External Reference: Ethereum Learn',
    'Approved external reference for students who want extra blockchain fundamentals reading.',
    'https://ethereum.org/en/learn/'
);
web3t_phase7_add_folder($course);

$student1 = $DB->get_record('user', ['username' => 'w3t.student1', 'deleted' => 0], '*', MUST_EXIST);
$student2 = $DB->get_record('user', ['username' => 'w3t.student2', 'deleted' => 0], '*', MUST_EXIST);
$mentor = $DB->get_record('user', ['username' => 'w3t.mentor1', 'deleted' => 0], '*', MUST_EXIST);
$alumni = web3t_phase7_ensure_user('w3t.alumni1', 'Alumni', 'One', 'w3t.alumni1@example.test', $testpassword);
web3t_phase7_enrol($course, $alumni, 'student');

web3t_phase7_ensure_discussion(
    'w3t_announcements',
    'Moodle course home is ready',
    '<p>The Web3 Talents fundamentals course is now the primary home for announcements, topic materials, session handouts, and cohort discussion.</p>',
    $mentor
);
web3t_phase7_ensure_discussion(
    'w3t_course_forum',
    'Introduce yourself and share one Web3 question',
    '<p>Use this thread to introduce yourself and share one question you want to explore during the fundamentals cohort.</p>',
    $mentor
);

\core\session\manager::set_user($student1);
message_post_message($student1, $student2, 'Phase 7 direct-message smoke test between enrolled students.', FORMAT_PLAIN);
\core\session\manager::set_user(get_admin());

rebuild_course_cache($course->id, true);
purge_all_caches();

web3t_phase7_log('Phase 7 fundamentals course materials and communication configuration complete.');
