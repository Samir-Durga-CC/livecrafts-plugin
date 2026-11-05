#!/usr/bin/env bash
# PHP syntax of every plugin file with the PHP on PATH. Each error is printed as a GitHub annotation (file + line),
# so it shows on the run page itself.
set -uo pipefail
cd "$(dirname "$0")/../.."
php -v | head -1
fails=0
while IFS= read -r -d '' f; do
  out=$(php -l "$f" 2>&1) && continue
  fails=$((fails + 1))
  line=$(printf '%s' "$out" | sed -n 's/.* on line \([0-9]*\).*/\1/p' | head -1)
  msg=$(printf '%s' "$out" | grep -v '^Errors parsing' | head -1)
  echo "::error file=$f,line=${line:-1}::$msg"
done < <(find livecrafts -name '*.php' -not -path '*/node_modules/*' -print0)
[ "$fails" -eq 0 ] && echo "PHP syntax: all files OK" || { echo "PHP syntax: $fails file(s) failed"; exit 1; }
