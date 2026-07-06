<?php
// Validates Phase 8B weekly group-slot topic selection.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');
require_once($CFG->dirroot . '/local/web3talents/classes/local/course_state_service.php');
require_once($CFG->dirroot . '/local/web3talents/classes/local/topic_round_service.php');
require_once($CFG->dirroot . '/local/web3talents/classes/task/finalize_topic_rounds.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->libdir . '/enrollib.php');

use local_web3talents\local\topic_round_service;

global $DB;

\core\session\manager::set_user(get_admin());

function web3t_phase8b_assert(bool $condition, string $message): void {
    if (!$condition) {
        throw new moodle_exception("Phase 8B validation failed: {$message}");
    }
    echo "OK: {$message}" . PHP_EOL;
}

function web3t_phase8b_expect_exception(callable $callback, string $message): void {
    try {
        $callback();
    } catch (Throwable $exception) {
        echo "OK: {$message}" . PHP_EOL;
        return;
    }
    throw new moodle_exception("Phase 8B validation failed: {$message}");
}

function web3t_phase8b_topic_id(int $roundid, string $topicname): int {
    global $DB;

    $topics = $DB->get_records('local_w3t_topic', ['roundid' => $roundid]);
    foreach ($topics as $topic) {
        if (trim($topic->name) === $topicname) {
            return (int)$topic->id;
        }
    }
    throw new moodle_exception("Missing Phase 8B topic {$topicname}");
}

foreach (['local_w3t_pset', 'local_w3t_pgroup', 'local_w3t_pmember', 'local_w3t_round', 'local_w3t_topic', 'local_w3t_choice', 'local_w3t_fgroup'] as $table) {
    web3t_phase8b_assert($DB->get_manager()->table_exists($table), "{$table} table exists");
}

$task = $DB->get_record('task_scheduled', ['component' => 'local_web3talents', 'classname' => '\local_web3talents\task\finalize_topic_rounds']);
web3t_phase8b_assert($task !== false, 'automatic finalization scheduled task is registered');

$course = $DB->get_record('course', ['shortname' => 'W3T-FUNDAMENTALS-DEV'], '*', MUST_EXIST);
$coursecontext = context_course::instance($course->id);
$rounds = $DB->get_records('local_w3t_round', ['courseid' => $course->id, 'name' => 'Phase 8B Weekly Topic Selection'], 'id DESC', '*', 0, 1);
$round = reset($rounds);
web3t_phase8b_assert($round !== false, 'Phase 8B round exists');

$student1 = $DB->get_record('user', ['username' => 'w3t.student1', 'deleted' => 0], '*', MUST_EXIST);
$student2 = $DB->get_record('user', ['username' => 'w3t.student2', 'deleted' => 0], '*', MUST_EXIST);
$alumni = $DB->get_record('user', ['username' => 'w3t.alumni1', 'deleted' => 0], '*', MUST_EXIST);
$warning = $DB->get_record('user', ['username' => 'w3t.phase8.warning', 'deleted' => 0], '*', MUST_EXIST);
$third = $DB->get_record('user', ['username' => 'w3t.phase8b.third', 'deleted' => 0], '*', MUST_EXIST);

foreach ([$student1, $student2, $alumni, $warning, $third] as $user) {
    web3t_phase8b_assert(is_enrolled($coursecontext, $user, '', true), "{$user->username} is enrolled");
}

$alphastate = topic_round_service::get_student_state((int)$round->id, (int)$student1->id);
$betastate = topic_round_service::get_student_state((int)$round->id, (int)$student2->id);
web3t_phase8b_assert(count($alphastate['members']) === 2, 'Phase 8B Alpha is a two-person partner group');
web3t_phase8b_assert(count($betastate['members']) === 3, 'Phase 8B Beta is a three-person partner group');

$wallets = web3t_phase8b_topic_id((int)$round->id, 'Wallets And Transactions');
$smartcontracts = web3t_phase8b_topic_id((int)$round->id, 'Smart Contracts');
$applications = web3t_phase8b_topic_id((int)$round->id, 'Applications And Protocols');

$student1topic = topic_round_service::get_user_choice_topic((int)$round->id, (int)$student1->id);
web3t_phase8b_assert($student1topic && $student1topic->name === 'Wallets And Transactions', 'partner override changed Alpha choice for both members');

topic_round_service::select_topic((int)$round->id, (int)$alumni->id, $applications);
$changedtopic = topic_round_service::get_user_choice_topic((int)$round->id, (int)$student1->id);
web3t_phase8b_assert($changedtopic && $changedtopic->name === 'Applications And Protocols', 'either partner can change topic before close');

$topics = topic_round_service::topics_with_capacity((int)$round->id);
$topicsbyid = [];
foreach ($topics as $topic) {
    $topicsbyid[(int)$topic->id] = $topic;
}
web3t_phase8b_assert((int)$topicsbyid[$wallets]->slotsleft === 1, 'old topic slot returns after partner group changes choice');
web3t_phase8b_assert((int)$topicsbyid[$applications]->slotsleft === 0, 'new topic consumes one group slot');

web3t_phase8b_expect_exception(
    fn() => topic_round_service::select_topic((int)$round->id, (int)$third->id, $applications),
    'full topic blocks another partner group'
);

$DB->set_field('local_w3t_round', 'closetime', time() - MINSECS, ['id' => $round->id]);
$task = new \local_web3talents\task\finalize_topic_rounds();
$task->execute();

$round = $DB->get_record('local_w3t_round', ['id' => $round->id], '*', MUST_EXIST);
web3t_phase8b_assert($round->status === topic_round_service::STATUS_FINALIZED, 'closed round finalizes automatically through scheduled task');

$finalgroups = $DB->get_records('local_w3t_fgroup', ['roundid' => $round->id]);
web3t_phase8b_assert(count($finalgroups) >= 4, 'Moodle groups are generated for round topics');

$applicationgroup = $DB->get_record('local_w3t_fgroup', ['roundid' => $round->id, 'topicid' => $applications], '*', MUST_EXIST);
$smartgroup = $DB->get_record('local_w3t_fgroup', ['roundid' => $round->id, 'topicid' => $smartcontracts], '*', MUST_EXIST);

foreach ([$student1, $alumni] as $user) {
    web3t_phase8b_assert($DB->record_exists('groups_members', ['groupid' => $applicationgroup->moodlegroupid, 'userid' => $user->id]), "{$user->username} is in generated Applications group");
}
foreach ([$student2, $warning, $third] as $user) {
    web3t_phase8b_assert($DB->record_exists('groups_members', ['groupid' => $smartgroup->moodlegroupid, 'userid' => $user->id]), "{$user->username} is in generated Smart Contracts group");
    web3t_phase8b_assert($DB->record_exists('local_web3talents_log', ['eventtype' => 'topic_assignment_notified', 'userid' => $user->id, 'courseid' => $course->id]), "{$user->username} assignment notification is logged");
}

web3t_phase8b_assert($DB->record_exists('local_web3talents_log', ['eventtype' => 'topic_round_finalized', 'courseid' => $course->id]), 'round finalization is logged');

echo 'Phase 8B weekly group-slot topic selection validation complete.' . PHP_EOL;
