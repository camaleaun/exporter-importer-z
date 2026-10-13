<?php
/**
 * Copies: Playground .zip files (and, from the export ability, .wxrz
 * bundles) kept in uploads/eizport/tmp/,
 * next to the folders of bundles being imported, so playground.wordpress.net
 * can load them by link. tmp/ is deleted as a whole once it is empty.
 *
 * Playground runs in the browser and fetches the .zip from its own origin,
 * so the copy is served by this plugin with CORS headers (and Chrome's
 * Private Network Access one, for a site on localhost), not as a plain
 * uploads file. Each copy's name ends in a random token: anyone with the
 * link can download it, nobody can guess it.
 *
 * Copies are temporary: after a day (Settings → Eizport →
 * General, or the `eizport_copy_lifetime` filter; it can also be "until
 * deleted") they stop being served and are deleted, by cron and by the
 * next export. The Playground copies
 * tab of the same page lists them and deletes them earlier.
 *
 * @package Eizport
 */

namespace Eizport\Copies;

use Eizport\Files;

defined( 'ABSPATH' ) || exit;

const QUERY_VAR    = 'eizport_playground';
const COOKIE       = 'eizport_download';
const CLEANUP_HOOK = 'eizport_cleanup';
const PLAYGROUND   = 'https://playground.wordpress.net/';

/**
 * Registers the hooks.
 */
function bootstrap() {
	add_action( 'init', __NAMESPACE__ . '\serve', 0 );
	add_action( CLEANUP_HOOK, __NAMESPACE__ . '\cleanup' );
	add_filter( 'robots_txt', __NAMESPACE__ . '\robots_txt', 99 );
}

/**
 * Asks crawlers to stay away from copies: their folder and their links.
 * Added to the "User-agent: *" group of WordPress's robots.txt (and left out
 * when another plugin or a static file replaces it).
 *
 * @param string $output robots.txt.
 * @return string
 */
function robots_txt( $output ) {
	$folder = (string) wp_parse_url( wp_get_upload_dir()['baseurl'], PHP_URL_PATH ) . '/' . Files\UPLOADS_FOLDER . '/';
	$rules  = "Disallow: {$folder}\nDisallow: /*?" . QUERY_VAR . "=\n";
	if ( false === strpos( $output, "User-agent: *\n" ) ) {
		return $output . "\nUser-agent: *\n" . $rules;
	}

	return preg_replace( '/^User-agent: \*\n/m', "User-agent: *\n" . $rules, $output, 1 );
}

/**
 * Keeps a copy of a Playground .zip, a .wxrz or a WXR .xml (by its
 * download name's extension; .zip otherwise).
 *
 * @param string $zip      The file.
 * @param string $download Its download name.
 * @param string $hash     The random part of its name, or '' for a new one.
 * @return string The copy's name, or '' when it couldn't be kept.
 */
function keep( $zip, $download, $hash = '' ) {
	$dir = Files\tmp_folder();
	if ( ! $dir ) {
		return '';
	}

	$extension = extension_of( $download );
	$base      = preg_replace( '/\.(zip|wxrz|xml)$/i', '', sanitize_file_name( $download ) );
	$base      = preg_replace( '/^[^A-Za-z0-9]+/', '', (string) $base );
	$name      = ( '' === $base ? 'playground' : $base ) . '-' . ( '' === $hash ? new_hash() : $hash ) . '.' . $extension;

	return copy( $zip, $dir . '/' . $name ) ? $name : '';
}

/**
 * The random part of a copy's name.
 *
 * @return string 16 letters and digits.
 */
function new_hash() {
	return wp_generate_password( 16, false );
}

/**
 * How long an abandoned import folder is kept, whatever copies last.
 */
const FOLDER_LIFETIME = DAY_IN_SECONDS;

/**
 * How long a copy lasts, in seconds; 0 keeps it until it is deleted.
 *
 * @return int
 */
function lifetime() {
	/**
	 * Filters how long a Playground copy lasts before it is deleted.
	 *
	 * @param int $seconds Lifetime in seconds: the one chosen in the
	 *                     settings, a day by default; 0 keeps copies until
	 *                     they are deleted.
	 */
	$seconds = (int) apply_filters( 'eizport_copy_lifetime', \Eizport\Settings\copy_lifetime() );

	return $seconds <= 0 ? 0 : max( MINUTE_IN_SECONDS, $seconds );
}

