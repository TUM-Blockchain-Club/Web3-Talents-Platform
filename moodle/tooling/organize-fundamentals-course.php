<?php
/** Explicit, repeatable course-label migration. Dry run unless --apply is supplied. */
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/clilib.php');
[$options, $unknown] = cli_get_params(['apply'=>false, 'courseid'=>0], []);
if ($unknown || !(int)$options['courseid']) { cli_error('Supply --courseid=N and optionally --apply.'); }
$course = $DB->get_record('course', ['id'=>(int)$options['courseid']], '*', MUST_EXIST);
$configured = get_config('local_web3talents', 'fundamentals_course_shortname');
$newshort = 'Blockchain Fundamentals 1';
if ($configured !== $course->shortname || !in_array($course->shortname, ['W3T-FUNDAMENTALS-DEV', $newshort], true)) {
    cli_error('Course identity/configuration differs; refusing changes.');
}
if ($DB->record_exists_select('course', 'shortname = ? AND id <> ?', [$newshort, $course->id])) {
    cli_error('Target shortname is already used by another course.');
}
$topics = [
    1 => ['Digital Trust & Decentralization', 'Foundational Trust', 'The Need for Decentralization & Digital Trust'],
    2 => ['Cryptography: Hashing, Keys & Signatures', 'Foundational Trust', 'Cryptography: Hashing, Keys & Signatures'],
    3 => ['Wallets & Peer-to-Peer Networks', 'The Ledger Architecture', 'Wallets, Peer-to-Peer Networks & Propagation'],
    4 => ['Bitcoin: Data & Transactions', 'The Ledger Architecture', 'Bitcoin: Data Structure & Transactions'],
    5 => ['Consensus & the Blockchain Trilemma', 'Securing the State', 'Proof-of-Work, Proof-of-Stake & the Blockchain Trilemma'],
    6 => ['Ethereum: The World Computer', 'Securing the State', 'Architectural Shift: The World Computer (Ethereum)'],
    7 => ['Smart Contracts, Gas, Tokens & Oracles', 'The Programmable Layer', 'Smart Contracts, Gas, Token Standards & Oracles'],
    8 => ['Tokenomics', 'The Programmable Layer', 'Tokenomics'],
    9 => ['Stablecoins, RWA & DeFi', 'The New Economy', 'The Financial Layer: Stablecoins, RWA & DeFi Intro'],
    10 => ['Taxation in Crypto', 'The New Economy', 'Taxation in Crypto'],
];
$sections = $DB->get_records('course_sections', ['course'=>$course->id], 'section', '*');
$byindex = [];
foreach ($sections as $section) { $byindex[(int)$section->section] = $section; }
foreach ($topics as $index=>$topic) {
    if (!isset($byindex[$index]) || !in_array($byindex[$index]->name, ['Topic ' . $index, $index . '. ' . $topic[0]], true)) {
        cli_error('Unexpected topic section; inspect before applying.');
    }
}
$legacytitles = ['Blockchain Foundations Starter Notes', 'Wallets And Transactions Starter Notes',
    'Smart Contracts Starter Notes', 'Applications And Protocols Starter Notes',
    'Security And Responsible Participation Starter Notes', 'External Reference: Ethereum Learn'];
\core\session\manager::set_user(get_admin());
$info = get_fast_modinfo($course);
$tomove = [];
foreach ($info->get_cms() as $cm) {
    if (in_array($cm->name, $legacytitles, true) && in_array($cm->modname, ['page','url'], true)) {
        $tomove[] = $cm->id;
    }
}
$plan = ['courseid'=>(int)$course->id, 'shortname'=>$newshort, 'fullname'=>'Blockchain Fundamentals 1',
    'topic_titles'=>array_map(static fn($t)=>$t[0], $topics), 'supplementary_activities'=>count($tomove),
    'deletions'=>0, 'enrolment_changes'=>0, 'emails'=>0];
if (!$options['apply']) { echo json_encode($plan, JSON_PRETTY_PRINT) . PHP_EOL; exit; }
$CFG->noemailever = true;
$backupdir = $CFG->dataroot . '/web3talents-backups';
if (!is_dir($backupdir) && !mkdir($backupdir, 0700, true)) { cli_error('Cannot create backup directory.'); }
$backup = $backupdir . '/course-layout-' . $course->id . '-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.json';
$modules = $DB->get_records('course_modules', ['course'=>$course->id], 'id');
$payload = ['course'=>$course, 'configured_shortname'=>$configured, 'sections'=>$sections, 'modules'=>$modules];
if (file_put_contents($backup, json_encode($payload, JSON_PRETTY_PRINT), LOCK_EX) === false) { cli_error('Backup failed.'); }
chmod($backup, 0600);
$transaction = $DB->start_delegated_transaction();
try {
    update_course((object)['id'=>$course->id, 'shortname'=>$newshort, 'fullname'=>'Blockchain Fundamentals 1']);
    set_config('fundamentals_course_shortname', $newshort, 'local_web3talents');
    foreach ($topics as $index=>$topic) {
        $firstweek = $index * 2 - 1;
        $summary = '<p><strong>' . s($topic[1]) . ' | Weeks ' . $firstweek . '-' . ($firstweek + 1) . '</strong></p>' .
            '<p>' . s($topic[2]) . '</p><p>Week ' . $firstweek . ': live lecture and group preparation. Week ' .
            ($firstweek + 1) . ': peer presentations and discussion.</p>';
        course_update_section($course->id, $byindex[$index], ['name'=>$index . '. ' . $topic[0],
            'summary'=>$summary, 'summaryformat'=>FORMAT_HTML]);
    }
    $supplement = $DB->get_record('course_sections', ['course'=>$course->id, 'name'=>'Supplementary resources']);
    if ($tomove && !$supplement) {
        $supplement = course_create_section($course->id);
        course_update_section($course->id, $supplement, ['name'=>'Supplementary resources',
            'summary'=>'<p>Introductory starter notes and reference links. These supplement the course and are not the complete lecture materials or group worksheets.</p>',
            'summaryformat'=>FORMAT_HTML]);
    }
    foreach ($tomove as $cmid) {
        \core_courseformat\formatactions::cm($course->id)->move_end_section($cmid, $supplement->id);
    }
    $after = $DB->get_records('course_modules', ['course'=>$course->id], 'id');
    if (array_keys($modules) !== array_keys($after)) { throw new RuntimeException('Activity IDs changed.'); }
    foreach ($modules as $id=>$cm) {
        foreach (['module','instance','visible','availability','completion'] as $field) {
            if ($cm->$field !== $after[$id]->$field) { throw new RuntimeException('Unexpected activity change.'); }
        }
    }
    $transaction->allow_commit();
} catch (Throwable $e) { $transaction->rollback($e); }
rebuild_course_cache($course->id, true);
echo json_encode($plan + ['applied'=>true,'backup'=>$backup], JSON_PRETTY_PRINT) . PHP_EOL;
