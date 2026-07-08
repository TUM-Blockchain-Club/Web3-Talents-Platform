<?php
// Validates Phase 8 Moodle groups and Choice source-data integration.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/local/web3talents/classes/local/course_state_service.php');

use local_web3talents\local\course_state_service;

global $DB;

function web3t_phase8_assert(bool $condition, string $message): void {
    if (!$condition) {
        throw new moodle_exception("Phase 8 validation failed: {$message}");
    }
    echo "OK: {$message}" . PHP_EOL;
}

\core\session\manager::set_user(get_admin());

$state = course_state_service::get_state();
$course = $state['course'];
$context = context_course::instance($course->id);

web3t_phase8_assert($state['choice'] !== null, 'topic Choice activity exists');
$choicecm = $DB->get_record('course_modules', [
    'course' => $course->id,
    'idnumber' => course_state_service::CHOICE_IDNUMBER,
    'deletioninprogress' => 0,
], '*', MUST_EXIST);
$choicesection = $DB->get_record('course_sections', ['id' => $choicecm->section], '*', MUST_EXIST);
web3t_phase8_assert((int)$choicesection->section === 11, 'topic Choice activity is in Topic Selection');
foreach (course_state_service::launch_topics() as $topic) {
    $found = false;
    foreach ($state['choice']->options as $option) {
        $found = $found || trim($option->text) === $topic;
    }
    web3t_phase8_assert($found, "Choice supports launch topic {$topic}");
}

$student1 = $DB->get_record('user', ['username' => 'w3t.student1', 'deleted' => 0], '*', MUST_EXIST);
$student2 = $DB->get_record('user', ['username' => 'w3t.student2', 'deleted' => 0], '*', MUST_EXIST);
$alumni = $DB->get_record('user', ['username' => 'w3t.alumni1', 'deleted' => 0], '*', MUST_EXIST);
$warning = $DB->get_record('user', ['username' => 'w3t.phase8.warning', 'deleted' => 0], '*', MUST_EXIST);

foreach ([$student1, $student2, $alumni, $warning] as $user) {
    web3t_phase8_assert(is_enrolled($context, $user, '', true), "{$user->username} is enrolled");
    web3t_phase8_assert(isset($state['students'][(int)$user->id]), "{$user->username} is read by plugin enrolled-students service");
}

$groupsbyidnumber = [];
foreach ($state['groups'] as $group) {
    $groupsbyidnumber[$group->idnumber] = $group;
}

foreach (['w3t_partner_alpha', 'w3t_partner_beta', 'w3t_partner_alumni'] as $idnumber) {
    web3t_phase8_assert(isset($groupsbyidnumber[$idnumber]), "partner group {$idnumber} exists");
}

web3t_phase8_assert(isset($groupsbyidnumber['w3t_partner_alpha']->studentmembers[(int)$student1->id]), 'plugin maps student one to Alpha group');
web3t_phase8_assert(isset($groupsbyidnumber['w3t_partner_beta']->studentmembers[(int)$student2->id]), 'plugin maps student two to Beta group');
web3t_phase8_assert(isset($groupsbyidnumber['w3t_partner_alumni']->studentmembers[(int)$alumni->id]), 'plugin maps alumni user to Alumni group');

$student1choices = array_map(fn($response) => $response->text, $state['responses'][(int)$student1->id] ?? []);
$student2choices = array_map(fn($response) => $response->text, $state['responses'][(int)$student2->id] ?? []);
$alumnichoices = array_map(fn($response) => $response->text, $state['responses'][(int)$alumni->id] ?? []);

web3t_phase8_assert(in_array('Blockchain Foundations', $student1choices, true), 'plugin reads student one Choice response');
web3t_phase8_assert(in_array('Smart Contracts', $student2choices, true), 'plugin reads student two Choice response');
web3t_phase8_assert(in_array('Wallets And Transactions', $alumnichoices, true), 'plugin reads alumni Choice response');

$warningrow = $state['studentrows'][(int)$warning->id] ?? null;
web3t_phase8_assert($warningrow !== null, 'warning fixture appears in student source-data rows');
web3t_phase8_assert(in_array('missing_group', $warningrow['warnings'], true), 'student without partner group is warned');
web3t_phase8_assert(in_array('missing_choice', $warningrow['warnings'], true), 'student without topic choice is warned');

$betawarning = $state['groupwarnings'][(int)$groupsbyidnumber['w3t_partner_beta']->id] ?? null;
web3t_phase8_assert($betawarning !== null && in_array('invalid_group_size', $betawarning['warnings'], true), 'invalid group size warning matches real group state');

$alphawarning = $state['groupwarnings'][(int)$groupsbyidnumber['w3t_partner_alpha']->id] ?? null;
web3t_phase8_assert($alphawarning !== null && in_array('split_choices', $alphawarning['warnings'], true), 'split-choice group warning matches real group state');

echo 'Phase 8 Moodle groups and Choice source-data validation complete.' . PHP_EOL;
