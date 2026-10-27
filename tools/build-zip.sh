#!/usr/bin/env bash
# Build an installable Livecrafts plugin zip into dist/.
#
# This is the Linux/CI twin of tools/build-zip.ps1 (which stays for Windows, where PowerShell's Compress-Archive
# writes backslashes in entry names and WordPress then unpacks the plugin as a heap of flat files). Here `zip`
# writes forward slashes already, so the job is only: copy the plugin folder, drop what is never shipped, zip it
# with the folder itself at the root so it unpacks as wp-content/plugins/livecrafts.
#
# The version comes from the plugin header, so the zip can never disagree with what the plugin says it is.
set -euo pipefail

cd "$(dirname "$0")/.."

version=$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\(.*\)$/\1/p' livecrafts/livecrafts.php | head -1)
[ -n "$version" ] || { echo "No 'Version:' line in livecrafts/livecrafts.php." >&2; exit 1; }

rm -rf dist .build
mkdir -p dist .build
cp -r livecrafts .build/livecrafts

# In the repository but never shipped: ~570 files of build tooling, and the test harnesses.
rm -rf .build/livecrafts/templates-lib/node_modules .build/livecrafts/tests .build/livecrafts/tester

(cd .build && zip -rq "../dist/livecrafts-$version.zip" livecrafts -x '*.git*' '*/.DS_Store')
rm -rf .build

# A backslash in any entry name is the failure this script exists to prevent - fail loudly rather than ship it.
if unzip -l "dist/livecrafts-$version.zip" | grep -q '\\'; then
  echo "dist/livecrafts-$version.zip has backslashes in entry names - WordPress would unpack it flat." >&2
  exit 1
fi

echo "Built dist/livecrafts-$version.zip ($(wc -c < "dist/livecrafts-$version.zip") bytes)"
