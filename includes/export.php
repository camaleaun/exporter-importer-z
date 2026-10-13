<?php
/**
 * Additions to the core Tools → Export screen.
 *
 * Content types, by filters or by selection: see selection.php.
 *
 * Bundle: get a .wxrz (the WXR plus, optionally, the attachment files and
 * the content as .html files) instead of core's .xml, with a file name of
 * your choice, or a Playground .zip holding it; after downloading a .zip,
 * the screen shows the command that runs it and, when a copy is kept, the
 * link that opens it on playground.wordpress.net.
 *
 * Settings (options) can go in a bundle too: see options.php.
 *
 * @package Eizport
 */

namespace Eizport\Export;

defined( 'ABSPATH' ) || exit;

const BUNDLE_FIELD    = 'eizport_bundle';
const FILENAME_FIELD  = 'eizport_filename';
const MEDIA_FIELD     = 'eizport_media';
const BLUEPRINT_FIELD = 'eizport_blueprint';
const THEMES_FIELD    = 'eizport_themes';
const PLUGINS_FIELD   = 'eizport_plugins';
const KEEP_FIELD      = 'eizport_keep_copy';
const SAVE_FIELD      = 'eizport_save_defaults';
const SAVE_NONCE      = 'eizport_save_defaults_nonce';

/**
 * Registers the hooks.
 */
function bootstrap() {
	add_action( 'export_filters', __NAMESPACE__ . '\file_options', 20 );
	add_filter( 'export_args', __NAMESPACE__ . '\file_args' );
	add_action( 'export_wp', __NAMESPACE__ . '\file_output' );
}

/**
 * Fills the classic screen with the saved defaults (Defaults\get()), once
 * the type checkboxes exist: types and their items or filters, the bundle,
 * its file name and media, settings, Playground themes and plugins.
 */
function defaults_script() {
	$defaults = \Eizport\Defaults\get();
	if ( ! $defaults ) {
		return;
	}
	$contents = \Eizport\Selection\types();
	?>
	<script>
		// After the selection script's own jQuery ready handlers, which react to the changes.
		( window.jQuery || function ( run ) {
			document.addEventListener( 'DOMContentLoaded', run );
		} )( function () {
			var defaults = <?php echo wp_json_encode( $defaults ); ?>;
			var contents = <?php echo wp_json_encode( $contents ); ?>;
			var form = document.getElementById( 'export-filters' );
			if ( ! form ) {
				return;
			}
			var set = function ( input, value ) {
				if ( input && input.checked !== value ) {
					input.checked = value;
					input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}
			};
			var named = function ( name, value ) {
				return form.querySelector( '[name="' + name + '"]' + ( undefined === value ? '' : '[value="' + value + '"]' ) );
			};
			var settings = defaults.settings || {};

			Object.keys( defaults.types || {} ).forEach( function ( type ) {
				var spec = defaults.types[ type ];
				var box = form.querySelector( '.eizport-type-box[value="' + ( contents[ type ] || type ) + '"]' );
				set( box, true );
				if ( spec.ids ) {
					set( named( 'eizport_mode[' + type + ']', 'select' ), true );
					spec.ids.forEach( function ( id ) {
						set( named( 'eizport_ids[' + type + '][]', id ), true );
					} );
				} else if ( spec.filters && spec.filters.status ) {
					var status = named( 'eizport_filters[' + type + '][status]' ) || named( type + '_status' );
					if ( status ) {
						status.value = spec.filters.status;
					}
				}
			} );

			var bundle = 'playground' === settings.format || settings.media || ( settings.option_groups || [] ).length || ( settings.option_names || [] ).length;
			set( document.getElementById( 'eizport-bundle' ), !! bundle );
			if ( settings.filename ) {
				document.getElementById( 'eizport-filename' ).value = settings.filename;
			}
			set( named( <?php echo wp_json_encode( MEDIA_FIELD ); ?> ), !! settings.media );
			( settings.option_groups || [] ).forEach( function ( group ) {
				set( named( <?php echo wp_json_encode( \Eizport\Options\GROUPS_FIELD . '[]' ); ?>, group ), true );
			} );
			if ( ( settings.option_names || [] ).length ) {
				document.getElementById( 'eizport-options-extra' ).value = settings.option_names.join( ', ' );
			}
			set( document.getElementById( 'eizport-blueprint' ), 'playground' === settings.format );
			form.querySelectorAll( 'input[name="<?php echo esc_js( THEMES_FIELD ); ?>[]"]' ).forEach( function ( input ) {
				if ( 'playground' === settings.format ) {
					set( input, ( settings.themes || [] ).indexOf( input.value ) !== -1 );
				}
			} );
			( settings.plugins || [] ).forEach( function ( plugin ) {
				set( named( <?php echo wp_json_encode( PLUGINS_FIELD . '[]' ); ?>, plugin ), true );
			} );
		} );
	</script>
	<?php
}

