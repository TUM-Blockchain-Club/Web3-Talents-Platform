#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MOODLE_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

if [ ! -f "${MOODLE_DIR}/.env" ]; then
  echo "Missing moodle/.env. Copy moodle/.env.example to moodle/.env first."
  exit 1
fi

# shellcheck disable=SC1091
source "${MOODLE_DIR}/.env"

docker compose --project-directory "${MOODLE_DIR}" up -d db web mailpit

docker compose --project-directory "${MOODLE_DIR}" exec web php admin/cli/install.php \
  --chmod=2777 \
  --lang=en \
  --wwwroot="${MOODLE_URL:-http://localhost:8080}" \
  --dataroot=/var/moodledata \
  --dbtype=pgsql \
  --dbhost=db \
  --dbname="${POSTGRES_DB:-moodle}" \
  --dbuser="${POSTGRES_USER:-moodle}" \
  --dbpass="${POSTGRES_PASSWORD:-moodle}" \
  --fullname="${MOODLE_SITE_FULLNAME:-Web3 Talents Moodle}" \
  --shortname="${MOODLE_SITE_SHORTNAME:-Web3 Talents}" \
  --adminuser="${MOODLE_ADMIN_USER:-admin}" \
  --adminpass="${MOODLE_ADMIN_PASSWORD:-Admin123!}" \
  --adminemail="${MOODLE_ADMIN_EMAIL:-admin@example.test}" \
  --non-interactive \
  --agree-license

docker compose --project-directory "${MOODLE_DIR}" exec web php admin/cli/cfg.php --name=debug --set=32767
docker compose --project-directory "${MOODLE_DIR}" exec web php admin/cli/cfg.php --name=debugdisplay --set=1
docker compose --project-directory "${MOODLE_DIR}" exec web php admin/cli/cfg.php --name=smtphosts --set=mailpit:1025

echo "Moodle installed at ${MOODLE_URL:-http://localhost:8080}"
echo "Mailpit is available at http://localhost:8025"
