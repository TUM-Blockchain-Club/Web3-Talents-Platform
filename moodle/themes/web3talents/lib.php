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

    // Global dark theme for standard Moodle (Boost) pages.
    $coredark = $base . '/moodle-core.scss';
    if (file_exists($coredark)) {
        $scss .= file_get_contents($coredark) . "\n";
    }

    return $scss;
}

/**
 * Pre-SCSS: override Bootstrap/Boost variables so the whole Moodle interface
 * adopts the Web3 Talents dark palette + typography (injected before Boost's
 * own SCSS so every component inherits it).
 *
 * @param theme_config $theme
 * @return string
 */
function theme_web3talents_get_pre_scss($theme): string {
    return <<<'SCSS'
// --- Web3 Talents design tokens: THE single source of truth -----------------
// This block is injected before Boost's own SCSS, so every value below is in
// scope for theme/boost/**, scss/web3talents.scss, scss/pages/*.scss and
// scss/moodle-core.scss. Nothing downstream should re-declare a colour: the
// `--w3d-*` custom properties in web3talents.scss are interpolated from here,
// scss/pages/dashboard.scss consumes those, and scss/moodle-core.scss derives
// its `$w3c-*` aliases from the Bootstrap variables set below.
//
// Palette 1 — the Figma *Dashboard* frames. This is the logged-in palette: a
// near-black canvas with raised #1a1a1a cards, and it drives all authenticated
// Moodle chrome. (Palette 2, the lighter #01061c marketing palette, lives in
// `:root { --w3-* }` in scss/web3talents.scss and is scoped to `.web3t-page`.)
$w3d-canvas: #090909;         // page canvas
$w3d-surface: #1a1a1a;        // cards / raised panels
$w3d-raised: #111111;         // hover + secondary surface
$w3d-divider: #111118;        // hairline between rows
$w3d-border: #222226;         // card border
$w3d-text: #ffffff;           // headings
$w3d-text-2: #e2e2e8;         // body copy
$w3d-muted: #6b6b7b;          // Figma's muted; 3.8:1 on the canvas, so it is
                              // used for decorative/large text only.
$w3d-muted-aa: #9a9aad;       // WCAG AA (7.1:1) variant for small chrome text.
$w3d-accent: #793ff4;         // Figma accent (fills only — 3.0:1 as text)
$w3d-accent-2: #9465f6;       // lighter accent, AA-safe for links (5.1:1)

// --- Bootstrap/Boost variable overrides, derived from the tokens above ------
$body-bg: $w3d-canvas;
$body-color: $w3d-text-2;

$primary: #2b1dff;
$secondary: #2a2740;
$success: #1f9d78;
$info: #2297fe;
$warning: #c99a2e;
$danger: #c94f44;
$light: $w3d-raised;
$dark: #050505;

$font-family-sans-serif: "Inter web3t", "Helvetica Neue", Arial, sans-serif;
$headings-font-family: "Space Grotesk", "Inter web3t", sans-serif;
$headings-color: $w3d-text;

$link-color: $w3d-accent-2;
$link-hover-color: #b79bfa;

$border-color: rgba(255, 255, 255, 0.12);
$hr-border-color: rgba(255, 255, 255, 0.12);
$text-muted: $w3d-muted-aa;

$card-bg: $w3d-surface;
$card-border-color: $w3d-border;
$card-cap-bg: rgba(255, 255, 255, 0.03);

$input-bg: #121212;
$input-disabled-bg: #14142a;
$input-color: #f0f0f5;
$input-border-color: rgba(255, 255, 255, 0.18);
$input-focus-bg: $input-bg;
$input-focus-color: #ffffff;
$input-focus-border-color: #4f8cfa;
$input-placeholder-color: rgba(255, 255, 255, 0.4);
$form-select-bg: $input-bg;
$form-select-color: $input-color;

$dropdown-bg: $w3d-raised;
$dropdown-color: $w3d-text-2;
$dropdown-border-color: rgba(255, 255, 255, 0.12);
$dropdown-link-color: $w3d-text-2;
$dropdown-link-hover-color: #ffffff;
$dropdown-link-hover-bg: rgba(255, 255, 255, 0.06);
$dropdown-divider-bg: rgba(255, 255, 255, 0.10);

$table-color: $w3d-text-2;
$table-bg: transparent;
$table-border-color: rgba(255, 255, 255, 0.12);
$table-accent-bg: rgba(255, 255, 255, 0.03);
$table-hover-bg: rgba(255, 255, 255, 0.06);

$list-group-bg: $w3d-surface;
$list-group-color: $w3d-text-2;
$list-group-border-color: rgba(255, 255, 255, 0.10);
$list-group-hover-bg: rgba(255, 255, 255, 0.05);
$list-group-action-color: $w3d-text-2;
$list-group-action-hover-color: #ffffff;

$modal-content-bg: $w3d-surface;
$modal-content-border-color: rgba(255, 255, 255, 0.12);
$modal-header-border-color: rgba(255, 255, 255, 0.10);
$modal-footer-border-color: rgba(255, 255, 255, 0.10);

$breadcrumb-bg: transparent;
$breadcrumb-divider-color: rgba(255, 255, 255, 0.4);
$breadcrumb-active-color: rgba(255, 255, 255, 0.7);

$nav-tabs-border-color: rgba(255, 255, 255, 0.15);
$nav-tabs-link-hover-border-color: rgba(255, 255, 255, 0.2);
$nav-tabs-link-active-color: #ffffff;
$nav-tabs-link-active-bg: transparent;
$nav-tabs-link-active-border-color: transparent transparent #2b1dff;

$pagination-bg: $w3d-raised;
$pagination-color: $w3d-text-2;
$pagination-border-color: rgba(255, 255, 255, 0.12);
$pagination-hover-bg: rgba(255, 255, 255, 0.06);
$pagination-hover-border-color: rgba(255, 255, 255, 0.2);
$pagination-disabled-bg: $input-bg;
$pagination-disabled-border-color: rgba(255, 255, 255, 0.08);

$component-active-color: #ffffff;
$component-active-bg: #2b1dff;

$popover-bg: $w3d-raised;
$popover-border-color: rgba(255, 255, 255, 0.12);
$popover-header-bg: rgba(255, 255, 255, 0.04);
$tooltip-bg: $w3d-raised;

$navbar-dark-color: rgba(255, 255, 255, 0.8);
$navbar-dark-hover-color: #ffffff;
$navbar-dark-active-color: #ffffff;
$navbar-light-color: rgba(255, 255, 255, 0.8);
$navbar-light-hover-color: #ffffff;
$navbar-light-active-color: #ffffff;

// Bootstrap 5.3 emphasis/secondary/tertiary text tokens (BS4 called the last
// two $dark-text / $body-color-secondary, which no longer exist).
$body-emphasis-color: $w3d-text;
$body-secondary-color: $w3d-muted-aa;
$body-tertiary-color: rgba(255, 255, 255, 0.45);
SCSS;
}

