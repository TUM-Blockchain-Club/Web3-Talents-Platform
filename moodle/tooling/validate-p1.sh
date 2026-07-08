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

docker compose --project-directory "${MOODLE_DIR}" exec web php /opt/web3talents/tooling/validate-p1.php

admincookies="$(mktemp)"
adminlogin="$(curl -fsS -c "${admincookies}" "${MOODLE_URL}/login/index.php")"
admintoken="$(printf '%s' "${adminlogin}" | sed -n 's/.*name="logintoken" value="\([^"]*\)".*/\1/p')"
curl -fsS -b "${admincookies}" -c "${admincookies}" \
  -d "username=admin&password=Admin123!&logintoken=${admintoken}" \
  "${MOODLE_URL}/login/index.php" >/dev/null

adminparticipation="$(curl -fsS -b "${admincookies}" "${MOODLE_URL}/local/web3talents/participation.php")"
for expected in "Attendance and participation" "P1 Attendance Fixture Session" "Save attendance"; do
  if ! grep -q "${expected}" <<< "${adminparticipation}"; then
    echo "Expected admin participation page to show ${expected}." >&2
    rm -f "${admincookies}"
    exit 1
  fi
done

admindashboard="$(curl -fsS -b "${admincookies}" "${MOODLE_URL}/my/")"
if ! grep -q "Attendance and participation" <<< "${admindashboard}" || ! grep -q "Mentor availability" <<< "${admindashboard}"; then
  echo "Expected Moodle dashboard block to link to P1 participation features." >&2
  rm -f "${admincookies}"
  exit 1
fi

mentorcookies="$(mktemp)"
mentorlogin="$(curl -fsS -c "${mentorcookies}" "${MOODLE_URL}/login/index.php")"
mentortoken="$(printf '%s' "${mentorlogin}" | sed -n 's/.*name="logintoken" value="\([^"]*\)".*/\1/p')"
curl -fsS -b "${mentorcookies}" -c "${mentorcookies}" \
  -d "username=w3t.mentor1&password=${testpassword}&logintoken=${mentortoken}" \
  "${MOODLE_URL}/login/index.php" >/dev/null

mentoravailability="$(curl -fsS -b "${mentorcookies}" "${MOODLE_URL}/local/web3talents/mentor_availability.php")"
if ! grep -q "Mentor availability" <<< "${mentoravailability}" || ! grep -q "P1 Attendance Fixture Session" <<< "${mentoravailability}"; then
  echo "Expected mentor availability page to render for mentor." >&2
  rm -f "${admincookies}" "${mentorcookies}"
  exit 1
fi

studentcookies="$(mktemp)"
studentlogin="$(curl -fsS -c "${studentcookies}" "${MOODLE_URL}/login/index.php")"
studenttoken="$(printf '%s' "${studentlogin}" | sed -n 's/.*name="logintoken" value="\([^"]*\)".*/\1/p')"
curl -fsS -b "${studentcookies}" -c "${studentcookies}" \
  -d "username=w3t.student1&password=${testpassword}&logintoken=${studenttoken}" \
  "${MOODLE_URL}/login/index.php" >/dev/null

studentparticipation="$(curl -sS -b "${studentcookies}" "${MOODLE_URL}/local/web3talents/participation.php" || true)"
if grep -q "Save attendance" <<< "${studentparticipation}"; then
  echo "Expected student not to access participation management." >&2
  rm -f "${admincookies}" "${mentorcookies}" "${studentcookies}"
  exit 1
fi

rm -f "${admincookies}" "${mentorcookies}" "${studentcookies}"

echo "OK: P1 participation and mentor availability pages render with expected permissions."
echo "P1 validation complete."
