<?php
/** Prepare named mentors and course essentials without sending invitations. JSON is supplied on stdin. */
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
[$options, $unknown] = cli_get_params(['apply'=>false, 'courseid'=>0], []);
if ($unknown || !$options['courseid']) { cli_error('Use --courseid=N [--apply], with JSON on stdin.'); }
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$course = \local_web3talents\local\participation_service::get_configured_course();
if ((int)$course->id !== (int)$options['courseid']) { cli_error('Configured course differs.'); }
if (get_config('local_web3talents', 'activation_emails_enabled')) { cli_error('Pause invitation emails first.'); }
$CFG->noemailever = true;
$CFG->nosmsever = true;
$context = context_course::instance($course->id);
$manual = $DB->get_record('enrol', ['courseid'=>$course->id,'enrol'=>'manual','status'=>ENROL_INSTANCE_ENABLED], '*', MUST_EXIST);
$roster = $input['mentors'] ?? [];
if (!$roster || count($roster) > 20) { cli_error('Provide an explicit mentor roster.'); }
$seen = [];
foreach ($roster as &$person) {
    $person['email'] = core_text::strtolower(trim($person['email'] ?? ''));
    if (!validate_email($person['email']) || empty($person['firstname']) || empty($person['lastname']) || isset($seen[$person['email']])) {
        cli_error('Invalid or duplicate mentor.');
    }
    $seen[$person['email']] = true;
    $users = $DB->get_records('user', ['email'=>$person['email'],'deleted'=>0]);
    if (count($users) > 1) { cli_error('Multiple accounts match a mentor email.'); }
    $person['user'] = $users ? reset($users) : null;
    if ($person['user'] && ($person['user']->suspended || is_siteadmin($person['user']))) {
        cli_error('Existing account needs manual review.');
    }
}
unset($person);
$zoom = $input['zoom_url'] ?? '';
if (parse_url($zoom, PHP_URL_SCHEME) !== 'https' || parse_url($zoom, PHP_URL_HOST) !== 'us06web.zoom.us'
        || !preg_match('~^/j/[0-9]+$~', parse_url($zoom, PHP_URL_PATH) ?? '')) { cli_error('Expected a Zoom participant join URL.'); }
$sessions = $input['sessions'] ?? [];
if (!$sessions) { cli_error('Explicit first-session dates are required.'); }
foreach ($sessions as &$session) {
    $session['timestamp'] = (new DateTimeImmutable($session['date'], new DateTimeZone('Europe/Berlin')))->getTimestamp();
    if (empty($session['name'])) { cli_error('Session name required.'); }
}
unset($session);
$role = $DB->get_record('role', ['shortname'=>'web3tmentor']);
$caps = ['moodle/course:view', 'moodle/course:viewparticipants', 'moodle/user:viewdetails',
    'mod/page:view', 'mod/resource:view', 'mod/url:view', 'mod/folder:view', 'mod/forum:viewdiscussion',
    'mod/forum:replypost', 'mod/forum:startdiscussion',
    'local/web3talents:viewmentorrooms', 'local/web3talents:viewstudentrooms',
    'local/web3talents:manageownavailability', 'local/web3talents:gradeassignedroom',
    'local/web3talents:manageparticipation'];
foreach ($caps as $cap) { if (!$DB->record_exists('capabilities', ['name'=>$cap])) { cli_error('Unknown capability: '.$cap); } }
echo json_encode(['mentor_count'=>count($roster), 'new_accounts'=>count(array_filter($roster,fn($p)=>!$p['user'])),
    'role'=>'Course mentor (no course editing or site administration)',
    'sessions'=>array_column($sessions,'name'), 'zoom_link'=>true, 'hide_page_modified'=>true,
    'emails'=>0, 'apply'=>(bool)$options['apply']],JSON_PRETTY_PRINT).PHP_EOL;
