#!/usr/bin/env bash
# Plugin Check on what ships: everything .distignore leaves out of the zip is
# left out of the check too, so the two lists can't drift apart.
#
#   bin/plugin-check.sh                       with `wp`
#   bin/plugin-check.sh studio wp             on WordPress Studio (from the site root)
#   bin/plugin-check.sh npx wp-env run cli wp in CI
#
# Fails unless Plugin Check reports "No errors found".
set -euo pipefail

plugin="$(cd "$(dirname "$0")/.." && pwd)"
dirs=()
files=()
while IFS= read -r line || [ -n "$line" ]; do
	case "$line" in '' | '#'* | *'*'*) continue ;; esac
	name="${line#/}"
	if [ -d "$plugin/$name" ]; then
		dirs+=("$name")
	elif [ -e "$plugin/$name" ]; then
		files+=("$name")
	fi
done < "$plugin/.distignore"

join() {
	local IFS=,
	echo "$*"
}

cmd=("$@")
[ ${#cmd[@]} -eq 0 ] && cmd=(wp)
out="$("${cmd[@]}" plugin check eizport \
	--exclude-directories="$(join ${dirs[@]+"${dirs[@]}"})" \
	--exclude-files="$(join ${files[@]+"${files[@]}"})" 2>&1)" || true
echo "$out"
grep -q 'No errors found' <<< "$out"
