# Moodle application review

The Web3 Talents admin dashboard now opens **Applications**, rather than an already-accepted applicant importer. This is part of the Moodle plugin, not the public React website. It uses the current Moodle theme without custom theme dependencies.

## Workflow

1. New applications can arrive automatically through the signed Tally webhook (see below). For historical submissions or recovery, open **Applications → Upload Tally CSV**. Export CSV from Tally; keep the original headers. Select the cohort and preview the file.
2. The preview lists ready, invalid, duplicate, changed and already-existing-user records. Confirm to import only new valid applications. No Moodle users or emails are created at import time.
3. Open an application to read responses grouped into expandable sections. Attachment URLs open separately; the importer does not download CVs/videos or execute CSV contents.
4. Reviewers can leave private comments, approve, raise an objection with an explanation, or withdraw their own decision. Use separate Moodle accounts: repeating a vote under the same account still counts once.
5. Two distinct, currently authorised reviewers must approve, with no outstanding objections. The second approval creates an account and enrols the student in the configured fundamentals course. The branded participant invitation with a direct password-setting link is attempted only when **Applicant invitation emails → Send applicant account emails** is enabled by a site admin (disabled by default). The existing force-password-change and participation-agreement workflow remains in place.
6. An objection sets **Needs discussion**, not an automatic rejection. Its author must withdraw or change it after discussion. Withdrawing alone never triggers an email or account creation; an explicit approval or provisioning retry is required.
7. If provisioning fails, approvals stay saved and **Retry account creation** is available. Account creation, enrolment and linking are transactional. If email fails, the account stays intact and **Resend login email** is available. While invitation emails are deliberately paused, automatic sends and manual resends are blocked before password generation, and the UI explains the hold. Newly created held accounts do not start their 30-day unactivated-account retention window until an invitation is sent. Re-enabling mail never bulk-sends held invitations; reviewers must explicitly send each one. Core password-reset and other Moodle emails are unaffected by this application-specific switch. Moodle/SMTP accepting an email is not proof of delivery to the inbox.

Re-importing never changes existing answers, comments, votes or accounts. A changed email with the same Tally submission ID is also flagged. Changed submissions require separate human resolution; there is no automatic merge of answers already reviewed. Existing Moodle users are not automatically linked, overwritten or emailed.

While invitations are paused, this workflow also suppresses Moodle's manual-enrolment welcome message for the newly accepted student. It does not change the course's stored enrolment settings or other administrators' manual enrolments. Run `php moodle/tooling/validate-applicant-mail-pause.php` for isolated no-network regression checks of the hold, welcome-message suppression, and explicit resume behavior.

## Permissions and data handling

- View/import/comment/object: `local/web3talents:manageacceptedapplicants` in the configured course context.
- Approve/provision/resend: the above plus `local/web3talents:createstudentaccounts`. The existing course Manager role grants these; do not make reviewers site administrators merely for this workflow.
- Old direct provisioning entry points also require two eligible approvals. Imported/manual `status=accepted` cannot bypass review. Existing linked student accounts are preserved during upgrade.
- Interactive web mutations are POST requests with Moodle session-key validation. The machine-to-machine Tally endpoint instead requires an HMAC signature and exact form allowlist; it never uses a browser login. Import previews are session-bound, expire after 30 minutes and can be confirmed only once.
- File limit: UTF-8 CSV, 5 MB, 2,000 records, 250 columns, 20 MB expanded responses. All question labels/answers are retained, including conditional empty fields and repeated questions.
- Applicant responses can contain sensitive personal information. Limit reviewer access, use HTTPS, and keep source exports outside Git. No applicant ranking or automated evaluation of answers is performed.
- A pending application has no automatic activation deadline. The 30-day unactivated-account window starts at account creation when invitation sending is enabled; for accounts created while sending is paused, it starts on the first successful invitation. The existing retention task marks expired accounts' application records as removed; **it does not erase personal data**. Application-response/comment erasure and applicant-data export need an explicit operational retention process; the plugin's existing privacy provider is metadata-only. Do not assume automatic deletion or complete privacy-request support.

