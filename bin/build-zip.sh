#!/usr/bin/env bash
#
# Build a distributable plugin ZIP containing only runtime files.
# Everything listed in .distignore is left out.
#
# Usage: bin/build-zip.sh [output-dir]

set -euo pipefail

SLUG="external-image-importer"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="${1:-$ROOT/build}"
STAGE="$OUT_DIR/$SLUG"

for tool in tar zip; do
	command -v "$tool" >/dev/null || { echo "$tool is required" >&2; exit 1; }
done

rm -rf "$STAGE" "$OUT_DIR/$SLUG.zip"
mkdir -p "$STAGE"

# Turn .distignore into tar --exclude arguments, anchored at the repo root.
EXCLUDES=(--exclude='./.git')
while IFS= read -r line || [ -n "$line" ]; do
	line="${line%%$'\r'}"
	[ -z "$line" ] && continue
	case "$line" in \#*) continue ;; esac
	EXCLUDES+=("--exclude=./${line#./}")
done < "$ROOT/.distignore"

( cd "$ROOT" && tar -cf - "${EXCLUDES[@]}" . ) | ( cd "$STAGE" && tar -xf - )

( cd "$OUT_DIR" && zip -qr "$SLUG.zip" "$SLUG" )
rm -rf "$STAGE"

echo "Built $OUT_DIR/$SLUG.zip"
