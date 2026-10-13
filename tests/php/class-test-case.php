<?php
/**
 * Base test case: temporary files removed after each test.
 *
 * @package Eizport
 */

namespace Eizport\Tests;

use Yoast\PHPUnitPolyfills\TestCases\TestCase as Polyfill_Test_Case;

/**
 * Base test case.
 */
abstract class Test_Case extends Polyfill_Test_Case {

	/**
	 * Files and folders created by the test.
	 *
	 * @var string[]
	 */
	private $temporary = array();

	/**
	 * Runs in English whatever the site's language (assertions check the
	 * plugin's English strings, which a local translation would change), and
	 * with the default settings whatever the site saved. A test changes them
	 * with its own pre_option filter, which runs after this one.
	 */
	protected function set_up() {
		parent::set_up();
		switch_to_locale( 'en_US' );
		add_filter( 'pre_option_' . \Eizport\Settings\OPTION, '\Eizport\Settings\defaults', 1 );
	}

	/**
	 * Removes the temporary files and filters added by the test.
	 */
	protected function tear_down() {
		restore_previous_locale();
		remove_filter( 'pre_option_' . \Eizport\Settings\OPTION, '\Eizport\Settings\defaults', 1 );
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		$filesystem = new \WP_Filesystem_Direct( null );
		foreach ( $this->temporary as $path ) {
			$filesystem->delete( $path, true );
		}
		$this->temporary = array();

		if ( $this->upload_dir ) {
			remove_filter( 'upload_dir', $this->upload_dir );
			$this->upload_dir = null;
		}
		remove_all_filters( 'eizport_post_content' );
		remove_all_filters( 'eizport_wxrz_max_bytes' );
		parent::tear_down();
	}

	/**
	 * A new temporary file, removed after the test.
	 *
	 * @param string $contents Contents.
	 * @return string Path.
	 */
	protected function temp_file( $contents = '' ) {
		$path = \Eizport\Files\temp_file( 'eizport-test' );
		file_put_contents( $path, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$this->temporary[] = $path;

		return $path;
	}

	/**
	 * Points uploads at a temporary folder for this test, so copies and
	 * bundle folders (uploads/eizport/tmp/) never touch the site's real ones:
	 * cleanup() and delete_copies() act on every copy there.
	 */
	protected function sandbox_uploads() {
		$dir = get_temp_dir() . 'eizport-uploads-' . wp_generate_password( 8, false );
		wp_mkdir_p( $dir );
		$this->remove_later( $dir );
		$this->upload_dir = function ( $uploads ) use ( $dir ) {
			$uploads['basedir'] = $dir;
			$uploads['path']    = $dir . $uploads['subdir'];
			return $uploads;
		};
		add_filter( 'upload_dir', $this->upload_dir );
	}

	/**
	 * The upload_dir filter of sandbox_uploads().
	 *
	 * @var callable|null
	 */
	private $upload_dir = null;

	/**
	 * Registers a path to remove after the test.
	 *
	 * @param string $path File or folder.
	 */
	protected function remove_later( $path ) {
		$this->temporary[] = $path;
	}

	/**
	 * Builds a gzipped tar from name => contents with the plugin's writer.
	 *
	 * @param array<string, string> $entries Entries.
	 * @return string Tar bytes (not compressed).
	 */
	protected function tar( array $entries ) {
		ob_start();
		foreach ( $entries as $name => $contents ) {
			$stream = fopen( 'php://memory', 'w+b' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			fwrite( $stream, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
			\Eizport\Tar\print_entry( $name, $stream );
		}
		\Eizport\Tar\print_end();

		return ob_get_clean();
	}
}
