#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MOODLE_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

if [ -f "${MOODLE_DIR}/.env" ]; then
  # shellcheck disable=SC1091
  source "${MOODLE_DIR}/.env"
fi

MOODLE_VERSION="${MOODLE_VERSION:-5.2.1}"
SRC_DIR="${MOODLE_DIR}/src"
ARCHIVE="${MOODLE_DIR}/moodle-${MOODLE_VERSION}.tgz"
DOWNLOAD_URL="https://download.moodle.org/download.php/direct/stable502/moodle-${MOODLE_VERSION}.tgz"

if [ -f "${SRC_DIR}/version.php" ]; then
  echo "Moodle source already exists at ${SRC_DIR}"
  exit 0
fi

mkdir -p "${SRC_DIR}"

if [ -f "${ARCHIVE}" ] && ! tar -tzf "${ARCHIVE}" >/dev/null 2>&1; then
  echo "Existing archive is invalid; downloading a fresh copy."
  rm -f "${ARCHIVE}"
fi

if [ ! -f "${ARCHIVE}" ]; then
  echo "Downloading Moodle ${MOODLE_VERSION}..."
  curl -fL "${DOWNLOAD_URL}" -o "${ARCHIVE}"
fi

echo "Extracting Moodle ${MOODLE_VERSION}..."
tar -xzf "${ARCHIVE}" -C "${SRC_DIR}" --strip-components=1

echo "Moodle ${MOODLE_VERSION} source is ready at ${SRC_DIR}"
