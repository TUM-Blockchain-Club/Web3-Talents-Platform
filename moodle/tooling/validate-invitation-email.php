<?php
// Standalone: no Moodle config, network, database or real email. Synthetic recipients only.
require(__DIR__ . '/../plugins/local_web3talents/classes/local/applicant_service.php');
require(__DIR__ . '/../plugins/local_web3talents/classes/local/invitation_template.php');
require(__DIR__ . '/../plugins/local_web3talents/classes/local/invitation_mailer.php');
use local_web3talents\local\invitation_template;
use local_web3talents\local\invitation_mailer;
$setting = '0';
$deliver = true;
$throwmail = false;
$mail = [];
function get_config($plugin, $key) { global $setting; return $setting; }
function validate_email($value) { return (bool)filter_var($value, FILTER_VALIDATE_EMAIL); }
function s($value) { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function html_to_text($html, $width = 75, $dolinks = true) {
    if ($dolinks) {
        $html = preg_replace('/<a href="([^"]+)"[^>]*>(.*?)<\/a>/s', '$2 ($1)', $html);
    }
    return html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
}
function check($ok, $label) {
    if (!$ok) { throw new RuntimeException('FAIL: ' . $label); }
    echo 'PASS: ' . $label . PHP_EOL;
}
class moodle_url {
    public function __construct(private string $path, private array $params = []) {}
    public function out($escape) {
        global $CFG;
        return $CFG->wwwroot . $this->path . ($this->params ? '?' . http_build_query($this->params) : '');
    }
}
$CFG = (object)['wwwroot'=>'https://moodle.example.invalid', 'dirroot'=>__DIR__ . '/fixtures/invitation',
    'noreplyaddress'=>'web3talents@example.invalid', 'pwresettime'=>86400];
$DB = new class {
    public array $tokens = [];
    public int $sequence = 0;
    public function delete_records($table, $where) {
        foreach ($this->tokens as $id => $token) {
            if ($id === $where['id'] && $token->userid === $where['userid']) { unset($this->tokens[$id]); }
        }
    }
    public function delete_records_select($table, $sql, $params) {
        foreach ($this->tokens as $id => $token) {
            if ($id !== $params['id'] && $token->userid === $params['userid']) { unset($this->tokens[$id]); }
        }
    }
};
function core_login_generate_password_reset($user) {
    global $DB;
    // Match Moodle 5.2.5: new reset requests supersede all earlier links.
    foreach ($DB->tokens as $id => $token) {
        if ($token->userid === $user->id) { unset($DB->tokens[$id]); }
    }
    $id = ++$DB->sequence;
    return $DB->tokens[$id] = (object)['id'=>$id, 'userid'=>$user->id, 'token'=>'synthetic-' . $id];
}
function email_to_user(...$args) {
    global $deliver, $throwmail, $mail;
    $mail[] = $args;
    if ($throwmail) { throw new RuntimeException('Synthetic SMTP failure'); }
    return $deliver;
}
$user = (object)['id'=>10, 'firstname'=>'Test <b>& Name', 'email'=>'student@example.invalid', 'auth'=>'manual',
    'confirmed'=>1, 'password'=>'unchanged'];
$message = invitation_template::render($user, 'https://moodle.example.invalid/login/forgot_password.php?token=fake&x=1',
    'https://moodle.example.invalid/login/forgot_password.php', 86400);
check(str_contains($message['html'], 'Test &lt;b&gt;&amp; Name'), 'Recipient name is HTML-escaped');
check(str_contains($message['html'], 'token=fake&amp;x=1'), 'Personal URL is HTML-escaped');
check(str_contains($message['text'], 'token=fake&x=1'), 'Plain text includes usable personal URL');
check($message['subject'] === 'You’re in! Welcome to Web3 Talents: Blockchain Fundamentals 1 🚀'
    && str_contains($message['html'], 'Congratulations, you’re in!')
    && str_contains($message['html'], 'We’re thrilled to welcome you')
    && !preg_match('/[🎉🚀📌🔑🎥👤]/u', explode('🌟', $message['html'])[0]), 'Warm opening with restrained body styling');
check(str_contains($message['html'], 'around 1 hour') && str_contains($message['html'], 'up to 1.5 hours')
    && !str_contains($message['html'], 'last <strong>1.5 hours</strong>'), 'Typical and maximum session durations are distinct');
check(!str_contains($message['html'], 'certified Web3 Talent') && !str_contains($message['html'], 'discord.gg'),
    'No obsolete certification promise or Discord instructions');
$preview = invitation_template::render($user, 'https://example.invalid/setup-secret', 'https://example.invalid/reset', 86400, true);
check(str_contains($preview['html'], 'Choose your password (preview only)')
    && str_contains($preview['text'], 'intentionally disabled')
    && !str_contains(implode('', $preview), 'https://example.invalid/setup-secret')
    && !str_contains(implode('', $preview), 'https://example.invalid/reset'), 'Preview has no misleading login or personal setup link');
check(substr_count($message['html'], 'font-size:22px;line-height:1.35') === 3, 'All three step headings remain enlarged');
check(str_contains($message['html'], '<strong>Important: You must sign in to Zoom using the same email address'), 'Same-email requirement is prominent');
foreach (['web3-talents.com/card', 'linkedin.com/company/web-3-talents/', 'chat.whatsapp.com/LAYZdrkfg5tAxZVVyILj9e',
    'Wednesday, 2 December 2026 at 18:00 Berlin time', '24 hours', 'signed in automatically', 'within 3 days'] as $copy) {
    check(str_contains($message['html'], $copy), 'Approved template content: ' . $copy);
}
check(!str_contains(implode('', $message), '—'), 'No em dashes in approved copy');
$otherexpiry = invitation_template::render($user, 'https://example.invalid', 'https://example.invalid', 1800);
check(str_contains($otherexpiry['html'], '30 minutes'), 'Displayed expiry follows actual Moodle setting');
$weekexpiry = invitation_template::render($user, 'https://example.invalid', 'https://example.invalid', 604800);
check(str_contains($weekexpiry['html'], 'valid for 7 days') && str_contains($weekexpiry['text'], 'valid for 7 days'),
    'One-week lifetime is clearly stated in HTML and plain text');
check(!invitation_mailer::send($user) && !$DB->tokens && !$mail, 'Paused setting creates no tokens or emails');
$setting = '1';
check(!$DB->tokens && !$mail, 'Enabling flag alone sends nothing');
foreach (['noemailever'=>true, 'divertallemailsto'=>'sink@example.invalid', 'forgottenpasswordurl'=>'https://example.invalid',
    'wwwroot'=>'http://moodle.example.invalid', 'pwresettime'=>0] as $key=>$value) {
    $old = $CFG->$key ?? null;
    $CFG->$key = $value;
    check(!invitation_mailer::send($user) && !$DB->tokens && !$mail, 'Unsafe/disabled configuration blocked: ' . $key);
    $CFG->$key = $old;
}
foreach (['suspended'=>1, 'deleted'=>1, 'emailstop'=>1, 'confirmed'=>0, 'auth'=>'nologin', 'email'=>'invalid'] as $key=>$value) {
    $bad = clone $user;
    $bad->$key = $value;
    check(!invitation_mailer::send($bad) && !$DB->tokens && !$mail, 'Ineligible recipient blocked: ' . $key);
}
$old = core_login_generate_password_reset($user);
$other = core_login_generate_password_reset((object)['id'=>99]);
check(invitation_mailer::send($user), 'Enabled invitation accepted');
check(count($mail) === 1 && $mail[0][0]->email === $user->email && $mail[0][1] === 'Web3 Talents' &&
    $mail[0][7] === false && $mail[0][8] === $CFG->noreplyaddress, 'Exactly one recipient with configured sender and reply-to');
check(str_contains($mail[0][4], '/login/forgot_password.php?token=synthetic-')
    && !str_contains($mail[0][4], 'preview only'), 'Real invitations retain a personal password-creation URL');
check(!isset($DB->tokens[$old->id]) && isset($DB->tokens[$other->id]), 'Success invalidates only recipient’s earlier links');
check($user->password === 'unchanged', 'Invitation never replaces existing password');
$kept = array_filter($DB->tokens, static fn($token) => $token->userid !== $user->id);
$deliver = false;
check(!invitation_mailer::send($user) && $kept === $DB->tokens, 'SMTP failure removes new token without reviving superseded links');
$throwmail = true;
try { invitation_mailer::send($user); } catch (RuntimeException $e) {}
check($kept === $DB->tokens, 'SMTP exception leaves other recipients unchanged and no usable failed token');
