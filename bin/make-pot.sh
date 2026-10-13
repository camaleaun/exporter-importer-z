#!/usr/bin/env bash
# Writes languages/eizport.pot, the template translations start
# from: the PHP strings (with file:line) merged with the new screens'
# strings. WP-CLI's make-pot doesn't read TypeScript, so those come from the
# built routes (build/routes/*/*.js, not minified): run `npm run build` first.
#
#   bin/make-pot.sh           write the .pot
#   bin/make-pot.sh --check   fail when the committed .pot is out of date
#                             (dates and file:line references ignored)
#
# WP-CLI is $WP ("wp" by default); on a WordPress Studio site with no `wp`,
# `studio wp` on the site this plugin sits in.
set -euo pipefail

cd "$(dirname "$0")/.."
plugin="$PWD"
pot="$plugin/languages/eizport.pot"

if [ -n "${WP:-}" ]; then
	read -r -a wp <<< "$WP"
elif command -v wp > /dev/null; then
	wp=( wp )
else
	wp=( studio wp "--path=$( cd ../../.. && pwd )" )
fi

tmp="$( mktemp -d )"
trap 'rm -rf "$tmp"' EXIT

common=( --slug=eizport --domain=eizport --skip-audit )
"${wp[@]}" i18n make-pot "$plugin/build" "$tmp/js.pot" "${common[@]}" --skip-php --exclude='*.min.js' --no-location > /dev/null
"${wp[@]}" i18n make-pot "$plugin" "$tmp/eizport.pot" "${common[@]}" \
	--exclude=site,node_modules,vendor,build,tests,bin,playground,languages,.wordpress-org,ENVIO-WPORG \
	--merge="$tmp/js.pot" \
	--headers='{"Report-Msgid-Bugs-To":"https://github.com/eizport/eizport/issues"}' > /dev/null

# The strings and their comments, without what changes on every run.
normalize() {
	grep -v -e '^#: ' -e '^"Project-Id-Version: ' -e '^"POT-Creation-Date: ' -e '^"X-Generator: ' "$1" || true
}

if [ "${1:-}" = "--check" ]; then
	if [ ! -f "$pot" ] || ! diff -q <( normalize "$pot" ) <( normalize "$tmp/eizport.pot" ) > /dev/null; then
		echo "languages/eizport.pot is out of date: run bin/make-pot.sh (after npm run build)." >&2
		diff <( normalize "$pot" 2> /dev/null ) <( normalize "$tmp/eizport.pot" ) | head -20 >&2 || true
		exit 1
	fi
	echo "languages/eizport.pot is up to date ($( grep -c '^msgid ' "$pot" ) strings)."
	exit 0
fi

mkdir -p "$plugin/languages"
cp "$tmp/eizport.pot" "$pot"
echo "Wrote languages/eizport.pot ($( grep -c '^msgid ' "$pot" ) strings)."
