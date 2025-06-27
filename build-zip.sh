#!/usr/bin/env bash
set -euo pipefail

# Usage: ./build-zip.sh [plugin-slug]
# If you omit [plugin-slug], it will use the name of the current directory.

# 1) Determine plugin slug & target ZIP name
PLUGIN_SLUG="${1:-$(basename "$(pwd)")}"
ZIP_FILE="${PLUGIN_SLUG}.zip"

# 2) Compile .po → .mo
if [ -d "languages" ]; then
  echo "Compiling translation files…"
  for PO in languages/*.po; do
    [ -f "$PO" ] || continue
    MO="${PO%.po}.mo"
    msgfmt "$PO" -o "$MO"
    echo "  → $PO → $MO"
  done
else
  echo "Warning: no 'languages/' directory found; skipping .mo generation."
fi

# 3) Remove any existing ZIP
if [ -f "$ZIP_FILE" ]; then
  echo "Removing old $ZIP_FILE"
  rm -f "$ZIP_FILE"
fi

# 4) Build the ZIP
echo "Creating $ZIP_FILE …"
# 4a) add root PHP files
zip -q "$ZIP_FILE" ./*.php

# 4b) add readme
[ -f "readme.txt" ] && zip -q "$ZIP_FILE" "readme.txt"

# 4c) add languages (pot, po, mo)
[ -d "languages" ] && zip -qr "$ZIP_FILE" "languages"

# 4d) add assets
[ -d "assets" ] && zip -qr "$ZIP_FILE" "assets"

echo "✅ $ZIP_FILE created successfully."