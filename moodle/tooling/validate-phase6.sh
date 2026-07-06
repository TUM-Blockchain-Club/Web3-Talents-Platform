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

courseid="$(
  docker compose --project-directory "${MOODLE_DIR}" exec -T web php -r \
    'define("CLI_SCRIPT", true); require("/var/www/html/config.php"); global $DB; echo $DB->get_field("course", "id", ["shortname" => "W3T-FUNDAMENTALS-DEV"], MUST_EXIST);'
)"

cookies="$(mktemp)"
loginpage="$(curl -fsS -c "${cookies}" "${MOODLE_URL}/login/index.php")"
logintoken="$(printf '%s' "${loginpage}" | sed -n 's/.*name="logintoken" value="\([^"]*\)".*/\1/p')"
curl -fsS -b "${cookies}" -c "${cookies}" \
  -d "username=w3t.phase6.student&password=ChangeMe123!&logintoken=${logintoken}" \
  "${MOODLE_URL}/login/index.php" >/dev/null

headers="$(curl -fsSI -b "${cookies}" "${MOODLE_URL}/course/view.php?id=${courseid}" || true)"
rm -f "${cookies}"

if ! grep -Eq "HTTP/1.1 (303|302)" <<< "${headers}" || ! grep -q "/local/web3talents/agreement.php" <<< "${headers}"; then
  echo "Expected pending phase 6 student course request to redirect to agreement page." >&2
  exit 1
fi
echo "OK: pending phase 6 student is redirected to agreement page."

docker compose --project-directory "${MOODLE_DIR}" exec web php /opt/web3talents/tooling/validate-phase6.php

cookies="$(mktemp)"
loginpage="$(curl -fsS -c "${cookies}" "${MOODLE_URL}/login/index.php")"
logintoken="$(printf '%s' "${loginpage}" | sed -n 's/.*name="logintoken" value="\([^"]*\)".*/\1/p')"
curl -fsS -b "${cookies}" -c "${cookies}" \
  -d "username=w3t.phase6.student&password=ChangeMe123!&logintoken=${logintoken}" \
  "${MOODLE_URL}/login/index.php" >/dev/null

status="$(curl -fsS -o /tmp/web3talents-phase6-course.html -w "%{http_code}" -b "${cookies}" "${MOODLE_URL}/course/view.php?id=${courseid}")"
rm -f "${cookies}" /tmp/web3talents-phase6-course.html

if [[ "${status}" != "200" ]]; then
  echo "Expected accepted phase 6 student to access course after agreement, got HTTP ${status}." >&2
  exit 1
fi

echo "OK: accepted phase 6 student can access course."
echo "Phase 6 validation complete."