/**
 * Guard for the four public marketing pages (overview/courses/course/community).
 *
 * Those pages are public by design, but on a site running with
 * $CFG->forcelogin that has to be a deliberate choice rather than the side
 * effect of never calling require_login(). The `publicpages` theme setting makes
 * it explicit; it defaults to "public" while the admin has never saved it, which
 * preserves the previous behaviour.
 *
 * @return void
 */
function theme_web3talents_guard_public_page(): void {
    $public = get_config('theme_web3talents', 'publicpages');
    if ($public === false) {
        // Never saved by an admin — fall back to the packaged default.
        $public = 1;
    }
    if (!$public) {
        require_login();
    }
}

/**
 * Return the configured course when the current user is a plain student.
 *
 * Mentors, managers, admins, guests and users outside the configured course do
 * not get the student portal shell. The result is cached for the request because
 * both the renderer and shared navigation context use it.
 *
 * @return stdClass|null
 */
function theme_web3talents_get_student_portal_course(): ?stdClass {
    global $CFG, $USER;

    static $resolved = false;
    static $portalcourse = null;

    if ($resolved) {
        return $portalcourse;
    }
    $resolved = true;

    if (!isloggedin() || isguestuser() || is_siteadmin()) {
        return null;
    }

    $pluginlib = $CFG->dirroot . '/local/web3talents/lib.php';
    if (file_exists($pluginlib)) {
        require_once($pluginlib);
    }
    if (!function_exists('local_web3talents_get_configured_course')) {
        return null;
    }

    $course = local_web3talents_get_configured_course();
    if (!$course) {
        return null;
    }

    $coursecontext = context_course::instance($course->id);
    $isstudent = has_capability(
        'local/web3talents:viewstudentrooms',
        $coursecontext,
        $USER->id
    );
    $isstaff = has_capability(
        'local/web3talents:viewmentorrooms',
        $coursecontext,
        $USER->id
    ) || has_capability(
        'local/web3talents:manage',
        context_system::instance(),
        $USER->id
    );

    if ($isstudent && !$isstaff) {
        $portalcourse = $course;
    }
    return $portalcourse;
}