/**
 * A bundle's extension: "wxrz", or "zip" with Settings → Export → "Bundle
 * as .zip".
 *
 * @return string
 */
function bundle_extension() {
	return \Eizport\Settings\get()['bundle_zip'] ? 'zip' : \Eizport\Wxrz\EXTENSION;
}

/**
 * Core's file name without extension: "sitename.WordPress.2026-10-10".
 *
 * @return string
 */
function default_filename() {
	$sitename = sanitize_key( get_bloginfo( 'name' ) );

	return ( '' === $sitename ? '' : $sitename . '.' ) . 'WordPress.' . gmdate( 'Y-m-d' );
}

/**
 * Prints the Bundle option and its settings right above the download button.
 * Without Bundle the export is core's .xml, named by core. Nothing when
 * Tools → Export isn't replaced: WordPress's screen is left as it is.
 */
function file_options() {
	if ( ! \Eizport\AdminApp\replaces_export() ) {
		return;
	}
	?>
	<p>
		<label>
			<input type="checkbox" id="eizport-bundle" name="<?php echo esc_attr( BUNDLE_FIELD ); ?>" value="1" />
			<?php
			/* translators: %s: bundle extension, "wxrz" or "zip". */
			echo esc_html( sprintf( __( 'Bundle (.%s): the WXR and, optionally, its media and content files, compressed', 'eizport' ), bundle_extension() ) );
			?>
		</label>
	</p>
	<fieldset id="eizport-bundle-options" style="margin-left: 1.75em;" disabled="disabled">
		<p>
			<label for="eizport-filename"><?php esc_html_e( 'File name:', 'eizport' ); ?></label>
			<input type="text" class="regular-text" id="eizport-filename" name="<?php echo esc_attr( FILENAME_FIELD ); ?>" value="" placeholder="<?php echo esc_attr( default_filename() ); ?>" />
			<code id="eizport-extension">.<?php echo esc_html( bundle_extension() ); ?></code>
		</p>
		<p>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( MEDIA_FIELD ); ?>" value="1" checked="checked" />
				<?php esc_html_e( 'Include media: the files of the exported attachments, so the importer doesn\'t download them from this site', 'eizport' ); ?>
			</label>
		</p>
		<?php \Eizport\Options\render(); ?>
		<p>
			<label>
				<input type="checkbox" id="eizport-blueprint" name="<?php echo esc_attr( BLUEPRINT_FIELD ); ?>" value="1" />
				<?php esc_html_e( 'Include a Playground blueprint', 'eizport' ); ?>
			</label>
		</p>
		<p class="description" style="margin-left: 1.75em;">
			<?php esc_html_e( 'The download becomes a .zip with blueprint.json, the WXR (.xml) and, as checked above, the attachment files and the content as .html files: run it with WordPress Playground to get a site with the exported content.', 'eizport' ); ?>
			<br />
			<code><?php echo esc_html( \Eizport\Copies\command( '<file>.zip' ) ); ?></code>
		</p>
		<fieldset id="eizport-blueprint-options" style="margin-left: 1.75em;" disabled="disabled">
			<p>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( KEEP_FIELD ); ?>" value="1" />
					<?php
					/* translators: %s: how long the copy lasts, like "1 day" or "until deleted". */
					echo esc_html( sprintf( __( 'Keep a copy (%s) to open by link on playground.wordpress.net', 'eizport' ), \Eizport\Copies\lifetime_label() ) );
					?>
				</label>
			</p>
			<p class="description" style="margin-left: 1.75em;">
				<?php
				printf(
					/* translators: %s: link to the plugin's settings. */
					esc_html__( 'The copy goes to uploads/eizport/tmp/, anyone with its link can download it, and it is deleted when it expires (the time is set in the same settings); manage the copies in %s.', 'eizport' ),
					'<a href="' . esc_url( \Eizport\Settings\page_url( '/copies' ) ) . '">' . esc_html__( 'Eizport settings', 'eizport' ) . '</a>'
				);
				?>
			</p>
			<?php blueprint_options(); ?>
		</fieldset>
	</fieldset>
	<?php if ( \Eizport\Defaults\can_save() ) : ?>
		<p>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( SAVE_FIELD ); ?>" value="1" />
				<?php
				echo esc_html(
					\Eizport\Defaults\site_wide()
						? __( 'Save these choices as the site\'s default', 'eizport' )
						: __( 'Save these choices as my default', 'eizport' )
				);
				?>
			</label>
			<?php wp_nonce_field( SAVE_FIELD, SAVE_NONCE, false ); ?>
		</p>
	<?php endif; ?>
	<?php defaults_script(); ?>
	<div id="eizport-download-notice" class="notice notice-success inline" hidden="hidden"></div>
	<style>
		/* Fieldsets shrink with the screen (browsers give them min-content). */
		#export-filters fieldset { min-width: 0; }
		#export-filters .regular-text { max-width: 100%; box-sizing: border-box; }
		@media screen and (max-width: 782px) {
			/* A whole row to tap, 40px high, the box as core draws it. */
			#export-filters label:has(> input[type="checkbox"]),
			#export-filters label:has(> input[type="radio"]) {
				display: flex;
				align-items: center;
				gap: 8px;
				min-height: 40px;
				line-height: 1.4;
			}
			#export-filters label > input[type="checkbox"],
			#export-filters label > input[type="radio"] { flex-shrink: 0; margin: 0; }
			#export-filters label + br { display: none; }
			/* Nested blocks keep a small indent. */
			#eizport-bundle-options,
			#eizport-blueprint-options,
			#export-filters .description[style*="margin-left"] { margin-left: 0.75em !important; }
			#export-filters .eizport-checklist,
			#export-filters .eizport-checklist-items { max-width: 100%; box-sizing: border-box; line-height: 1.4; }
			#eizport-extension { display: inline-block; margin-top: 4px; }
			#export-filters code { overflow-wrap: anywhere; }
		}
	</style>
	<?php if ( current_user_can( 'import' ) ) : ?>
		<p class="description">
			<?php
			if ( \Eizport\Import\importer_active() ) {
				/* translators: %s: link to the WordPress Importer screen. */
				$eizport_note = __( 'Import .xml and .wxrz files with %s.', 'eizport' );
				$eizport_link = __( 'Tools → Import → WordPress', 'eizport' );
			} else {
				/* translators: %s: link to Tools → Import. */
				$eizport_note = __( 'To import .xml and .wxrz files, install the WordPress Importer from %s.', 'eizport' );
				$eizport_link = __( 'Tools → Import', 'eizport' );
			}
			printf(
				esc_html( $eizport_note ),
				'<a href="' . esc_url( \Eizport\Import\importer_url() ) . '">' . esc_html( $eizport_link ) . '</a>'
			);
			?>
		</p>
	<?php endif; ?>
	<script>
		( function () {
			var bundle = document.getElementById( 'eizport-bundle' );
			var blueprint = document.getElementById( 'eizport-blueprint' );
			var extension = document.getElementById( 'eizport-extension' );
			var options = document.getElementById( 'eizport-bundle-options' );
			bundle.addEventListener( 'change', function () {
				options.disabled = ! bundle.checked;
			} );
			blueprint.addEventListener( 'change', function () {
				document.getElementById( 'eizport-blueprint-options' ).disabled = ! blueprint.checked;
				extension.textContent = blueprint.checked ? '.zip' : '.<?php echo esc_js( bundle_extension() ); ?>';
			} );

			// After a Playground .zip is downloaded, the response's cookie says its name and copy link.
			var notice = document.getElementById( 'eizport-download-notice' );
			var cookie = <?php echo wp_json_encode( \Eizport\Copies\COOKIE ); ?>;
			var path = <?php echo wp_json_encode( ADMIN_COOKIE_PATH ); ?>;
			var text = <?php echo wp_json_encode( download_notice_texts() ); ?>;
			var timer;

			function readCookie() {
				var match = document.cookie.match( new RegExp( '(?:^|; )' + cookie + '=([^;]*)' ) );
				if ( ! match ) {
					return null;
				}
				document.cookie = cookie + '=; Max-Age=0; path=' + path;
				try {
					return JSON.parse( decodeURIComponent( match[ 1 ] ) );
				} catch ( e ) {
					return null;
				}
			}

			function element( tag, content, attributes ) {
				var node = document.createElement( tag );
				node.textContent = content || '';
				Object.keys( attributes || {} ).forEach( function ( name ) {
					node.setAttribute( name, attributes[ name ] );
				} );
				return node;
			}

			function copyButton( value, label ) {
				var button = element( 'button', label, { type: 'button', 'class': 'button button-small' } );
				button.addEventListener( 'click', function () {
					navigator.clipboard.writeText( value ).then( function () {
						button.textContent = text.copied;
						setTimeout( function () {
							button.textContent = label;
						}, 2000 );
					} );
				} );
				return button;
			}

			function show( data ) {
				var line;
				notice.textContent = '';
				line = element( 'p', text.downloaded + ' ' );
				line.appendChild( element( 'code', data.file ) );
				notice.appendChild( line );

				line = element( 'p', text.run + ' ' );
				line.appendChild( element( 'code', data.command ) );
				line.appendChild( document.createTextNode( ' ' ) );
				line.appendChild( copyButton( data.command, text.copy ) );
				notice.appendChild( line );

				if ( data.playground ) {
					line = element( 'p', text.kept + ' ' );
					line.appendChild( element( 'a', text.open, { href: data.playground, target: '_blank', rel: 'noopener' } ) );
					line.appendChild( document.createTextNode( ' ' ) );
					line.appendChild( copyButton( data.playground, text.copyLink ) );
					notice.appendChild( line );
				}
				notice.hidden = false;
			}

			bundle.closest( 'form' ).addEventListener( 'submit', function ( event ) {
				var tries = 0;
				if ( event.defaultPrevented || ! bundle.checked || ! blueprint.checked ) {
					return;
				}
				readCookie();
				notice.hidden = true;
				clearInterval( timer );
				timer = setInterval( function () {
					var data = readCookie();
					if ( data || ++tries > 1200 ) {
						clearInterval( timer );
					}
					if ( data ) {
						show( data );
					}
				}, 500 );
			} );
		} )();
	</script>
	<?php
}

