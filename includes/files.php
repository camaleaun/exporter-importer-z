<?php
/**
 * File handles opened only after checking they can be, so failures are
 * reported by the callers instead of PHP warnings; the plugin's temporary
 * folder, uploads/eizport/tmp/.
 *
 * @package Eizport
 */

namespace Eizport\Files;

defined( 'ABSPATH' ) || exit;

/**
 * The plugin's folder in uploads, and the one in it for temporary files
 * (Playground copies, bundles being imported), deleted when empty.
 */
const UPLOADS_FOLDER = 'eizport';
const TMP_FOLDER     = 'tmp';

/**
 * The uploads/eizport/.htaccess: denies every direct request (Apache 2.4 and
 * 2.2, LiteSpeed). Other servers (nginx) ignore it: there, the random names
 * and robots.txt protect the files.
 */
const HTACCESS = "# Eizport: copies are served by PHP only.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder deny,allow\n\tDeny from all\n</IfModule>\n";

/**
 * Opens a file for reading.
 *
 * @param string $path File path.
 * @return resource|false
 */
function open_read( $path ) {
	if ( ! is_file( $path ) || ! is_readable( $path ) ) {
		return false;
	}

	return fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streamed binary reads.
}

/**
 * Opens a file for writing, creating or truncating it.
 *
 * @param string $path File path.
 * @return resource|false
 */
function open_write( $path ) {
	if ( ! wp_is_writable( file_exists( $path ) ? $path : dirname( $path ) ) ) {
		return false;
	}

	return fopen( $path, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streamed binary writes.
}

/**
 * A new empty temporary file. wp_tempnam() lives in an admin include, which
 * isn't loaded on WP-CLI, cron or the front end.
 *
 * @param string $name Name hint.
 * @return string Path.
 */
function temp_file( $name ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';

	return wp_tempnam( $name );
}

/**
 * Path of uploads/eizport/tmp/ or of a folder in it.
 *
 * @param string $name Folder name, or '' for uploads/eizport/tmp/ itself.
 * @return string
 */
function tmp_path( $name = '' ) {
	return wp_get_upload_dir()['basedir'] . '/' . UPLOADS_FOLDER . '/' . TMP_FOLDER . ( '' === $name ? '' : '/' . $name );
}

/**
 * Creates (if needed) uploads/eizport/tmp/ or a folder in it, with an empty
 * index.php in each level, so they can't be listed.
 *
 * @param string $name Folder name, or '' for uploads/eizport/tmp/ itself.
 * @return string|false Path.
 */
function tmp_folder( $name = '' ) {
	$path = tmp_path( $name );
	if ( ! wp_mkdir_p( $path ) ) {
		return false;
	}

	foreach ( array_unique( array( dirname( tmp_path() ), tmp_path(), $path ) ) as $folder ) {
		if ( ! file_exists( $folder . '/index.php' ) ) {
			file_put_contents( $folder . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}
	// No direct access on Apache and LiteSpeed: copies are served by PHP (their link checks the expiry).
	$htaccess = dirname( tmp_path() ) . '/.htaccess';
	if ( ! file_exists( $htaccess ) ) {
		file_put_contents( $htaccess, HTACCESS ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	return $path;
}

/**
 * Deletes uploads/eizport/tmp/ as a whole once nothing is left in it.
 *
 * @return bool Whether it is gone.
 */
function delete_tmp_if_empty() {
	$tmp = tmp_path();
	if ( ! is_dir( $tmp ) ) {
		return true;
	}
	if ( array_diff( (array) scandir( $tmp ), array( '.', '..', 'index.php' ) ) ) {
		return false;
	}
	delete_tree( $tmp );

	return ! is_dir( $tmp );
}

/**
 * Deletes a folder and everything in it.
 *
 * @param string $path Folder.
 */
function delete_tree( $path ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
	( new \WP_Filesystem_Direct( null ) )->rmdir( $path, true );
}
