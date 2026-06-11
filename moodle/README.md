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

## Source And Data

Ignored local artifacts:

- `moodle/src/`: downloaded Moodle 5.2.1 source.
- `moodle/moodledata/`: Moodle file data.
- `moodle/dbdata/`: PostgreSQL data.

Versioned development code should live in:

- `moodle/plugins/local_web3talents/`
- `moodle/themes/web3talents/`
- `moodle/tooling/`

## Plugin And Theme Mounts

The compose stack mounts:

```text
moodle/plugins/local_web3talents -> /var/www/html/local/web3talents
moodle/themes/web3talents       -> /var/www/html/theme/web3talents
```

This keeps custom code in git while keeping Moodle core source out of git.
