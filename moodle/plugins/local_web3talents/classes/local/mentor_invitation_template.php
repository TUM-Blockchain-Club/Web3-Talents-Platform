<?php
namespace local_web3talents\local;

/** Mentor-specific copy; rendering never creates tokens or sends mail. */
final class mentor_invitation_template {
    public static function render(\stdClass $user, string $url, string $fallback, int $validfor, bool $preview = false): array {
        global $CFG;
        if ($validfor <= 0) { throw new \InvalidArgumentException('Positive link lifetime required.'); }
        $expiry = $validfor % 86400 === 0 ? ($validfor / 86400) . ' days' : ceil($validfor / 3600) . ' hours';
        $firstname = s($user->firstname);
        $setup = $preview ? '<strong>Set up your mentor account (preview only)</strong>'
            : '<a href="' . s($url) . '">Set up your mentor account</a>';
        $note = $preview
            ? 'This is a template preview. The password action is intentionally disabled; each mentor will receive a personal setup link valid for ' . $expiry . '.'
            : 'Your personal setup link is valid for ' . $expiry . '. If it expires, <a href="' . s($fallback) . '">request a new link</a> using your mentor email address.';
        $availability = s($CFG->wwwroot . '/local/web3talents/mentor_availability.php');
        $grading = s($CFG->wwwroot . '/local/web3talents/mentor_grading.php');
        $course = s($CFG->wwwroot . '/course/view.php?id=2');
        $body = <<<HTML
        <p>Hi {$firstname},</p>
        <p>Welcome to the mentoring team for <strong>Web3 Talents: Blockchain Fundamentals 1</strong>. Thank you for helping our participants learn, discuss ideas and develop their understanding of Web3.</p>
        <p>The cohort begins on <strong>Wednesday, 2 December 2026 at 18:00 Berlin time</strong>. The first presentation session is on <strong>9 December</strong>.</p>
        <h2>Getting started</h2>
        <h3>1. Set up your Moodle account</h3>
        <p>Please complete your account setup <strong>within {$expiry}</strong>.</p>
        <p>{$setup}</p>
        <p style="font-size:14px;">{$note}</p>
        <h3>2. Check the course and your availability</h3>
        <p>Once signed in, open <a href="{$course}">Blockchain Fundamentals 1</a> for the course overview and live-session Zoom link. Use <a href="{$availability}">Mentor availability</a> to indicate which listed sessions you can attend.</p>
        <p><strong>Use the same email address and full name in Zoom and Moodle</strong> so the team can identify you correctly.</p>
        <h3>3. Support your assigned group</h3>
        <p>For presentation sessions, guide discussion, ask questions and provide constructive feedback. Use <a href="{$grading}">Mentor grading</a> to record grades and feedback for students in your assigned room. Your room and students will appear once the team has published the allocations and assigned you to a room.</p>
        <p>Our <a href="https://chat.whatsapp.com/LAYZdrkfg5tAxZVVyILj9e">cohort WhatsApp community</a> is the main channel for session updates and coordination. Please join before kick-off.</p>
        <p>If you need help with access or availability, reply to this email.</p>
        <p>We look forward to working with you!</p>
        <p>Best,<br><strong>The Web3 Talents Team</strong></p>
        HTML;
        $html = '<!doctype html><html><body style="margin:0;background:#fff;color:#242138;font-family:Arial,Helvetica,sans-serif;">'
            . '<div style="max-width:600px;margin:32px auto;padding:0 24px;line-height:1.65;font-size:16px;">'
            . '<p style="font-size:22px;margin-bottom:28px;"><strong>Web3 Talents</strong></p>' . $body . '</div></body></html>';
        return ['subject'=>'Welcome to the Web3 Talents mentoring team', 'html'=>$html, 'text'=>html_to_text($body,79,true)];
    }
}
