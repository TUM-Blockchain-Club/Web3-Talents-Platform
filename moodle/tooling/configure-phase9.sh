#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MOODLE_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

if [[ ! -f "${MOODLE_DIR}/.env" ]]; then
  echo "Missing moodle/.env. Copy moodle/.env.example to moodle/.env first." >&2
  exit 1
fi

docker compose --project-directory "${MOODLE_DIR}" up -d db web mailpit
docker compose --project-directory "${MOODLE_DIR}" exec web php admin/cli/upgrade.php --non-interactive
docker compose --project-directory "${MOODLE_DIR}" exec web php /opt/web3talents/tooling/configure-phase9.php
