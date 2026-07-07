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

docker compose --project-directory "${MOODLE_DIR}" exec web php /opt/web3talents/tooling/validate-phase10.php

cookies="$(mktemp)"
headersfile="$(mktemp)"
csvfile="$(mktemp)"
loginpage="$(curl -fsS -c "${cookies}" "${MOODLE_URL}/login/index.php")"
logintoken="$(printf '%s' "${loginpage}" | sed -n 's/.*name="logintoken" value="\([^"]*\)".*/\1/p')"
curl -fsS -b "${cookies}" -c "${cookies}" \
  -d "username=admin&password=Admin123!&logintoken=${logintoken}" \
  "${MOODLE_URL}/login/index.php" >/dev/null

page="$(curl -fsS -b "${cookies}" "${MOODLE_URL}/local/web3talents/room_assignments.php")"
if ! grep -q "Download Zoom CSV" <<< "${page}"; then
  echo "Expected room assignments page to show Download Zoom CSV." >&2
  rm -f "${cookies}" "${headersfile}" "${csvfile}"
  exit 1
fi

downloadpath="$(printf '%s' "${page}" | sed -n 's/.*href="\([^"]*action=downloadzoomcsv[^"]*\)".*/\1/p' | head -n 1)"
downloadpath="${downloadpath//&amp;/&}"
if [[ -z "${downloadpath}" ]]; then
  echo "Expected room assignments page to include a Zoom CSV download link." >&2
  rm -f "${cookies}" "${headersfile}" "${csvfile}"
  exit 1
fi

if [[ "${downloadpath}" == http* ]]; then
  downloadurl="${downloadpath}"
elif [[ "${downloadpath}" == /* ]]; then
  downloadurl="${MOODLE_URL}${downloadpath}"
else
  downloadurl="${MOODLE_URL}/${downloadpath}"
fi

status="$(curl -fsS -D "${headersfile}" -o "${csvfile}" -w "%{http_code}" -b "${cookies}" "${downloadurl}")"
if [[ "${status}" != "200" ]]; then
  echo "Expected Zoom CSV download to return HTTP 200, got ${status}." >&2
  rm -f "${cookies}" "${headersfile}" "${csvfile}"
  exit 1
fi
if ! grep -qi "content-disposition: attachment" "${headersfile}"; then
  echo "Expected Zoom CSV response to be an attachment." >&2
  rm -f "${cookies}" "${headersfile}" "${csvfile}"
  exit 1
fi
firstline="$(head -n 1 "${csvfile}" | tr -d '\r')"
if [[ "${firstline}" != "Pre-assign Room Name,Email Address" && "${firstline}" != "\"Pre-assign Room Name\",\"Email Address\"" ]]; then
  echo "Expected Zoom CSV header to match Zoom breakout pre-assignment columns." >&2
  rm -f "${cookies}" "${headersfile}" "${csvfile}"
  exit 1
fi
if ! grep -q "w3t.student1@example.test" "${csvfile}"; then
  echo "Expected Zoom CSV to use student email addresses." >&2
  rm -f "${cookies}" "${headersfile}" "${csvfile}"
  exit 1
fi
if grep -q "w3t.student1,\"" "${csvfile}"; then
  echo "Expected Zoom CSV to avoid Moodle usernames as participant identifiers." >&2
  rm -f "${cookies}" "${headersfile}" "${csvfile}"
  exit 1
fi

rm -f "${cookies}" "${headersfile}" "${csvfile}"

echo "OK: Zoom CSV download button and file response are valid."
echo "Phase 10 validation complete."
