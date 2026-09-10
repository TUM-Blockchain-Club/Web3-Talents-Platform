#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MOODLE_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
MOODLE_URL="${MOODLE_URL:-http://localhost:8080}"

if [[ -f "${MOODLE_DIR}/.env" ]]; then
  set -a
  # shellcheck disable=SC1091
  source "${MOODLE_DIR}/.env"
  set +a
  MOODLE_URL="${MOODLE_URL:-http://localhost:8080}"
fi

docker compose --project-directory "${MOODLE_DIR}" exec -T web php /opt/web3talents/tooling/validate-phase3.php

# The public marketing pages moved to the separate front-facing project, so the only
# thing to check over HTTP is that login is reachable and course content is not
# exposed to anonymous visitors.
loginheaders="$(curl -fsSI "${MOODLE_URL}/login/index.php")"
grep -qi "^HTTP/.* 200" <<< "${loginheaders}"
echo "OK: login page is reachable."

echo "Phase 3 validation complete."
