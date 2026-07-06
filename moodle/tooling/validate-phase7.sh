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

docker compose --project-directory "${MOODLE_DIR}" exec web php /opt/web3talents/tooling/validate-phase7.php

courseid="$(
  docker compose --project-directory "${MOODLE_DIR}" exec -T web php -r \
    'define("CLI_SCRIPT", true); require("/var/www/html/config.php"); global $DB; echo $DB->get_field("course", "id", ["shortname" => "W3T-FUNDAMENTALS-DEV"], MUST_EXIST);'
)"

cookies="$(mktemp)"
loginpage="$(curl -fsS -c "${cookies}" "${MOODLE_URL}/login/index.php")"
logintoken="$(printf '%s' "${loginpage}" | sed -n 's/.*name="logintoken" value="\([^"]*\)".*/\1/p')"
curl -fsS -b "${cookies}" -c "${cookies}" \
  -d "username=w3t.student1&password=ChangeMe123!&logintoken=${logintoken}" \
  "${MOODLE_URL}/login/index.php" >/dev/null

status="$(curl -fsS -o /tmp/web3talents-phase7-course.html -w "%{http_code}" -b "${cookies}" "${MOODLE_URL}/course/view.php?id=${courseid}")"
if [[ "${status}" != "200" ]]; then
  echo "Expected student course page access, got HTTP ${status}." >&2
  rm -f "${cookies}" /tmp/web3talents-phase7-course.html
  exit 1
fi

if ! grep -q "Blockchain Foundations Starter Notes" /tmp/web3talents-phase7-course.html; then
  echo "Expected course page to include Phase 7 Moodle-hosted material links." >&2
  rm -f "${cookies}" /tmp/web3talents-phase7-course.html
  exit 1
fi

rm -f "${cookies}" /tmp/web3talents-phase7-course.html

echo "OK: student course page renders Phase 7 materials."
echo "Phase 7 validation complete."
