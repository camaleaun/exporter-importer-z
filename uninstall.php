<?php
/**
 * Removes what the plugin leaves behind: uploads/eizport/ (temporary
 * Playground copies and folders of bundles whose import was never
 * finished), the settings, the saved export defaults (the site's and each
 * user's), what bundle plugins noted and the cleanup event.
 *
 * @package Eizport
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
$eizport_folder = wp_get_upload_dir()['basedir'] . '/eizport';
if ( is_dir( $eizport_folder ) ) {
	( new WP_Filesystem_Direct( null ) )->rmdir( $eizport_folder, true );
}

wp_clear_scheduled_hook( 'eizport_cleanup' );
delete_option( 'eizport_settings' );
delete_option( 'eizport_export_defaults' );
delete_option( 'eizport_bundle_plugins_before' );
delete_option( 'eizport_bundle_plugins_imported' );
delete_metadata( 'user', 0, 'eizport_export_defaults', '', true );
