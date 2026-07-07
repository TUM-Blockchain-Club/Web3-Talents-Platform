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
testpassword="${WEB3T_PHASE2_TEST_PASSWORD:-ChangeMe123!}"

docker compose --project-directory "${MOODLE_DIR}" exec web php /opt/web3talents/tooling/validate-phase10.php

cookies="$(mktemp)"
headersfile="$(mktemp)"
csvfile="$(mktemp)"
internalheadersfile="$(mktemp)"
internalfile="$(mktemp)"
loginpage="$(curl -fsS -c "${cookies}" "${MOODLE_URL}/login/index.php")"
logintoken="$(printf '%s' "${loginpage}" | sed -n 's/.*name="logintoken" value="\([^"]*\)".*/\1/p')"
curl -fsS -b "${cookies}" -c "${cookies}" \
  -d "username=admin&password=Admin123!&logintoken=${logintoken}" \
  "${MOODLE_URL}/login/index.php" >/dev/null

page="$(curl -fsS -b "${cookies}" "${MOODLE_URL}/local/web3talents/room_assignments.php")"
if ! grep -q "Download Zoom CSV" <<< "${page}"; then
  echo "Expected room assignments page to show Download Zoom CSV." >&2
  rm -f "${cookies}" "${headersfile}" "${csvfile}" "${internalheadersfile}" "${internalfile}"
  exit 1
fi
if ! grep -q "Download internal room assignments" <<< "${page}"; then
  echo "Expected room assignments page to show Download internal room assignments." >&2
  rm -f "${cookies}" "${headersfile}" "${csvfile}" "${internalheadersfile}" "${internalfile}"
  exit 1
fi

downloadpath="$(printf '%s' "${page}" | sed -n 's/.*href="\([^"]*action=downloadzoomcsv[^"]*\)".*/\1/p' | head -n 1)"
downloadpath="${downloadpath//&amp;/&}"
if [[ -z "${downloadpath}" ]]; then
  echo "Expected room assignments page to include a Zoom CSV download link." >&2
  rm -f "${cookies}" "${headersfile}" "${csvfile}" "${internalheadersfile}" "${internalfile}"
  exit 1
fi
internalpath="$(printf '%s' "${page}" | sed -n 's/.*href="\([^"]*action=downloadinternal[^"]*\)".*/\1/p' | head -n 1)"
internalpath="${internalpath//&amp;/&}"
if [[ -z "${internalpath}" ]]; then
  echo "Expected room assignments page to include an internal workbook download link." >&2
  rm -f "${cookies}" "${headersfile}" "${csvfile}" "${internalheadersfile}" "${internalfile}"
  exit 1
fi

if [[ "${downloadpath}" == http* ]]; then
  downloadurl="${downloadpath}"
elif [[ "${downloadpath}" == /* ]]; then
  downloadurl="${MOODLE_URL}${downloadpath}"
else
  downloadurl="${MOODLE_URL}/${downloadpath}"
fi
if [[ "${internalpath}" == http* ]]; then
  internalurl="${internalpath}"
elif [[ "${internalpath}" == /* ]]; then
  internalurl="${MOODLE_URL}${internalpath}"
else
  internalurl="${MOODLE_URL}/${internalpath}"
fi

status="$(curl -fsS -D "${headersfile}" -o "${csvfile}" -w "%{http_code}" -b "${cookies}" "${downloadurl}")"
if [[ "${status}" != "200" ]]; then
  echo "Expected Zoom CSV download to return HTTP 200, got ${status}." >&2
  rm -f "${cookies}" "${headersfile}" "${csvfile}" "${internalheadersfile}" "${internalfile}"
  exit 1
fi
if ! grep -qi "content-disposition: attachment" "${headersfile}"; then
  echo "Expected Zoom CSV response to be an attachment." >&2
  rm -f "${cookies}" "${headersfile}" "${csvfile}" "${internalheadersfile}" "${internalfile}"
  exit 1
fi
if ! grep -q "Phase 9 Room Generation Round-zoom-breakout-rooms.csv" "${headersfile}"; then
  echo "Expected Zoom CSV filename to use the topic round name." >&2
  rm -f "${cookies}" "${headersfile}" "${csvfile}" "${internalheadersfile}" "${internalfile}"
  exit 1
fi
if grep -qi "result-" "${headersfile}"; then
  echo "Expected Zoom CSV filename to avoid internal result ids." >&2
  rm -f "${cookies}" "${headersfile}" "${csvfile}" "${internalheadersfile}" "${internalfile}"
  exit 1
fi
firstline="$(head -n 1 "${csvfile}" | tr -d '\r')"
if [[ "${firstline}" != "Pre-assign Room Name,Email Address" && "${firstline}" != "\"Pre-assign Room Name\",\"Email Address\"" ]]; then
  echo "Expected Zoom CSV header to match Zoom breakout pre-assignment columns." >&2
  rm -f "${cookies}" "${headersfile}" "${csvfile}" "${internalheadersfile}" "${internalfile}"
  exit 1
fi
if ! grep -q "w3t.student1@example.test" "${csvfile}"; then
  echo "Expected Zoom CSV to use student email addresses." >&2
  rm -f "${cookies}" "${headersfile}" "${csvfile}" "${internalheadersfile}" "${internalfile}"
  exit 1
fi
if grep -q "w3t.student1,\"" "${csvfile}"; then
  echo "Expected Zoom CSV to avoid Moodle usernames as participant identifiers." >&2
  rm -f "${cookies}" "${headersfile}" "${csvfile}" "${internalheadersfile}" "${internalfile}"
  exit 1
fi

status="$(curl -fsS -D "${internalheadersfile}" -o "${internalfile}" -w "%{http_code}" -b "${cookies}" "${internalurl}")"
if [[ "${status}" != "200" ]]; then
  echo "Expected internal workbook download to return HTTP 200, got ${status}." >&2
  rm -f "${cookies}" "${headersfile}" "${csvfile}" "${internalheadersfile}" "${internalfile}"
  exit 1
fi
if ! grep -qi "content-disposition: attachment" "${internalheadersfile}"; then
  echo "Expected internal workbook response to be an attachment." >&2
  rm -f "${cookies}" "${headersfile}" "${csvfile}" "${internalheadersfile}" "${internalfile}"
  exit 1
fi
if ! grep -q "Phase 9 Room Generation Round-internal-room-assignments.xlsx" "${internalheadersfile}"; then
  echo "Expected internal workbook filename to use the topic round name." >&2
  rm -f "${cookies}" "${headersfile}" "${csvfile}" "${internalheadersfile}" "${internalfile}"
  exit 1
fi
if [[ "$(head -c 2 "${internalfile}")" != "PK" ]]; then
  echo "Expected internal room assignments download to be an XLSX file." >&2
  rm -f "${cookies}" "${headersfile}" "${csvfile}" "${internalheadersfile}" "${internalfile}"
  exit 1
fi

studentcookies="$(mktemp)"
studentpage="$(curl -fsS -c "${studentcookies}" "${MOODLE_URL}/login/index.php")"
studenttoken="$(printf '%s' "${studentpage}" | sed -n 's/.*name="logintoken" value="\([^"]*\)".*/\1/p')"
curl -fsS -b "${studentcookies}" -c "${studentcookies}" \
  -d "username=w3t.student1&password=${testpassword}&logintoken=${studenttoken}" \
  "${MOODLE_URL}/login/index.php" >/dev/null

studentroom="$(curl -fsS -b "${studentcookies}" "${MOODLE_URL}/local/web3talents/my_room.php")"
if ! grep -q "My room assignment" <<< "${studentroom}"; then
  echo "Expected student room page to render." >&2
  rm -f "${cookies}" "${headersfile}" "${csvfile}" "${internalheadersfile}" "${internalfile}" "${studentcookies}"
  exit 1
fi
if ! grep -q "Phase 9 Alpha" <<< "${studentroom}"; then
  echo "Expected student room page to show the student's partner group." >&2
  rm -f "${cookies}" "${headersfile}" "${csvfile}" "${internalheadersfile}" "${internalfile}" "${studentcookies}"
  exit 1
fi
if grep -q "Phase 9 Beta Trio" <<< "${studentroom}"; then
  echo "Expected student room page to hide other partner groups." >&2
  rm -f "${cookies}" "${headersfile}" "${csvfile}" "${internalheadersfile}" "${internalfile}" "${studentcookies}"
  exit 1
fi
if grep -q "Download Zoom CSV" <<< "${studentroom}"; then
  echo "Expected student room page not to expose Zoom export." >&2
  rm -f "${cookies}" "${headersfile}" "${csvfile}" "${internalheadersfile}" "${internalfile}" "${studentcookies}"
  exit 1
fi

courseid="$(
  docker compose --project-directory "${MOODLE_DIR}" exec -T web php -r \
    'define("CLI_SCRIPT", true); require("/var/www/html/config.php"); global $DB; echo $DB->get_field("course", "id", ["shortname" => "W3T-FUNDAMENTALS-DEV"], MUST_EXIST);'
)"
coursepage="$(curl -fsS -b "${studentcookies}" "${MOODLE_URL}/course/view.php?id=${courseid}")"
if ! grep -q "My room assignment" <<< "${coursepage}"; then
  echo "Expected student course page to link to My room assignment." >&2
  rm -f "${cookies}" "${headersfile}" "${csvfile}" "${internalheadersfile}" "${internalfile}" "${studentcookies}"
  exit 1
fi

mentorcookies="$(mktemp)"
mentorpage="$(curl -fsS -c "${mentorcookies}" "${MOODLE_URL}/login/index.php")"
mentortoken="$(printf '%s' "${mentorpage}" | sed -n 's/.*name="logintoken" value="\([^"]*\)".*/\1/p')"
curl -fsS -b "${mentorcookies}" -c "${mentorcookies}" \
  -d "username=w3t.mentor1&password=${testpassword}&logintoken=${mentortoken}" \
  "${MOODLE_URL}/login/index.php" >/dev/null

mentorrooms="$(curl -fsS -b "${mentorcookies}" "${MOODLE_URL}/local/web3talents/mentor_rooms.php")"
if ! grep -q "Room assignments overview" <<< "${mentorrooms}"; then
  echo "Expected mentor room overview page to render." >&2
  rm -f "${cookies}" "${headersfile}" "${csvfile}" "${internalheadersfile}" "${internalfile}" "${studentcookies}" "${mentorcookies}"
  exit 1
fi
if ! grep -q "Phase 9 Beta Trio" <<< "${mentorrooms}"; then
  echo "Expected mentor room overview to show all partner groups." >&2
  rm -f "${cookies}" "${headersfile}" "${csvfile}" "${internalheadersfile}" "${internalfile}" "${studentcookies}" "${mentorcookies}"
  exit 1
fi
if grep -q "Download Zoom CSV" <<< "${mentorrooms}" || grep -q "Move to room" <<< "${mentorrooms}"; then
  echo "Expected mentor room overview to be read-only and without exports." >&2
  rm -f "${cookies}" "${headersfile}" "${csvfile}" "${internalheadersfile}" "${internalfile}" "${studentcookies}" "${mentorcookies}"
  exit 1
fi

rm -f "${cookies}" "${headersfile}" "${csvfile}" "${internalheadersfile}" "${internalfile}" "${studentcookies}" "${mentorcookies}"

echo "OK: Room export download buttons and file responses are valid."
echo "OK: Student and mentor room visibility pages are valid."
echo "Phase 10 validation complete."
