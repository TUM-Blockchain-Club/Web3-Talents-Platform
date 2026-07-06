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
fi

docker compose --project-directory "${MOODLE_DIR}" exec web php /opt/web3talents/tooling/validate-phase5.php

headers="$(curl -fsSI "${MOODLE_URL}/local/web3talents/applicants.php" || true)"
if ! grep -Eq "HTTP/1.1 (303|302)" <<< "${headers}"; then
  echo "Expected logged-out applicants page request to redirect to login." >&2
  exit 1
fi

messages="$(curl -fsS http://localhost:8025/api/v1/messages)"
if ! grep -q "w3t.phase5.student@example.test" <<< "${messages}"; then
  echo "Expected activation email for Phase 5 student in Mailpit." >&2
  exit 1
fi

echo "OK: logged-out applicants page is protected."
echo "OK: activation email reached Mailpit."
echo "Phase 5 validation complete."
