#!/usr/bin/env bash
# =============================================================================
# build.sh — Build a distributable zip for Lax Abilities Toolkit
#
# Usage:
#   ./bin/build.sh              # builds dist/lax-abilities-toolkit-{version}.zip
#   ./bin/build.sh --clean      # removes the dist/ directory first
# =============================================================================

set -euo pipefail

PLUGIN_SLUG="lax-abilities-toolkit"
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST_DIR="${PLUGIN_DIR}/dist"

# Read version from the main plugin file.
VERSION=$(grep -m1 "^.* Version:" "${PLUGIN_DIR}/${PLUGIN_SLUG}.php" | awk '{ print $NF }')

if [[ -z "${VERSION}" ]]; then
  echo "Error: could not read version from ${PLUGIN_SLUG}.php" >&2
  exit 1
fi

# Optional --clean flag.
if [[ "${1:-}" == "--clean" ]]; then
  echo "Removing dist/ ..."
  rm -rf "${DIST_DIR}"
fi

mkdir -p "${DIST_DIR}"

# Build the React admin UI.
echo "Building admin UI ..."
cd "${PLUGIN_DIR}"
npm ci --silent
npm run build --silent
cd "${PLUGIN_DIR}/.."

ZIP_FILE="${DIST_DIR}/${PLUGIN_SLUG}-${VERSION}.zip"

echo "Building ${ZIP_FILE} ..."

# Build zip, excluding development files.
cd "${PLUGIN_DIR}/.."
zip -r "${ZIP_FILE}" "${PLUGIN_SLUG}/" \
  --exclude "*/.git/*" \
  --exclude "*/.github/*" \
  --exclude "*/.wordpress-org/*" \
  --exclude "*/.gitignore" \
  --exclude "*/bin/*" \
  --exclude "*/.distignore" \
  --exclude "*/node_modules/*" \
  --exclude "*/.DS_Store" \
  --exclude "*/*.sh" \
  --exclude "*/CHANGELOG.md" \
  --exclude "*/.editorconfig" \
  --exclude "*/.phpcs.xml" \
  --exclude "*/phpunit.xml*" \
  --exclude "*/tests/*" \
  --exclude "*/src/*" \
  --exclude "*/package.json" \
  --exclude "*/package-lock.json"

echo "Done: ${ZIP_FILE}"
echo "Size: $(du -h "${ZIP_FILE}" | cut -f1)"
