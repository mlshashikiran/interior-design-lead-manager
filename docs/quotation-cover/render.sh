#!/bin/sh
# Renders cover.html to cover.jpg (1810 x 2560 px) with headless Chrome. Usage: sh render.sh
set -e
DIR="$(cd "$(dirname "$0")" && pwd)"
CHROME="${CHROME:-/Applications/Google Chrome.app/Contents/MacOS/Google Chrome}"
TMP="$(mktemp -d)"
"$CHROME" --headless=new --disable-gpu --hide-scrollbars --force-device-scale-factor=1 \
  --window-size=1810,2560 --virtual-time-budget=8000 --user-data-dir="$TMP/profile" \
  --screenshot="$TMP/cover.png" "file://$DIR/cover.html" >/dev/null 2>&1
sips -s format jpeg -s formatOptions 90 "$TMP/cover.png" --out "$DIR/cover.jpg" >/dev/null
rm -rf "$TMP"
echo "Wrote $DIR/cover.jpg"