/**
 * Shared template context for every Web3 Talents public page (header + footer).
 *
 * @param renderer_base $output The page output renderer.
 * @return array
 */
function theme_web3talents_common_context($output): array {
    global $USER;

    $loginurl = (new moodle_url('/theme/web3talents/portal_login.php'))->out(false);
    $img = function(string $name) use ($output): string {
        return $output->image_url('home/' . $name, 'theme_web3talents')->out(false);
    };
    $page = function(string $file): string {
        return (new moodle_url('/theme/web3talents/' . $file))->out(false);
    };
    $s = function(string $key): string {
        return get_string($key, 'theme_web3talents');
    };

    $tumurl = 'https://www.tum-blockchain.com';
    $isloggedin = isloggedin() && !isguestuser();
    $homeurl = $page('overview.php');
    $dashboardurl = $page('dashboard.php');
    $mycourseurl = (new moodle_url('/my/courses.php'))->out(false);
    $accounturl = $isloggedin
        ? (new moodle_url('/user/profile.php', ['id' => $USER->id]))->out(false)
        : $loginurl;
    $portalcourse = $isloggedin ? theme_web3talents_get_student_portal_course() : null;
    $studentportal = (bool)$portalcourse;
    if ($portalcourse) {
        $mycourseurl = (new moodle_url('/course/view.php', ['id' => $portalcourse->id]))->out(false);
    }

    // Students get a clear portal navigation. Anonymous visitors and staff retain
    // the public marketing navigation; staff continue to use Moodle's own dashboard.
    $nav = $studentportal ? [
        ['label' => $s('navdashboard'), 'url' => $dashboardurl],
        ['label' => $s('navmycourse'), 'url' => $mycourseurl],
        ['label' => $s('navcommunity'), 'url' => $page('community.php')],
        ['label' => $s('navaccount'), 'url' => $accounturl],
    ] : [
        ['label' => $s('navcourses'), 'url' => $page('courses.php')],
        ['label' => $s('navcommunity'), 'url' => $page('community.php')],
    ];

    return [
        'loginurl' => $loginurl,
        'homeurl' => $homeurl,
        'brandurl' => $studentportal ? $dashboardurl : $homeurl,
        'coursesurl' => $page('courses.php'),
        'courseurl' => $page('course.php'),
        'communityurl' => $page('community.php'),
        'dashboardurl' => $dashboardurl,
        'mycourseurl' => $mycourseurl,
        'accounturl' => $accounturl,
        'logourl' => $img('logo'),
        'linkedinurl' => $img('social-linkedin'),
        'tumurl' => $tumurl,
        // Logged-in state for the shared nav (nav.mustache renders Log out +
        // initials instead of Join Us / Login when this is true).
        'isloggedin' => $isloggedin,
        'studentportal' => $studentportal,
        'logouturl' => $isloggedin
            ? (new moodle_url('/login/logout.php', ['sesskey' => sesskey()]))->out(false)
            : $loginurl,
        'initials' => $isloggedin ? theme_web3talents_initials($USER) : '',
        'nav' => $nav,
        'footernav1' => [
            ['label' => $s('navhome'), 'url' => $page('overview.php')],
            ['label' => $s('navcourses'), 'url' => $page('courses.php')],
            ['label' => $s('navcommunity'), 'url' => $page('community.php')],
            ['label' => $s('navspeakers'), 'url' => $page('overview.php') . '#speakers'],
        ],
        'footernav2' => [
            ['label' => $s('navabout'), 'url' => $page('overview.php') . '#program'],
            ['label' => $s('navtumclub'), 'url' => $tumurl],
            ['label' => $s('navfaq'), 'url' => $page('community.php') . '#faq'],
        ],
        'contacturl' => $tumurl,
    ];
}

