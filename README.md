# Web3 Talents Moodle Platform

This repository is now centered on the Moodle migration for the Web3 Talents cohort platform.

The current local product is a Moodle 5.2.1 development stack with custom Web3 Talents plugins for:

- accepted-applicant management
- student first-login agreement tracking
- weekly topic selection by partner group
- Moodle group generation
- room assignment and Zoom CSV export
- student and mentor room views
- attendance and participation tracking
- mentor availability
- mentor/admin presentation grading

The older TypeScript app in `apps/` and `packages/` is kept as a reference implementation for grouping and export behavior. New product work should happen in the Moodle stack unless a task explicitly says otherwise.

## Repository Layout

```text
moodle/
  plugins/local_web3talents/   # Main Moodle local plugin
  blocks/web3talents/          # Dashboard block for Web3 Talents shortcuts
  themes/web3talents/          # Custom Moodle theme
  tooling/                     # Repeatable configure/validate scripts
  docker/                      # Local PHP image

docs/moodle-migration-prd/     # Active Moodle product and phase docs

apps/, packages/               # Legacy TypeScript reference app
```

Ignored local Moodle runtime folders:

```text
moodle/src/
moodle/moodledata/
moodle/dbdata/
```

## Requirements

- Docker
- Docker Compose
- `curl`
- `tar`
- Node/npm only if you are working on the legacy TypeScript reference app

## First-Time Moodle Setup

Run these from the repository root:

```bash
cp moodle/.env.example moodle/.env
bash moodle/tooling/fetch-moodle.sh
docker compose --project-directory moodle build
bash moodle/tooling/install-moodle.sh
```

Then apply the current full local configuration:

```bash
bash moodle/tooling/configure-phase2.sh
bash moodle/tooling/configure-phase3.sh
bash moodle/tooling/configure-phase4.sh
bash moodle/tooling/configure-phase5.sh
bash moodle/tooling/configure-phase6.sh
bash moodle/tooling/configure-phase7.sh
bash moodle/tooling/configure-phase8.sh
bash moodle/tooling/configure-phase8b.sh
bash moodle/tooling/configure-phase9.sh
bash moodle/tooling/configure-phase10.sh
bash moodle/tooling/configure-phase11.sh
bash moodle/tooling/configure-p1.sh
```

Open Moodle:

```text
http://localhost:8080
```

Open local email inbox:

```text
http://localhost:8025
```

## Daily Development

Start Moodle:

```bash
docker compose --project-directory moodle up -d
```

Stop Moodle:

```bash
docker compose --project-directory moodle down
```

Run Moodle cron:

```bash
docker compose --project-directory moodle exec web php admin/cli/cron.php
```

Run environment checks:

```bash
bash moodle/tooling/doctor.sh
```

## Local Accounts

Default local credentials:

```text
Admin:   admin / Admin123!
Student: w3t.student1 / ChangeMe123!
Student: w3t.student2 / ChangeMe123!
Mentor:  w3t.mentor1 / ChangeMe123!
```

The admin password is read from `moodle/.env`. Test-user passwords come from `WEB3T_PHASE2_TEST_PASSWORD` in `moodle/.env`, falling back to `ChangeMe123!`.

## Main Moodle URLs

Use the Moodle dashboard first:

```text
http://localhost:8080/my/
```

The dashboard includes the Web3 Talents block with shortcuts into the custom workflows.

Direct local plugin URLs:

```text
Admin dashboard:       http://localhost:8080/local/web3talents/index.php
Accepted applicants:  http://localhost:8080/local/web3talents/applicants.php
Topic rounds:         http://localhost:8080/local/web3talents/topic_rounds.php
Choose weekly topic:  http://localhost:8080/local/web3talents/choose_topic.php
Room assignments:     http://localhost:8080/local/web3talents/room_assignments.php
My room:              http://localhost:8080/local/web3talents/my_room.php
Mentor rooms:         http://localhost:8080/local/web3talents/mentor_rooms.php
Participation:        http://localhost:8080/local/web3talents/participation.php
Mentor availability:  http://localhost:8080/local/web3talents/mentor_availability.php
Mentor grading:       http://localhost:8080/local/web3talents/mentor_grading.php
```

Native Moodle admin navigation:

```text
Site administration -> Plugins -> Local plugins -> Web3 Talents
```

## Current Course Structure

The local fundamentals course is `Web3 Talents Fundamentals Cohort`.

The course index is configured as:

```text
Overview
Topic 1
  Subtopic 1
  Subtopic 2
  Subtopic 3
  Subtopic 4
...
Topic 10
  Subtopic 1
  Subtopic 2
  Subtopic 3
  Subtopic 4
Topic Selection
```

`Overview` should contain one Announcements forum. Student-facing workflow links such as `Choose Weekly Topic` and `My room assignment` should appear under `Topic Selection`, not under a weekly topic.

