#!/usr/bin/env bash
# Runs every check: syntax, unit and flow tests on a simulated WordPress, static consistency
# checks and (when Playwright is installed) the browser tests.
#   bash tests/run.sh               everything available
#   bash tests/run.sh --no-browser  skip Chromium
set -u
cd "$(dirname "$0")/.."

fail=0
ok()   { printf '  \033[32mok\033[0m   %s\n' "$1"; }
bad()  { printf '  \033[31mFAIL\033[0m %s\n' "$1"; fail=1; }

echo "Syntax"
php_bad=0
while IFS= read -r f; do php -l "$f" >/dev/null 2>&1 || { bad "php -l $f"; php_bad=1; }; done < <(find themes tests -name '*.php')
[ $php_bad -eq 0 ] && ok "all PHP files parse ($(find themes tests -name '*.php' | wc -l | tr -d ' ') files)"
js_bad=0
for f in themes/scriptmedia-cemeti/assets/js/*.js tests/browser/*.js; do node --check "$f" >/dev/null 2>&1 || { bad "node --check $f"; js_bad=1; }; done
[ $js_bad -eq 0 ] && ok "all JS files parse"

echo "Tests (simulated WordPress, no database needed)"
for t in static-checks m2-logic m3-logic m4-logic m5-logic migration-logic render-pages flows-content flows-apply flows-migration flows-polylang; do
  out=$(php "tests/$t.php" 2>&1); code=$?
  last=$(printf '%s' "$out" | tail -1)
  if [ $code -eq 0 ]; then ok "$t: $last"; else bad "$t: $last"; printf '%s\n' "$out" | grep -E 'FAIL|ERROR|Fatal|thrown' | head -8 | sed 's/^/        /'; fi
done

if [ "${1:-}" != "--no-browser" ]; then
  echo "Browser (Chromium via Playwright)"
  if node -e "require('playwright')" >/dev/null 2>&1; then
    out=$(node tests/browser/browser-qa.js 2>&1); code=$?
    last=$(printf '%s' "$out" | grep 'checks passed' | tail -1)
    if [ $code -eq 0 ]; then ok "browser-qa: $last"; else bad "browser-qa: ${last:-crashed}"; printf '%s\n' "$out" | grep -E '^FAIL' | head -8 | sed 's/^/        /'; fi
  else
    echo "  skipped: Playwright is not installed (npm install, or NODE_PATH=\$(npm root -g))"
  fi
fi

echo
[ $fail -eq 0 ] && echo "All checks passed." || echo "Some checks FAILED."
exit $fail