/**
 * Texts of the notice shown after a Playground .zip is downloaded.
 *
 * @return array<string, string>
 */
function download_notice_texts() {
	return array(
		'downloaded' => __( 'Playground .zip downloaded:', 'eizport' ),
		'run'        => __( 'Run it in a terminal, in the folder you saved it to:', 'eizport' ),
		'kept'       => __( 'Or open the copy kept on this site until it expires (anyone with the link can download it):', 'eizport' ),
		'open'       => __( 'Open in WordPress Playground', 'eizport' ),
		'copy'       => __( 'Copy', 'eizport' ),
		'copyLink'   => __( 'Copy link', 'eizport' ),
		'copied'     => __( 'Copied!', 'eizport' ),
	);
}

/**
 * Prints the themes and plugins that can go in the Playground .zip. The
 * active theme comes checked.
 */
function blueprint_options() {
	$themes  = \Eizport\Playground\theme_choices();
	$plugins = \Eizport\Playground\plugin_choices();
	?>
	<div class="eizport-blueprint-lists">
		<fieldset>
			<legend><?php esc_html_e( 'Themes to install:', 'eizport' ); ?></legend>
			<div class="eizport-checklist">
				<?php foreach ( $themes as $stylesheet => $theme ) : ?>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( THEMES_FIELD ); ?>[]" value="<?php echo esc_attr( $stylesheet ); ?>" <?php checked( get_stylesheet(), $stylesheet ); ?> />
						<?php
						echo esc_html( $theme->display( 'Name', false ) );
						if ( get_stylesheet() === $stylesheet ) {
							echo ' — ' . esc_html__( 'active', 'eizport' );
						}
						if ( $theme->parent() ) {
							/* translators: %s: parent theme name. */
							echo ' — ' . esc_html( sprintf( __( 'child of %s, installed too', 'eizport' ), $theme->parent()->display( 'Name', false ) ) );
						}
						?>
					</label><br />
				<?php endforeach; ?>
			</div>
		</fieldset>
		<fieldset>
			<legend><?php esc_html_e( 'Plugins to install and activate:', 'eizport' ); ?></legend>
			<div class="eizport-checklist">
				<?php if ( ! $plugins ) : ?>
					<?php esc_html_e( 'No other plugins.', 'eizport' ); ?>
				<?php endif; ?>
				<?php foreach ( $plugins as $file => $data ) : ?>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( PLUGINS_FIELD ); ?>[]" value="<?php echo esc_attr( $file ); ?>" />
						<?php echo esc_html( '' === $data['Name'] ? $file : $data['Name'] ); ?>
					</label><br />
				<?php endforeach; ?>
			</div>
		</fieldset>
		<p class="description"><?php esc_html_e( 'The active theme is activated, if checked; else the first theme checked.', 'eizport' ); ?></p>
		<p class="description"><?php esc_html_e( 'Any installed theme or plugin can go in. The ones from the WordPress.org directory are installed from there, in the version this site has; premium and custom ones are copied into the file: share it only where their licenses allow.', 'eizport' ); ?></p>
	</div>
	<style>
		.eizport-blueprint-lists fieldset { margin: 0.5em 0 1em; }
		.eizport-blueprint-lists legend { margin-bottom: 0.25em; }
		.eizport-checklist { max-height: 12em; overflow: auto; max-width: 30em; padding: 0.5em 0.75em; border: 1px solid #c3c4c7; background: #fff; line-height: 2; }
	</style>
	<?php
}

/**
 * Adds the bundle settings to the export args: `bundle`, `media`,
 * `post_content`, `blueprint` with its `themes` and `plugins` (only
 * installed ones) and `keep_copy`, and, for a bundle, `filename` and
 * `options` (names of the options to include). Unchanged when Tools →
 * Export isn't replaced.
 *
 * @param array $args Export args.
 * @return array
 */
function file_args( $args ) {
	if ( ! \Eizport\AdminApp\replaces_export() ) {
		return $args;
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$field = function ( $name ) {
		return ! empty( $_GET[ $name ] );
	};
	$list  = function ( $name ) {
		return isset( $_GET[ $name ] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_GET[ $name ] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	};
	if ( $field( SAVE_FIELD ) && isset( $_GET[ SAVE_NONCE ] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_GET[ SAVE_NONCE ] ) ), SAVE_FIELD ) && \Eizport\Defaults\can_save() ) {
		$bundle = $field( BUNDLE_FIELD );
		\Eizport\Defaults\save(
			array(
				'types'    => \Eizport\Selection\specs_from_request( $args ),
				'settings' => array(
					'format'        => $bundle && $field( BLUEPRINT_FIELD ) ? 'playground' : 'wxr',
					'filename'      => isset( $_GET[ FILENAME_FIELD ] ) ? sanitize_file_name( wp_unslash( $_GET[ FILENAME_FIELD ] ) ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					'media'         => $bundle && $field( MEDIA_FIELD ),
					'option_groups' => $bundle ? $list( \Eizport\Options\GROUPS_FIELD ) : array(),
					'option_names'  => $bundle && isset( $_GET[ \Eizport\Options\EXTRA_FIELD ] ) ? \Eizport\Options\parse_names( sanitize_textarea_field( wp_unslash( $_GET[ \Eizport\Options\EXTRA_FIELD ] ) ) ) : array(),
					'themes'        => $list( THEMES_FIELD ),
					'plugins'       => $list( PLUGINS_FIELD ),
				),
			)
		);
	}
	$settings = bundle_settings(
		array(
			'bundle'       => $field( BUNDLE_FIELD ),
			'blueprint'    => $field( BLUEPRINT_FIELD ),
			'media'        => $field( MEDIA_FIELD ),
			// Settings → Eizport → General.
			'post_content' => \Eizport\Settings\get()['content_files'],
			'linked'       => in_array( \Eizport\Settings\get()['linked_options'], array( 'export', 'both' ), true ),
			'keep_copy'    => $field( KEEP_FIELD ),
			'options'      => $field( BUNDLE_FIELD ) ? \Eizport\Options\requested_names() : array(),
			'themes'       => $list( THEMES_FIELD ),
			'plugins'      => $list( PLUGINS_FIELD ),
			'filename'     => isset( $_GET[ FILENAME_FIELD ] ) ? sanitize_file_name( wp_unslash( $_GET[ FILENAME_FIELD ] ) ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		)
	);
	// phpcs:enable

	return array_merge( $args, $settings );
}

/**
 * Validates bundle settings, from the form or from an ability: `bundle`,
 * `blueprint` (only with bundle), `media` and `post_content` (only with
 * bundle), `keep_copy` (only with blueprint), `options` (names, only with
 * bundle), `themes` and `plugins` (installed ones, only with blueprint)
 * and, for a bundle, `filename` (without extension; empty: the default).
 *
 * @param array $in Settings asked for.
 * @return array
 */
function bundle_settings( $in ) {
	$out              = array(
		'bundle' => ! empty( $in['bundle'] ),
	);
	$out['blueprint'] = $out['bundle'] && ! empty( $in['blueprint'] );
	// The Playground .zip holds the plain WXR, with the same media and content files as a .wxrz.
	$out['media']        = $out['bundle'] && ! empty( $in['media'] );
	$out['post_content'] = $out['bundle'] && ! empty( $in['post_content'] );
	$out['keep_copy']    = $out['blueprint'] && ! empty( $in['keep_copy'] );
	// A plugin .zip holding the bundle (not with the Playground .zip).
	$out['as_plugin'] = $out['bundle'] && ! $out['blueprint'] && ! empty( $in['as_plugin'] );
	// The bundle asks its importer to update the items that already exist (not with
	// the Playground .zip: Playground starts from a new site).
	$out['update_existing'] = $out['bundle'] && ! $out['blueprint'] && ! empty( $in['update_existing'] );
	$out['options']         = $out['bundle'] ? array_values( array_filter( (array) ( $in['options'] ?? array() ), 'is_string' ) ) : array();
	$out['linked']          = $out['bundle'] && ! empty( $in['linked'] );
	// A plain .zip instead of the .wxrz (gzipped tar): as asked, else as Settings → Export says.
	$out['zip']     = $out['bundle'] && ! $out['blueprint'] && ! $out['as_plugin'] && ( array_key_exists( 'zip', $in ) ? ! empty( $in['zip'] ) : \Eizport\Settings\get()['bundle_zip'] );
	$out['themes']  = array();
	$out['plugins'] = array();
	if ( $out['blueprint'] ) {
		$out['themes']  = array_values( array_intersect( array_unique( array_map( 'strval', (array) ( $in['themes'] ?? array() ) ) ), array_keys( \Eizport\Playground\theme_choices() ) ) );
		$out['plugins'] = array_values( array_intersect( array_unique( array_map( 'strval', (array) ( $in['plugins'] ?? array() ) ) ), array_keys( \Eizport\Playground\plugin_choices() ) ) );
	}
	if ( $out['bundle'] ) {
		$name = preg_replace( '/\.(xml|zip|' . \Eizport\Wxrz\EXTENSION . ')$/i', '', sanitize_file_name( (string) ( $in['filename'] ?? '' ) ) );

		$out['filename'] = '' === $name ? default_filename() : $name;
	}

	return $out;
}

/**
 * For a bundle, names the file and swaps the WXR output for the archive (or
 * for the Playground .zip). Runs before export_wp() sets its headers and
 * prints the WXR. Without Bundle, core's .xml goes out untouched.
 *
 * @param array $args Export args.
 */
function file_output( $args ) {
	if ( empty( $args['bundle'] ) ) {
		return;
	}

	$name      = $args['filename'] ?? default_filename();
	$blueprint = ! empty( $args['blueprint'] );
	$as_plugin = ! empty( $args['as_plugin'] );
	// Read when the manifest (and a plugin's header) are written.
	$GLOBALS['eizport_update_existing'] = ! empty( $args['update_existing'] );
	$zip                                = $blueprint || $as_plugin || ! empty( $args['zip'] );
	$extension                          = $zip ? 'zip' : \Eizport\Wxrz\EXTENSION;
	$type                               = $zip ? 'application/zip' : 'application/gzip';

	add_filter(
		'export_wp_filename',
		function () use ( $name, $extension ) {
			return $name . '.' . $extension;
		}
	);

	$target = (string) ( $args['eizport_target'] ?? '' );
	if ( '' === $target ) {
		// export_wp() sends text/xml after this; replace it right before headers go out.
		header_register_callback(
			function () use ( $type ) {
				header( 'Content-Type: ' . $type );
			}
		);
	}

	\Eizport\Bundle\start_export(
		default_filename() . '.xml',
		array(
			'media'        => ! empty( $args['media'] ),
			'blueprint'    => $blueprint,
			'themes'       => $args['themes'] ?? array(),
			'plugins'      => $args['plugins'] ?? array(),
			'keep_copy'    => ! empty( $args['keep_copy'] ),
			'option_names' => $args['options'] ?? array(),
			'linked'       => ! empty( $args['linked'] ),
			'zip'          => ! empty( $args['zip'] ),
			'as_plugin'    => $as_plugin,
			'download'     => $name . '.' . $extension,
			'target'       => $target,
		)
	);
	if ( ! empty( $args['post_content'] ) ) {
		\Eizport\Bundle\start_content_files();
	}
}
