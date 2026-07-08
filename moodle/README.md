# Web3 Talents Moodle Development

This folder contains the local Moodle 5.2.1 development environment for the Web3 Talents Moodle migration.

## Requirements

- Docker
- Docker Compose
- `curl`
- `tar`

## First-Time Setup

From the repository root:

```bash
cp moodle/.env.example moodle/.env
bash moodle/tooling/fetch-moodle.sh
docker compose --project-directory moodle build
bash moodle/tooling/install-moodle.sh
```

Open Moodle:

```text
http://localhost:8080
```

Open local email inbox:

```text
http://localhost:8025
```

Default local admin credentials come from `moodle/.env`.

## Daily Development

Start services:

```bash
docker compose --project-directory moodle up -d
```

Stop services:

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

Apply the Phase 2 base configuration:

```bash
bash moodle/tooling/configure-phase2.sh
```

Validate the Phase 2 base configuration:

```bash
bash moodle/tooling/validate-phase2.sh
```

Phase 2 creates local smoke-test users for role validation. Their password is read from
`WEB3T_PHASE2_TEST_PASSWORD` in `moodle/.env`, falling back to `ChangeMe123!`.

Apply the Phase 3 theme and public overview configuration:

```bash
bash moodle/tooling/configure-phase3.sh
```

Validate the Phase 3 public overview page:

```bash
bash moodle/tooling/validate-phase3.sh
```

## Source And Data

Ignored local artifacts:

- `moodle/src/`: downloaded Moodle 5.2.1 source.
- `moodle/moodledata/`: Moodle file data.
- `moodle/dbdata/`: PostgreSQL data.

Versioned development code should live in:

- `moodle/plugins/local_web3talents/`
- `moodle/blocks/web3talents/`
- `moodle/themes/web3talents/`
- `moodle/tooling/`

## Plugin And Theme Mounts

The compose stack mounts:

```text
moodle/plugins/local_web3talents -> /var/www/html/public/local/web3talents
moodle/themes/web3talents -> /var/www/html/public/theme/web3talents
```

This keeps custom code in git while keeping Moodle core source out of git.

Apply the Phase 4 local plugin scaffold:

```bash
bash moodle/tooling/configure-phase4.sh
```

Validate the Phase 4 local plugin scaffold:

```bash
bash moodle/tooling/validate-phase4.sh
```

Apply the Phase 5 accepted-applicant workflow:

```bash
bash moodle/tooling/configure-phase5.sh
```

Validate the Phase 5 accepted-applicant workflow:

```bash
bash moodle/tooling/validate-phase5.sh
```

Apply the Phase 6 first-login agreement workflow:

```bash
bash moodle/tooling/configure-phase6.sh
```

Validate the Phase 6 first-login agreement workflow:

```bash
bash moodle/tooling/validate-phase6.sh
```

Apply the Phase 7 course materials and communication setup:

```bash
bash moodle/tooling/configure-phase7.sh
```

Validate the Phase 7 course materials and communication setup:

```bash
bash moodle/tooling/validate-phase7.sh
```

Apply the Phase 8 Moodle groups and Choice source-data setup:

```bash
bash moodle/tooling/configure-phase8.sh
```

Validate the Phase 8 Moodle groups and Choice source-data setup:

```bash
bash moodle/tooling/validate-phase8.sh
```

Apply the Phase 8B weekly group-slot topic-selection setup:

```bash
bash moodle/tooling/configure-phase8b.sh
```

Validate the Phase 8B weekly group-slot topic-selection setup:

```bash
bash moodle/tooling/validate-phase8b.sh
```

Apply the Phase 9 hidden room-generation setup:

```bash
bash moodle/tooling/configure-phase9.sh
```

Validate the Phase 9 hidden room-generation setup:

```bash
bash moodle/tooling/validate-phase9.sh
```

Apply the Phase 10 Zoom CSV export setup:

```bash
bash moodle/tooling/configure-phase10.sh
```

Validate the Phase 10 Zoom CSV export:

```bash
bash moodle/tooling/validate-phase10.sh
```

Apply the Phase 11 retention cleanup and operations-readiness setup:

```bash
bash moodle/tooling/configure-phase11.sh
```

Validate the Phase 11 cleanup, permission guards, and operations plan:

```bash
bash moodle/tooling/validate-phase11.sh
```

Apply the P1 attendance, participation, and mentor availability setup:

```bash
bash moodle/tooling/configure-p1.sh
```

Validate the P1 attendance, participation, and mentor availability workflow:

```bash
bash moodle/tooling/validate-p1.sh
```
