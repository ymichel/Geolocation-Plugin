/**
 * Geolocation: notice before a visitor follows the link to an external map.
 *
 * The links are printed hidden and without an address, so nobody leaves the website without the notice.
 * The texts are provided by PHP in window.geolocationMapLink.
 */
( function () {
	'use strict';

	var texts  = window.geolocationMapLink || {};
	var dialog = null;
	var open   = null;

	// Build the notice once; it is shared by all links of the page.
	function getDialog() {
		if ( dialog ) {
			return dialog;
		}
		dialog           = document.createElement( 'dialog' );
		dialog.className = 'geolocation-map-link-notice';
		dialog.setAttribute( 'aria-labelledby', 'geolocation-map-link-notice-title' );

		var title         = document.createElement( 'p' );
		title.id          = 'geolocation-map-link-notice-title';
		title.className   = 'geolocation-map-link-notice-title';
		title.textContent = texts.title || '';

		var text         = document.createElement( 'p' );
		text.textContent = texts.text || '';

		var buttons       = document.createElement( 'div' );
		buttons.className = 'geolocation-map-link-notice-buttons';

		var cancel         = document.createElement( 'button' );
		cancel.type        = 'button';
		cancel.textContent = texts.cancel || '';
		cancel.addEventListener( 'click', function () {
			dialog.close();
		} );

		// A real link, so the browser opens it like any other one; no referrer is sent.
		open             = document.createElement( 'a' );
		open.className   = 'geolocation-map-link-notice-open';
		open.target      = '_blank';
		open.rel         = 'noopener noreferrer';
		open.textContent = texts.open || '';
		open.addEventListener( 'click', function () {
			dialog.close();
		} );

		buttons.appendChild( cancel );
		buttons.appendChild( open );
		dialog.appendChild( title );
		dialog.appendChild( text );
		dialog.appendChild( buttons );
		// A click on the backdrop closes the notice.
		dialog.addEventListener( 'click', function ( event ) {
			if ( event.target === dialog ) {
				dialog.close();
			}
		} );
		document.body.appendChild( dialog );
		return dialog;
	}

	function init() {
		// Browsers without the dialog element keep the links hidden.
		if ( typeof HTMLDialogElement === 'undefined' ) {
			return;
		}
		document.querySelectorAll( 'a.geolocation-map-link[data-url]' ).forEach( function ( link ) {
			var url = link.getAttribute( 'data-url' );
			link.setAttribute( 'href', '#' );
			link.setAttribute( 'role', 'button' );
			link.hidden = false;
			link.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				getDialog();
				open.href = url;
				dialog.showModal();
			} );
		} );
	}

	if ( document.readyState !== 'loading' ) {
		init();
	} else {
		document.addEventListener( 'DOMContentLoaded', init );
	}
}() );
