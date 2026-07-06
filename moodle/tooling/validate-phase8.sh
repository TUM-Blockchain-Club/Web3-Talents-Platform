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

docker compose --project-directory "${MOODLE_DIR}" exec web php /opt/web3talents/tooling/validate-phase8.php

headers="$(curl -fsSI "${MOODLE_URL}/local/web3talents/course_state.php" || true)"
if ! grep -Eq "HTTP/1.1 (303|302)" <<< "${headers}"; then
  echo "Expected logged-out course state page request to redirect to login." >&2
  exit 1
fi

cookies="$(mktemp)"
loginpage="$(curl -fsS -c "${cookies}" "${MOODLE_URL}/login/index.php")"
logintoken="$(printf '%s' "${loginpage}" | sed -n 's/.*name="logintoken" value="\([^"]*\)".*/\1/p')"
curl -fsS -b "${cookies}" -c "${cookies}" \
  -d "username=admin&password=Admin123!&logintoken=${logintoken}" \
  "${MOODLE_URL}/login/index.php" >/dev/null

status="$(curl -fsS -o /tmp/web3talents-phase8-course-state.html -w "%{http_code}" -b "${cookies}" "${MOODLE_URL}/local/web3talents/course_state.php")"
if [[ "${status}" != "200" ]]; then
  echo "Expected admin course state page access, got HTTP ${status}." >&2
  rm -f "${cookies}" /tmp/web3talents-phase8-course-state.html
  exit 1
fi

if ! grep -q "W3T Partner Group Alpha" /tmp/web3talents-phase8-course-state.html; then
  echo "Expected course state page to include partner group warnings." >&2
  rm -f "${cookies}" /tmp/web3talents-phase8-course-state.html
  exit 1
fi

if ! grep -q "missing_group" /tmp/web3talents-phase8-course-state.html; then
  echo "Expected course state page to include missing group warning." >&2
  rm -f "${cookies}" /tmp/web3talents-phase8-course-state.html
  exit 1
fi

rm -f "${cookies}" /tmp/web3talents-phase8-course-state.html

echo "OK: logged-out course state page is protected."
echo "OK: admin course state page renders Phase 8 warnings."
echo "Phase 8 validation complete."
