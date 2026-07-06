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

docker compose --project-directory "${MOODLE_DIR}" exec web php /opt/web3talents/tooling/validate-phase8b.php

headers="$(curl -fsSI "${MOODLE_URL}/local/web3talents/topic_rounds.php" || true)"
if ! grep -Eq "HTTP/1.1 (303|302)" <<< "${headers}"; then
  echo "Expected logged-out topic rounds page request to redirect to login." >&2
  exit 1
fi

cookies="$(mktemp)"
loginpage="$(curl -fsS -c "${cookies}" "${MOODLE_URL}/login/index.php")"
logintoken="$(printf '%s' "${loginpage}" | sed -n 's/.*name="logintoken" value="\([^"]*\)".*/\1/p')"
curl -fsS -b "${cookies}" -c "${cookies}" \
  -d "username=admin&password=Admin123!&logintoken=${logintoken}" \
  "${MOODLE_URL}/login/index.php" >/dev/null

status="$(curl -fsS -o /tmp/web3talents-phase8b-admin.html -w "%{http_code}" -b "${cookies}" "${MOODLE_URL}/local/web3talents/topic_rounds.php")"
if [[ "${status}" != "200" ]]; then
  echo "Expected admin topic rounds page access, got HTTP ${status}." >&2
  rm -f "${cookies}" /tmp/web3talents-phase8b-admin.html
  exit 1
fi
if ! grep -q "Topic rounds" /tmp/web3talents-phase8b-admin.html; then
  echo "Expected admin topic rounds page to render." >&2
  rm -f "${cookies}" /tmp/web3talents-phase8b-admin.html
  exit 1
fi

rm -f "${cookies}" /tmp/web3talents-phase8b-admin.html

cookies="$(mktemp)"
loginpage="$(curl -fsS -c "${cookies}" "${MOODLE_URL}/login/index.php")"
logintoken="$(printf '%s' "${loginpage}" | sed -n 's/.*name="logintoken" value="\([^"]*\)".*/\1/p')"
curl -fsS -b "${cookies}" -c "${cookies}" \
  -d "username=w3t.student1&password=ChangeMe123!&logintoken=${logintoken}" \
  "${MOODLE_URL}/login/index.php" >/dev/null

status="$(curl -fsS -o /tmp/web3talents-phase8b-student.html -w "%{http_code}" -b "${cookies}" "${MOODLE_URL}/local/web3talents/choose_topic.php")"
if [[ "${status}" != "200" ]]; then
  echo "Expected student topic page access, got HTTP ${status}." >&2
  rm -f "${cookies}" /tmp/web3talents-phase8b-student.html
  exit 1
fi
if ! grep -q "Applications And Protocols" /tmp/web3talents-phase8b-student.html; then
  echo "Expected student topic page to show final assigned topic." >&2
  rm -f "${cookies}" /tmp/web3talents-phase8b-student.html
  exit 1
fi

courseid="$(
  docker compose --project-directory "${MOODLE_DIR}" exec -T web php -r \
    'define("CLI_SCRIPT", true); require("/var/www/html/config.php"); global $DB; echo $DB->get_field("course", "id", ["shortname" => "W3T-FUNDAMENTALS-DEV"], MUST_EXIST);'
)"
status="$(curl -fsS -o /tmp/web3talents-phase8b-course.html -w "%{http_code}" -b "${cookies}" "${MOODLE_URL}/course/view.php?id=${courseid}")"
if [[ "${status}" != "200" ]]; then
  echo "Expected student course page access, got HTTP ${status}." >&2
  rm -f "${cookies}" /tmp/web3talents-phase8b-student.html /tmp/web3talents-phase8b-course.html
  exit 1
fi
if ! grep -q "Choose Weekly Topic" /tmp/web3talents-phase8b-course.html; then
  echo "Expected student course page to include the Choose Weekly Topic link." >&2
  rm -f "${cookies}" /tmp/web3talents-phase8b-student.html /tmp/web3talents-phase8b-course.html
  exit 1
fi

rm -f "${cookies}" /tmp/web3talents-phase8b-student.html /tmp/web3talents-phase8b-course.html

echo "OK: topic rounds admin page is protected and renders."
echo "OK: student topic page shows final assignment."
echo "OK: student course page links to Choose Weekly Topic."
echo "Phase 8B validation complete."
