/**
 * Geolocation: provider independent part of the post editor's meta box.
 *
 * Settings and the post's data are provided by PHP in window.geolocationAdmin.
 */
( function () {
	'use strict';

	var data = window.geolocationAdmin || {};

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	// Wire the meta box's fields. handlers.geocode( address ) has to look up an address,
	// handlers.setLocation( lat, lng, recenter ) has to move the marker to a position picked by the user.
	// Returns null if the meta box is not available.
	function init( handlers ) {
		var els = {
			map: document.getElementById( 'geolocation-map' ),
			isPublic: document.getElementById( 'geolocation-public' ),
			enabled: document.getElementById( 'geolocation-enabled' ),
			disabled: document.getElementById( 'geolocation-disabled' ),
			lat: document.getElementById( 'geolocation-latitude' ),
			lng: document.getElementById( 'geolocation-longitude' ),
			addr: document.getElementById( 'geolocation-address' ),
			addrRev: document.getElementById( 'geolocation-address-reverse' ),
			load: document.getElementById( 'geolocation-load' ),
			locate: document.getElementById( 'geolocation-locate' ),
			status: document.getElementById( 'geolocation-status' ),
			remove: document.getElementById( 'geolocation-remove' ),
			removeFlag: document.getElementById( 'geolocation-remove-flag' )
		};
		var key;
		for ( key in els ) {
			if ( ! els[ key ] ) {
				return null;
			}
		}

		var hasLocation = data.latitude !== '' && data.longitude !== '';

		function setGeoEnabled( enabled ) {
			els.addr.disabled     = ! enabled;
			els.load.disabled     = ! enabled;
			els.locate.disabled   = ! enabled;
			els.remove.disabled   = ! enabled;
			els.isPublic.disabled = ! enabled;
			els.map.style.opacity = enabled ? '' : '0.5';
			els.enabled.checked   = enabled;
			els.disabled.checked  = ! enabled;
		}

		// Coordinates are stored with 7 decimals, which is more precise than a map can show.
		function round( value ) {
			return String( Math.round( parseFloat( value ) * 1e7 ) / 1e7 );
		}

		function setPosition( lat, lng ) {
			els.lat.value         = round( lat );
			els.lng.value         = round( lng );
			els.status.textContent = '';
			els.removeFlag.value  = '';
			els.map.style.opacity = '';
		}

		function setAddress( address ) {
			els.addr.value    = address;
			els.addrRev.value = address;
		}

		// Only show "public" as checked if it is really stored as 1 (missing meta means not public).
		els.isPublic.checked = data.isPublic === '1';
		setGeoEnabled( data.isEnabled !== '0' );

		if ( hasLocation ) {
			els.lat.value     = data.latitude;
			els.lng.value     = data.longitude;
			els.addrRev.value = data.addressReverse;
			els.addr.value    = data.address;
		}

		els.addr.addEventListener( 'click', function () {
			if ( els.addr.value !== '' ) {
				els.addr.value = '';
			}
		} );
		els.addr.addEventListener( 'keyup', function ( e ) {
			if ( e.key === 'Enter' ) {
				els.load.click();
			}
		} );
		els.load.addEventListener( 'click', function () {
			if ( els.addr.value !== '' ) {
				handlers.geocode( els.addr.value );
			}
		} );
		els.enabled.addEventListener( 'click', function () {
			setGeoEnabled( true );
		} );
		els.disabled.addEventListener( 'click', function () {
			setGeoEnabled( false );
		} );
		// The browser only reveals the position on secure pages (https or localhost).
		if ( ! navigator.geolocation || window.isSecureContext === false ) {
			els.locate.style.display = 'none';
		}
		els.locate.addEventListener( 'click', function () {
			els.status.textContent = '';
			navigator.geolocation.getCurrentPosition(
				function ( position ) {
					handlers.setLocation( position.coords.latitude, position.coords.longitude, true );
				},
				function () {
					els.status.textContent = ( data.i18n && data.i18n.locateFailed ) || '';
				},
				{ enableHighAccuracy: true, timeout: 15000 }
			);
		} );
		els.remove.addEventListener( 'click', function () {
			els.lat.value         = '';
			els.lng.value         = '';
			els.addr.value        = '';
			els.addrRev.value     = '';
			els.removeFlag.value  = '1';
			els.status.textContent = '';
			els.map.style.opacity = '0.5';
		} );

		return {
			data: data,
			els: els,
			hasLocation: hasLocation,
			needsAddress: hasLocation && data.address === '',
			isEnabled: function () {
				return els.enabled.checked;
			},
			setPosition: setPosition,
			setAddress: setAddress
		};
	}

	window.geolocationAdminCommon = {
		ready: ready,
		init: init
	};
}() );
