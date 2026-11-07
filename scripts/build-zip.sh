#!/usr/bin/env bash
# Builds the installable theme zip: build/scriptmedia-cemeti-<version>.zip
# The version comes from themes/scriptmedia-cemeti/style.css (the only place it is written).
#   bash scripts/build-zip.sh              run the quick checks first, then build
#   bash scripts/build-zip.sh --skip-tests build only
set -euo pipefail
cd "$(dirname "$0")/.."

theme=themes/scriptmedia-cemeti
version=$(sed -n 's/^Version:[[:space:]]*//p' "$theme/style.css" | head -1 | tr -d '\r')
[ -n "$version" ] || { echo "No Version in $theme/style.css" >&2; exit 1; }
echo "$version" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+$' || { echo "Version '$version' is not x.y.z" >&2; exit 1; }

if [ "${1:-}" != "--skip-tests" ]; then
  bash tests/run.sh --no-browser
fi

mkdir -p build
zip_path="build/scriptmedia-cemeti-$version.zip"
rm -f "$zip_path"
( cd themes && zip -rqX "../$zip_path" scriptmedia-cemeti -x '*.DS_Store' '*/.git*' )

# The zip must hold the theme and nothing that belongs to development.
list=$(unzip -Z1 "$zip_path")
for need in scriptmedia-cemeti/style.css scriptmedia-cemeti/functions.php scriptmedia-cemeti/assets/fonts/vampiro-one-latin-400-normal.woff2 scriptmedia-cemeti/assets/img/logo-480.webp; do
  printf '%s\n' "$list" | grep -qx "$need" || { echo "Missing in zip: $need" >&2; exit 1; }
done
if printf '%s\n' "$list" | grep -Eq '(^|/)(tests|docs|build|node_modules|\.git)(/|$)|\.zip$|\.env'; then
  echo "The zip contains development files" >&2; exit 1
fi
top=$(printf '%s\n' "$list" | cut -d/ -f1 | sort -u)
[ "$top" = "scriptmedia-cemeti" ] || { echo "Zip must hold a single scriptmedia-cemeti/ folder, found: $top" >&2; exit 1; }

echo "Built $zip_path ($(du -h "$zip_path" | cut -f1), $(printf '%s\n' "$list" | grep -vc '/$') files)"
if command -v sha256sum >/dev/null; then sha256sum "$zip_path"; fi
