<?php
// Read-only audit: print aggregate security metadata, never credentials or user identities.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
$result = ['runtime'=>['php'=>PHP_VERSION], 'settings'=>[], 'password_storage'=>[], 'integrations'=>[]];
$versionfile = $CFG->dirroot . '/version.php';
if (is_file($versionfile)) {
    require($versionfile);
    $result['runtime']['moodle_release'] = $release;
    $result['runtime']['moodle_version'] = $version;
    $result['runtime']['moodle_branch'] = $branch;
}
foreach (['debug','debugdisplay','cookiesecure','cookiehttponly','sessiontimeout','passwordpolicy',
    'minpasswordlength','minpassworddigits','minpasswordlower','minpasswordupper','minpasswordnonalphanum',
    'passwordreuselimit','pwresettime','lockoutthreshold','lockoutwindow','lockoutduration',
    'protectusernames','forcelogin','guestloginbutton','registerauth','enablewebservices',
    'enablemobilewebservice','cronclionly','disableupdateautodeploy','allowframembedding',
    'enabletrusttext','allowobjectembed','auth','sslproxy','reverseproxy'] as $key) {
    $result['settings'][$key] = $CFG->$key ?? null;
}
$result['settings']['https_wwwroot'] = parse_url($CFG->wwwroot, PHP_URL_SCHEME) === 'https';
$result['settings']['dataroot_outside_webroot'] = !str_starts_with(realpath($CFG->dataroot) . '/', realpath($CFG->dirroot) . '/');
$result['settings']['php_display_errors'] = ini_get('display_errors');
$result['settings']['php_log_errors'] = ini_get('log_errors');
$result['settings']['mfa_enabled'] = get_config('tool_mfa', 'enabled');
$result['settings']['mfa_factor_types'] = array_values(array_map(static fn($x)=>$x->plugin,
    $DB->get_records_sql("SELECT DISTINCT plugin FROM {config_plugins} WHERE plugin LIKE :prefix AND name = :name AND value = :value",
        ['prefix'=>'factor_%', 'name'=>'enabled', 'value'=>'1'])));
$counts = [];
$costs = [];
$rounds = [];
$salts = [];
$defaultmatches = 0;
$peppers = function_exists('get_password_peppers') ? get_password_peppers() : [];
$users = $DB->get_recordset('user', ['deleted'=>0], '', 'id,auth,password');
foreach ($users as $user) {
    if ($user->auth !== 'manual') {
        continue;
    }
    $hash = $user->password;
    $info = password_get_info($hash);
    $algorithm = $info['algoName'];
    if (preg_match('/^\$6\$rounds=([0-9]+)\$([^$]+)\$[.\/a-zA-Z0-9]{86}$/D', $hash, $matches)) {
        $algorithm = 'salted SHA-512-crypt';
        $rounds[$matches[1]] = ($rounds[$matches[1]] ?? 0) + 1;
        $salts[$matches[2]] = true;
    } else if ($algorithm === 'unknown') {
        $algorithm = in_array($hash, ['not cached','not set',''], true) ? 'non-login sentinel' :
            (preg_match('/^[a-f0-9]{32}$/iD', $hash) ? 'legacy MD5-shaped' : 'unrecognised');
    }
    $counts[$algorithm] = ($counts[$algorithm] ?? 0) + 1;
    if (isset($info['options']['cost'])) {
        $costs[(string)$info['options']['cost']] = ($costs[(string)$info['options']['cost']] ?? 0) + 1;
    }
    if ($algorithm === 'salted SHA-512-crypt' || $info['algoName'] !== 'unknown') {
        foreach (['Admin123!', 'ChangeMe123!'] as $fixturepassword) {
            foreach (array_merge([''], array_values($peppers)) as $pepper) {
                if (password_verify($fixturepassword . $pepper, $hash)) {
                    $defaultmatches++;
                    break 2;
                }
            }
        }
    }
}
$users->close();
$result['password_storage'] = ['manual_account_algorithms'=>$counts, 'bcrypt_cost_counts'=>$costs,
    'sha512_round_counts'=>$rounds, 'unique_sha512_salts'=>count($salts), 'pepper_configured'=>!empty($peppers),
    'known_repository_fixture_password_matches'=>$defaultmatches];
$adminids = array_filter(array_map('intval', explode(',', $CFG->siteadmins)));
$result['site_admin_count'] = count($adminids);
$defaultrole = $DB->get_record('role', ['id'=>$CFG->defaultuserroleid], 'id,shortname,archetype');
$result['default_user_role'] = $defaultrole;
if ($defaultrole) {
    $riskysql = 'SELECT rc.id, rc.contextid, rc.capability FROM {role_capabilities} rc
        JOIN {capabilities} cap ON cap.name = rc.capability WHERE '
        . $DB->sql_bitand('cap.riskbitmask', RISK_XSS | RISK_CONFIG | RISK_DATALOSS)
        . ' <> 0 AND rc.permission = :allowed AND rc.roleid = :roleid';
    $risky = $DB->get_records_sql($riskysql, ['allowed'=>CAP_ALLOW, 'roleid'=>$defaultrole->id]);
    if (!get_config('tool_dataprivacy', 'automaticdatadeletionapproval')) {
        $risky = array_filter($risky, static fn($r)=>$r->capability !== 'tool/dataprivacy:requestdelete');
    }
    $result['default_role_risky_grants'] = array_values($risky);
}
$result['password_reset_records'] = ['total'=>$DB->count_records('user_password_resets'),
    'currently_within_lifetime'=>$DB->count_records_select('user_password_resets', 'timerequested >= :since', ['since'=>time()-(int)$CFG->pwresettime])];
$plugin = get_config('local_web3talents');
$result['integrations'] = ['tally_enabled'=>!empty($plugin->tally_enabled),
    'tally_secret_at_least_32_chars'=>strlen($plugin->tally_secret ?? '') >= 32,
    'tally_form_scoped'=>!empty($plugin->tally_formid),
    'applicant_activation_emails_enabled'=>!empty($plugin->activation_emails_enabled),
    'smtp_password_configured'=>!empty($CFG->smtppass),
    'smtp_security'=>$CFG->smtpsecure ?? null,
    'zoom_host_enabled'=>getenv('WEB3T_ZOOM_HOST_ENABLED') === '1',
    'zoom_allowlist_nonempty'=>trim((string)getenv('WEB3T_ZOOM_ORGANISER_IDS')) !== ''];
$result['latest_cron_task_run'] = (int)$DB->get_field_sql('SELECT MAX(lastruntime) FROM {task_scheduled}');
$result['task_run_history_count'] = $DB->count_records('task_log');
$result['oldest_due_scheduled_task_age_seconds'] = max(0, time() - (int)$DB->get_field_sql('SELECT MIN(nextruntime) FROM {task_scheduled} WHERE disabled = 0 AND nextruntime > 0'));
$result['core_security_checks'] = [];
// These selected checks inspect configuration/permissions without HTTP probes or data changes.
foreach ([
    \core\check\environment\displayerrors::class,
    \core\check\environment\configrw::class,
    \core\check\security\emailchangeconfirmation::class,
    \core\check\security\embed::class,
    \core\check\security\webcron::class,
    \core\check\access\defaultuserrole::class,
    \core\check\access\guestrole::class,
    \core\check\access\frontpagerole::class,
] as $checkclass) {
    $check = new $checkclass();
    $result['core_security_checks'][$checkclass] = $check->get_result()->get_status();
}
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
