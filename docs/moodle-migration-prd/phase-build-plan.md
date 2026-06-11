# Web3 Talents Moodle Platform Phase Build Plan

## 1. Build Goal

Migrate the Web3 Talents platform direction to Moodle 5.2.1 and build the first usable student-facing release.

The first release should provide:

- Public overview page inside Moodle.
- Moodle-hosted fundamentals cohort course.
- Accepted-applicant gated account creation.
- Moodle-native communication and topic selection.
- Moodle groups for partner groups.
- Hidden Web3 Talents room-generation workflow.
- Admin-only Zoom CSV export.
- Mentor and student room visibility.
- OCI-hosted development, staging, and production path.

This document is an implementation phase plan derived from `project-requirements.md`. It is not a replacement for the PRD.

## 2. Target Stack

### Platform

- Moodle version: 5.2.1.
- Hosting: Oracle Cloud Infrastructure.
- Primary Moodle database: to be selected during infrastructure planning.
- Moodle file storage: to be selected during infrastructure planning.
- Email: Moodle-configured SMTP or OCI-compatible email delivery.

### Moodle Customizations

- Theme: custom Boost child theme.
- Main plugin: `local_web3talents`.
- Moodle-native features:
  - Courses.
  - Enrolments.
  - Roles.
  - Groups.
  - Choice.
  - Forums.
  - Messaging.
  - Resources.
  - Moodle email.

### Legacy Reference

The current TypeScript platform should be used as a behavior reference for:

- Partner-group validation.
- Topic assignment rules.
- Balanced fallback handling.
- Room generation.
- Manual room movement.
- Zoom CSV export shape.

The long-term product shell should be Moodle, not the existing Next.js/Fastify app.

## 3. Repository Direction

Recommended Moodle migration folder structure inside this repository:

```text
docs/
  moodle-migration-prd/
    project-requirements.md
    phase-build-plan.md
moodle/
  plugins/
    local_web3talents/
  themes/
    web3talents/
  tooling/
```

The exact Moodle source checkout, deployment structure, and plugin packaging approach should be finalized in Phase 1.

## 4. Phase Plan

Each phase should end with a checkpoint. Do not move major product assumptions forward if the checkpoint fails.

## Phase 0: Migration Kickoff And Decision Lock

Goal:

- Freeze the product direction around Moodle and prevent new work from drifting back into the old architecture.

Build:

- Confirm Moodle 5.2.1 target.
- Confirm OCI as hosting target.
- Confirm `local_web3talents` plugin name.
- Confirm Boost child theme direction.
- Mark existing TypeScript app as reference implementation for grouping/export behavior.
- Keep the Moodle PRD and phase plan as the active planning documents.

Validation:

- `docs/moodle-migration-prd/project-requirements.md` exists and reflects current decisions.
- `docs/moodle-migration-prd/phase-build-plan.md` exists.
- No remaining first-release product blockers are open in the PRD.

Checkpoint:

- Product direction is accepted before environment setup starts.

Estimated time:

- 0.5 day.

## Phase 1: Moodle Development Environment

Goal:

- Create a repeatable local Moodle development environment for Moodle 5.2.1.

Build:

- Choose local development approach:
  - Docker-based Moodle stack, or
  - local PHP/web server/database setup.
- Install Moodle 5.2.1.
- Configure local database.
- Configure local Moodle data directory.
- Enable developer debugging.
- Confirm plugin and theme development workflow.
- Document local setup commands.

Validation:

- Moodle 5.2.1 loads locally.
- Admin user can log in.
- A test course can be created.
- Moodle cron can run locally.
- Debug mode is available for development.

Checkpoint:

- Local Moodle development is reproducible from documentation.

Estimated time:

- 1 to 2 days.

## Phase 2: Base Moodle Configuration

Goal:

- Configure Moodle's core structure for the fundamentals cohort before custom plugin work.

Build:

- Configure site name and base settings.
- Configure authentication method for manually created student accounts.
- Configure Moodle email for local/staging testing where possible.
- Create base roles:
  - Student.
  - Mentor using Moodle teacher role.
  - Program admin.
  - Platform admin.
- Create fundamentals cohort course.
- Organize course by topic-based sections.
- Enable announcements.
- Enable course forum.
- Enable direct messaging according to Moodle permissions.
- Confirm Moodle Choice is available.

