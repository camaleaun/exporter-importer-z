<?php
/**
 * WooCommerce compatibility, through the plugin's hooks only: its pages
 * (shop, cart, checkout, my account, terms) as linked options, a
 * "WooCommerce pages" group, its version options never exported, and
 * product galleries as media used.
 *
 * It is what WooCommerce itself could ship: hooked to
 * `eizport_register_compat`, so another copy (WooCommerce's own) replaces
 * it with
 *
 *     remove_action( 'eizport_register_compat', 'Eizport\Compat\WooCommerce\register' );
 *
 * @package Eizport
 */

namespace Eizport\Compat\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce's page options and their roles.
 *
 * @return array<string, string> Option => role.
 */
function pages() {
	return array(
		'woocommerce_shop_page_id'      => __( 'Shop page', 'eizport' ),
		'woocommerce_cart_page_id'      => __( 'Cart page', 'eizport' ),
		'woocommerce_checkout_page_id'  => __( 'Checkout page', 'eizport' ),
		'woocommerce_myaccount_page_id' => __( 'My account page', 'eizport' ),
		'woocommerce_terms_page_id'     => __( 'Terms and conditions page', 'eizport' ),
	);
}

/**
 * Hooks the compatibility, when WooCommerce is active.
 */
function register() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	add_filter( 'eizport_linked_options', __NAMESPACE__ . '\linked_options' );
	add_filter( 'eizport_option_groups', __NAMESPACE__ . '\option_groups' );
	add_filter( 'eizport_denied_options', __NAMESPACE__ . '\denied_options' );
	add_filter( 'eizport_media_used', __NAMESPACE__ . '\media_used', 10, 2 );
	add_filter( 'eizport_term_media', __NAMESPACE__ . '\term_media' );
	add_action( 'import_end', __NAMESPACE__ . '\refresh_imported_products', 20 );
	add_filter( 'eizport_suggested_items', __NAMESPACE__ . '\suggested_products', 10, 2 );
	add_action( 'export_wp', __NAMESPACE__ . '\reviews_to_export' );
}

/**
 * Leaves out of the WXR the product reviews the export didn't choose, when
 * it says which (`eizport_reviews` in export_wp()'s args: comment IDs);
 * without it, every review of the products exported goes, as WordPress
 * does. export_wp() has no filter for a post's comments, so its query
 * gets one more condition through `query`, as Selection\limit_query()
 * does for the posts.
 *
 * @param array $args Export args.
 */
function reviews_to_export( $args ) {
	if ( ! isset( $args['eizport_reviews'] ) ) {
		return;
	}
	add_filter( 'query', review_filter( (array) $args['eizport_reviews'] ) );
}

/**
 * The `query` filter that keeps only these reviews in export_wp()'s
 * comments query.
 *
 * @param int[] $ids Review (comment) IDs to keep.
 * @return callable
 */
function review_filter( $ids ) {
	$keep = implode( ',', array_merge( array( 0 ), array_map( 'absint', $ids ) ) );

	return function ( $query ) use ( $keep ) {
		global $wpdb;

		// "SELECT * FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_approved <> 'spam'" in export_wp().
		if ( ! preg_match( '/^SELECT \* FROM ' . preg_quote( $wpdb->comments, '/' ) . ' WHERE comment_post_ID = \d+ AND comment_approved <> \'spam\'$/', $query ) ) {
			return $query;
		}

		return $query . " AND ( comment_type <> 'review' OR comment_ID IN ( {$keep} ) )";
	};
}

/**
 * A page of the approved product reviews, for lists: searched, with only
 * the ones asked for (`include`).
 *
 * @param array $input `search`, `page`, `per_page` (1–100), `include`.
 * @return array{items: array, total: int, total_pages: int}
 */
function reviews_page( $input ) {
	$per_page = min( 100, max( 1, (int) ( $input['per_page'] ?? 20 ) ) );
	$query    = array(
		'post_type' => 'product',
		'type'      => 'review',
		'status'    => 'approve',
		'search'    => (string) ( $input['search'] ?? '' ),
	);
	if ( isset( $input['include'] ) ) {
		// None asked for, none listed (comment__in ignores an empty list).
		$query['comment__in'] = array_merge( array( 0 ), array_map( 'absint', (array) $input['include'] ) );
	}
	$total    = (int) get_comments( array_merge( $query, array( 'count' => true ) ) );
	$comments = get_comments(
		array_merge(
			$query,
			array(
				'number' => $per_page,
				'offset' => ( max( 1, (int) ( $input['page'] ?? 1 ) ) - 1 ) * $per_page,
			)
		)
	);

	return array(
		'items'       => array_map(
			function ( $comment ) {
				return array(
					'id'            => (int) $comment->comment_ID,
					'author'        => $comment->comment_author,
					'product'       => (int) $comment->comment_post_ID,
					'product_title' => get_the_title( (int) $comment->comment_post_ID ),
					'rating'        => (int) get_comment_meta( (int) $comment->comment_ID, 'rating', true ),
					'excerpt'       => wp_trim_words( wp_strip_all_tags( $comment->comment_content ), 20 ),
					'date'          => mysql_to_rfc3339( $comment->comment_date ),
				);
			},
			$comments
		),
		'total'       => $total,
		'total_pages' => (int) ceil( $total / $per_page ),
	);
}

