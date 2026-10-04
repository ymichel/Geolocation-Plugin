/**
 * Geolocation: provider independent helpers for the frontend maps.
 *
 * Settings are provided by PHP in window.geolocationFront.
 */
( function () {
	'use strict';

	var settings = window.geolocationFront || {};

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	// Read "latitude,longitude" from an element; returns [ lat, lng ] or null.
	function parseLatLng( el ) {
		var parts = ( el.getAttribute( 'data-geolocation' ) || el.getAttribute( 'name' ) || '' ).split( ',' );
		var lat   = parseFloat( parts[0] );
		var lng   = parseFloat( parts[1] );
		if ( parts.length !== 2 || isNaN( lat ) || isNaN( lng ) ) {
			return null;
		}
		return [ lat, lng ];
	}

	// Defer creating a map until its container is about to scroll into view.
	function whenVisible( el, fn ) {
		if ( ! ( 'IntersectionObserver' in window ) ) {
			fn();
			return;
		}
		var observer = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( entry.isIntersecting ) {
					observer.disconnect();
					fn();
				}
			} );
		}, { rootMargin: '200px' } );
		observer.observe( el );
	}

	// One static map per post (display mode "map"): calls create( el, latLng ).
	function forEachPostMap( create ) {
		document.querySelectorAll( '.geolocation-map' ).forEach( function ( el ) {
			var latLng = parseLatLng( el );
			if ( ! latLng ) {
				return;
			}
			whenVisible( el, function () {
				create( el, latLng );
			} );
		} );
	}

	// One map showing all posts' locations (shortcode inside a page): calls create( el, markers ).
	function forEachPageMap( create ) {
		document.querySelectorAll( '.geolocation-page-map' ).forEach( function ( el ) {
			var markers = [];
			try {
				markers = JSON.parse( el.getAttribute( 'data-markers' ) || '[]' );
			} catch ( e ) {
				markers = [];
			}
			if ( ! markers.length ) {
				return;
			}
			whenVisible( el, function () {
				create( el, markers );
			} );
		} );
	}

	// One shared popup map shown while hovering a location link (display mode "link").
	// create( mapEl ) has to build the map and return a function show( latLng ).
	function initHoverMap( create ) {
		var mapEl = document.getElementById( 'map' );
		var links = Array.prototype.filter.call( document.querySelectorAll( '.geolocation-link' ), parseLatLng );
		if ( ! mapEl || ! links.length ) {
			return;
		}

		var show            = create( mapEl );
		var allowDisappear  = true;
		var cancelDisappear = false;

		function scheduleHide() {
			allowDisappear  = true;
			cancelDisappear = false;
			setTimeout( function () {
				if ( allowDisappear && ! cancelDisappear ) {
					mapEl.style.opacity = 0;
					mapEl.style.zIndex  = '-1';
				}
			}, 800 );
		}

		links.forEach( function ( link ) {
			link.addEventListener( 'mouseover', function () {
				var rect = link.getBoundingClientRect();
				show( parseLatLng( link ) );
				mapEl.style.opacity    = 1;
				mapEl.style.zIndex     = '99';
				mapEl.style.visibility = 'visible';
				mapEl.style.top        = ( rect.bottom + window.scrollY + 4 ) + 'px';
				mapEl.style.left       = ( rect.left + window.scrollX ) + 'px';
				allowDisappear         = false;
			} );
			link.addEventListener( 'mouseout', scheduleHide );
		} );

		mapEl.addEventListener( 'mouseover', function () {
			allowDisappear         = false;
			cancelDisappear        = true;
			mapEl.style.visibility = 'visible';
		} );
		mapEl.addEventListener( 'mouseout', scheduleHide );
	}

	window.geolocationFrontCommon = {
		settings: settings,
		ready: ready,
		forEachPostMap: forEachPostMap,
		forEachPageMap: forEachPageMap,
		initHoverMap: initHoverMap
	};
}() );
