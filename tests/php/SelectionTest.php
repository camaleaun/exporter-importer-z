<?php
/**
 * Tests for exporting several content types, by filters or by selection.
 *
 * Reads the site's content (WooCommerce sample products when it is active)
 * without writing to the database.
 *
 * @package Eizport
 */

namespace Eizport\Tests;

use Eizport\Selection;

/**
 * Selection tests.
 */
class SelectionTest extends Test_Case {

	/**
	 * Restores $_GET.
	 */
	protected function tear_down() {
		$_GET = array();
		parent::tear_down();
	}

	/**
	 * IDs export_wp()'s post query would pick for export args.
	 *
	 * @param array $args Export args, after the export_args filter.
	 * @return int[]
	 */
	private function exported( $args ) {
		global $wpdb;

		return array_map( 'intval', $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE " . Selection\where( $args ) . ' ORDER BY ID' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- read-only, as export_wp().
	}

	/**
	 * The first item of a type.
	 *
	 * @param string $type Post type.
	 * @param array  $args More get_posts() args.
	 * @return int
	 */
	private function first( $type, $args = array() ) {
		$ids = get_posts(
			array_merge(
				array(
					'post_type'   => $type,
					'post_status' => 'any',
					'numberposts' => 1,
					'fields'      => 'ids',
					'orderby'     => 'ID',
					'order'       => 'ASC',
				),
				$args
			)
		);

		return $ids ? (int) $ids[0] : 0;
	}

	/**
	 * Skips without WooCommerce products.
	 *
	 * @return array{variable: int, simple: int, variations: int[]}
	 */
	private function products() {
		if ( ! post_type_exists( 'product' ) ) {
			$this->markTestSkipped( 'WooCommerce is needed.' );
		}
		$variations = get_posts(
			array(
				'post_type'   => 'product_variation',
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		if ( ! $variations ) {
			$this->markTestSkipped( 'A variable product is needed.' );
		}
		$variable = (int) wp_get_post_parent_id( $variations[0] );
		$simple   = $this->first( 'product', array( 'post__not_in' => array( $variable ) ) );

		return array(
			'variable'   => $variable,
			'simple'     => $simple,
			'variations' => array_map(
				'intval',
				get_posts(
					array(
						'post_type'   => 'product_variation',
						'post_status' => 'any',
						'post_parent' => $variable,
						'numberposts' => -1,
						'fields'      => 'ids',
					)
				)
			),
		);
	}

	public function test_types_from_checkboxes_or_from_core_radio() {
		$_GET = array( 'eizport_types' => array( 'pages', 'attachment', 'nope', 'pages' ) );
		$this->assertSame( array( 'page', 'attachment' ), Selection\requested_types( array( 'content' => 'all' ) ) );

		$_GET = array();
		$this->assertSame( array( 'page' ), Selection\requested_types( array( 'content' => 'page' ) ) );
		$this->assertSame( array(), Selection\requested_types( array( 'content' => 'all' ) ) );
		$this->assertSame( array( 'content' => 'all' ), Selection\args( array( 'content' => 'all' ) ), 'All content stays core\'s.' );
	}

	public function test_selection_keeps_only_items_of_the_type() {
		$page = $this->first( 'page' );
		$post = $this->first( 'post' );
		$_GET = array(
			'eizport_types' => array( 'pages' ),
			'eizport_mode'  => array( 'page' => 'select' ),
			'eizport_ids'   => array( 'page' => array( $page, $post, 999999 ) ),
		);
		$args = Selection\args(
			array(
				'content' => 'all',
				'author'  => 3,
			)
		);

		$this->assertSame( 'page', $args['content'], 'export_wp() still adds attachments.' );
		$this->assertFalse( $args['author'] );
		$this->assertSame( array( 'page' => array( 'ids' => array( $page ) ) ), $args['eizport_types'] );
		$this->assertSame( array( $page ), array_intersect( $this->exported( $args ), array( $page, $post ) ) );

		unset( $_GET['eizport_ids'] );
		$this->assertSame( array( 0 ), Selection\args( array( 'content' => 'all' ) )['eizport_types']['page']['ids'], 'Nothing checked: nothing of the type.' );
	}

	public function test_pages_and_products_together_without_posts() {
		$products = $this->products();
		$page     = $this->first( 'page' );
		$_GET     = array(
			'eizport_types' => array( 'pages', 'product' ),
			'eizport_mode'  => array(
				'page'    => 'select',
				'product' => 'select',
			),
			'eizport_ids'   => array(
				'page'    => array( $page ),
				'product' => array( $products['variable'] ),
			),
		);
		$exported = $this->exported( Selection\args( array( 'content' => 'all' ) ) );

		$this->assertContains( $page, $exported );
		$this->assertContains( $products['variable'], $exported );
		$this->assertEmpty( array_diff( $products['variations'], $exported ), 'The variations come with their product.' );
		$this->assertNotContains( $products['simple'], $exported );
		$this->assertNotContains( $this->first( 'post' ), $exported );
	}

	public function test_products_by_category_and_status() {
		$products = $this->products();
		$terms    = wp_get_object_terms( $products['variable'], 'product_cat', array( 'fields' => 'ids' ) );
		$_GET     = array(
			'eizport_types'   => array( 'product' ),
			'eizport_filters' => array(
				'product' => array(
					'tax'    => array( 'product_cat' => $terms[0] ),
					'status' => 'publish',
					'author' => '0',
				),
			),
		);
		$args     = Selection\args( array( 'content' => 'all' ) );

		$this->assertSame( array( (int) $terms[0] ), $args['eizport_types']['product']['filters']['terms'] );
		$this->assertSame( 'publish', $args['eizport_types']['product']['filters']['status'] );
		$this->assertContains( $products['variable'], $this->exported( $args ) );
		$this->assertEmpty( array_diff( $products['variations'], $this->exported( $args ) ), 'Filtered products bring their variations too.' );

		$_GET['eizport_filters']['product']['tax']['nope_tax'] = '5';
		$_GET['eizport_filters']['product']['status']          = 'not-a-status';
		$_GET['eizport_filters']['product']['start_date']      = '2026-1';
		$filters = Selection\args( array( 'content' => 'all' ) )['eizport_types']['product']['filters'];
		$this->assertSame( '', $filters['status'] );
		$this->assertSame( '', $filters['start_date'] );
		$this->assertSame( array( (int) $terms[0] ), $filters['terms'] );
	}

	public function test_core_filters_apply_next_to_other_types() {
		$post = $this->first( 'post', array( 'post_status' => 'publish' ) );
		$_GET = array(
			'eizport_types' => array( 'posts', 'pages' ),
			'post_status'   => 'draft',
			'post_author'   => '0',
			'cat'           => '0',
		);
		$args = Selection\args( array( 'content' => 'all' ) );

		$this->assertSame( 'draft', $args['eizport_types']['post']['filters']['status'] );
		$this->assertNotContains( $post, $this->exported( $args ), 'Only draft posts.' );
		$this->assertContains( $this->first( 'page', array( 'post_status' => 'publish' ) ), $this->exported( $args ), 'Pages without filters.' );
	}

	public function test_statuses_offered_are_the_ones_used() {
		$products = $this->products();
		$names    = array_keys( Selection\statuses( 'product' ) );

		$this->assertContains( get_post_status( $products['variable'] ), $names );
		$this->assertNotContains( 'wc-processing', $names, 'No order statuses for products.' );
	}

	public function test_custom_types_offered_have_an_admin_screen() {
		$types = Selection\types();

		$this->assertSame( array( 'post', 'page' ), array_slice( array_keys( $types ), 0, 2 ) );
		$this->assertSame( 'attachment', array_key_last( $types ) );
		if ( post_type_exists( 'product' ) ) {
			$this->assertArrayHasKey( 'product', $types );
			$this->assertArrayNotHasKey( 'product_variation', $types, 'Variations go with their product.' );
			$this->assertSame( 'product_variation', Selection\post_type_of( 'product_variation' ), 'Core still lists it.' );
		}
	}
}
