#!/usr/bin/env bash
# Build a clean WordPress plugin ZIP (top-level folder matches plugin slug).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

VERSION="$(grep -E "define\( 'XDWP_VERSION'" xorro-direct-wallet-payments-woocommerce.php | sed -E "s/.*'([0-9.]+)'.*/\1/")"
if [[ -z "${VERSION}" ]]; then
	echo "Could not read XDWP_VERSION" >&2
	exit 1
fi

# wordpress.org plugin slug / zip folder name
PLUGIN_SLUG="${PLUGIN_SLUG:-xorro-direct-wallet-payments-woocommerce}"

OUT_DIR="${1:-$(dirname "$ROOT")}"
STAGE="${TMPDIR:-/tmp}/xdwp-dist-$$"
ZIP_PATH="${OUT_DIR}/${PLUGIN_SLUG}.zip"
ZIP_VERSIONED="${OUT_DIR}/${PLUGIN_SLUG}-${VERSION}.zip"

rm -rf "${STAGE}"
mkdir -p "${STAGE}/${PLUGIN_SLUG}"

# Prefer rsync + .distignore-style excludes (keep runtime plugin files only).
rsync -a \
	--exclude='.git/' \
	--exclude='.github/' \
	--exclude='.gitignore' \
	--exclude='.gitattributes' \
	--exclude='.DS_Store' \
	--exclude='**/.DS_Store' \
	--exclude='tests/' \
	--exclude='bin/' \
	--exclude='releases/' \
	--exclude='.phpunit*' \
	--exclude='.phpcs*' \
	--exclude='composer.json' \
	--exclude='composer.lock' \
	--exclude='phpunit.xml' \
	--exclude='phpunit.xml.dist' \
	--exclude='node_modules/' \
	--exclude='package.json' \
	--exclude='package-lock.json' \
	--exclude='.editorconfig' \
	--exclude='.eslintrc*' \
	--exclude='.prettierrc*' \
	--exclude='.cursor/' \
	--exclude='.distignore' \
	--exclude='*.zip' \
	./ "${STAGE}/${PLUGIN_SLUG}/"

test -f "${STAGE}/${PLUGIN_SLUG}/xorro-direct-wallet-payments-woocommerce.php"
test -f "${STAGE}/${PLUGIN_SLUG}/readme.txt"
test ! -d "${STAGE}/${PLUGIN_SLUG}/tests"
test ! -d "${STAGE}/${PLUGIN_SLUG}/.git"

rm -f "${ZIP_PATH}" "${ZIP_VERSIONED}"
( cd "${STAGE}" && zip -r -q "${ZIP_PATH}" "${PLUGIN_SLUG}" )
cp -f "${ZIP_PATH}" "${ZIP_VERSIONED}"
shasum -a 256 "${ZIP_PATH}" > "${ZIP_VERSIONED}.sha256"

rm -rf "${STAGE}"

echo "Built WordPress ZIP v${VERSION} (folder: ${PLUGIN_SLUG}/)"
echo "  ${ZIP_PATH}"
echo "  ${ZIP_VERSIONED}"
echo "  ${ZIP_VERSIONED}.sha256"
