<?php
/**
 * The .wxrz format: a WXR file compressed with gzip.
 *
 * Both directions stream in small chunks, so memory doesn't grow with the
 * size of the export.
 *
 * @package Eizport
 */

namespace Eizport\Wxrz;

use Eizport\Files;
use WP_Error;

defined( 'ABSPATH' ) || exit;

const EXTENSION = 'wxrz';

/**
 * Compressed bytes read per step when decompressing. Deflate expands at most
 * about 1032:1, so one step never holds more than ~16 MB.
 */
const READ_CHUNK = 16384;

/**
 * Whether a file starts with the gzip magic bytes.
 *
 * @param string $path File path.
 * @return bool
 */
function is_gzip( $path ) {
	$handle = Files\open_read( $path );
	if ( ! $handle ) {
		return false;
	}
	$magic = fread( $handle, 2 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
	fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

	return "\x1f\x8b" === $magic;
}

/**
 * Largest WXR a .wxrz may expand to, guarding against decompression bombs.
 *
 * @return int Bytes.
 */
function max_bytes() {
	/**
	 * Filters the largest size a .wxrz file may expand to.
	 *
	 * @param int $bytes Default 512 MB.
	 */
	return (int) apply_filters( 'eizport_wxrz_max_bytes', 512 * MB_IN_BYTES );
}

/**
 * Decompresses a gzip file into another file.
 *
 * @param string $source Gzip file.
 * @param string $target File to write the WXR to (created or truncated).
 * @return true|WP_Error
 */
function decompress( $source, $target ) {
	// phpcs:disable WordPress.WP.AlternativeFunctions
	$in  = Files\open_read( $source );
	$out = Files\open_write( $target );
	if ( ! $in || ! $out ) {
		$in && fclose( $in );
		$out && fclose( $out );
		return new WP_Error( 'eizport_wxrz_unreadable', __( 'The .wxrz file could not be read.', 'eizport' ) );
	}

	$context = inflate_init( ZLIB_ENCODING_GZIP );
	$max     = max_bytes();
	$total   = 0;
	$error   = null;

	while ( ! feof( $in ) ) {
		// inflate_add() warns on damaged data and returns false, which is handled below.
		$plain = @inflate_add( $context, (string) fread( $in, READ_CHUNK ), ZLIB_SYNC_FLUSH ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $plain ) {
			$error = 'corrupt';
			break;
		}
		$total += strlen( $plain );
		if ( $total > $max ) {
			$error = 'too_large';
			break;
		}
		fwrite( $out, $plain );
	}

	// A truncated file inflates without errors but never reaches the end of the stream.
	if ( ! $error && ZLIB_STREAM_END !== inflate_get_status( $context ) ) {
		$error = 'corrupt';
	}

	fclose( $in );
	fclose( $out );
	// phpcs:enable

	if ( 'corrupt' === $error ) {
		return new WP_Error( 'eizport_wxrz_corrupt', __( 'The .wxrz file is damaged or incomplete.', 'eizport' ) );
	}
	if ( 'too_large' === $error ) {
		return new WP_Error(
			'eizport_wxrz_too_large',
			sprintf(
				/* translators: %s: size such as "512 MB". */
				__( 'The .wxrz file expands to more than %s.', 'eizport' ),
				size_format( $max )
			)
		);
	}

	return true;
}

/**
 * Starts compressing everything printed from now on as gzip, in chunks, until
 * the request ends.
 */
function start_output_compression() {
	$context = deflate_init( ZLIB_ENCODING_GZIP );

	ob_start(
		function ( $buffer, $phase ) use ( $context ) {
			$flush = ( $phase & PHP_OUTPUT_HANDLER_FINAL ) ? ZLIB_FINISH : ZLIB_NO_FLUSH;
			return deflate_add( $context, $buffer, $flush );
		},
		64 * KB_IN_BYTES
	);
}
