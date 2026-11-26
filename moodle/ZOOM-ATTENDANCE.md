# Zoom attendance

This is separate from the shared-host start-link feature. It uses read-only Zoom
Server-to-Server OAuth API access and a dedicated worker, not general Moodle cron.
It sends no emails and does not create users or change course enrolments or grades.

## Connect

As a site administrator, open `/local/web3talents/zoom_attendance_settings.php`
over HTTPS. Enter the Account ID, Client ID and Client Secret from the Zoom
Marketplace app belonging to the account hosting the meeting. Use the recurring
meeting number (currently `82770750338`), not a password, join URL or occurrence UUID.

Required granular scopes:

- `meeting:read:meeting:admin`
- `meeting:read:list_past_instances:admin`
- `meeting:read:list_past_participants:admin`

Activate the app after adding scopes. Check **Enable automatic attendance syncing**
and choose **Verify meeting and save**. Saving verifies the meeting/host; the first
completed-session report verifies reporting scopes. A paid Zoom Pro-or-higher
account is required by the participants endpoint. No additional mentor licences
are needed just to read the hosting account's reports.

Credentials are saved atomically, mode 0600, under
`$CFG->dataroot/web3talents-private/zoom-attendance.json`. They are never displayed
back in HTML or saved to Git/Moodle settings. Protect dataroot backups as secrets.
Blank credential fields retain existing values. **Pause syncing** does not erase
attendance, change the meeting or affect the existing host-delegation settings.

## Worker

Install `docker/systemd/web3talents-zoom-attendance.{service,timer}` in
`/etc/systemd/system/`, run `systemctl daemon-reload`, then
`systemctl enable --now web3talents-zoom-attendance.timer`.

The timer invokes only `local/web3talents/cli/sync_zoom_attendance.php` as www-data
inside `moodle-web-1` every 15 minutes. It never invokes Moodle cron, sends mail,
finalises topic rounds or processes invitation queues. With no enabled connection
it reports disabled and makes no Zoom API calls. Errors are sanitised in the journal.

## Matching and rules

- Sessions must exist in Attendance/Participation with their correct date/time.
- Look back seven days, starting two hours after the scheduled start. Query only
  ended instances of the configured recurring meeting, using occurrence UUIDs.
- Match an occurrence to exactly one course session starting within 45 minutes.
  Unmatched/ambiguous occurrences are counted in the status notice, not guessed.
  Correct session dates or meeting configuration, then rerun the check. The worker
  does not offer arbitrary historical backfills beyond seven days.
- Fetch **every** participant page before writing anything. Incomplete/zero-duration
  reports are retried. Refresh imported reports through 48 hours after the meeting;
  failed reports remain eligible for retry within the seven-day window.
- Match only a nonempty Zoom-provided email from a signed-in attendee (nonempty
  Zoom `id`) to exactly one active enrolled student. Guest/registration emails
  without a signed-in identity always need reviewer confirmation.
  Do not infer identities from names or assume external emails are always provided.
- Group reconnects by email, or by signed-in Zoom ID within the occurrence when
  email is hidden. Anonymous identities stay separate. Union overlapping intervals
  so multiple connections cannot double-count time. Exclude waiting-room records.
- Positive verified meeting time creates **Present**. This is evidence of attendance,
  not a minimum-duration/completion rule. Display minutes for mentor judgement.
  Never infer **Absent**, **Late**, or participation/grade scores from Zoom.
- Manual attendance is protected by an explicit `source` field, defaulting all
  existing records to manual. Manual attendance saves and imports share a session
  lock. Reviewer matches/ignores/holds survive refreshed reports.
- Attendance → **Zoom attendance and unmatched attendees** lets mentors/admins
  review evidence, explicitly match an enrolled student, or ignore hosts/mentors.
  Corrections affect only automatic attendance. Matching is occurrence-specific,
  not a permanent mapping of a display name to a student.
- Evidence stores only name, email, hashed occurrence/identity keys, intervals and
  reviewer decisions. No raw API responses, tokens, IP addresses or host-start URLs.
  The dedicated worker deletes evidence after 90 days; summary attendance remains.

## Verification

`tooling/validate-zoom-attendance-runtime.php` refuses to run outside isolated
`reviewtest` with email disabled and `W3T_SECURITY_TEST=1`. It uses fake API responses
and transaction-rolled-back fixtures. Covers pagination, encoding, retries, overlapping
duration, hidden/duplicate email, ambiguous sessions, permission checks, reviewer
correction and manual attendance preservation. Production acceptance additionally
requires a completed Zoom meeting with an identifiable student and an unmatched
attendee, plus a second sync proving the outcome is unchanged. Until that live test
is done, do not describe the integration as end-to-end verified.

Zoom reference: https://developers.zoom.us/docs/api/meetings/