/**
 * How long copies last, in words: "1 day", or "until deleted".
 *
 * @return string
 */
function lifetime_label() {
	return lifetime() ? human_time_diff( 0, lifetime() ) : __( 'until deleted', 'eizport' );
}

/**
 * Whether a copy (or other file) is past a lifetime.
 *
 * @param string   $path     File.
 * @param int      $now      Current time.
 * @param int|null $lifetime Lifetime in seconds (0: never expires); null
 *                           for the copies' one.
 * @return bool
 */
function is_expired( $path, $now = 0, $lifetime = null ) {
	$lifetime = null === $lifetime ? lifetime() : $lifetime;

	return $lifetime > 0 && (int) filemtime( $path ) + $lifetime <= ( $now ? $now : time() );
}

/**
 * Deletes expired copies, and folders of imports abandoned for a day.
 *
 * @param int $now Current time.
 * @return int How many copies and folders were deleted.
 */
function cleanup( $now = 0 ) {
	$expired = array();
	foreach ( copies() as $copy ) {
		if ( is_expired( path( $copy['name'] ), $now ) ) {
			$expired[] = $copy['name'];
		}
	}
	$deleted = delete_copies( $expired );

	foreach ( \Eizport\Bundle\folders() as $dir ) {
		if ( is_expired( $dir, $now, FOLDER_LIFETIME ) ) {
			\Eizport\Bundle\delete_folder( $dir );
			$deleted += (int) ! is_dir( $dir );
		}
	}

	return $deleted;
}

/**
 * Schedules the cleanup for when a copy just kept expires.
 */
function schedule_cleanup() {
	if ( lifetime() ) {
		wp_schedule_single_event( time() + lifetime() + MINUTE_IN_SECONDS, CLEANUP_HOOK );
	}
}

/**
 * Whether a name can be a copy's: no folders, ends in the random token.
 *
 * @param mixed $name Name.
 * @return bool
 */
function is_copy_name( $name ) {
	return is_string( $name ) && (bool) preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]*-[A-Za-z0-9]{16}\.(zip|wxrz|xml)$/', $name ) && false === strpos( $name, '..' );
}

/**
 * A file name's copy extension: "wxrz", "xml", else "zip".
 *
 * @param string $name File name.
 * @return string
 */
function extension_of( $name ) {
	if ( preg_match( '/\.(wxrz|xml)$/i', (string) $name, $match ) ) {
		return strtolower( $match[1] );
	}
	return 'zip';
}

/**
 * Whether a copy is a Playground .zip (it opens on playground.wordpress.net).
 *
 * @param string $name Copy name.
 * @return bool
 */
function is_playground( $name ) {
	return 'zip' === extension_of( $name ) && ! is_zip_bundle( $name ) && ! is_plugin( $name );
}

/**
 * Whether a .zip copy is a plugin that imports its bundle ("As plugin").
 *
 * @param string $name Copy name.
 * @return bool
 */
function is_plugin( $name ) {
	$path = path( $name );

	return 'zip' === extension_of( $name ) && '' !== $path && \Eizport\BundlePlugin\is_plugin_zip( $path );
}

/**
 * Whether a .zip copy is a bundle (Settings → Export → "Bundle as .zip"),
 * not a Playground .zip.
 *
 * @param string $name Copy name.
 * @return bool
 */
function is_zip_bundle( $name ) {
	$path = path( $name );

	return 'zip' === extension_of( $name ) && '' !== $path && \Eizport\Bundle\is_zip_bundle( $path );
}

/**
 * Path of a copy.
 *
 * @param string $name Copy name.
 * @return string Path, or '' for a name that isn't a copy's.
 */
function path( $name ) {
	return is_copy_name( $name ) ? Files\tmp_path() . '/' . $name : '';
}

/**
 * The copy's download URL, with the CORS headers Playground needs.
 *
 * @param string $name Copy name.
 * @return string
 */
function url( $name ) {
	return add_query_arg( QUERY_VAR, rawurlencode( $name ), home_url( '/' ) );
}

/**
 * The link that opens the copy on playground.wordpress.net.
 *
 * @param string $name Copy name.
 * @return string
 */
function playground_url( $name ) {
	return PLAYGROUND . '?blueprint-url=' . rawurlencode( url( $name ) );
}

/**
 * The terminal command that runs a downloaded Playground .zip.
 *
 * @param string $file File name.
 * @return string
 */
