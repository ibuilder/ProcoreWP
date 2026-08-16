/**
 * Connect for Procore admin behaviour.
 *
 * Plain ES5 with no build step, so the shipped file is the source file.
 *
 * @package ProcoreConnect
 */

( function () {
	'use strict';

	var config = window.procoreConnectAdmin || {};
	var strings = config.strings || {};

	/**
	 * POST to admin-ajax with the shared nonce.
	 *
	 * @param {string}   action   Ajax action name.
	 * @param {Object}   data     Additional payload.
	 * @param {Function} onDone   Success callback.
	 * @param {Function} onFail   Failure callback.
	 */
	function post( action, data, onDone, onFail ) {
		var body = new window.FormData();

		body.append( 'action', action );
		body.append( 'nonce', config.nonce );

		Object.keys( data || {} ).forEach( function ( key ) {
			body.append( key, data[ key ] );
		} );

		window
			.fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: body,
			} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( payload ) {
				if ( payload && payload.success ) {
					onDone( payload.data || {} );
				} else {
					onFail( ( payload && payload.data && payload.data.message ) || strings.failed );
				}
			} )
			.catch( function () {
				onFail( strings.failed );
			} );
	}

	/**
	 * Create an element with text content.
	 *
	 * @param {string} tag       Tag name.
	 * @param {string} className Class attribute.
	 * @param {string} text      Text content.
	 * @return {HTMLElement} The element.
	 */
	function el( tag, className, text ) {
		var node = document.createElement( tag );

		if ( className ) {
			node.className = className;
		}

		if ( undefined !== text && null !== text ) {
			node.textContent = text;
		}

		return node;
	}

	/**
	 * Render the connection diagnostic report.
	 *
	 * @param {Object} report Report payload.
	 */
	function renderReport( report ) {
		var target = document.getElementById( 'procore-connect-test-results' );

		if ( ! target ) {
			return;
		}

		target.innerHTML = '';

		var summary = el(
			'div',
			'notice notice-' + ( report.ok ? 'success' : 'warning' ) + ' procore-connect-summary'
		);
		summary.appendChild( el( 'p', '', report.summary ) );
		target.appendChild( summary );

		var list = el( 'ul', 'procore-connect-steps' );

		( report.steps || [] ).forEach( function ( step ) {
			var item = el( 'li', 'procore-connect-step procore-connect-step--' + ( step.ok ? 'ok' : 'fail' ) );

			item.appendChild( el( 'strong', '', step.label ) );
			item.appendChild( el( 'span', 'procore-connect-step__message', step.message ) );
			list.appendChild( item );
		} );

		target.appendChild( list );

		if ( ! report.probes || ! report.probes.length ) {
			return;
		}

		target.appendChild( el( 'h3', '', 'Endpoint permissions' ) );

		var table = el( 'table', 'widefat striped procore-connect-probes' );
		var tbody = document.createElement( 'tbody' );

		report.probes.forEach( function ( probe ) {
			var row = document.createElement( 'tr' );

			row.appendChild( el( 'td', '', probe.label ) );
			row.appendChild(
				el( 'td', 'procore-connect-probe--' + probe.status, probe.message )
			);
			row.appendChild( el( 'td', '', probe.permission ) );
			tbody.appendChild( row );
		} );

		table.appendChild( tbody );
		target.appendChild( table );
	}

	/**
	 * Wire the connection test button.
	 */
	function bindTest() {
		var button = document.getElementById( 'procore-connect-test' );

		if ( ! button ) {
			return;
		}

		button.addEventListener( 'click', function () {
			var original = button.textContent;

			button.disabled = true;
			button.textContent = strings.testing;

			post(
				'procore_connect_test_connection',
				{},
				function ( report ) {
					button.disabled = false;
					button.textContent = original;
					renderReport( report );
				},
				function ( message ) {
					button.disabled = false;
					button.textContent = original;
					renderReport( { ok: false, summary: message, steps: [], probes: [] } );
				}
			);
		} );
	}

	/**
	 * Wire the cache purge button.
	 */
	function bindCache() {
		var button = document.getElementById( 'procore-connect-clear-cache' );
		var message = document.getElementById( 'procore-connect-cache-message' );

		if ( ! button ) {
			return;
		}

		button.addEventListener( 'click', function () {
			button.disabled = true;

			if ( message ) {
				message.textContent = strings.clearing;
			}

			post(
				'procore_connect_clear_cache',
				{},
				function ( data ) {
					button.disabled = false;

					if ( message ) {
						message.textContent = data.message || '';
					}
				},
				function ( text ) {
					button.disabled = false;

					if ( message ) {
						message.textContent = text;
					}
				}
			);
		} );
	}

	/**
	 * Wire the company lookup button.
	 */
	function bindCompanies() {
		var button = document.getElementById( 'procore-connect-load-companies' );
		var list = document.getElementById( 'procore-connect-company-list' );
		var input = document.getElementById( 'procore-connect-company-id' );

		if ( ! button || ! list ) {
			return;
		}

		button.addEventListener( 'click', function () {
			button.disabled = true;
			list.textContent = strings.loading;

			post(
				'procore_connect_companies',
				{},
				function ( data ) {
					button.disabled = false;
					list.innerHTML = '';

					var select = document.createElement( 'select' );

					( data.companies || [] ).forEach( function ( company ) {
						var option = document.createElement( 'option' );

						option.value = company.id;
						option.textContent = company.name + ' (' + company.id + ')';

						if ( input && String( company.id ) === String( input.value ) ) {
							option.selected = true;
						}

						select.appendChild( option );
					} );

					select.addEventListener( 'change', function () {
						if ( input ) {
							input.value = select.value;
						}
					} );

					list.appendChild( select );
				},
				function ( text ) {
					button.disabled = false;
					list.textContent = text;
				}
			);
		} );
	}

	/**
	 * Wire the disconnect button.
	 */
	function bindDisconnect() {
		var button = document.getElementById( 'procore-connect-disconnect' );

		if ( ! button ) {
			return;
		}

		button.addEventListener( 'click', function () {
			if ( ! window.confirm( strings.confirmed ) ) {
				return;
			}

			button.disabled = true;

			post(
				'procore_connect_disconnect',
				{},
				function () {
					window.location.reload();
				},
				function () {
					button.disabled = false;
				}
			);
		} );
	}

	/**
	 * Wire click-to-copy on code elements.
	 */
	function bindCopy() {
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-procore-connect-copy], [data-procore-connect-copy-button]' );

			if ( ! trigger ) {
				return;
			}

			var source = trigger.hasAttribute( 'data-procore-connect-copy' )
				? trigger
				: trigger.parentNode.querySelector( '[data-procore-connect-copy]' );

			if ( ! source || ! window.navigator.clipboard ) {
				return;
			}

			window.navigator.clipboard
				.writeText( source.getAttribute( 'data-procore-connect-copy' ) )
				.then( function () {
					source.classList.add( 'is-copied' );
					window.setTimeout( function () {
						source.classList.remove( 'is-copied' );
					}, 1500 );
				} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		bindTest();
		bindCache();
		bindCompanies();
		bindDisconnect();
		bindCopy();
	} );
} )();
