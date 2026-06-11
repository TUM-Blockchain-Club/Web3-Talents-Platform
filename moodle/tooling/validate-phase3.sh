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

docker compose --project-directory "${MOODLE_DIR}" exec web php /opt/web3talents/tooling/validate-phase3.php
courseid="$(
  docker compose --project-directory "${MOODLE_DIR}" exec -T web php -r \
    'define("CLI_SCRIPT", true); require("/var/www/html/config.php"); global $DB; echo $DB->get_field("course", "id", ["shortname" => "W3T-FUNDAMENTALS-DEV"], MUST_EXIST);'
)"

overview="$(curl -fsS "${MOODLE_URL}/theme/web3talents/overview.php")"
loginheaders="$(curl -fsSI "${MOODLE_URL}/login/index.php")"
courseheaders="$(curl -fsSI "${MOODLE_URL}/course/view.php?id=${courseid}" || true)"

grep -q "Web3 Talents Fundamentals" <<< "${overview}"
grep -q "Fundamentals Cohort" <<< "${overview}"
grep -q "Student login" <<< "${overview}"
grep -qi "Content-Type: text/html" <<< "${loginheaders}"

if grep -qi "HTTP/1.1 200" <<< "${courseheaders}"; then
  coursebody="$(curl -fsS "${MOODLE_URL}/course/view.php?id=${courseid}")"
  if grep -q "Course Forum" <<< "${coursebody}"; then
    echo "Private course content is visible while logged out." >&2
    exit 1
  fi
fi

echo "OK: public overview is reachable while logged out."
echo "OK: login page is reachable."
echo "OK: private course content is not exposed to logged-out users."
echo "Phase 3 validation complete."
