<?php
/**
 * Minimal ustar writer and reader, enough for .wxrz bundles: regular files
 * only, written as a stream (no temporary archive) and read entry by entry.
 *
 * @package Eizport
 */

namespace Eizport\Tar;

use Eizport\Files;

defined( 'ABSPATH' ) || exit;

const BLOCK = 512;

/**
 * Builds the 512-byte ustar header of a regular file.
 *
 * @param string $name  Path inside the archive (up to 255 bytes, split at a "/").
 * @param int    $size  File size in bytes.
 * @param int    $mtime Modification time.
 * @return string|false False when the name doesn't fit.
 */
function header_block( $name, $size, $mtime ) {
	$prefix = '';
	if ( strlen( $name ) > 100 ) {
		$cut = strrpos( substr( $name, 0, 156 ), '/' );
		if ( false === $cut || strlen( $name ) - $cut - 1 > 100 ) {
			return false;
		}
		$prefix = substr( $name, 0, $cut );
		$name   = substr( $name, $cut + 1 );
	}

	$header = pack(
		'a100a8a8a8a12a12a8a1a100a6a2a32a32a8a8a155a12',
		$name,
		sprintf( '%07o', 0644 ),
		sprintf( '%07o', 0 ),
		sprintf( '%07o', 0 ),
		sprintf( '%011o', $size ),
		sprintf( '%011o', $mtime ),
		str_repeat( ' ', 8 ), // Checksum placeholder: counted as spaces.
		'0',
		'',
		"ustar\0",
		'00',
		'',
		'',
		'',
		'',
		$prefix,
		''
	);

	$checksum = array_sum( array_map( 'ord', str_split( $header ) ) );

	return substr_replace( $header, sprintf( "%06o\0 ", $checksum ), 148, 8 );
}

/**
 * Prints one file entry: header, contents and padding.
 *
 * @param string          $name   Path inside the archive.
 * @param string|resource $source File path or an open stream (read from its start).
 * @return bool False when the name doesn't fit or the source can't be read.
 */
function print_entry( $name, $source ) {
	// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.OutputNotEscaped -- binary archive output.
	$handle = is_resource( $source ) ? $source : Files\open_read( $source );
	if ( ! $handle ) {
		return false;
	}
	rewind( $handle );
	$stat   = fstat( $handle );
	$header = header_block( $name, $stat['size'], is_resource( $source ) ? time() : $stat['mtime'] );
	if ( false === $header ) {
		is_resource( $source ) || fclose( $handle );
		return false;
	}

	echo $header;
	while ( ! feof( $handle ) ) {
		echo fread( $handle, 64 * KB_IN_BYTES );
	}
	echo padding( $stat['size'] );

	is_resource( $source ) || fclose( $handle );
	// phpcs:enable

	return true;
}

/**
 * Prints the end-of-archive marker (two empty blocks).
 */
function print_end() {
	echo str_repeat( "\0", 2 * BLOCK ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Zero bytes that complete the last block of a file.
 *
 * @param int $size File size.
 * @return string
 */
function padding( $size ) {
	$rest = $size % BLOCK;
	return $rest ? str_repeat( "\0", BLOCK - $rest ) : '';
}

/**
 * Whether a file is a tar archive (ustar magic in the first header).
 *
 * @param string $path File path.
 * @return bool
 */
function is_tar( $path ) {
	// phpcs:disable WordPress.WP.AlternativeFunctions
	$handle = Files\open_read( $path );
	if ( ! $handle ) {
		return false;
	}
	$block = fread( $handle, BLOCK );
	fclose( $handle );
	// phpcs:enable

	return is_string( $block ) && BLOCK === strlen( $block ) && 'ustar' === substr( $block, 257, 5 );
}

/**
 * Walks the regular files of an archive. The callback receives the entry
 * name, its size and the archive handle positioned at the contents; it may
 * read up to `size` bytes. Return false from it to stop.
 *
 * @param string   $path     Archive path.
 * @param callable $callback function ( string $name, int $size, resource $handle ): bool|void.
 * @return bool False when the archive is malformed.
 */
function each_file( $path, $callback ) {
	// phpcs:disable WordPress.WP.AlternativeFunctions
	$handle = Files\open_read( $path );
	if ( ! $handle ) {
		return false;
	}

	$ok = true;
	while ( true ) {
		$header = fread( $handle, BLOCK );
		if ( ! is_string( $header ) || BLOCK !== strlen( $header ) ) {
			$ok = false;
			break;
		}
		if ( str_repeat( "\0", BLOCK ) === $header ) {
			break;
		}

		$fields = unpack( 'a100name/a8mode/a8uid/a8gid/a12size/a12mtime/a8chksum/a1type/a100link/a6magic/a2version/a32uname/a32gname/a8major/a8minor/a155prefix', $header );
		$stored = octdec( trim( $fields['chksum'] ) );
		$actual = array_sum( array_map( 'ord', str_split( substr_replace( $header, str_repeat( ' ', 8 ), 148, 8 ) ) ) );
		if ( $stored !== $actual ) {
			$ok = false;
			break;
		}

		$name  = rtrim( $fields['name'], "\0" );
		$parts = rtrim( $fields['prefix'], "\0" );
		$name  = '' === $parts ? $name : $parts . '/' . $name;
		$size  = (int) octdec( trim( $fields['size'] ) );
		$start = ftell( $handle );

		if ( in_array( $fields['type'], array( '0', "\0" ), true ) && false === $callback( $name, $size, $handle ) ) {
			break;
		}

		// Next header: skip whatever the callback didn't read, plus padding.
		fseek( $handle, $start + $size + strlen( padding( $size ) ) );
	}

	fclose( $handle );
	// phpcs:enable

	return $ok;
}

/**
 * Copies `size` bytes from an archive handle into a file.
 *
 * @param resource $handle Archive handle at the entry contents.
 * @param int      $size   Bytes to copy.
 * @param string   $target File to write.
 * @return bool
 */
function copy_to( $handle, $size, $target ) {
	// phpcs:disable WordPress.WP.AlternativeFunctions
	$out = Files\open_write( $target );
	if ( ! $out ) {
		return false;
	}
	$copied = stream_copy_to_stream( $handle, $out, $size );
	fclose( $out );
	// phpcs:enable

	return $copied === $size;
}