/**
 * Add the role-aware student portal links to Moodle's standard navbar.
 *
 * The native navbar retains Moodle's notification, messaging and user controls;
 * SCSS positions this navigation in the centre and hides the redundant primary
 * menu only for plain students.
 *
 * @param renderer_base $output The active page renderer.
 * @return string
 */
function theme_web3talents_render_navbar_output(renderer_base $output): string {
    global $PAGE, $SCRIPT, $USER;

    if ($PAGE->theme->name !== 'web3talents') {
        return '';
    }
    $course = theme_web3talents_get_student_portal_course();
    if (!$course) {
        return '';
    }

    $items = [
        [
            'label' => get_string('navdashboard', 'theme_web3talents'),
            'url' => new moodle_url('/theme/web3talents/dashboard.php'),
            'active' => $SCRIPT === '/theme/web3talents/dashboard.php',
        ],
        [
            'label' => get_string('navmycourse', 'theme_web3talents'),
            'url' => new moodle_url('/course/view.php', ['id' => $course->id]),
            'active' => str_starts_with($SCRIPT, '/course/')
                || str_starts_with($SCRIPT, '/mod/')
                || str_starts_with($SCRIPT, '/local/web3talents/'),
        ],
        [
            'label' => get_string('navcommunity', 'theme_web3talents'),
            'url' => new moodle_url('/theme/web3talents/community.php'),
            'active' => $SCRIPT === '/theme/web3talents/community.php',
        ],
        [
            'label' => get_string('navaccount', 'theme_web3talents'),
            'url' => new moodle_url('/user/profile.php', ['id' => $USER->id]),
            'active' => $SCRIPT === '/user/profile.php',
        ],
    ];

    $links = '';
    foreach ($items as $item) {
        $class = 'web3t-native-portal-nav__link';
        if ($item['active']) {
            $class .= ' is-active';
        }
        $links .= html_writer::link($item['url'], $item['label'], ['class' => $class]);
    }

    return html_writer::tag('nav', $links, [
        'class' => 'web3t-native-portal-nav',
        'aria-label' => get_string('studentportal', 'theme_web3talents'),
    ]);
}

/**
 * Send students who land on Moodle's default dashboard (/my/) to the branded
 * Web3 Talents dashboard instead. Runs as a standard after_require_login hook.
 *
 * Guards: never for CLI/AJAX/guests/admins; never before the agreement gate has
 * been satisfied (so it can't bypass it); only for plain students (users who can
 * view student rooms but are not mentors/managers); only on the /my/ page.
 *
 * @param mixed $courseorid
 * @param bool $autologinguest
 * @param mixed $cm
 * @param bool $setwantsurltome
 * @param bool $preventredirect
 * @return void
 */
function theme_web3talents_after_require_login($courseorid, $autologinguest, $cm,
        $setwantsurltome, $preventredirect): void {
    global $SCRIPT, $USER, $CFG;

    if (CLI_SCRIPT || AJAX_SCRIPT || (defined('WS_SERVER') && WS_SERVER)
            || $preventredirect || !isloggedin() || isguestuser() || is_siteadmin()) {
        return;
    }
    // Only intercept the default Moodle dashboard landing.
    if ($SCRIPT !== '/my/index.php') {
        return;
    }

    $pluginlib = $CFG->dirroot . '/local/web3talents/lib.php';
    if (file_exists($pluginlib)) {
        require_once($pluginlib);
    }
    if (!function_exists('local_web3talents_get_configured_course')) {
        return;
    }
    // Do not jump ahead of the first-login agreement gate.
    if (class_exists('\\local_web3talents\\local\\agreement_service')
            && \local_web3talents\local\agreement_service::requires_agreement((int)$USER->id)) {
        return;
    }
    $course = local_web3talents_get_configured_course();
    if (!$course) {
        return;
    }
    $coursecontext = context_course::instance($course->id);
    // Students only — leave mentors and managers on the standard dashboard.
    $isstudent = has_capability('local/web3talents:viewstudentrooms', $coursecontext, $USER->id);
    $isstaff = has_capability('local/web3talents:viewmentorrooms', $coursecontext, $USER->id)
        || has_capability('local/web3talents:manage', context_system::instance(), $USER->id);
    if (!$isstudent || $isstaff) {
        return;
    }
    redirect(new moodle_url('/theme/web3talents/dashboard.php'));
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
