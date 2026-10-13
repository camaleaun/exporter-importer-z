/**
 * A bundle plugin's import screen (includes/bundle-plugin.php): "Run
 * import" says it is importing, and can't be pressed twice, while the
 * page waits for the import. "Install Now" installs and activates the
 * WordPress Importer here, like Tools → Import installs an importer, with
 * core's updates script; the requirement then shows ✓ and "Run import"
 * can go.
 */
( function () {
	var texts = window.eizportBundlePlugin || {};

	document.querySelectorAll( '.eizport-bundle-import' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function () {
			var button = form.querySelector( 'button[type="submit"]' );
			if ( button ) {
				button.disabled = true;
				button.classList.add( 'is-busy' );
				button.textContent = texts.importing || button.textContent;
			}
		} );
	} );

	if ( ! window.wp || ! wp.updates ) {
		// Core's install screen, through the link.
		return;
	}

	/**
	 * Shows a step on the button, and says it.
	 *
	 * @param {HTMLElement} button The Install Now button.
	 * @param {string}      text   Its text.
	 */
	function step( button, text ) {
		button.classList.add( 'updating-message', 'button-disabled' );
		button.setAttribute( 'aria-disabled', 'true' );
		button.textContent = text;
		wp.a11y.speak( text );
	}

	/**
	 * Puts the button back, with the error in a notice.
	 *
	 * @param {HTMLElement} button   The Install Now button.
	 * @param {string}      label    Its text.
	 * @param {Object}      response The failed request's answer.
	 */
	function fail( button, label, response ) {
		button.classList.remove( 'updating-message', 'button-disabled' );
		button.removeAttribute( 'aria-disabled' );
		button.textContent = label;
		if ( response && response.errorMessage ) {
			wp.updates.addAdminNotice( {
				id: 'eizport-install-error',
				className: 'notice-error is-dismissible',
				message: ( texts.failed || '%s' ).replace( '%s', response.errorMessage ),
			} );
			wp.a11y.speak( response.errorMessage, 'assertive' );
		}
	}

	/**
	 * Runs once core's updates script takes a new request: while one runs,
	 * another is queued, and its queue drops activations.
	 *
	 * @param {Function} callback What to run.
	 */
	function whenFree( callback ) {
		if ( wp.updates.ajaxLocked ) {
			setTimeout( function () {
				whenFree( callback );
			}, 50 );
			return;
		}
		callback();
	}

	/**
	 * After the install: activates the plugin, then shows the requirement met
	 * and lets "Run import" go.
	 *
	 * @param {HTMLElement} button The Install Now button.
	 * @param {string}      label  Its text.
	 */
	function activate( button, label ) {
		var data = button.dataset;
		wp.updates.activatePlugin( {
			name: data.name,
			slug: data.slug,
			plugin: data.plugin,
			success: function () {
				var row = button.closest( '.eizport-requirement' );
				var message = ( texts.installed || '%s' ).replace( '%s', data.name );
				if ( row ) {
					row.querySelectorAll( '.eizport-requirement__before' ).forEach( function ( el ) {
						el.hidden = true;
					} );
					row.querySelectorAll( '.eizport-requirement__after' ).forEach( function ( el ) {
						el.hidden = false;
					} );
				}
				button.remove();
				document.querySelectorAll( '[data-eizport-needs-importer]' ).forEach( function ( run ) {
					run.disabled = false;
				} );
				wp.updates.addAdminNotice( {
					id: 'eizport-install-success',
					className: 'notice-success is-dismissible',
					message: message,
				} );
				wp.a11y.speak( message );
			},
			error: function ( response ) {
				fail( button, label, response );
			},
		} );
	}

	document.querySelectorAll( '.eizport-install-now' ).forEach( function ( button ) {
		var label = button.textContent;

		button.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			if ( button.classList.contains( 'updating-message' ) ) {
				return;
			}
			step( button, texts.installing || label );
			wp.updates.installPlugin( {
				slug: button.dataset.slug,
				success: function () {
					step( button, texts.activating || label );
					whenFree( function () {
						activate( button, label );
					} );
				},
				error: function ( response ) {
					fail( button, label, response );
				},
			} );
		} );
	} );
} )();