function command( $file ) {
	return 'npx @wp-playground/cli@latest server --blueprint=./' . $file;
}

/**
 * The copies, newest first, expired ones too.
 *
 * @return array<int, array{name: string, size: int, time: int}>
 */
function copies() {
	$copies = array();
	// Not GLOB_BRACE: some systems lack it; is_copy_name() picks the copies.
	foreach ( (array) glob( Files\tmp_path() . '/*' ) as $path ) {
		$name = basename( (string) $path );
		if ( is_copy_name( $name ) && is_file( $path ) ) {
			$copies[] = array(
				'name' => $name,
				'size' => (int) filesize( $path ),
				'time' => (int) filemtime( $path ),
			);
		}
	}
	usort(
		$copies,
		function ( $a, $b ) {
			return $b['time'] - $a['time'];
		}
	);

	return $copies;
}

/**
 * Deletes copies.
 *
 * @param string[]|null $names Copies to delete; null for all.
 * @return int How many were deleted.
 */
function delete_copies( $names = null ) {
	$names   = null === $names ? wp_list_pluck( copies(), 'name' ) : $names;
	$deleted = 0;
	foreach ( $names as $name ) {
		$path = path( $name );
		if ( $path && is_file( $path ) ) {
			wp_delete_file( $path );
			$deleted += (int) ! file_exists( $path );
		}
	}
	Files\delete_tmp_if_empty();

	return $deleted;
}

/**
 * Tells the Export screen, through a short-lived cookie, that the download
 * is ready: its name and, when kept, the copy's Playground link. The export
 * output is still buffered, so the header can go out.
 *
 * @param string $download Download name.
 * @param string $copy     Copy name, or ''.
 */
function announce( $download, $copy = '' ) {
	if ( '' === $download || headers_sent() ) {
		return;
	}

	$data = array(
		'file'       => $download,
		'command'    => command( $download ),
		'playground' => '' === $copy ? '' : playground_url( $copy ),
	);
	setrawcookie(
		COOKIE,
		rawurlencode( (string) wp_json_encode( $data ) ),
		array(
			'expires'  => time() + 10 * MINUTE_IN_SECONDS,
			'path'     => ADMIN_COOKIE_PATH,
			'secure'   => is_ssl(),
			'httponly' => false, // Read by the Export screen's script.
			'samesite' => 'Lax',
		)
	);
}

/**
 * What a request for a copy gets: the status and, for 200, the file.
 *
 * @param string $name   Copy name asked for.
 * @param string $method Request method.
 * @return array{status: int, path: string}
 */
function response( $name, $method ) {
	if ( 'OPTIONS' === $method ) {
		return array(
			'status' => 204,
			'path'   => '',
		);
	}
	$path = path( $name );

	return array(
		'status' => $path && is_file( $path ) && ! is_expired( $path ) && in_array( $method, array( 'GET', 'HEAD' ), true ) ? 200 : 404,
		'path'   => $path,
	);
}

/**
 * Serves a copy (on `init`), to anyone with its link, with the headers
 * playground.wordpress.net needs to fetch it.
 */
function serve() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- a public link: the unguessable name is the key.
	if ( ! isset( $_GET[ QUERY_VAR ] ) ) {
		return;
	}
	$name = sanitize_file_name( wp_unslash( $_GET[ QUERY_VAR ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	// phpcs:enable
	$method   = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
	$response = response( $name, $method );

	header( 'Access-Control-Allow-Origin: *' );
	header( 'Access-Control-Allow-Private-Network: true' );
	header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
	status_header( $response['status'] );

	if ( 204 === $response['status'] ) {
		header( 'Access-Control-Allow-Methods: GET, HEAD, OPTIONS' );
		header( 'Access-Control-Allow-Headers: *' );
		exit;
	}
	if ( 200 !== $response['status'] ) {
		nocache_headers();
		exit;
	}

	$types = array(
		'zip'  => 'application/zip',
		'wxrz' => 'application/gzip',
		'xml'  => 'text/xml',
	);
	header( 'Content-Type: ' . $types[ extension_of( $name ) ] );
	header( 'Content-Length: ' . filesize( $response['path'] ) );
	header( 'Content-Disposition: attachment; filename="' . $name . '"' );
	if ( 'GET' === $method ) {
		readfile( $response['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
	}
	exit;
}
