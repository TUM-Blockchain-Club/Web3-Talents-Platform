<?php
// This file is part of Moodle - http://moodle.org/

namespace local_web3talents\local;

use stdClass;

/** Participant invitation copy shared by initial invitations and resends. */
final class invitation_template {
    /**
     * Render HTML and plain text without creating accounts, tokens or sending mail.
     * This copy is for the December 2026 Blockchain Fundamentals 1 cohort.
     *
     * @return array{subject: string, html: string, text: string}
     */
    public static function render(stdClass $user, string $url, string $fallback, int $validfor, bool $preview = false): array {
        if ($validfor <= 0) {
            throw new \InvalidArgumentException('Password-link lifetime must be positive.');
        }
        $subject = 'You’re in! Welcome to Web3 Talents: Blockchain Fundamentals 1 🚀';
        $firstname = s($user->firstname);
        $expiry = $validfor % 3600 === 0 ? ($validfor / 3600) . ' hours' : ceil($validfor / 60) . ' minutes';
        $setupurl = s($url);
        $freshurl = s($fallback);
        $setupaction = $preview
            ? '<strong>Choose your password (preview only)</strong>'
            : '<a href="' . $setupurl . '" style="color:#4721a8;text-decoration:underline;"><strong>Choose your password</strong></a>';
        $linknote = $preview
            ? 'This is a team-review preview, not an account invitation. The password action is intentionally disabled. Each participant will receive their own secure password-creation link.'
            : 'This secure link is valid for ' . $expiry . '. If it expires, <a href="' . $freshurl . '" style="color:#4721a8;">request a fresh link</a> using the email address from your application.';
        $body = <<<HTML
        <p>Hi {$firstname},</p>
        <p><strong>Congratulations, you’re in!</strong> We’re thrilled to welcome you to <strong>Blockchain Fundamentals 1</strong>, our free, 20-week programme.</p>
        <p>You’ll be learning alongside curious peers, exploring ideas with industry experts, and building your understanding of Web3 together. We can’t wait to meet you and get started!</p>
        <h2 style="font-size:20px;margin-top:28px;">Next steps to secure your spot</h2>
        <p>Please complete these steps <strong>within 3 days</strong> to confirm your participation:</p>
        <h3 style="font-size:22px;line-height:1.35;margin:28px 0 10px;font-weight:700;">1. Set up your Moodle account</h3>
        <p>Choose your password using the link below. <strong>Completing your account setup confirms your participation.</strong> No additional confirmation form is needed, and you’ll be signed in automatically.</p>
        <p style="margin:24px 0;">{$setupaction}</p>
        <p style="font-size:14px;">{$linknote}</p>
        <h3 style="font-size:22px;line-height:1.35;margin:28px 0 10px;font-weight:700;">2. Get ready for Zoom: use the same email address</h3>
        <p>Live sessions take place on Zoom, with meeting access and course materials available through Moodle.</p>
        <p><strong>Important: You must sign in to Zoom using the same email address you used for your application and Moodle account.</strong> This is essential for matching your attendance to your student profile. Using a different address could mean your attendance is not recorded correctly.</p>
        <p>Please check this before the first session. If you cannot use the same email address, reply to this email so we can help.</p>
        <h3 style="font-size:22px;line-height:1.35;margin:28px 0 10px;font-weight:700;">3. Check your display name</h3>
        <p>Use your <strong>full name from your application</strong> on both Zoom and Moodle so coordinators and mentors can easily identify you.</p>
        <p>Our first session is <strong>Wednesday, 2 December 2026 at 18:00 Berlin time</strong>. Sessions take place online on Wednesdays and usually last <strong>around 1 hour</strong>. Please reserve <strong>up to 1.5 hours</strong> for each session.</p>
        <h2 style="font-size:20px;margin-top:28px;">🌟 Share the exciting news!</h2>
        <p>Celebrate your achievement by creating your customised acceptance post:</p>
        <p>✨ <a href="https://www.web3-talents.com/card" style="color:#4721a8;">Your Web3 Talents card</a></p>
        <p>Tag us on LinkedIn so we can celebrate with you!</p>
        <h2 style="font-size:20px;margin-top:28px;">🔗 Dive in early</h2>
        <p>Connect with us and start networking ahead of kick-off:</p>
        <ul style="padding-left:22px;">
        <li><strong>LinkedIn:</strong> <a href="https://www.linkedin.com/company/web-3-talents/" style="color:#4721a8;">Web3 Talents</a></li>
        <li><strong>Website:</strong> <a href="https://www.web3-talents.com/" style="color:#4721a8;">web3-talents.com</a></li>
        <li><strong>WhatsApp:</strong> <a href="https://chat.whatsapp.com/LAYZdrkfg5tAxZVVyILj9e" style="color:#4721a8;">Join our community</a></li>
        </ul>
        <p>We can’t wait to embark on this journey with you!</p>
        <p>See you very soon,<br><strong>The Web3 Talents Team</strong> 🪩 🎉</p>
        HTML;
        $html = '<!doctype html><html><body style="margin:0;background:#fff;color:#242138;font-family:Arial,Helvetica,sans-serif;">'
            . '<div style="max-width:600px;margin:32px auto;padding:0 24px;line-height:1.65;font-size:16px;">'
            . '<p style="font-size:22px;margin-bottom:28px;"><strong>Web3 Talents</strong></p>'
            . $body . '</div></body></html>';
        $text = html_to_text($body, 79, true);
        return ['subject' => $subject, 'html' => $html, 'text' => $text];
    }
}