Validation:

- Student test account can log in.
- Mentor test account can access the course as teacher.
- Course forum works.
- Announcements work.
- Direct messaging works between students.
- Moodle Choice can be created in the course.

Checkpoint:

- Core Moodle behavior covers the baseline student and mentor experience.

Estimated time:

- 1 to 3 days.

## Phase 3: Boost Child Theme And Public Overview Page

Goal:

- Build the first branded Moodle experience and implement the public overview page with high Figma fidelity.

Build:

- Create custom Boost child theme, recommended directory name `theme_web3talents`.
- Add Web3 Talents branding foundations:
  - Typography.
  - Colors.
  - Buttons.
  - Basic layout styling.
  - Responsive behavior.
- Implement public overview page inside Moodle.
- Ensure public overview page is visible before login.
- Keep deeper student/admin screens lower fidelity for first release.

Validation:

- Theme can be installed and selected.
- Public overview page works while logged out.
- Public overview page works on desktop and mobile.
- Private course content is not visible to public users.
- Login path remains accessible.

Checkpoint:

- Public overview page is acceptable as the one high-fidelity first-release screen.

Estimated time:

- 3 to 6 days, depending on Figma readiness.

## Phase 4: `local_web3talents` Plugin Scaffold

Goal:

- Create the main custom plugin foundation.

Build:

- Scaffold `local_web3talents`.
- Add plugin version metadata.
- Add access capabilities.
- Add admin navigation entry.
- Add basic plugin settings page.
- Add database install/upgrade structure.
- Add basic renderer/templates if needed.
- Add first automated plugin smoke test if tooling is available.

Initial capabilities:

- Manage accepted applicants.
- Create accepted student accounts.
- Manage room generation.
- View room assignments as mentor.
- View room information as student.
- Download Zoom CSV as admin.

Validation:

- Plugin installs cleanly.
- Plugin can be enabled/disabled in local Moodle.
- Admin can access plugin landing page.
- Non-admin users cannot access admin-only plugin pages.

Checkpoint:

- Plugin foundation is stable before implementing workflows.

Estimated time:

- 2 to 4 days.

## Phase 5: Accepted-Applicant List And Account Creation

Goal:

- Implement accepted-applicant verification and individual account creation.

Build:

- Accepted-applicant data model in `local_web3talents`.
- Admin UI for accepted-applicant list.
- Manual accepted-applicant creation.
- CSV import.
- Excel import.
- Search by name/email.
- Account creation status.
- Individual Moodle user creation flow.
- Verification that email exists on accepted-applicant list.
- Enrollment into the correct Moodle course.
- Student role assignment.
- Moodle email activation instructions.
- One-month retention marker for unactivated accepted applicants.

Validation:

- Admin can import accepted applicants from CSV.
- Admin can import accepted applicants from Excel.
- Admin can create an account only for an accepted applicant.
- Duplicate account creation is blocked or clearly warned.
- Created student is enrolled in fundamentals course.
- Activation email is sent through Moodle email.
- Non-accepted email cannot become a student account through this flow.

Checkpoint:

- Accepted-student onboarding works end to end.

Estimated time:

- 5 to 8 days.

## Phase 6: First-Login Policy Agreement

Goal:

- Require students to agree to communication or code-of-conduct terms on first login.

Build:

- Decide whether to use Moodle policy tooling if available or implement a simple plugin-managed agreement.
- Add policy text placeholder or configurable content.
- Require first-login agreement before normal course access.
- Store agreement timestamp and user ID.
- Provide admin visibility into agreement status if practical.

Validation:

- New student sees agreement on first login.
- Student cannot proceed until agreement is accepted.
- Agreement acceptance is stored.
- Student does not see agreement repeatedly after accepting current version.

Checkpoint:

- First-login policy flow is acceptable for launch.

Estimated time:

- 1 to 3 days.

## Phase 7: Fundamentals Course Materials And Communication

Goal:

- Make the fundamentals course useful as the student course home.

Build:

- Configure topic-based course sections.
- Migrate useful Google Drive materials into Moodle.
- Add pages, files, URLs, folders, or labels as appropriate.
- Add course announcements area.
- Add course forum.
- Confirm direct messaging between students, mentors, and admins.
- Add basic student navigation to current materials and communication.
- Configure alumni access model for same-course retention.

