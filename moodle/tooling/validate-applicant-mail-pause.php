<?php
// Standalone regression checks: in-memory doubles only, no Moodle config, network or live database.
define('DAYSECS', 86400);
define('ENROL_INSTANCE_ENABLED', 0);
define('ENROL_DO_NOT_SEND_EMAIL', 0);
require(__DIR__ . '/../plugins/local_web3talents/classes/local/applicant_service.php');
use local_web3talents\local\applicant_service as applicants;

$setting = false;
$mailcalls = 0;
$normalenrolcalls = 0;
function get_config($plugin, $key) { global $setting; return $setting; }
class InvitationMailerDouble {
    public static function send($user) { global $mailcalls; $mailcalls++; return true; }
}
class_alias(InvitationMailerDouble::class, 'local_web3talents\\local\\invitation_mailer');
function enrol_is_enabled($plugin) { return true; }
function enrol_get_plugin($name) { global $plugin; return $plugin; }
function enrol_try_internal_enrol(...$args) { global $normalenrolcalls; $normalenrolcalls++; return true; }
function check(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException('FAIL: ' . $label); }
    echo 'PASS: ' . $label . PHP_EOL;
}
$CFG = (object)['noemailever' => false];
$DB = new class {
    public array $logs = [];
    public array $fields = [];
    public array $instances;
    public function __construct() { $this->instances = [(object)['id'=>1, 'customint1'=>3, 'enrol'=>'manual']]; }
    public function insert_record($table, $data) { $this->logs[] = $data; }
    public function set_field($table, $field, $value, $where) { $this->fields[$field] = $value; }
    public function record_exists($table, $where) { return true; }
    public function get_records(...$args) { return $this->instances; }
};
$plugin = new class {
    public array $calls = [];
    public function enrol_user(...$args) { $this->calls[] = $args; }
};
$send = new ReflectionMethod(applicants::class, 'send_activation_email');
$enrol = new ReflectionMethod(applicants::class, 'enrol_reviewed_student');
$app = (object)['id'=>1, 'retentionuntil'=>null];
$user = (object)['id'=>2];
foreach ([false, '0'] as $setting) {
    check(!applicants::activation_emails_enabled(), 'Missing/off setting fails closed');
    check(!$send->invoke(null, $app, $user, 3) && $mailcalls === 0 && !$DB->fields,
        'Paused invitations do not send, reset passwords or mark delivery');
}
check($enrol->invoke(null, 2, 2, 5), 'Paused invitations still enrol the accepted student');
check($plugin->calls[0][0]->customint1 === ENROL_DO_NOT_SEND_EMAIL &&
    $DB->instances[0]->customint1 === 3, 'Welcome email suppressed without modifying course settings');
$DB->instances = [];
check(!$enrol->invoke(null, 2, 2, 5), 'Missing enrolment method fails safely');
$setting = '1';
check(applicants::activation_emails_enabled() && $mailcalls === 0, 'Enabling does not bulk-send held emails');
$DB->instances = [(object)['id'=>1, 'customint1'=>3, 'enrol'=>'manual']];
check($enrol->invoke(null, 2, 2, 5) && $normalenrolcalls === 0 &&
    $plugin->calls[1][0]->customint1 === ENROL_DO_NOT_SEND_EMAIL,
    'Enabled workflow enrols without a duplicate default welcome email');
check($send->invoke(null, $app, $user, 3) && $mailcalls === 1 &&
    !empty($DB->fields['activationemailsenttime']) && $DB->fields['retentionuntil'] >= time() + 30 * DAYSECS - 1,
    'Explicit invitation records delivery and starts the held-account activation window');
$CFG->noemailever = true;
check(!$send->invoke(null, $app, $user, 3) && $mailcalls === 1, 'Global no-email mode never sends');