## Deployment

### Participant invitation template

The application list supports a status filter combined with name/email search, with filtered counts and pagination. “Needs discussion / objection” keeps the existing objection meaning. “Reject application” is a separate final decision requiring an account-creation-authorised reviewer, a reason, and an explicit confirmation checkbox. It sends no email and cannot reject an already provisioned student. Rejected applications remain available for comments and audit history. “Reopen for review” requires a reason and clears current review votes so fresh approvals are needed; it preserves comment/decision history. Existing objections are not converted into rejections.

The application overview shows global Total applications, Accepted, Rejected and
Left to review counts. Accepted includes `accepted`, `accountcreated` and
`accountactivated`. Left to review includes `pending` and `discussion`, including
applications with one approval; it means no final decision yet, not necessarily
untouched. Deferred, removed and unknown statuses are counted separately as Other.
Totals do not change when searching or filtering. Selecting a card resets search
and pagination and opens its corresponding queue. The status dropdown also offers
Left to review and Accepted (including created and activated accounts), while
retaining all individual status filters. Aggregate filters are not stored statuses.
Run `php moodle/tooling/validate-applicant-overview.php` for isolated aggregate checks.

The queue uses stable import order (ID ascending), so comments and decisions do not move applications and new entries appear at the end. Review links preserve search, status and page through form submissions. Back to applications restores that context and highlights/anchors the reviewed row. If a status change removes it from the current filtered page, an informational message explains why it is no longer visible.

Run `php moodle/tooling/validate-review-decisions.php`, `php moodle/tooling/validate-applicant-pagination.php` and `php moodle/tooling/validate-review-navigation.php` for isolated decision, filtering and navigation checks.

Initial applicant invitations and explicit resends use `invitation_template` through `invitation_mailer`, rather than Moodle's default generated-password email. The December 2026 copy uses a warm welcome and one rocket in the subject, enlarged numbered steps, Zoom email/name requirements, acceptance card and community links. Sessions are described as usually around one hour, with up to 1.5 hours reserved. Update the cohort-specific date and links in the renderer before using it for another cohort. Team-review rendering can use the optional `preview=true` argument: the password action is then disabled and explicitly labelled, with no personal setup or misleading login link.

The primary link opens Moodle's password-setting form directly. It uses a single-use core reset token and the configured `pwresettime` (currently 24 hours); a secondary link lets the recipient request a fresh one. Setting a password signs the recipient in automatically. Sending/resending never changes the existing password. Failed delivery removes the newly generated token; successful delivery invalidates that recipient's earlier reset links. Recipient names and URLs are HTML-escaped and both HTML and plain-text versions are generated.

The existing invitation pause remains authoritative and defaults off: paused calls create no tokens and send no emails. Enabling the flag alone does not send held invitations. This workflow suppresses the separate manual-enrolment welcome email even when invitations are enabled, so recipients receive only the branded invitation. Normal core password-reset emails are unchanged. The template's three-day request does not introduce an automatic deadline, unenrolment or changes to agreement tracking.

Run `php moodle/tooling/validate-invitation-email.php` and `php moodle/tooling/validate-applicant-mail-pause.php` for isolated synthetic tests with no live database or SMTP access.

### Automatic Tally intake

In **Site administration → Plugins → Local plugins → Web3 Talents → Tally application sync**, configure:

- Endpoint: `https://moodle.web3-talents.com/local/web3talents/tally_webhook.php`.
- Allowed form ID: the application form's ID (`BE4l9Y` for the 26/27 form).
- Signing secret: the same random secret shown under **Tally → Integrations → Webhooks → Add a signing secret**. Store this only in Moodle settings/Tally, never in Git or an endpoint URL.
- Cohort: `fundamentals` (or the same cohort ID used by your CSV imports).
- Enable sync after configuring both ends. Optional stable question-key mappings support renamed identity questions; otherwise First Name, Last Name and Email labels (including punctuation) are recognised.

