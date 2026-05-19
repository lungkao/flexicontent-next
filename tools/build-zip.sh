#!/usr/bin/env bash
#
# build-zip.sh — Build the FLEXIcontent component release zip.
#
# Reads <version> from flexicontent.xml, then writes
#   dist/com_flexicontent_<version>.zip
# containing the installable component tree, excluding dev-only paths
# (.git, tests, dist, tools, vendor, node_modules, IDE/OS dotfiles).
#
# Usage:
#   ./tools/build-zip.sh                # build current version
#   ./tools/build-zip.sh --check        # just print the resolved zip path
#   ./tools/build-zip.sh --force        # overwrite an existing zip
#
# Memory rule: release zips live in project dist/ folder; naming
# com_flexicontent_<version>.zip.

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ ! -f flexicontent.xml ]]; then
	echo "build-zip: flexicontent.xml not found at $ROOT" >&2
	exit 1
fi

VERSION="$(sed -nE 's#.*<version>([^<]+)</version>.*#\1#p' flexicontent.xml | head -n1 | tr -d '[:space:]')"

if [[ -z "$VERSION" ]]; then
	echo "build-zip: could not parse <version> from flexicontent.xml" >&2
	exit 1
fi

ZIP_NAME="com_flexicontent_${VERSION}.zip"
ZIP_PATH="dist/${ZIP_NAME}"

if [[ "${1:-}" == "--check" ]]; then
	echo "$ZIP_PATH"
	exit 0
fi

FORCE=0
if [[ "${1:-}" == "--force" ]]; then
	FORCE=1
fi

mkdir -p dist

if [[ -e "$ZIP_PATH" ]]; then
	if [[ "$FORCE" -eq 1 ]]; then
		rm -f "$ZIP_PATH"
	else
		echo "build-zip: $ZIP_PATH already exists. Re-run with --force to overwrite." >&2
		exit 2
	fi
fi

# Exclude dev / non-shippable paths. Patterns are zip-glob style; trailing
# slashes (in directory form) match recursive contents.
EXCLUDES=(
	'.git/*'
	'.git*'
	'.github/*'
	'.gitignore'
	'.gitattributes'
	'.claude/*'
	'.claude*'
	'.vscode/*'
	'.idea/*'
	'.DS_Store'
	'Thumbs.db'
	'node_modules/*'
	'vendor/*'
	'tests/*'
	'tools/*'
	'dist/*'
	'*.original.*'
	'*.log'
	'*.bak'
	'*.swp'
	'.editorconfig'
	'phpunit.xml*'
	'composer.lock'
	'package-lock.json'
	'yarn.lock'
	'pnpm-lock.yaml'
)

EXCLUDE_ARGS=()
for pattern in "${EXCLUDES[@]}"; do
	EXCLUDE_ARGS+=( -x "$pattern" )
done

echo "build-zip: building $ZIP_PATH (version $VERSION)"

zip -r -q "$ZIP_PATH" . "${EXCLUDE_ARGS[@]}"

SIZE_HUMAN="$(du -h "$ZIP_PATH" | awk '{print $1}')"
FILE_COUNT="$(unzip -Z1 "$ZIP_PATH" | wc -l | tr -d ' ')"

echo "build-zip: wrote $ZIP_PATH ($SIZE_HUMAN, $FILE_COUNT entries)"