Validation:

- Student can access topic-based materials.
- Student does not need Google Drive as the primary material source.
- Student can use course forum.
- Student can direct message another student.
- Mentor can participate in forum and announcements.
- Alumni-style test user can still access approved materials and old forum.

Checkpoint:

- The fundamentals course is usable as the main student home.

Estimated time:

- 3 to 6 days, depending on material migration volume.

## Phase 8: Moodle Groups And Choice Integration

Goal:

- Connect partner groups and topic choices to Moodle-native structures.

Build:

- Create partner groups as Moodle groups.
- Add admin guidance or plugin view for group validation.
- Add Moodle Choice activity for topic selection.
- Support four topic options for first release.
- Build plugin service to read enrolled students.
- Build plugin service to read Moodle groups.
- Build plugin service to read Choice responses.
- Show admin warnings for:
  - Student without partner group.
  - Invalid group size.
  - Student without topic choice.
  - Group with split choices.
  - Group with no responses.

Validation:

- Admin can create or review Moodle groups.
- Students can submit Choice responses.
- Plugin reads Choice responses correctly.
- Plugin maps students to Moodle groups.
- Plugin warning output matches real course state.

Checkpoint:

- Moodle groups and Choice provide the required source data for room generation.

Estimated time:

- 4 to 7 days.

## Phase 9: Hidden Topic Assignment And Room Generation

Goal:

- Port the validated jigsaw-style assignment logic into Moodle while keeping it hidden from students.

Build:

- Port topic assignment rules from TypeScript reference behavior.
- Assign one topic per Moodle partner group.
- Keep partner groups together.
- Balance split-choice groups.
- Balance no-response groups.
- Generate `Room1` through `RoomN`.
- Target one partner group per topic per room where possible.
- Support admin-selected or adjustable room count.
- Keep only latest generated room assignment result.
- Store latest result in plugin tables.
- Add admin review UI.
- Add manual room movement UI.
- Show warnings for imperfect balance.

Validation:

- Same-vote group assigns correctly.
- One-response group assigns correctly.
- Split-response group balances correctly.
- Three-person majority assigns correctly.
- No-response group gets fallback balanced topic.
- Partner groups are never split.
- Room names match exact `RoomN` format.
- Manual move updates latest result.

Checkpoint:

- Room generation behavior matches the PRD and validated legacy logic.

Estimated time:

- 6 to 10 days.

## Phase 10: Room Visibility And Zoom Export

Goal:

- Make room assignments usable by admins, mentors, students, and Zoom.

Build:

- Admin current room assignment view.
- Mentor read-only room assignment view.
- Student room information view.
- Admin-only Zoom CSV export.
- CSV columns:
  - `Pre-assign Room Name`
  - `Email Address`
- Two-week retention for generated Zoom export files.
- Retention cleanup mechanism through Moodle cron or scheduled task.
- Permission checks for all views and exports.

Validation:

- Admin can download Zoom CSV.
- Mentor can view but not edit assignments.
- Student can see relevant room information.
- Student cannot access export.
- Mentor cannot access export.
- CSV contains only required columns.
- CSV uses Moodle user email addresses.
- Export is retained for two weeks and eligible for deletion afterward.

Checkpoint:

- Live-session room workflow is ready for operational testing.

Estimated time:

- 4 to 7 days.

## Phase 11: Security, Retention, Backups, And OCI Plan

Goal:

- Prepare the platform for safe staging and production use.

Build:

- Define OCI production architecture.
- Define OCI staging architecture.
- Configure Moodle application hosting path.
- Configure database hosting path.
- Configure Moodle file storage path.
- Configure email delivery.
- Define backup implementation:
  - Daily backups retained for 7 days.
  - Weekly backups retained for 4 weeks.
  - Monthly backups retained for 6 months.
- Define restore test process.
- Implement plugin cleanup for:
  - Zoom exports after two weeks.
  - Unactivated accepted applicants after one month, or mark them for removal.
- Review permissions for public, student, mentor, program admin, and platform admin.

Validation:

