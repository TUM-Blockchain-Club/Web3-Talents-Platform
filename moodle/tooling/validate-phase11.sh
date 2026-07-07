#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MOODLE_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
REPO_DIR="$(cd "${MOODLE_DIR}/.." && pwd)"
MOODLE_URL="${MOODLE_URL:-http://localhost:8080}"

if [[ -f "${MOODLE_DIR}/.env" ]]; then
  set -a
  # shellcheck disable=SC1091
  source "${MOODLE_DIR}/.env"
  set +a
fi
testpassword="${WEB3T_PHASE2_TEST_PASSWORD:-ChangeMe123!}"

docker compose --project-directory "${MOODLE_DIR}" exec web php /opt/web3talents/tooling/validate-phase11.php

admincookies="$(mktemp)"
adminlogin="$(curl -fsS -c "${admincookies}" "${MOODLE_URL}/login/index.php")"
admintoken="$(printf '%s' "${adminlogin}" | sed -n 's/.*name="logintoken" value="\([^"]*\)".*/\1/p')"
curl -fsS -b "${admincookies}" -c "${admincookies}" \
  -d "username=admin&password=Admin123!&logintoken=${admintoken}" \
  "${MOODLE_URL}/login/index.php" >/dev/null

admindashboard="$(curl -fsS -b "${admincookies}" "${MOODLE_URL}/local/web3talents/index.php")"
for expected in \
  "Web3 Talents workflows" \
  "Course administration" \
  "Participants and mentors" \
  "Scheduled tasks"; do
  if ! grep -q "${expected}" <<< "${admindashboard}"; then
    echo "Expected Web3 Talents dashboard to show ${expected}." >&2
    rm -f "${admincookies}"
    exit 1
  fi
done

opsdoc="${REPO_DIR}/docs/moodle-migration-prd/phase-11-operations-plan.md"
for expected in \
  "daily snapshots for 7 days" \
  "weekly snapshots for 4 weeks" \
  "monthly snapshots for 6 months" \
  "restore test"; do
  if ! grep -qi "${expected}" "${opsdoc}"; then
    echo "Expected Phase 11 operations plan to document ${expected}." >&2
    exit 1
  fi
done

courseid="$(
  docker compose --project-directory "${MOODLE_DIR}" exec -T web php -r \
    'define("CLI_SCRIPT", true); require("/var/www/html/config.php"); global $DB; echo $DB->get_field("course", "id", ["shortname" => "W3T-FUNDAMENTALS-DEV"], MUST_EXIST);'
)"

publicheaders="$(curl -fsSI "${MOODLE_URL}/course/view.php?id=${courseid}" || true)"
if ! grep -Eq "HTTP/[0-9.]+ (303|302)" <<< "${publicheaders}"; then
  echo "Expected logged-out course request to redirect to login." >&2
  exit 1
fi

studentcookies="$(mktemp)"
studentlogin="$(curl -fsS -c "${studentcookies}" "${MOODLE_URL}/login/index.php")"
studenttoken="$(printf '%s' "${studentlogin}" | sed -n 's/.*name="logintoken" value="\([^"]*\)".*/\1/p')"
curl -fsS -b "${studentcookies}" -c "${studentcookies}" \
  -d "username=w3t.student1&password=${testpassword}&logintoken=${studenttoken}" \
  "${MOODLE_URL}/login/index.php" >/dev/null

studentadminpage="$(curl -sS -b "${studentcookies}" "${MOODLE_URL}/local/web3talents/room_assignments.php" || true)"
if grep -q "Download Zoom CSV" <<< "${studentadminpage}" || grep -q "Move to room" <<< "${studentadminpage}"; then
  echo "Expected student not to access admin room management." >&2
  rm -f "${studentcookies}"
  exit 1
fi

studentapplicantpage="$(curl -sS -b "${studentcookies}" "${MOODLE_URL}/local/web3talents/applicants.php" || true)"
if grep -q "Create account" <<< "${studentapplicantpage}" || grep -q "Import applicants" <<< "${studentapplicantpage}"; then
  echo "Expected student not to manage accepted applicants." >&2
  rm -f "${studentcookies}"
  exit 1
fi

mentorcookies="$(mktemp)"
mentorlogin="$(curl -fsS -c "${mentorcookies}" "${MOODLE_URL}/login/index.php")"
mentortoken="$(printf '%s' "${mentorlogin}" | sed -n 's/.*name="logintoken" value="\([^"]*\)".*/\1/p')"
curl -fsS -b "${mentorcookies}" -c "${mentorcookies}" \
  -d "username=w3t.mentor1&password=${testpassword}&logintoken=${mentortoken}" \
  "${MOODLE_URL}/login/index.php" >/dev/null

mentoradminpage="$(curl -sS -b "${mentorcookies}" "${MOODLE_URL}/local/web3talents/room_assignments.php" || true)"
if grep -q "Download Zoom CSV" <<< "${mentoradminpage}" || grep -q "Move to room" <<< "${mentoradminpage}"; then
  echo "Expected mentor not to edit/download room assignments." >&2
  rm -f "${studentcookies}" "${mentorcookies}"
  exit 1
fi

rm -f "${studentcookies}" "${mentorcookies}"
rm -f "${admincookies}"

echo "OK: Phase 11 operations plan documents OCI, backup, restore, and retention policy."
echo "OK: Web3 Talents admin dashboard exposes Moodle course shortcuts."
echo "OK: Public, student, and mentor permission guards are valid."
echo "Phase 11 validation complete."
