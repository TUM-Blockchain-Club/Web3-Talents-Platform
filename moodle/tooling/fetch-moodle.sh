#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MOODLE_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

if [ -f "${MOODLE_DIR}/.env" ]; then
  # shellcheck disable=SC1091
  source "${MOODLE_DIR}/.env"
fi

MOODLE_VERSION="${MOODLE_VERSION:-5.2.5}"
SRC_DIR="${MOODLE_DIR}/src"
ARCHIVE="${MOODLE_DIR}/moodle-${MOODLE_VERSION}.tgz"
DOWNLOAD_URL="https://download.moodle.org/download.php/direct/stable502/moodle-${MOODLE_VERSION}.tgz"

if [ -f "${SRC_DIR}/version.php" ] || [ -f "${SRC_DIR}/public/version.php" ]; then
  echo "Moodle source already exists at ${SRC_DIR}"
  exit 0
fi

# Pin official release bytes, not only a version-shaped download URL.
if [ "${MOODLE_VERSION}" = "5.2.5" ]; then
  MOODLE_SHA256="${MOODLE_SHA256:-a0b2bf3013a8ef149db17c508f647d18b2a7f4b2c34056010f75081c87b4bb66}"
fi
if [[ ! "${MOODLE_SHA256:-}" =~ ^[a-fA-F0-9]{64}$ ]]; then
  echo "Set MOODLE_SHA256 to the official checksum for Moodle ${MOODLE_VERSION}."
  exit 1
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

printf '%s  %s\n' "${MOODLE_SHA256}" "${ARCHIVE}" | shasum -a 256 -c -

echo "Extracting Moodle ${MOODLE_VERSION}..."
tar -xzf "${ARCHIVE}" -C "${SRC_DIR}" --strip-components=1

echo "Moodle ${MOODLE_VERSION} source is ready at ${SRC_DIR}"
