<?php
/**
 * The plugin prints no <script> or <style> tags: scripts and styles are
 * enqueued (wp_enqueue_*, wp_add_inline_*), as WordPress.org asks. Plugin
 * Check doesn't look for these; the directory's review does.
 *
 * @package Eizport
 */

namespace Eizport\Tests;

/**
 * Enqueued assets tests.
 */
class EnqueuedAssetsTest extends Test_Case {

	public function test_php_that_ships_prints_no_script_or_style_tags() {
		$root  = dirname( __DIR__, 2 );
		$files = array( $root . '/eizport.php' );
		// build/ exists once `npm run build` ran (bin/postbuild.mjs enqueues its styles).
		foreach ( array( 'includes', 'templates', 'build/pages' ) as $dir ) {
			if ( ! is_dir( $root . '/' . $dir ) ) {
				continue;
			}
			$found = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root . '/' . $dir, \FilesystemIterator::SKIP_DOTS ) );
			foreach ( $found as $file ) {
				if ( preg_match( '/\.(php|tpl)$/', $file->getFilename() ) ) {
					$files[] = $file->getPathname();
				}
			}
		}

		$tags = array();
		foreach ( $files as $file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file.
			foreach ( explode( "\n", (string) file_get_contents( $file ) ) as $number => $line ) {
				if ( preg_match( '/<(script|style)\b/i', $line ) ) {
					$tags[] = substr( $file, strlen( $root ) + 1 ) . ':' . ( $number + 1 );
				}
			}
		}

		$this->assertSame( array(), $tags, 'Enqueue scripts and styles instead of printing tags.' );
	}
}
