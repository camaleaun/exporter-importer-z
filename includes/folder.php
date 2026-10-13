<?php
/**
 * Bundles as folders, to keep a delivery under version control and compare
 * it with the last one: `wp eizport export --dir=<folder>` writes the
 * bundle's entries there (manifest.json, the WXR formatted line by line,
 * post_content/, media files, options.json), and `wp eizport import
 * <folder>` imports such a folder back.
 *
 * @package Eizport
 */

namespace Eizport\Folder;

use Eizport\Bundle;
use Eizport\Files;
use Eizport\Tar;
use Eizport\Wxrz;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * A bundle file (.wxrz or .zip) as an uncompressed tar.
 *
 * @param string $bundle Bundle file.
 * @param string $tar    File to write.
 * @return true|WP_Error
 */
function to_tar( $bundle, $tar ) {
	if ( Bundle\is_zip( $bundle ) ) {
		return Bundle\zip_to_tar( $bundle, $tar );
	}
	if ( Wxrz\is_gzip( $bundle ) ) {
		$result = Wxrz\decompress( $bundle, $tar );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
	} elseif ( ! copy( $bundle, $tar ) ) {
		return new WP_Error( 'eizport_folder', __( 'The file could not be read.', 'eizport' ) );
	}

	return Tar\is_tar( $tar ) ? true : new WP_Error( 'eizport_folder', __( 'This is not a bundle (.wxrz or .zip).', 'eizport' ) );
}

/**
 * Formats a WXR line by line (elements indented, contents untouched: they
 * are text or CDATA), so two exports compare well.
 *
 * @param string $path WXR file, rewritten.
 * @return bool
 */
function format_wxr( $path ) {
	if ( ! class_exists( 'DOMDocument' ) ) {
		return false;
	}
	$document                     = new \DOMDocument();
	$document->preserveWhiteSpace = false; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMDocument's.
	$document->formatOutput       = true; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMDocument's.
	if ( ! $document->load( $path, LIBXML_PARSEHUGE | LIBXML_NONET ) ) {
		return false;
	}

	return false !== $document->save( $path );
}

/**
 * Writes a bundle's entries into a folder, the WXR formatted. The bundle
 * files already there go first; other files stay.
 *
 * @param string $bundle   Bundle file (.wxrz or .zip).
 * @param string $dir      Folder.
 * @param string $wxr_name The WXR's name in the folder (without .xml), the
 *                         manifest following; '' keeps the bundle's, which
 *                         has the export's date.
 * @return array|WP_Error The manifest.
 */
function extract( $bundle, $dir, $wxr_name = '' ) {
	$tar    = Files\temp_file( 'eizport-tar' );
	$result = to_tar( $bundle, $tar );
	if ( is_wp_error( $result ) ) {
		wp_delete_file( $tar );
		return $result;
	}
	$manifest = Bundle\read_manifest( $tar );
	if ( is_wp_error( $manifest ) ) {
		wp_delete_file( $tar );
		return $manifest;
	}
	// Only the bundle's files go: the folder may hold others (.git, notes).
	clear( $dir );
	if ( ! wp_mkdir_p( $dir ) ) {
		wp_delete_file( $tar );
		return new WP_Error( 'eizport_folder', __( 'The folder could not be created.', 'eizport' ) );
	}
	$failed = false;
	Tar\each_file(
		$tar,
		function ( $name, $size, $handle ) use ( $dir, &$failed ) {
			if ( ! Bundle\is_bundle_entry( $name ) ) {
				return;
			}
			$path   = $dir . '/' . $name;
			$failed = $failed || ! wp_mkdir_p( dirname( $path ) ) || ! Tar\copy_to( $handle, $size, $path );
		}
	);
	wp_delete_file( $tar );
	if ( $failed ) {
		return new WP_Error( 'eizport_folder', __( 'The bundle could not be written to the folder.', 'eizport' ) );
	}
	// One name from one delivery to the next, so the folder shows a diff, not a delete and an add.
	$wxr_name = sanitize_file_name( (string) $wxr_name );
	if ( '' !== $wxr_name && $manifest['wxr'][0] !== $wxr_name . '.xml' ) {
		if ( ! rename( $dir . '/' . $manifest['wxr'][0], $dir . '/' . $wxr_name . '.xml' ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- a file of the folder written here.
			return new WP_Error( 'eizport_folder', __( 'The bundle could not be written to the folder.', 'eizport' ) );
		}
		$manifest['wxr'][0] = $wxr_name . '.xml';
		file_put_contents( $dir . '/' . Bundle\MANIFEST, wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- a file of the folder written here.
	}
	foreach ( (array) $manifest['wxr'] as $wxr ) {
		format_wxr( $dir . '/' . $wxr );
	}

	return $manifest;
}

/**
 * The bundle entries of a folder, relative names => paths.
 *
 * @param string $dir Folder.
 * @return array<string, string>
 */
function entries( $dir ) {
	$dir     = untrailingslashit( (string) realpath( $dir ) );
	$entries = array();
	if ( '' === $dir ) {
		return $entries;
	}
	$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ) );
	foreach ( $files as $file ) {
		$name = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $dir ) + 1 ) );
		if ( $file->isFile() && ! $file->isLink() && Bundle\is_bundle_entry( $name ) ) {
			$entries[ $name ] = $file->getPathname();
		}
	}
	ksort( $entries );

	return $entries;
}

/**
 * Deletes a folder's bundle files, and the folders left empty by that.
 *
 * @param string $dir Folder.
 */
function clear( $dir ) {
	$root    = (string) realpath( $dir );
	$folders = array();
	foreach ( entries( $dir ) as $path ) {
		wp_delete_file( $path );
		$parent = dirname( $path );
		while ( $parent !== $root && 0 === strpos( $parent, $root . DIRECTORY_SEPARATOR ) ) {
			$folders[ $parent ] = true;
			$parent             = dirname( $parent );
		}
	}
	// Deepest first.
	$folders = array_keys( $folders );
	usort(
		$folders,
		function ( $a, $b ) {
			return strlen( $b ) - strlen( $a );
		}
	);
	foreach ( $folders as $folder ) {
		$left = scandir( $folder );
		if ( is_array( $left ) && array( '.', '..' ) === array_values( $left ) ) {
			rmdir( $folder ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		}
	}
}

/**
 * Packs a bundle folder into a tar, as a .wxrz holds it.
 *
 * @param string $dir Folder with a manifest.json.
 * @param string $tar File to write.
 * @return true|WP_Error
 */
function pack( $dir, $tar ) {
	$entries = entries( $dir );
	if ( ! isset( $entries[ Bundle\MANIFEST ] ) ) {
		return new WP_Error( 'eizport_folder', __( 'The folder has no manifest.json: it isn\'t a bundle.', 'eizport' ) );
	}
	$out = Files\open_write( $tar );
	if ( ! $out ) {
		return new WP_Error( 'eizport_folder', __( 'A temporary file could not be written.', 'eizport' ) );
	}
	// Tar\print_entry() prints: capture it into the file.
	ob_start(
		function ( $buffer ) use ( $out ) {
			fwrite( $out, $buffer ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
			return '';
		},
		64 * KB_IN_BYTES
	);
	// The manifest first, as in a .wxrz.
	Tar\print_entry( Bundle\MANIFEST, $entries[ Bundle\MANIFEST ] );
	unset( $entries[ Bundle\MANIFEST ] );
	foreach ( $entries as $name => $path ) {
		Tar\print_entry( $name, $path );
	}
	Tar\print_end();
	ob_end_flush();
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

	return true;
}