## Configure And Validate By Phase

Each phase has a configure script and a validation script. Use the matching validation script after changing a phase.

| Area | Configure | Validate |
| --- | --- | --- |
| Base Moodle config | `bash moodle/tooling/configure-phase2.sh` | `bash moodle/tooling/validate-phase2.sh` |
| Theme and public overview | `bash moodle/tooling/configure-phase3.sh` | `bash moodle/tooling/validate-phase3.sh` |
| Local plugin scaffold | `bash moodle/tooling/configure-phase4.sh` | `bash moodle/tooling/validate-phase4.sh` |
| Accepted applicants | `bash moodle/tooling/configure-phase5.sh` | `bash moodle/tooling/validate-phase5.sh` |
| First-login agreement | `bash moodle/tooling/configure-phase6.sh` | `bash moodle/tooling/validate-phase6.sh` |
| Course materials and communication | `bash moodle/tooling/configure-phase7.sh` | `bash moodle/tooling/validate-phase7.sh` |
| Moodle groups and legacy Choice source data | `bash moodle/tooling/configure-phase8.sh` | `bash moodle/tooling/validate-phase8.sh` |
| Weekly group-slot topic selection | `bash moodle/tooling/configure-phase8b.sh` | `bash moodle/tooling/validate-phase8b.sh` |
| Hidden room generation | `bash moodle/tooling/configure-phase9.sh` | `bash moodle/tooling/validate-phase9.sh` |
| Zoom CSV and internal room exports | `bash moodle/tooling/configure-phase10.sh` | `bash moodle/tooling/validate-phase10.sh` |
| Retention cleanup and operations | `bash moodle/tooling/configure-phase11.sh` | `bash moodle/tooling/validate-phase11.sh` |
| Attendance, participation, mentor availability, grading | `bash moodle/tooling/configure-p1.sh` | `bash moodle/tooling/validate-p1.sh` |

Full current validation pass:

```bash
bash moodle/tooling/validate-phase2.sh
bash moodle/tooling/validate-phase4.sh
bash moodle/tooling/validate-phase7.sh
bash moodle/tooling/validate-phase8.sh
bash moodle/tooling/validate-phase8b.sh
bash moodle/tooling/validate-phase9.sh
bash moodle/tooling/validate-phase10.sh
bash moodle/tooling/validate-phase11.sh
bash moodle/tooling/validate-p1.sh
```

Some validation fixtures depend on the latest generated room result. If a P1 or Phase 10 validation fails after running another configure script, rerun:

```bash
bash moodle/tooling/configure-p1.sh
bash moodle/tooling/validate-p1.sh
```

## Manual Testing Checklist

After course navigation or course setup changes:

1. Log in as admin.
2. Open the fundamentals course.
3. Confirm `Overview` has only one `Announcements` item.
4. Confirm the left course index shows `Topic 1` through `Topic 10`.
5. Expand a few topics and confirm each has `Subtopic 1` through `Subtopic 4`.
6. Confirm `Choose Weekly Topic` and room links are under `Topic Selection`.
7. Log in as a student and confirm the same course structure is visible.
8. Log in as a mentor and confirm mentor room/grading links still work.

After topic-selection changes:

1. As admin, open `Topic rounds`.
2. Confirm there is only one open poll per course.
3. Confirm topic names and group-slot capacity are correct.
4. As a student, open `Choose Weekly Topic`.
5. Confirm available group slots are visible.
6. Select a topic and confirm the partner group is assigned together.
7. Change the topic before the window closes and confirm the old slot is released.
8. Run cron or the scheduled finalization task and confirm Moodle groups are created.

After room-assignment or export changes:

1. As admin, open `Room assignments`.
2. Generate or inspect the latest room result.
3. Download the Zoom CSV and confirm the filename starts with the topic round name.
4. Confirm the Zoom CSV has `Pre-assign Room Name` and `Email Address`.
5. Download the internal room assignments workbook.
6. As a student, open `My room` and confirm only the student's own assignment is visible.
7. As a mentor, open `Mentor rooms` and confirm the assigned room is visible.

After mentor grading changes:

1. As admin, assign one official mentor to a presentation room.
2. As mentor, open `Mentor grading`.
3. Confirm the mentor sees only their assigned room.
4. Enter a per-student score from `0` to `7` and save.
5. Refresh and confirm the grade can still be edited.
6. As admin, confirm all rooms and grades are editable.

## Legacy TypeScript App

The original Next.js/Fastify app still exists for reference.

Install dependencies:

```bash
npm install
```

Run legacy dev servers:

```bash
npm run dev
```

Run legacy checks:

```bash
npm run typecheck
npm run test
npm run build
```

Do not use the legacy app as the primary product path unless the task specifically asks for it.
