#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MOODLE_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

echo "Checking local tools..."
docker --version
docker compose version

echo
echo "Checking Moodle files..."
test -f "${MOODLE_DIR}/.env" || echo "Missing moodle/.env. Copy .env.example before installing."
test -f "${MOODLE_DIR}/src/public/version.php" || echo "Moodle source is missing. Run moodle/tooling/fetch-moodle.sh."

echo
echo "Checking compose configuration..."
docker compose --project-directory "${MOODLE_DIR}" config >/dev/null
echo "Docker Compose configuration is valid."
