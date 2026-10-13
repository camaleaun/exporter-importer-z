<?php
/**
 * Applies the options of a bundle after its import, with the IDs they hold
 * mapped to the imported items — never the ones that would break, move or
 * open the site (eizport_never_set_option()): used by the plugin, and written by a
 * Playground blueprint as the temporary must-use plugin
 * wp-content/mu-plugins/eizport-options.php (so it can't use the plugin's
 * namespace or other files).
 *
 * The options file (options.json) holds `options` (name => value) and
 * `post_ids` (names of options holding a post ID, like page_on_front).
 *
 * @package Eizport
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'eizport_never_set_option' ) ) {
	/**
	 * Whether an option is never exported nor set by an import, whatever the
	 * bundle holds: it would break the site, move it, or open it (URLs, keys,
	 * active plugins, roles, who may register and as what, transients).
	 *
	 * @param string $name Option name.
	 * @return bool
	 */
	function eizport_never_set_option( $name ) {
		global $wpdb;

		$never = array( 'siteurl', 'home', 'admin_email', 'new_admin_email', 'active_plugins', 'active_sitewide_plugins', 'recently_activated', 'uninstall_plugins', 'template', 'stylesheet', 'cron', 'rewrite_rules', 'db_version', 'initial_db_version', 'db_upgraded', 'auth_key', 'auth_salt', 'logged_in_key', 'logged_in_salt', 'nonce_key', 'nonce_salt', 'secure_auth_key', 'secure_auth_salt', 'recovery_keys', 'mailserver_url', 'mailserver_login', 'mailserver_pass', 'mailserver_port', 'users_can_register', 'default_role', $wpdb->prefix . 'user_roles', 'eizport_settings' );

		return in_array( $name, $never, true ) || (bool) preg_match( '/^_(site_)?transient_/', $name );
	}
}

if ( ! function_exists( 'eizport_options_to_apply' ) ) {
	/**
	 * The options to set, with post and term IDs mapped to the imported
	 * ones. An option holding a post that wasn't imported is left out (and
	 * show_on_front = page with it, when it's the homepage); a theme mod
	 * holding one loses that key.
	 *
	 * @param array $data  The options file's data.
	 * @param array $posts Old post ID => new ID (WP_Import::$processed_posts).
	 * @param array $terms Old term ID => new ID (WP_Import::$processed_terms).
	 * @return array Option name => value.
	 */
	function eizport_options_to_apply( $data, $posts, $terms ) {
		$options  = isset( $data['options'] ) && is_array( $data['options'] ) ? $data['options'] : array();
		$post_ids = isset( $data['post_ids'] ) && is_array( $data['post_ids'] ) ? $data['post_ids'] : array();
		$apply    = array();

		foreach ( $options as $name => $value ) {
			$name = (string) $name;
			// A bundle is a file anyone could have written: these are never set.
			if ( eizport_never_set_option( $name ) ) {
				continue;
			}
			if ( in_array( $name, $post_ids, true ) && is_scalar( $value ) ) {
				$old = (int) $value;
				if ( $old && ! isset( $posts[ $old ] ) ) {
					continue;
				}
				$value = $old ? (int) $posts[ $old ] : 0;
			}
			if ( 0 === strpos( $name, 'theme_mods_' ) && is_array( $value ) ) {
				if ( isset( $value['custom_logo'] ) ) {
					$old = (int) $value['custom_logo'];
					if ( isset( $posts[ $old ] ) ) {
						$value['custom_logo'] = (int) $posts[ $old ];
					} else {
						unset( $value['custom_logo'] );
					}
				}
				if ( isset( $value['nav_menu_locations'] ) && is_array( $value['nav_menu_locations'] ) ) {
					foreach ( $value['nav_menu_locations'] as $location => $menu ) {
						if ( isset( $terms[ (int) $menu ] ) ) {
							$value['nav_menu_locations'][ $location ] = (int) $terms[ (int) $menu ];
						} else {
							unset( $value['nav_menu_locations'][ $location ] );
						}
					}
				}
			}
			$apply[ $name ] = $value;
		}

		// A static homepage needs its page.
		if ( isset( $apply['show_on_front'] ) && 'page' === $apply['show_on_front'] && empty( $apply['page_on_front'] ) && in_array( 'page_on_front', $post_ids, true ) && isset( $options['page_on_front'] ) ) {
			unset( $apply['show_on_front'] );
		}

		return $apply;
	}

	/**
	 * Sets the options of a bundle's options file.
	 *
	 * @param array $data  The options file's data.
	 * @param array $posts Old post ID => new ID.
	 * @param array $terms Old term ID => new ID.
	 * @return string[] Names of the options set.
	 */
	function eizport_apply_options( $data, $posts, $terms ) {
		$apply = eizport_options_to_apply( $data, $posts, $terms );
		foreach ( $apply as $name => $value ) {
			update_option( $name, $value );
		}

		return array_keys( $apply );
	}
}
