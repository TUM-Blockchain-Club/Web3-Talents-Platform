#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MOODLE_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

bash "${SCRIPT_DIR}/configure-phase11.sh"
docker compose --project-directory "${MOODLE_DIR}" exec web php /opt/web3talents/tooling/configure-p1.php