- Public users cannot access private course data.
- Students cannot access admin plugin pages.
- Mentors cannot edit room assignments.
- Mentors cannot download Zoom export.
- Only admins can manage accepted applicants.
- Backup and restore process is documented.
- Retention cleanup behavior is testable.

Checkpoint:

- Security and operations requirements are ready for staging.

Estimated time:

- 3 to 6 days, plus infrastructure provisioning time.

## Phase 12: Staging Deployment On OCI

Goal:

- Deploy a staging Moodle environment on OCI and run an end-to-end rehearsal.

Build:

- Provision OCI staging resources.
- Deploy Moodle 5.2.1.
- Install Boost child theme.
- Install `local_web3talents`.
- Configure email.
- Configure cron/scheduled tasks.
- Configure backup job or backup process.
- Load sample accepted applicants.
- Load sample students.
- Load sample course materials.
- Create sample groups and Choice activity.

Validation:

- Public overview page works in staging.
- Accepted-applicant account flow works in staging.
- Student login and first-login policy agreement work.
- Course materials are visible.
- Forum and direct messaging work.
- Choice responses are collected.
- Room generation works.
- Mentor view works.
- Student room view works.
- Zoom CSV downloads and has the correct shape.
- Retention scheduled task can be run manually.

Checkpoint:

- Staging validates the full first-release flow.

Estimated time:

- 3 to 7 days, depending on OCI setup maturity.

## Phase 13: Production Readiness And Launch

Goal:

- Launch the Moodle first release for the fundamentals cohort.

Build:

- Provision production OCI resources.
- Deploy Moodle 5.2.1.
- Install approved theme and plugin versions.
- Configure production email.
- Configure production backups.
- Configure production cron.
- Create production admin accounts.
- Create fundamentals course.
- Import accepted-applicant list.
- Migrate launch materials.
- Create partner groups.
- Create first topic Choice activity if needed.
- Verify privacy and permission settings.
- Prepare rollback/restore procedure.

Validation:

- Production smoke test passes.
- Public overview page works before login.
- Student account creation works.
- Activation email is delivered.
- Student can access course.
- Mentor can access course.
- Admin can run room generation.
- Zoom CSV export works.
- Backup job is active or first backup completed.
- Restore plan is documented.

Checkpoint:

- Production is ready for the fundamentals cohort.

Estimated time:

- 2 to 5 days after staging is accepted.

## Phase 14: Post-Launch Hardening

Goal:

- Improve reliability and usability after real users exercise the platform.

Build:

- Fix launch feedback.
- Improve confusing Moodle navigation.
- Improve admin warnings.
- Add focused automated tests around plugin logic.
- Add internal Excel export if still needed.
- Improve admin dashboard if operationally useful.
- Review communication usage.
- Review alumni access behavior.
- Review export retention cleanup.
- Review backup/restore execution.

Validation:

- No critical launch blockers remain.
- Admins can run weekly operations without manual workarounds.
- Students can find materials, communication, topic choices, and room information.
- Mentors can support live sessions from Moodle.

Checkpoint:

- First release is stable enough to plan P1 features.

Estimated time:

- 1 to 2 weeks after launch.

## 5. P1 Follow-Up Features

After the first release is stable, prioritize:

- Full applicant review pipeline.
- Presentation archive.
- Student uploads/submissions.
- Internal Excel review export.
- Admin operations dashboard.
- Attendance and participation tracking.
- Mentor availability.
- Better communication experience if Moodle-native tools are insufficient.
- Multi-cohort operational improvements.

## 6. P2 Future Features

Later roadmap:

- Web3 wallet identity fields.
- Credentials or certificates.
- Proof-of-participation.
- Tokenized achievements if needed.
- Collaborative document editing integration.
- Advanced analytics.
- External integrations beyond Zoom.

## 7. First Release Critical Path

The shortest path to a usable first release is:

1. Moodle 5.2.1 local environment.
2. Base course, users, roles, communication, and Choice.
3. Boost child theme and public overview page.
4. `local_web3talents` scaffold.
5. Accepted-applicant account creation.
6. Course materials and groups.
7. Choice response reading.
8. Room generation.
9. Mentor/student room views.
10. Zoom CSV export.
11. OCI staging.
12. OCI production.

Avoid delaying the first release for lower-priority items such as submissions, presentation archive, recordings, custom chat, or advanced admin dashboard.
