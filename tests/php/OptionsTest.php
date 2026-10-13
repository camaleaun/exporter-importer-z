<?php
/**
 * Tests for settings (options) in bundles.
 *
 * @package Eizport
 */

namespace Eizport\Tests;

use Eizport\Bundle;
use Eizport\Options;
use Eizport\Playground;

require_once dirname( __DIR__, 2 ) . '/includes/apply-options.php';

/**
 * Options tests.
 */
class OptionsTest extends Test_Case {

	/**
	 * Restores $_GET.
	 */
	protected function tear_down() {
		$_GET = array();
		parent::tear_down();
	}

	public function test_options_that_would_break_the_other_site_are_never_exported() {
		global $wpdb;

		foreach ( array( 'siteurl', 'home', 'admin_email', 'auth_salt', 'active_plugins', $wpdb->prefix . 'user_roles', '_transient_x', '_site_transient_y' ) as $name ) {
			$this->assertTrue( Options\is_denied( $name ), $name );
		}
		$this->assertFalse( Options\is_denied( 'blogname' ) );
		$this->assertSame( array( 'my_option', 'other.opt' ), Options\parse_names( "my_option, siteurl\nother.opt my_option bad/name" ) );
	}

	public function test_data_holds_existing_options_and_marks_post_ids() {
		$data = Options\data( array( 'blogname', 'siteurl', 'page_on_front', 'eizport_no_such_option' ) );

		$this->assertSame( array( 'blogname', 'page_on_front' ), array_keys( $data['options'] ) );
		$this->assertSame( get_option( 'blogname' ), $data['options']['blogname'] );
		$this->assertSame( array( 'page_on_front' ), $data['post_ids'] );
		$this->assertNull( Options\data( array( 'siteurl' ) ) );
	}

	public function test_requested_names() {
		$static = function ( $value, $option ) {
			return 'show_on_front' === $option ? 'page' : 5;
		};
		add_filter( 'pre_option_show_on_front', $static, 10, 2 );
		add_filter( 'pre_option_page_on_front', $static, 10, 2 );
		$_GET = array(
			'eizport_option_groups' => array( 'permalinks', 'nope' ),
			'eizport_options_extra' => 'my_option, siteurl',
			'eizport_front_pages'   => '1',
		);

		$this->assertSame( array( 'permalink_structure', 'category_base', 'tag_base', 'my_option', 'show_on_front', 'page_on_front', 'page_for_posts' ), Options\requested_names() );

		remove_filter( 'pre_option_show_on_front', $static, 10 );
		remove_filter( 'pre_option_page_on_front', $static, 10 );
		add_filter( 'pre_option_show_on_front', fn() => 'posts' );
		$this->assertNotContains( 'show_on_front', Options\names_for( array(), array(), true ), 'No static homepage: nothing to set.' );
		remove_all_filters( 'pre_option_show_on_front' );
	}

	public function test_an_import_never_sets_the_options_that_would_break_or_open_the_site() {
		global $wpdb;
		require_once dirname( __DIR__, 2 ) . '/includes/apply-options.php';

		$never = array( 'siteurl', 'home', 'active_plugins', 'users_can_register', 'default_role', $wpdb->prefix . 'user_roles', 'auth_key', '_transient_x' );
		$data  = array(
			'options'  => array_fill_keys( $never, 'x' ) + array( 'blogname' => 'Z' ),
			'post_ids' => array(),
		);

		$this->assertSame( array( 'blogname' => 'Z' ), eizport_options_to_apply( $data, array(), array() ), 'A bundle is a file anyone could have written.' );
		foreach ( $never as $name ) {
			$this->assertTrue( Options\is_denied( $name ), $name . ' is never exported either.' );
		}
	}

	public function test_ids_are_mapped_to_the_imported_items() {
		$data  = array(
			'options'  => array(
				'show_on_front'      => 'page',
				'page_on_front'      => 5,
				'page_for_posts'     => 6,
				'blogname'           => 'Z',
				'theme_mods_example' => array(
					'custom_logo'        => 13,
					'nav_menu_locations' => array(
						'primary' => 40,
						'footer'  => 41,
					),
					'color'              => 'red',
				),
			),
			'post_ids' => array( 'page_on_front', 'page_for_posts' ),
		);
		$apply = eizport_options_to_apply( $data, array( 5 => 105 ), array( 40 => 140 ) );

		$this->assertSame( 'page', $apply['show_on_front'] );
		$this->assertSame( 105, $apply['page_on_front'] );
		$this->assertArrayNotHasKey( 'page_for_posts', $apply, 'Its page was not imported.' );
		$this->assertSame( 'Z', $apply['blogname'] );
		$this->assertSame(
			array(
				'nav_menu_locations' => array( 'primary' => 140 ),
				'color'              => 'red',
			),
			$apply['theme_mods_example']
		);

		// The homepage wasn't imported: a static homepage can't be set.
		$apply = eizport_options_to_apply( $data, array( 6 => 106 ), array() );
		$this->assertArrayNotHasKey( 'show_on_front', $apply );
		$this->assertArrayNotHasKey( 'page_on_front', $apply );
		$this->assertSame( 106, $apply['page_for_posts'] );
	}

	public function test_bundle_carries_the_options_file() {
		$wxr = fopen( 'php://memory', 'w+b' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $wxr, '<rss></rss>' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		$data = Options\data( array( 'blogname' ) );

		ob_start();
		Bundle\print_archive( $wxr, 'site.xml', false, $data );
		$tar = $this->temp_file( ob_get_clean() );

		$dir = Bundle\unpack_bundle( $tar, $this->temp_file() );
		$this->assertIsString( $dir );
		$this->remove_later( $dir );
		$this->assertSame( $data, Bundle\read_options( $dir ) );
		$this->assertSame( 'options.json', Bundle\read_manifest( $tar )['options'] );
	}

	public function test_blueprint_sets_the_options_after_the_import() {
		$data  = Options\data( array( 'blogname' ) );
		$steps = Playground\blueprint( 'site.xml', array(), array(), array(), array(), $data )['steps'];

		$this->assertSame( array( 'mkdir', 'writeFile', 'writeFile', 'writeFile', 'importWxr', 'rm', 'rm', 'rmdir' ), wp_list_pluck( $steps, 'step' ) );
		$this->assertSame( Playground\MEDIA_DIR . '/options.json', $steps[1]['path'] );
		$this->assertSame( $data, json_decode( $steps[1]['data'], true ) );
		$this->assertSame( Playground\OPTIONS_PLUGIN, $steps[2]['path'] );
		$this->assertStringContainsString( 'function eizport_apply_options', $steps[2]['data'] );
	}
}
