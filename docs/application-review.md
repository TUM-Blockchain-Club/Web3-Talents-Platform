# Moodle application review

The Web3 Talents admin dashboard now opens **Applications**, rather than an already-accepted applicant importer. This is part of the Moodle plugin, not the public React website. It uses the current Moodle theme without custom theme dependencies.

## Workflow

1. Open **Applications → Upload Tally CSV**. Export CSV from Tally; keep the original headers. Select the cohort and preview the file.
2. The preview lists ready, invalid, duplicate, changed and already-existing-user records. Confirm to import only new valid applications. No Moodle users or emails are created at import time.
3. Open an application to read responses grouped into expandable sections. Attachment URLs open separately; the importer does not download CVs/videos or execute CSV contents.
4. Reviewers can leave private comments, approve, raise an objection with an explanation, or withdraw their own decision. Use separate Moodle accounts: repeating a vote under the same account still counts once.
5. Two distinct, currently authorised reviewers must approve, with no outstanding objections. The second approval creates an account and enrols the student in the configured fundamentals course, then attempts Moodle's new-password email. The existing force-password-change and participation-agreement workflow remains in place.
6. An objection sets **Needs discussion**, not an automatic rejection. Its author must withdraw or change it after discussion. Withdrawing alone never triggers an email or account creation; an explicit approval or provisioning retry is required.
7. If provisioning fails, approvals stay saved and **Retry account creation** is available. Account creation, enrolment and linking are transactional. If email fails, the account stays intact and **Resend login email** is available. Moodle/SMTP accepting an email is not proof of delivery to the inbox.

Re-importing never changes existing answers, comments, votes or accounts. A changed email with the same Tally submission ID is also flagged. Changed submissions require separate human resolution; there is no automatic merge of answers already reviewed. Existing Moodle users are not automatically linked, overwritten or emailed.

## Permissions and data handling

- View/import/comment/object: `local/web3talents:manageacceptedapplicants` in the configured course context.
- Approve/provision/resend: the above plus `local/web3talents:createstudentaccounts`. The existing course Manager role grants these; do not make reviewers site administrators merely for this workflow.
- Old direct provisioning entry points also require two eligible approvals. Imported/manual `status=accepted` cannot bypass review. Existing linked student accounts are preserved during upgrade.
- All web mutations are POST requests with Moodle session-key validation. Import previews are session-bound, expire after 30 minutes and can be confirmed only once.
- File limit: UTF-8 CSV, 5 MB, 2,000 records, 250 columns, 20 MB expanded responses. All question labels/answers are retained, including conditional empty fields and repeated questions.
- Applicant responses can contain sensitive personal information. Limit reviewer access, use HTTPS, and keep source exports outside Git. No applicant ranking or automated evaluation of answers is performed.
- A pending application has no automatic activation deadline. The existing 30-day unactivated-account window starts at account creation. The existing retention task marks expired accounts' application records as removed; **it does not erase personal data**. Application-response/comment erasure and applicant-data export need an explicit operational retention process; the plugin's existing privacy provider is metadata-only. Do not assume automatic deletion or complete privacy-request support.

## Deployment

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