/**
 * The products a post's content shows: Hand-picked Products
 * ("products"), Single Product and Featured Product ("productId"), Product
 * Collection's hand-picked ones ("woocommerceHandPickedProducts") and the
 * [products ids="…"] shortcode.
 *
 * @param string $content Post content.
 * @return int[] Product IDs.
 */
function products_in_content( $content ) {
	$ids = array();
	if ( preg_match_all( '/"(?:products|woocommerceHandPickedProducts)":\[([\d,\s"]*)\]/', $content, $lists ) ) {
		foreach ( $lists[1] as $list ) {
			$ids = array_merge( $ids, array_map( 'intval', preg_split( '/[^\d]+/', $list, -1, PREG_SPLIT_NO_EMPTY ) ) );
		}
	}
	if ( preg_match_all( '/"productId":"?(\d+)/', $content, $single ) ) {
		$ids = array_merge( $ids, array_map( 'intval', $single[1] ) );
	}
	if ( preg_match_all( '/\[products\b[^\]]*\bids="([\d,\s]+)"/', $content, $shortcodes ) ) {
		foreach ( $shortcodes[1] as $list ) {
			$ids = array_merge( $ids, array_map( 'intval', preg_split( '/[^\d]+/', $list, -1, PREG_SPLIT_NO_EMPTY ) ) );
		}
	}

	return array_values(
		array_filter(
			array_unique( $ids ),
			function ( $id ) {
				return 'product' === get_post_type( $id ) && 'publish' === get_post_status( $id );
			}
		)
	);
}

/**
 * Suggests the products a post's content shows.
 *
 * @param int[]    $suggested Post IDs.
 * @param \WP_Post $post      Post.
 * @return int[]
 */
function suggested_products( $suggested, $post ) {
	return array_merge( (array) $suggested, products_in_content( (string) $post->post_content ) );
}

/**
 * A product's gallery images go with it.
 *
 * @param int[]    $used Attachment IDs.
 * @param \WP_Post $post Post.
 * @return int[]
 */
function media_used( $used, $post ) {
	if ( 'product' === $post->post_type || 'product_variation' === $post->post_type ) {
		$used = array_merge( $used, array_map( 'absint', array_filter( explode( ',', (string) get_post_meta( $post->ID, '_product_image_gallery', true ) ) ) ) );
	}
	// Its categories' images (thumbnail_id), mapped on import (term_media()).
	if ( 'product' === $post->post_type ) {
		foreach ( wp_get_post_terms( $post->ID, 'product_cat', array( 'fields' => 'ids' ) ) as $term_id ) {
			$used[] = (int) get_term_meta( (int) $term_id, 'thumbnail_id', true );
		}
	}

	return array_values( array_filter( $used ) );
}

/**
 * Product categories keep their image's ID in thumbnail_id.
 *
 * @param array<string, string[]> $keys Taxonomy => meta keys.
 * @return array<string, string[]>
 */
function term_media( $keys ) {
	$keys['product_cat'] = array_merge( (array) ( $keys['product_cat'] ?? array() ), array( 'thumbnail_id' ) );

	return $keys;
}

/**
 * When an import ends, saves each imported product (and variation) the way
 * WooCommerce does, which fills its lookup table (wc_product_meta_lookup)
 * and clears its transients: the importer inserts posts directly.
 *
 * @param array|null $posts Old post ID => new (default: the importer's).
 * @return int How many products were saved.
 */
function refresh_imported_products( $posts = null ) {
	$importer = $GLOBALS['wp_import'] ?? null;
	$posts    = is_array( $posts ) ? $posts : ( is_object( $importer ) ? (array) $importer->processed_posts : array() );
	if ( ! function_exists( 'wc_get_product' ) ) {
		return 0;
	}
	$saved = 0;
	$store = \WC_Data_Store::load( 'product' );
	// WooCommerce 10.8+ refreshes a row on request; save() alone only does when a lookup prop changed.
	$refresh = method_exists( $store->get_current_class_name(), 'refresh_product_lookup_table' ); // The store forwards calls to this class.
	foreach ( array_unique( array_map( 'intval', $posts ) ) as $id ) {
		if ( ! in_array( get_post_type( $id ), array( 'product', 'product_variation' ), true ) ) {
			continue;
		}
		$product = wc_get_product( $id );
		if ( $product ) {
			$product->save();
			if ( $refresh ) {
				$store->refresh_product_lookup_table( $id );
			}
			++$saved;
		}
	}
	if ( $saved && ! $refresh && function_exists( 'wc_update_product_lookup_tables' ) ) {
		// Older WooCommerce: rebuild the tables in the background.
		wc_update_product_lookup_tables();
	}

	return $saved;
}

/**
 * Adds WooCommerce's pages to the linked options.
 *
 * @param array $linked Linked options.
 * @return array
 */
function linked_options( $linked ) {
	foreach ( pages() as $option => $label ) {
		$linked[ $option ] = array( 'label' => $label );
	}

	return $linked;
}

/**
 * Adds the "WooCommerce pages" group.
 *
 * @param array $groups Groups of options.
 * @return array
 */
function option_groups( $groups ) {
	$groups['woocommerce'] = array(
		'label'   => __( 'WooCommerce pages: shop, cart, checkout, my account, terms', 'eizport' ),
		'options' => array_keys( pages() ),
	);

	return $groups;
}

/**
 * WooCommerce's versions describe this site's database: never exported.
 *
 * @param string[] $names Option names.
 * @return string[]
 */
function denied_options( $names ) {
	return array_merge( $names, array( 'woocommerce_version', 'woocommerce_db_version' ) );
}
