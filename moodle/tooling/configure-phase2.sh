#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MOODLE_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

if [[ ! -f "${MOODLE_DIR}/.env" ]]; then
  echo "Missing moodle/.env. Copy moodle/.env.example to moodle/.env first." >&2
  exit 1
fi

set -a
# shellcheck disable=SC1091
source "${MOODLE_DIR}/.env"
set +a

docker compose --project-directory "${MOODLE_DIR}" up -d db web mailpit
docker compose --project-directory "${MOODLE_DIR}" exec \
  -e WEB3T_PHASE2_TEST_PASSWORD="${WEB3T_PHASE2_TEST_PASSWORD:-ChangeMe123!}" \
  web php /opt/web3talents/tooling/configure-phase2.php
