<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Web3 Talents theme callbacks.
 *
 * @package    theme_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/theme/boost/lib.php');

/**
 * Returns the main SCSS content for the Boost child theme.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_web3talents_get_main_scss_content($theme): string {
    global $CFG;

    $base = $CFG->dirroot . '/theme/web3talents/scss';
    $scss = theme_boost_get_main_scss_content($theme);
    $scss .= "\n";

    // Shared design system (tokens, fonts, buttons, nav, footer, base layout).
    $scss .= file_get_contents($base . '/web3talents.scss') . "\n";

    // Per-page styles. Each landing surface ships its own partial under scss/pages/.
    foreach (glob($base . '/pages/*.scss') as $page) {
        $scss .= file_get_contents($page) . "\n";
    }

    return $scss;
}

/**
 * Shared template context for every Web3 Talents public page (header + footer).
 *
 * @param renderer_base $output The page output renderer.
 * @return array
 */
function theme_web3talents_common_context($output): array {
    $loginurl = (new moodle_url('/login/index.php'))->out(false);
    $img = function(string $name) use ($output): string {
        return $output->image_url('home/' . $name, 'theme_web3talents')->out(false);
    };
    $page = function(string $file): string {
        return (new moodle_url('/theme/web3talents/' . $file))->out(false);
    };

    return [
        'loginurl' => $loginurl,
        'homeurl' => $page('overview.php'),
        'coursesurl' => $page('courses.php'),
        'courseurl' => $page('course.php'),
        'communityurl' => $page('community.php'),
        'dashboardurl' => $page('dashboard.php'),
        'logourl' => $img('logo'),
        'linkedinurl' => $img('social-linkedin'),
        'tumurl' => 'https://www.tum-blockchain.com',
        'nav' => [
            ['label' => 'Courses', 'url' => $page('courses.php')],
            ['label' => 'Community', 'url' => $page('community.php')],
            ['label' => 'Dashboard', 'url' => $page('dashboard.php')],
        ],
        'footernav1' => [
            ['label' => 'Home', 'url' => $page('overview.php')],
            ['label' => 'Courses', 'url' => $page('courses.php')],
            ['label' => 'Community', 'url' => $page('community.php')],
            ['label' => 'Speakers', 'url' => $page('overview.php') . '#speakers'],
        ],
        'footernav2' => [
            ['label' => 'About Us', 'url' => '#'],
            ['label' => 'TUM Blockchain Club', 'url' => 'https://www.tum-blockchain.com'],
            ['label' => 'FAQ', 'url' => '#'],
        ],
    ];
}

/**
 * Two-letter initials for a user (safe for guests / partial names).
 *
 * @param stdClass $user
 * @return string
 */
function theme_web3talents_initials($user): string {
    $first = core_text::substr(trim($user->firstname ?? ''), 0, 1);
    $last = core_text::substr(trim($user->lastname ?? ''), 0, 1);
    $initials = core_text::strtoupper($first . $last);
    return $initials !== '' ? $initials : '?';
}

/**
 * Build the real, per-student data context for the dashboard page from the
 * local_web3talents plugin (topics/rounds, room/team, mentor, sessions) plus
 * Moodle core (course materials via modinfo, the assignment via mod_assign).
 *
 * Everything is defensively guarded: if the plugin is absent or the student has
 * no round/room/session/assignment yet, the matching `has*` flag is false and the
 * template shows an empty state instead of fabricated data.
 *
 * @param stdClass|null $course The configured Fundamentals course, or null.
 * @param stdClass $user The logged-in user.
 * @param string $view 'materials' or 'assignment'.
 * @return array Template context (merged over the hardcoded defaults by dashboard.php).
 */
