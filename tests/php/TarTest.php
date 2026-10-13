<?php
/**
 * Tests for the ustar writer and reader.
 *
 * @package Eizport
 */

namespace Eizport\Tests;

use Eizport\Tar;

/**
 * Tar tests.
 */
class TarTest extends Test_Case {

	/**
	 * Reads every entry of an archive file.
	 *
	 * @param string $path Archive.
	 * @return array{0: bool, 1: array<string, string>}
	 */
	private function read_all( $path ) {
		$entries = array();
		$ok      = Tar\each_file(
			$path,
			function ( $name, $size, $handle ) use ( &$entries ) {
				$entries[ $name ] = $size ? fread( $handle, $size ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
			}
		);

		return array( $ok, $entries );
	}

	public function test_round_trip_keeps_names_and_contents() {
		$entries = array(
			'manifest.json'           => '{"version":1}',
			'empty.txt'               => '',
			'exact-block.bin'         => str_repeat( 'a', 512 ),
			'uploads/2026/10/a.jpg'   => random_bytes( 1500 ),
			'post_content/about.html' => "<p>a]]>b é ü</p>\n",
		);
		$path    = $this->temp_file( $this->tar( $entries ) );

		$this->assertTrue( Tar\is_tar( $path ) );
		list( $ok, $read ) = $this->read_all( $path );
		$this->assertTrue( $ok );
		$this->assertSame( $entries, $read );
	}

	public function test_long_names_use_the_prefix_field() {
		$name = str_repeat( 'd', 80 ) . '/' . str_repeat( 'e', 60 ) . '/file.jpg';
		$path = $this->temp_file( $this->tar( array( $name => 'x' ) ) );

		list( $ok, $read ) = $this->read_all( $path );
		$this->assertTrue( $ok );
		$this->assertSame( array( $name => 'x' ), $read );
	}

	public function test_names_that_do_not_fit_are_refused() {
		$this->assertFalse( Tar\header_block( str_repeat( 'n', 101 ), 1, 0 ), 'Over 100 bytes without a "/".' );
		$this->assertFalse( Tar\header_block( str_repeat( 'p', 160 ) . '/x', 1, 0 ), 'Prefix over 155 bytes.' );
		$this->assertSame( 512, strlen( Tar\header_block( 'ok.txt', 1, 0 ) ) );
	}

	public function test_system_tar_reads_the_archive() {
		if ( ! function_exists( 'exec' ) || ! is_executable( '/usr/bin/tar' ) ) {
			$this->markTestSkipped( 'No system tar.' );
		}
		$path = $this->temp_file( $this->tar( array( 'a/b.txt' => 'hello' ) ) );

		exec( '/usr/bin/tar -xOf ' . escapeshellarg( $path ) . ' a/b.txt 2>&1', $output, $status ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec

		$this->assertSame( 0, $status, implode( "\n", $output ) );
		$this->assertSame( array( 'hello' ), $output );
	}

	public function test_a_damaged_header_is_reported() {
		$bytes       = $this->tar( array( 'a.txt' => 'hello' ) );
		$bytes[10]   = 'X'; // Inside the name: the checksum no longer matches.
		$path        = $this->temp_file( $bytes );
		list( $ok, ) = $this->read_all( $path );

		$this->assertFalse( $ok );
	}

	public function test_a_truncated_archive_is_reported() {
		$bytes       = $this->tar( array( 'a.txt' => str_repeat( 'z', 2000 ) ) );
		$path        = $this->temp_file( substr( $bytes, 0, 1024 ) );
		list( $ok, ) = $this->read_all( $path );

		$this->assertFalse( $ok );
	}

	public function test_is_tar_rejects_other_files() {
		$this->assertFalse( Tar\is_tar( $this->temp_file( '<?xml version="1.0"?><rss/>' ) ) );
		$this->assertFalse( Tar\is_tar( '/nonexistent/file.tar' ) );
	}
}