if (!$options['apply']) { exit; }
\core\session\manager::set_user(get_admin());
$beforegrades=$DB->count_records('local_w3t_room_grade');
$beforeresets=$DB->count_records('user_password_resets');
$beforestudents=array_keys(\local_web3talents\local\participation_service::get_students($course));
$transaction = $DB->start_delegated_transaction();
try {
    if (!$role) {
        $roleid=create_role('Mentor','web3tmentor','Course mentor: availability, attendance and assigned-room presentation feedback.');
        set_role_contextlevels($roleid,[CONTEXT_COURSE]);
        foreach($caps as $cap) { assign_capability($cap,CAP_ALLOW,$roleid,context_system::instance()->id); }
    } else { $roleid=(int)$role->id; }
    $enrol=enrol_get_plugin('manual');
    $ids=[];
    foreach ($roster as $person) {
        $user=$person['user'];
        if (!$user) {
            $username=$person['email'];
            if ($DB->record_exists('user',['username'=>$username,'mnethostid'=>$CFG->mnet_localhost_id])) { throw new RuntimeException('Username collision.'); }
            $user=(object)['username'=>$username,'email'=>$person['email'],
                'firstname'=>$person['firstname'],'lastname'=>$person['lastname'],
                'auth'=>'manual','confirmed'=>1,'mnethostid'=>$CFG->mnet_localhost_id,
                'password'=>bin2hex(random_bytes(32)),'lang'=>'en','timezone'=>'Europe/Berlin'];
            $user->id=user_create_user($user,true,false);
            unset($user->password);
            set_user_preference('auth_forcepasswordchange',1,$user->id);
        }
        $enrol->enrol_user($manual,$user->id,$roleid,0,0,ENROL_USER_ACTIVE);
        $ids[]=(int)$user->id;
    }
    foreach($sessions as $session) {
        $existing=$DB->get_record('local_w3t_session',['courseid'=>$course->id,'name'=>$session['name']]);
        if ($existing && (int)$existing->sessiondate!==$session['timestamp']) { throw new RuntimeException('Existing session date differs.'); }
        if (!$existing) { \local_web3talents\local\participation_service::create_session((int)$course->id,$session['name'],$session['timestamp'],$session['notes']??'',(int)$USER->id); }
    }
    $overview=$DB->get_record('course_sections',['course'=>$course->id,'section'=>0],'*',MUST_EXIST);
    course_update_section($course,$overview,['summary'=>'<p>Start here: choose your topic, join the live session, and catch up on recordings. Use the cohort WhatsApp community for weekly communication.</p>','summaryformat'=>FORMAT_HTML]);
    $zoomcm=$DB->get_record('course_modules',['course'=>$course->id,'idnumber'=>'w3t-live-zoom']);
    if (!$zoomcm) {
        $added=add_moduleinfo((object)['modulename'=>'url','module'=>$DB->get_field('modules','id',['name'=>'url'],MUST_EXIST),
            'section'=>0,'name'=>'Join live session on Zoom','externalurl'=>$zoom,'cmidnumber'=>'w3t-live-zoom',
            'visible'=>1,'visibleoncoursepage'=>1,'groupmode'=>0,'groupingid'=>0,'completion'=>0,
            'intro'=>'<p>Wednesdays at 18:00 Berlin time. Use the same email and full name as your Moodle profile.</p>',
            'introformat'=>FORMAT_HTML,'showdescription'=>1,'display'=>0,'parameters'=>[]],$course);
        $zoomcm=$DB->get_record('course_modules',['id'=>$added->coursemodule],'*',MUST_EXIST);
    } else {
        if ($DB->get_field('modules','name',['id'=>$zoomcm->module])!=='url') { throw new RuntimeException('Zoom activity type differs.'); }
        $DB->update_record('url',(object)['id'=>$zoomcm->instance,'externalurl'=>$zoom]);
    }
    $recordings=$DB->get_record('course_modules',['course'=>$course->id,'idnumber'=>'w3t-session-recordings'],'*',MUST_EXIST);
    \core_courseformat\formatactions::cm($course)->move_before((int)$zoomcm->id,(int)$recordings->id);
    foreach($DB->get_records('page',['course'=>$course->id]) as $page) {
        $display=empty($page->displayoptions)?[]:(array)unserialize_array($page->displayoptions);
        $display['printlastmodified']=0;
        $DB->update_record('page',(object)['id'=>$page->id,'displayoptions'=>serialize($display)]);
    }
    set_config('printlastmodified',0,'page');
    if ($beforegrades!==$DB->count_records('local_w3t_room_grade') || $beforeresets!==$DB->count_records('user_password_resets')
            || $beforestudents!==array_keys(\local_web3talents\local\participation_service::get_students($course))) {
        throw new RuntimeException('Unexpected change to grades, setup links or student cohort.');
    }
    foreach($ids as $id) {
        if (!has_capability('local/web3talents:gradeassignedroom',$context,$id)
                || has_capability('moodle/course:update',$context,$id)
                || has_capability('local/web3talents:manageacceptedapplicants',$context,$id)) {
            throw new RuntimeException('Mentor permissions verification failed.');
        }
    }
    $transaction->allow_commit();
} catch(Throwable $e) { $transaction->rollback($e); }
rebuild_course_cache($course->id,true);
echo json_encode(['verified_mentors'=>count($ids),'mentor_user_ids'=>$ids,'zoom_cmid'=>$zoomcm->id,
    'emails'=>0,'grades_changed'=>0,'student_membership_changed'=>false],JSON_PRETTY_PRINT).PHP_EOL;
