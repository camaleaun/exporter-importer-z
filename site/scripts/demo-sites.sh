#!/usr/bin/env bash
# Creates (or refreshes) the three WordPress Studio sites the screenshots and
# the demo video are recorded on — never the development site:
#
#   eizport-demo         the source: WooCommerce, the importer, this plugin,
#                        and the development site's pages, products, media
#                        and settings (moved with the plugin itself).
#   eizport-demo-target  a clean site to import into, with the same plugins.
#   eizport-demo-client  a clean site with WooCommerce only: the "as plugin"
#                        .zip installs Eizport and the importer there.
#
# Run from site/: `npm run demo-sites` (needs the Studio CLI and the
# development site running). Prints the URLs at the end.
set -euo pipefail

SITE_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PLUGIN_DIR="$(cd "$SITE_DIR/.." && pwd)"
DEV_SITE="$(cd "$PLUGIN_DIR/../../.." && pwd)"
SOURCE="${EIZPORT_DEMO_SOURCE:-$HOME/Studio/eizport-demo}"
TARGET="${EIZPORT_DEMO_TARGET:-$HOME/Studio/eizport-demo-target}"
CLIENT="${EIZPORT_DEMO_CLIENT:-$HOME/Studio/eizport-demo-client}"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

wp() { local path="$1"; shift; studio wp --path="$path" "$@"; }
# "Could not update option" only means the value was already there.
set_option() { wp "$1" option update "${@:2}" --quiet 2>/dev/null || true; }

# The plugin as its release zip has it: tracked files that ship, plus build/.
package_plugin() {
	( cd "$PLUGIN_DIR" && npm run build --silent >/dev/null && git archive --format=tar --prefix=eizport/ HEAD ) | tar -x -C "$TMP"
	cp -R "$PLUGIN_DIR/build" "$TMP/eizport/"
}

create_site() {
	local path="$1" name="$2" with_plugin="${3:-yes}"
	if [ ! -f "$path/wp-config.php" ]; then
		studio site create --path "$path" --name "$name" --skip-browser
	else
		studio site start --path "$path" --skip-browser >/dev/null 2>&1 || true
	fi
	# A fresh copy of the plugin each time (none on the client: the video installs it).
	rm -rf "$path/wp-content/plugins/eizport"
	wp "$path" plugin install woocommerce --activate --quiet
	if [ "$with_plugin" = yes ]; then
		cp -R "$TMP/eizport" "$path/wp-content/plugins/"
		wp "$path" plugin install wordpress-importer --activate --quiet
		wp "$path" plugin activate eizport --quiet
	else
		wp "$path" plugin delete wordpress-importer --quiet 2>/dev/null || true
	fi
	# Studio's inactive extras would show up as (translation) updates in the admin bar.
	wp "$path" plugin delete hello akismet --quiet 2>/dev/null || true
	set_option "$path" blogname "$name"
	# No onboarding, no "coming soon" banner, a neutral admin name in the admin bar.
	set_option "$path" woocommerce_coming_soon no
	set_option "$path" woocommerce_onboarding_profile '{"skipped":true}' --format=json
	set_option "$path" woocommerce_task_list_hidden yes
	wp "$path" user update 1 --display_name=Admin --first_name=Admin --quiet
	wp "$path" rewrite structure '/%postname%/' --quiet
}

package_plugin
create_site "$SOURCE" "Z Store"
create_site "$TARGET" "New Site"
create_site "$CLIENT" "Client Site" no

# Seed the source with the development site's content: an export made by
# the plugin (read-only on the development site; its copy is deleted).
copy=$(wp "$DEV_SITE" eval '
	wp_set_current_user( 1 );
	$out = wp_get_ability( "eizport/export" )->execute( array(
		"types"    => array( "post" => (object) array(), "page" => (object) array(), "product" => (object) array(), "attachment" => (object) array() ),
		"options"  => array( "groups" => array( "basics", "reading", "woocommerce" ), "front_pages" => true ),
		"filename" => "eizport-demo-seed",
	) );
	echo is_wp_error( $out ) ? "ERROR " . $out->get_error_message() : $out["name"];
')
case "$copy" in ERROR*) echo "$copy" >&2; exit 1 ;; esac
cp "$DEV_SITE/wp-content/uploads/eizport/tmp/$copy" "$TMP/"
wp "$DEV_SITE" eval 'wp_set_current_user( 1 ); wp_get_ability( "eizport/delete-copies" )->execute( array( "names" => array( "'"$copy"'" ) ) );' >/dev/null
# Start from empty sites: no default or WooCommerce-made pages to duplicate.
# The target stays empty until the video imports into it.
wp "$SOURCE" site empty --uploads --yes --quiet
wp "$TARGET" site empty --uploads --yes --quiet
wp "$CLIENT" site empty --uploads --yes --quiet
cp "$TMP/$copy" "$SOURCE/"
wp "$SOURCE" eizport import "$SOURCE/$copy" --user=1
rm -f "$SOURCE/$copy"
set_option "$SOURCE" blogname "Z Store"

# The development site's content predates the plugin's name: the demo shows
# the name it has now, in the text and on the images.
wp "$SOURCE" search-replace 'Exporter Importer Z' 'Eizport' "$(wp "$SOURCE" db prefix)posts" --quiet
images=$(wp "$SOURCE" eval '
	foreach ( get_posts( array( "post_type" => "attachment", "post_mime_type" => "image", "numberposts" => -1 ) ) as $image ) {
		echo $image->ID, " ", get_attached_file( $image->ID ), "\n";
	}
')
node "$SITE_DIR/scripts/demo-images.mjs" $(cut -d' ' -f2- <<< "$images")
wp "$SOURCE" media regenerate $(cut -d' ' -f1 <<< "$images") --yes --quiet

echo "Source: $(wp "$SOURCE" option get siteurl)"
echo "Target: $(wp "$TARGET" option get siteurl)"
echo "Client: $(wp "$CLIENT" option get siteurl)"
