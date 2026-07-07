# Phase 11 Operations Plan

This phase defines the first production-ready operating model for the Web3 Talents Moodle stack. It is intentionally conservative: keep the moving parts small, make backups restorable, and use Moodle role capabilities instead of custom access rules wherever possible.

## OCI Environments

Use two OCI environments:

- `staging`: mirrors production sizing at a smaller scale and receives every plugin/theme release before production.
- `production`: runs the live Moodle course, production database, Moodle data storage, mail configuration, cron, and backups.

Recommended baseline:

- Moodle app: OCI Compute VM or container host running Nginx/Apache with PHP-FPM, Moodle cron, and the versioned Web3 Talents local plugin/theme.
- Database: OCI PostgreSQL-compatible managed database when available, or a dedicated PostgreSQL VM with restricted ingress from the app subnet only.
- File storage: persistent block volume for `moodledata`, with OCI volume backups enabled. If object storage is introduced later, use Moodle-supported file storage patterns rather than direct ad hoc writes.
- Email: production SMTP provider with SPF, DKIM, and DMARC configured for the sending domain. Staging should use a sandbox or test recipient allowlist.
- Secrets: store database credentials, SMTP credentials, admin bootstrap passwords, and salt values outside git in OCI vault or deployment environment variables.

## Backups

Production must keep:

- daily snapshots for 7 days
- weekly snapshots for 4 weeks
- monthly snapshots for 6 months

Back up all three state sources together:

- Moodle database dump or managed database snapshot
- `moodledata` volume snapshot
- deployed plugin/theme release artifact or git commit SHA

Snapshots should be tagged with environment, timestamp, source commit, and Moodle version. Database and file snapshots should be taken close together so restored course files and database rows match.

## Restore Test

Run a restore test at least once before launch and again after any infrastructure change:

1. Restore the latest staging backup into an isolated restore environment.
2. Confirm Moodle loads and the Web3 Talents plugin upgrade completes.
3. Log in as admin and verify accepted applicants, topic rounds, room assignments, and exports.
4. Log in as student and mentor test users and verify they only see their allowed room views.
5. Run Moodle cron and confirm scheduled tasks complete without errors.
6. Record the restore date, source snapshot, target host, elapsed time, and issues found.

## Retention Cleanup

The local plugin now registers a scheduled cleanup task:

- task: `local_web3talents\task\cleanup_retention`
- default schedule: daily at 03:15 server time
- export temp files: delete files in `local_web3talents/exports` under Moodle temp storage after 14 days
- accepted applicants: mark accepted or account-created applicants as removed when their `retentionuntil` timestamp has passed

Zoom CSV downloads are streamed directly. Internal room-assignment workbooks are written as temporary export files and are covered by the 14-day cleanup rule.

## Permission Review

Current expected access:

- Logged-out users cannot access private course pages or local plugin workflow pages.
- Students can use the course link for topic selection and their own room assignment only.
- Mentors can use the course link for the read-only room overview only.
- Mentors cannot move groups, download Zoom CSV, or download internal room assignments.
- Admins/managers can manage accepted applicants, topic rounds, rooms, exports, and plugin settings.

Run `bash moodle/tooling/validate-phase11.sh` after configuration to check the local version of these guards.
