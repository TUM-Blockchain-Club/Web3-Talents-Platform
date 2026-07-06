<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Language strings for local_web3talents.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Web3 Talents';
$string['settings'] = 'Web3 Talents settings';
$string['setting_enabled'] = 'Enable Web3 Talents workflows';
$string['setting_enabled_desc'] = 'Controls whether custom Web3 Talents workflows should be available once implemented.';
$string['setting_fundamentals_course_shortname'] = 'Fundamentals course shortname';
$string['setting_fundamentals_course_shortname_desc'] = 'Moodle course shortname used by Web3 Talents workflows.';
$string['setting_policy_version'] = 'Policy version';
$string['setting_policy_version_desc'] = 'Version identifier for the current first-login communication/code-of-conduct agreement. Changing it requires accepted students to agree again.';
$string['setting_policy_text'] = 'First-login policy text';
$string['setting_policy_text_desc'] = 'Communication or code-of-conduct text shown to accepted students before normal course access.';
$string['default_policy_text'] = "## Web3 Talents participation agreement\n\nBy continuing, I agree to follow Web3 Talents communication expectations, participate respectfully, protect private cohort information, and use Moodle course spaces for program-related collaboration.";
$string['dashboard_intro'] = 'This plugin will manage accepted applicants, student account creation, room generation, mentor/student room visibility, and Zoom CSV exports.';
$string['dashboard_status_enabled'] = 'Workflows enabled';
$string['dashboard_status_disabled'] = 'Workflows disabled';
$string['dashboard_course_shortname'] = 'Fundamentals course';
$string['dashboard_next_steps'] = 'Next implementation steps';
$string['dashboard_next_applicants'] = 'Accepted-applicant list and account creation';
$string['dashboard_next_policy'] = 'First-login policy agreement';
$string['dashboard_next_rooms'] = 'Topic-based room generation and exports';
$string['applicants'] = 'Accepted applicants';
$string['applicants_intro'] = 'Manage accepted applicants and create Moodle student accounts after acceptance.';
$string['add_applicant'] = 'Add accepted applicant';
$string['import_applicants'] = 'Import accepted applicants';
$string['search_applicants'] = 'Search applicants';
$string['firstname'] = 'First name';
$string['lastname'] = 'Last name';
$string['email'] = 'Email address';
$string['cohortid'] = 'Cohort identifier';
$string['status'] = 'Status';
$string['notes'] = 'Admin notes';
$string['source'] = 'Source';
$string['accountstatus'] = 'Account';
$string['agreementstatus'] = 'Agreement';
$string['actions'] = 'Actions';
$string['createdaccount'] = 'Created Moodle account for {$a}.';
$string['createaccount'] = 'Create account';
$string['accountcreated'] = 'Account created';
$string['noaccount'] = 'No account';
$string['retentionuntil'] = 'Retention until';
$string['importfile'] = 'CSV or Excel file';
$string['importfile_help'] = 'Upload a CSV, XLSX, or XLS file with columns: firstname, lastname, email, cohortid, status, notes.';
$string['importresult'] = 'Imported {$a->created} new and updated {$a->updated} existing applicant records.';
$string['applicant_saved'] = 'Accepted applicant saved.';
$string['applicant_status_accepted'] = 'Accepted';
$string['applicant_status_accountcreated'] = 'Account created';
$string['applicant_status_accountactivated'] = 'Account activated';
$string['applicant_status_deferred'] = 'Deferred';
$string['applicant_status_removed'] = 'Removed';
$string['error_missing_required_columns'] = 'Import file must include firstname, lastname, and email columns.';
$string['error_invalid_email'] = 'Invalid email address: {$a}';
$string['error_applicant_not_accepted'] = 'Only accepted applicants can receive a Moodle account.';
$string['error_applicant_account_exists'] = 'This applicant already has a Moodle account.';
$string['error_user_email_exists'] = 'A Moodle user already exists for this email address.';
$string['error_course_missing'] = 'Could not find the configured fundamentals course.';
$string['error_enrol_failed'] = 'Could not enrol the student into the configured course.';
$string['error_email_failed'] = 'The Moodle user was created, but activation email delivery failed.';
$string['agreement_title'] = 'Web3 Talents agreement';
$string['agreement_intro'] = 'Please review and accept the current communication and code-of-conduct terms before continuing.';
$string['agreement_checkbox'] = 'I have read and agree to the Web3 Talents communication and code-of-conduct terms.';
$string['agreement_accept'] = 'Accept and continue';
$string['agreement_saved'] = 'Agreement accepted.';
$string['agreement_status_pending'] = 'Pending';
$string['agreement_status_accepted'] = 'Accepted {$a}';
$string['privacy:metadata:local_web3talents_app'] = 'Stores accepted applicant records used to gate Moodle account creation.';
$string['privacy:metadata:local_web3talents_app:firstname'] = 'The accepted applicant first name.';
$string['privacy:metadata:local_web3talents_app:lastname'] = 'The accepted applicant last name.';
$string['privacy:metadata:local_web3talents_app:email'] = 'The accepted applicant email address.';
$string['privacy:metadata:local_web3talents_app:cohortid'] = 'The cohort identifier associated with the applicant.';
$string['privacy:metadata:local_web3talents_app:status'] = 'The onboarding status for the accepted applicant.';
$string['privacy:metadata:local_web3talents_app:notes'] = 'Admin-only notes for the accepted applicant.';
$string['privacy:metadata:local_web3talents_app:userid'] = 'The Moodle user created for this accepted applicant, when available.';
$string['privacy:metadata:local_web3talents_agree'] = 'Stores first-login policy agreement acceptances.';
$string['privacy:metadata:local_web3talents_agree:userid'] = 'The Moodle user who accepted the current policy.';
$string['privacy:metadata:local_web3talents_agree:policyversion'] = 'The accepted policy version.';
$string['privacy:metadata:local_web3talents_agree:agreedtime'] = 'The time the policy was accepted.';
$string['privacy:metadata:local_web3talents_agree:ipaddress'] = 'The IP address used when the policy was accepted.';
$string['privacy:metadata:local_web3talents_agree:useragent'] = 'The browser user agent used when the policy was accepted.';
$string['privacy:metadata'] = 'The Web3 Talents plugin stores accepted-applicant records and operational events for Web3 Talents workflows.';
$string['privacy:metadata:local_web3talents_log'] = 'Stores operational events generated by Web3 Talents plugin workflows.';
$string['privacy:metadata:local_web3talents_log:userid'] = 'The user associated with an operational event, when applicable.';
$string['privacy:metadata:local_web3talents_log:courseid'] = 'The course associated with an operational event, when applicable.';
$string['privacy:metadata:local_web3talents_log:eventtype'] = 'The type of operational event.';
$string['privacy:metadata:local_web3talents_log:metadata'] = 'Structured event details.';
$string['privacy:metadata:local_web3talents_log:timecreated'] = 'The time the operational event was recorded.';
