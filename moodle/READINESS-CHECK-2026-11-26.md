# Cohort readiness and source reconciliation

This repository-safe summary excludes participant identities, recipient manifests,
private journals, credentials and account-cleanup records. Detailed operational
records belong in protected server backups, not Git.

## Implemented

- Seven-day personal password-setup links and matching invitation deadlines.
- Participant invitation batch completed after explicit authorisation. Do not rerun
  invitation sending or regenerate existing tokens during deployment.
- Mentor accounts and grading permissions prepared. Mentor copy is a render-only
  template, not yet wired into a production mentor-invitation sender.
- Native Mentor tools subsection at the top of Overview: grading, attendance,
  availability and room assignments. Student visibility and direct tool permissions
  were checked. A private non-participation group gates navigation; role/enrolment
  observers keep membership aligned and it is excluded from student allocation.
- Participant activation/pending-setup dashboard counts with matching list filters.
  Activation is first-login agreement completion, not provisioning or password
  creation alone. Timestamps use recorded agreement acceptance, not edit times.
  Counts exclude mentors and non-applicant accounts.
- Worksheet release restrictions follow the approved lecture calendar at 19:30
  Europe/Berlin, including the holiday break and daylight-saving transition.
- Weekly workflow prioritises topic selection, Zoom and recordings; WhatsApp is
  the cohort's main communication channel.
- Activity-page layout fixes and recording upload configuration are included.
- Dedicated attendance and topic-round worker units are versioned separately from
  general Moodle cron.

## Remaining operational boundaries

- General Moodle cron and persistent invitation sending remain paused. Do not
  enable them during source deployment or drain queued notifications implicitly.
- The topic-round runner honours the invitation pause, so round finalisation will
  remain paused until organisers explicitly change the operational settings.
- Zoom OAuth and meeting access were checked. No completed teaching occurrence
  was available for a real participant-report import. Synthetic import/retry and
  manual-override tests passed; end-to-end Zoom attendance remains to be verified
  after a real session. See `ZOOM-ATTENDANCE.md`.
- Mentors do not use the participant agreement-activation status. Use course
  Participants filtered to Mentor and the course-access column for course visits;
  a user's profile also shows site-access information. Neither account existence
  nor enrolment status proves that a person has signed in.

## Verification and deployment

Custom plugin/theme/block source and the PHP upload configuration/systemd units
were compared by hash with Oracle. `mentor_invitation_template.php` is intentionally
local-only preview code, not an active sender. Read-only runtime tests verify account
setup totals, filters, pagination and agreement timestamps. Isolated suites cover
review decisions, invitation formatting/guards, grading and Zoom attendance.

Moodle course content, activity settings, roles, account state and uploaded PDFs
live in the database/dataroot. They are not recreated by a Git push. Preserve
protected database/dataroot backups; provisioning tools are explicit operator
commands, not automatic deployment hooks. Never commit Moodle config, Zoom/SMTP
secrets, password links, recipient data or runtime backups.

The Git branch records source changes; Oracle deployment is currently explicit
SSH/file deployment rather than a GitHub Actions pipeline. For future changes:
review and test source, commit and push it, then deploy that reviewed revision and
verify source hashes and the affected behaviour. Do not run one-off provisioning
or mail-sending scripts as part of ordinary source synchronisation.