function theme_web3talents_dashboard_context($course, $user, string $view): array {
    global $DB;

    $ns = '\\local_web3talents\\local\\';
    $hasplugin = $course && class_exists($ns . 'topic_round_service');
    $ctx = [
        'coursename' => $course ? format_string($course->fullname) : 'Your Course',
    ];

    $topics = [];
    $currenttopic = null;
    $timeline = [];
    $team = [];
    $mentor = null;
    $nextsession = null;
    $roomid = null;
    $resultid = null;

    if ($hasplugin) {
        $tr = $ns . 'topic_round_service';
        $ra = $ns . 'room_assignment_service';
        $ps = $ns . 'participation_service';
        $mg = $ns . 'mentor_grading_service';
        $courseid = (int)$course->id;
        $now = time();

        // --- Sidebar weeks from rounds; current round drives the header topic. ---
        try {
            $current = $tr::get_current_round($courseid);
            $rounds = $DB->get_records('local_w3t_round', ['courseid' => $courseid], 'opentime ASC');
            $n = 1;
            foreach ($rounds as $round) {
                $isactive = $current && (int)$round->id === (int)$current->id;
                $topics[] = [
                    'num' => (string)$n,
                    'title' => format_string($round->name),
                    'dates' => userdate($round->opentime, '%b %d') . ' - ' . userdate($round->closetime, '%b %d'),
                    'active' => $isactive,
                    'locked' => ($round->opentime > $now) && !$isactive,
                    'url' => (new moodle_url('/theme/web3talents/dashboard.php'))->out(false),
                ];
                $n++;
            }
            if ($current) {
                $chosen = $tr::get_user_choice_topic((int)$current->id, (int)$user->id);
                $currenttopic = [
                    'title' => $chosen ? format_string($chosen->name) : format_string($current->name),
                    'weeklabel' => 'Current Week',
                ];
            }
        } catch (\Throwable $e) {
            $topics = [];
        }

        // --- Room + team members (and the room id used to resolve the mentor). ---
        try {
            $roomstate = $ra::get_user_room_state($courseid, (int)$user->id);
            if ($roomstate && !empty($roomstate['members'])) {
                $roomid = isset($roomstate['room']->id) ? (int)$roomstate['room']->id : null;
                $resultid = isset($roomstate['result']->id) ? (int)$roomstate['result']->id : null;
                foreach ($roomstate['members'] as $member) {
                    $team[] = [
                        'initials' => theme_web3talents_initials($member),
                        'name' => fullname($member),
                        'role' => ((int)$member->id === (int)$user->id) ? 'You' : 'Member',
                    ];
                }
                if (empty($ctx['roomname']) && isset($roomstate['room']->roomname)) {
                    $ctx['roomname'] = format_string($roomstate['room']->roomname);
                }
            }
        } catch (\Throwable $e) {
            $team = [];
        }

        // --- Sessions → timeline (3 nearest) + next upcoming session. ---
        try {
            $sessions = $ps::get_sessions($courseid);
            if ($sessions) {
                $ordered = array_values($sessions);
                usort($ordered, function($a, $b) { return $a->sessiondate <=> $b->sessiondate; });
                foreach ($ordered as $s) {
                    if ($s->sessiondate >= $now && $nextsession === null) {
                        $nextsession = [
                            'name' => format_string($s->name),
                            'when' => userdate($s->sessiondate, '%a %b %d · %l:%M %p'),
                        ];
                    }
                }
                // Timeline: up to 3 sessions centred on "now".
                $pastfut = [];
                foreach ($ordered as $s) { $pastfut[] = $s; }
                $count = count($pastfut);
                $activeidx = 0;
                foreach ($pastfut as $idx => $s) { if ($s->sessiondate <= $now) { $activeidx = $idx; } }
                $start = max(0, min($activeidx - 1, $count - 3));
                $slice = array_slice($pastfut, $start, 3);
                foreach ($slice as $s) {
                    $state = $s->sessiondate < $now ? 'past' : ($s->sessiondate === $now ? 'active' : 'future');
                    // Mark the most recent past-or-now as the active node.
                    $timeline[] = [
                        'label' => format_string($s->name),
                        'date' => userdate($s->sessiondate, '%b %d'),
                        'state' => $state,
                        'ts' => (int)$s->sessiondate,
                    ];
                }
                // Set the single active node (closest past/now).
                $bestidx = null; $bestts = null;
                foreach ($timeline as $ti => $node) {
                    if ($node['ts'] <= $now && ($bestts === null || $node['ts'] > $bestts)) { $bestts = $node['ts']; $bestidx = $ti; }
                }
                if ($bestidx !== null) { $timeline[$bestidx]['state'] = 'active'; $timeline[$bestidx]['active'] = true; }
            }
        } catch (\Throwable $e) {
            $timeline = [];
        }

        // --- Mentor: compose from the student's room + a session. ---
        try {
            if ($roomid && $resultid) {
                $sessions = $ps::get_sessions($courseid);
                $mentorid = 0;
                foreach ($sessions as $s) {
                    $byroom = $mg::get_assignments_by_room((int)$s->id, $resultid);
                    if (isset($byroom[$roomid]->mentorid)) { $mentorid = (int)$byroom[$roomid]->mentorid; break; }
                }
                if ($mentorid) {
                    $muser = $DB->get_record('user', ['id' => $mentorid, 'deleted' => 0]);
                    if ($muser) {
                        $mentor = ['name' => fullname($muser), 'initials' => theme_web3talents_initials($muser)];
                    }
                }
            }
        } catch (\Throwable $e) {
            $mentor = null;
        }
    }

    // --- Course materials (Moodle core modinfo). ---
    $speakerslides = null;
    $teamrows = [];
    if ($course) {
        try {
            $modinfo = get_fast_modinfo($course, $user->id);
            $files = [];
            foreach ($modinfo->get_cms() as $cm) {
                if (!$cm->uservisible || $cm->deletioninprogress) { continue; }
                if (in_array($cm->modname, ['resource', 'folder', 'url', 'page'], true)) {
                    $files[] = [
                        'title' => format_string($cm->name),
                        'meta' => ucfirst($cm->modname),
                        'url' => $cm->url ? $cm->url->out(false) : '',
                    ];
                }
            }
            if ($files) {
                $speakerslides = array_shift($files);
                foreach ($files as $f) { $teamrows[] = $f + ['awaiting' => false]; }
            }
        } catch (\Throwable $e) {
            $speakerslides = null; $teamrows = [];
        }
    }

    // --- Assignment (Moodle core mod_assign). ---
    $assignment = null;
    if ($course) {
        try {
            $modinfo = isset($modinfo) ? $modinfo : get_fast_modinfo($course, $user->id);
            foreach ($modinfo->get_instances_of('assign') as $cm) {
                if (!$cm->uservisible) { continue; }
                $arec = $DB->get_record('assign', ['id' => $cm->instance]);
                if (!$arec) { continue; }
                $sub = $DB->get_record('assign_submission',
                    ['assignment' => $cm->instance, 'userid' => $user->id, 'latest' => 1]);
                $statusmap = ['submitted' => 'Submitted', 'draft' => 'In Progress', 'reopened' => 'In Progress'];
                $status = $sub && isset($statusmap[$sub->status]) ? $statusmap[$sub->status] : 'Not started';
                $assignment = [
                    'title' => format_string($cm->name),
                    'due' => $arec->duedate ? 'Due ' . userdate($arec->duedate, '%A, %b %d · %l:%M %p') : 'No due date',
                    'duets' => (int)$arec->duedate,
                    'status' => $status,
                    'taskdesc' => trim(html_to_text(format_module_intro('assign', $arec, $cm->id), 0)) ?: 'See the assignment page for full details.',
                    'submiturl' => (new moodle_url('/mod/assign/view.php', ['id' => $cm->id]))->out(false),
                ];
                break;
            }
        } catch (\Throwable $e) {
            $assignment = null;
        }
    }

    // Sidebar "Assignment Due" upcoming entry from the real assignment.
    if ($assignment && !empty($assignment['duets'])) {
        $ctx['assignmentdue'] = ['when' => userdate($assignment['duets'], '%a %b %d · %l:%M %p')];
    }

    // --- Assemble + empty-state flags. ---
    $ctx['topics'] = $topics;
    $ctx['hastopics'] = !empty($topics);
    $ctx['currenttopic'] = $currenttopic;
    $ctx['timeline'] = $timeline;
    $ctx['hastimeline'] = !empty($timeline);
    $ctx['team'] = $team;
    $ctx['hasteam'] = !empty($team);
    $ctx['mentor'] = $mentor;
    $ctx['hasmentor'] = (bool)$mentor;
    $ctx['nextsession'] = $nextsession;
    $ctx['speakerslides'] = $speakerslides;
    $ctx['teamrows'] = $teamrows;
    $ctx['hasmaterials'] = (bool)$speakerslides || !empty($teamrows);
    $ctx['assignment'] = $assignment;
    $ctx['hasassignment'] = (bool)$assignment;
    $ctx['allmaterialsurl'] = $course
        ? (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false)
        : '#';

    return $ctx;
}
