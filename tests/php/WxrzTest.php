<?php
/**
 * Tests for gzip detection, decompression and output compression.
 *
 * @package Eizport
 */

namespace Eizport\Tests;

use Eizport\Wxrz;

/**
 * Wxrz tests.
 */
class WxrzTest extends Test_Case {

	public function test_is_gzip_checks_the_magic_bytes() {
		$this->assertTrue( Wxrz\is_gzip( $this->temp_file( gzencode( 'x' ) ) ) );
		$this->assertFalse( Wxrz\is_gzip( $this->temp_file( 'x' ) ) );
		$this->assertFalse( Wxrz\is_gzip( '/nonexistent/file.gz' ) );
	}

	public function test_decompress_round_trip() {
		$plain  = str_repeat( "<item>é ü ]]> ñ</item>\n", 5000 );
		$target = $this->temp_file();

		$this->assertTrue( Wxrz\decompress( $this->temp_file( gzencode( $plain ) ), $target ) );
		$this->assertSame( $plain, file_get_contents( $target ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	public function test_a_truncated_file_is_corrupt() {
		$gzip   = gzencode( random_bytes( 50000 ) );
		$result = Wxrz\decompress( $this->temp_file( substr( $gzip, 0, 20000 ) ), $this->temp_file() );

		$this->assertWPError( $result );
		$this->assertSame( 'eizport_wxrz_corrupt', $result->get_error_code() );
	}

	public function test_damaged_data_is_corrupt() {
		$gzip = gzencode( str_repeat( 'abc', 10000 ) );
		$gzip = substr( $gzip, 0, 10 ) . str_repeat( "\xff", 40 ) . substr( $gzip, 50 );

		$result = Wxrz\decompress( $this->temp_file( $gzip ), $this->temp_file() );

		$this->assertWPError( $result );
		$this->assertSame( 'eizport_wxrz_corrupt', $result->get_error_code() );
	}

	public function test_the_size_cap_stops_decompression_bombs() {
		add_filter(
			'eizport_wxrz_max_bytes',
			function () {
				return 1000;
			}
		);
		$result = Wxrz\decompress( $this->temp_file( gzencode( str_repeat( "\0", 100000 ) ) ), $this->temp_file() );

		$this->assertWPError( $result );
		$this->assertSame( 'eizport_wxrz_too_large', $result->get_error_code() );
	}

	public function test_output_compression_produces_gzip() {
		$level = ob_get_level();
		ob_start();
		Wxrz\start_output_compression();
		echo str_repeat( 'Hello WXR. ', 20000 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		while ( ob_get_level() > $level + 1 ) {
			ob_end_flush();
		}
		$gzip = ob_get_clean();

		$this->assertSame( str_repeat( 'Hello WXR. ', 20000 ), gzdecode( $gzip ) );
	}

	/**
	 * Asserts a WP_Error.
	 *
	 * @param mixed $actual Value.
	 */
	private function assertWPError( $actual ) {
		$this->assertInstanceOf( \WP_Error::class, $actual );
	}
}
