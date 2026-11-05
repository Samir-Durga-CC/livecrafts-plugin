#!/usr/bin/env bash
# The plugin's offline tests (no database, no site). All of them run, even after one fails; a failure's last lines
# become a GitHub annotation, so it shows on the run page itself.
#   bash tools/ci/offline-tests.sh <path to a WordPress download>   (only the block test reads it)
set -uo pipefail
cd "$(dirname "$0")/../.."
wp="${1:-}"
fails=0
for t in acf-rows elementor file-changes lcs-src blocks; do
  args=(); [ "$t" = blocks ] && args=("$wp")
  echo "::group::$t"
  out=$(php "tests/$t-offline.php" "${args[@]}" 2>&1); code=$?
  printf '%s\n' "$out"
  echo "::endgroup::"
  if [ $code -ne 0 ]; then
    fails=$((fails + 1))
    echo "::error title=$t-offline.php failed (exit $code)::$(printf '%s' "$out" | tail -6 | tr '\n' ' ' | cut -c1-900)"
  fi
done
[ "$fails" -eq 0 ] && echo "Offline tests: all passed" || { echo "Offline tests: $fails failed"; exit 1; }