The receiver validates a base64 HMAC-SHA256 signature over the original request body, limits bodies to 1 MB / 250 fields, and accepts identified submission events from the configured form. When `eventType` is present it must be `FORM_RESPONSE`; Tally's dashboard test/replay variant omits it. Choice IDs are converted to answer labels. All answers remain untrusted data and are escaped in application review; attachment URLs are never fetched. Imports use the existing shared lock and preserve applications by submission ID or email, including their comments/decisions. Existing Moodle users are skipped. Webhooks only insert pending applications with system actor `0`; they cannot approve, provision, enrol or send emails.

Tally only sends new submissions automatically. Historical applications still need a deliberate CSV import or selected replay; enabling this integration does not backfill them. Do not replay all historical entries without checking scope. Existing Notion/other Tally integrations should be left unchanged.

The settings page shows the latest successful delivery time. Check Tally's webhook event log for failures; non-2xx responses trigger Tally's retries. An HTTP 200 means the submission was inserted or safely skipped as a duplicate/existing user, not accepted as a student. Duplicate deliveries do not overwrite changed answers: resolve resubmissions manually if necessary. Disabling sync makes the endpoint return 503 and causes retries; pause the webhook in Tally as well for planned shutdowns.

Run `php moodle/tooling/validate-tally-webhook.php` for standalone signature, parsing and idempotency tests (in-memory persistence doubles; no live credentials, database or emails). Production verification must also check unsigned requests are rejected and a real Tally delivery appears as pending before considering the connection live.

Reference: https://tally.so/help/webhooks

### Moodle plugin deployment

GitHub stores source; a push to this branch is not an Oracle Moodle deployment.
The current Oracle instance runs Docker Compose with plugin/theme directories
mounted from `/opt/web3talents/moodle`. Recent changes were deployed by copying
reviewed source files into those host directories, backing up replaced files and
purging relevant Moodle caches. This explains why live changes previously existed
before a commit. Keep source commits/pushes alongside deployments going forward.
Do not commit `.env`, Moodle `config.php`, credentials, applicant exports, database
backups or moodledata. Course uploads/settings remain runtime data, not Git content.
No automatic GitHub-to-Oracle deployment workflow is configured in this repository.

Back up the Moodle database and moodledata first. Deploy `moodle/plugins/local_web3talents` with the project's normal Moodle release process, then run the core CLI upgrade and purge caches in the Moodle container:

```sh
php /var/www/html/admin/cli/upgrade.php --non-interactive
php /var/www/html/admin/cli/purge_caches.php
```

Version `2026110500` adds response/submission fields and separate review/comment tables. It does not change the active theme, existing accounts, course content or enrolments. Before a real acceptance, verify the configured course shortname, enabled manual enrolment, two separate reviewer logins, and Moodle SMTP/password email delivery using a controlled test address. Importing real applications and accepting people are separate operator actions.

## Verification

`moodle/tooling/validate-application-review.php` exercises CSV parsing, duplicate/changed imports, private comments, distinct approvals, conflicts, permissions, enrolment, retry and duplicate-account prevention using synthetic `example.invalid` accounts. It refuses to run unless the database is literally `reviewtest`, `noemailever` is enabled, and `W3T_APPLICATION_REVIEW_TEST=1` is set. Run only in an isolated disposable Moodle installation, never against Oracle or an existing development database.

Example after mounting tooling at `/opt/w3t`:

```sh
docker exec -e W3T_APPLICATION_REVIEW_TEST=1 w3t-review-web php /opt/w3t/validate-application-review.php
```

The original Tally export is not a test fixture and must not be committed.

The older Phase 5/6 sample setup scripts assume immediate acceptance and direct account creation. Do not rerun those seeders on this version: applications now deliberately require two real reviewer decisions. Use the isolated validation script above for synthetic account-creation checks; existing linked demo/real accounts are unaffected by the upgrade.
