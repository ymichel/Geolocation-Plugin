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

	// The distance between two positions [ lat, lng ] in kilometres.
	function distance( from, to ) {
		var rad  = Math.PI / 180;
		var lat1 = from[0] * rad;
		var lat2 = to[0] * rad;
		var half = Math.pow( Math.sin( ( lat2 - lat1 ) / 2 ), 2 ) + Math.cos( lat1 ) * Math.cos( lat2 ) * Math.pow( Math.sin( ( to[1] - from[1] ) * rad / 2 ), 2 );
		return 2 * 6371.0088 * Math.asin( Math.min( 1, Math.sqrt( half ) ) );
	}

	// Drop the positions which are not needed to keep the shape of a track (Douglas-Peucker).
	function simplify( points, tolerance ) {
		var scale = Math.cos( points[0][0] * Math.PI / 180 );
		var keep  = [];
		var stack = [ [ 0, points.length - 1 ] ];
		var range, first, last, ax, ay, dx, dy, length, max, index, i, px, py, t, d;
		keep[0]                 = true;
		keep[ points.length - 1 ] = true;
		while ( stack.length ) {
			range  = stack.pop();
			first  = range[0];
			last   = range[1];
			ax     = points[ first ][1] * scale;
			ay     = points[ first ][0];
			dx     = points[ last ][1] * scale - ax;
			dy     = points[ last ][0] - ay;
			length = dx * dx + dy * dy;
			max    = 0;
			index  = 0;
			for ( i = first + 1; i < last; i++ ) {
				px = points[ i ][1] * scale - ax;
				py = points[ i ][0] - ay;
				t  = length > 0 ? Math.max( 0, Math.min( 1, ( px * dx + py * dy ) / length ) ) : 0;
				d  = Math.pow( px - t * dx, 2 ) + Math.pow( py - t * dy, 2 );
				if ( d > max ) {
					max   = d;
					index = i;
				}
			}
			if ( max > tolerance * tolerance ) {
				keep[ index ] = true;
				stack.push( [ first, index ], [ index, last ] );
			}
		}
		return points.filter( function ( point, position ) {
			return keep[ position ];
		} );
	}

	// Read the track of a GPX file: { points: [ [ lat, lng ], ... ], km: length } or null.
	// The track is reduced to at most 1000 positions; times and elevations are not read.
	function parseGpx( text ) {
		var doc, nodes, i, lat, lng;
		var points    = [];
		var km        = 0;
		var tolerance = 0.00002;
		try {
			doc = new DOMParser().parseFromString( text, 'application/xml' );
		} catch ( e ) {
			return null;
		}
		nodes = doc.getElementsByTagNameNS( '*', 'trkpt' );
		if ( ! nodes.length ) {
			nodes = doc.getElementsByTagNameNS( '*', 'rtept' );
		}
		for ( i = 0; i < nodes.length; i++ ) {
			lat = parseFloat( nodes[ i ].getAttribute( 'lat' ) );
			lng = parseFloat( nodes[ i ].getAttribute( 'lon' ) );
			if ( ! isNaN( lat ) && ! isNaN( lng ) && Math.abs( lat ) <= 90 && Math.abs( lng ) <= 180 ) {
				points.push( [ lat, lng ] );
			}
		}
		if ( points.length < 2 ) {
			return null;
		}
		for ( i = 1; i < points.length; i++ ) {
			km += distance( points[ i - 1 ], points[ i ] );
		}
		while ( points.length > 1000 ) {
			points     = simplify( points, tolerance );
			tolerance *= 1.3;
		}
		return {
			points: points.map( function ( point ) {
				return [ Math.round( point[0] * 1e5 ) / 1e5, Math.round( point[1] * 1e5 ) / 1e5 ];
			} ),
			km: km
		};
	}

	// Wire the fields of the track. onTrack( points ) is called when a file has been read.
	function initTrack( onTrack ) {
		var file       = document.getElementById( 'geolocation-track-file' );
		var remove     = document.getElementById( 'geolocation-track-remove' );
		var status     = document.getElementById( 'geolocation-track-status' );
		var field      = document.getElementById( 'geolocation-track' );
		var km         = document.getElementById( 'geolocation-track-km' );
		var removeFlag = document.getElementById( 'geolocation-track-remove-flag' );
		var i18n       = data.i18n || {};
		if ( ! file || ! remove || ! status || ! field || ! km || ! removeFlag ) {
			return;
		}

		function showLength( length ) {
			status.textContent = length ? ( i18n.trackLength || '%s' ).replace( '%s', length ) : '';
			remove.style.display = length ? '' : 'none';
		}
		showLength( data.trackKm || '' );

		file.addEventListener( 'change', function () {
			var reader;
			if ( ! file.files || ! file.files[0] ) {
				return;
			}
			reader        = new FileReader();
			reader.onload = function () {
				var track = parseGpx( String( reader.result ) );
				if ( ! track ) {
					field.value        = '';
					km.value           = '';
					status.textContent = i18n.trackInvalid || '';
					return;
				}
				field.value      = JSON.stringify( track.points );
				km.value         = track.km.toFixed( 2 );
				removeFlag.value = '';
				showLength( ( track.km < 10 ? track.km.toFixed( 1 ) : Math.round( track.km ) ) + ' km' );
				onTrack( track.points );
			};
			reader.readAsText( file.files[0] );
		} );
		remove.addEventListener( 'click', function () {
			file.value       = '';
			field.value      = '';
			km.value         = '';
			removeFlag.value = '1';
			showLength( '' );
		} );
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

		// A post without a location gets the end of its track as location.
		initTrack( function ( points ) {
			var end = points[ points.length - 1 ];
			if ( els.lat.value === '' && els.lng.value === '' && els.enabled.checked ) {
				handlers.setLocation( end[0], end[1], true );
			}
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
		init: init,
		parseGpx: parseGpx
	};
}() );
