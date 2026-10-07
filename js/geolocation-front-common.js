/**
 * Geolocation: provider independent helpers for the frontend maps.
 *
 * Settings are provided by PHP in window.geolocationFront.
 */
( function () {
	'use strict';

	var settings = window.geolocationFront || {};
	// The map library of the provider is only loaded once a map is needed.
	var library      = settings.library || {};
	var libraryState = 0;
	var waiting      = [];
	// The location link the visitor points at while the library is loading.
	var pendingLink  = null;

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

	// Load the styles and scripts of the map library, then call fn(). They are requested only once.
	function loadLibrary( fn ) {
		if ( libraryState === 2 ) {
			fn();
			return;
		}
		waiting.push( fn );
		if ( libraryState === 1 ) {
			return;
		}
		libraryState = 1;

		// Clustering is only needed for the overview map of a page.
		var cluster = !! document.querySelector( '.geolocation-page-map' );
		var styles  = ( library.styles || [] ).concat( cluster ? library.clusterStyles || [] : [] );
		var scripts = ( library.scripts || [] ).concat( cluster ? library.clusterScripts || [] : [] );
		var open    = styles.length + 1 + ( library.callback ? 1 : 0 );

		function done() {
			open -= 1;
			if ( open > 0 ) {
				return;
			}
			libraryState = 2;
			waiting.splice( 0 ).forEach( function ( callback ) {
				callback();
			} );
		}

		// Scripts are loaded one after the other, as the later ones need the earlier ones.
		function loadScript( index ) {
			if ( index >= scripts.length ) {
				done();
				return;
			}
			var script     = document.createElement( 'script' );
			script.src     = scripts[ index ];
			script.onload  = function () {
				loadScript( index + 1 );
			};
			script.onerror = script.onload;
			document.head.appendChild( script );
		}

		styles.forEach( function ( href ) {
			var link     = document.createElement( 'link' );
			link.rel     = 'stylesheet';
			link.href    = href;
			link.onload  = done;
			link.onerror = done;
			document.head.appendChild( link );
		} );
		if ( library.callback ) {
			// Google Maps reports by itself when it is ready.
			window[ library.callback ] = done;
		}
		loadScript( 0 );
	}

	// The links which show the popup map while hovering (display mode "link").
	function getHoverLinks() {
		if ( ! document.getElementById( 'map' ) ) {
			return [];
		}
		return Array.prototype.filter.call( document.querySelectorAll( '.geolocation-link' ), parseLatLng );
	}

	// Call init() once the map library is loaded. It is loaded when the first map is about to scroll
	// into view or when the visitor points at a location link, so pages cost nothing until then.
	function start( init ) {
		var begun = false;
		function begin() {
			if ( ! begun ) {
				begun = true;
				loadLibrary( init );
			}
		}
		document.querySelectorAll( '.geolocation-map' ).forEach( function ( el ) {
			var markers = el.getAttribute( 'data-markers' );
			if ( parseLatLng( el ) || ( markers && markers !== '[]' ) ) {
				whenVisible( el, begin );
			}
		} );
		getHoverLinks().forEach( function ( link ) {
			link.addEventListener( 'mouseover', function () {
				pendingLink = link;
				begin();
			} );
			link.addEventListener( 'mouseout', function () {
				pendingLink = null;
			} );
		} );
	}

	// Read a list of positions from an attribute of an element; returns [ [ lat, lng ], ... ].
	function parseTrack( el ) {
		var track = [];
		try {
			track = JSON.parse( el.getAttribute( 'data-track' ) || '[]' );
		} catch ( e ) {
			track = [];
		}
		return Array.isArray( track ) && track.length > 1 ? track : [];
	}

	// One static map per post (display mode "map"): calls create( el, latLng, track ).
	// The track of the post is an empty list if it has none.
	function forEachPostMap( create ) {
		document.querySelectorAll( '.geolocation-map' ).forEach( function ( el ) {
			var latLng = parseLatLng( el );
			if ( ! latLng ) {
				return;
			}
			whenVisible( el, function () {
				create( el, latLng, parseTrack( el ) );
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
		var links = getHoverLinks();
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

		function open( link ) {
			var rect = link.getBoundingClientRect();
			show( parseLatLng( link ) );
			mapEl.style.opacity    = 1;
			mapEl.style.zIndex     = '99';
			mapEl.style.visibility = 'visible';
			mapEl.style.top        = ( rect.bottom + window.scrollY + 4 ) + 'px';
			mapEl.style.left       = ( rect.left + window.scrollX ) + 'px';
			allowDisappear         = false;
		}

		links.forEach( function ( link ) {
			link.addEventListener( 'mouseover', function () {
				open( link );
			} );
			link.addEventListener( 'mouseout', scheduleHide );
		} );
		// The visitor is still pointing at the link which made the library load.
		if ( pendingLink ) {
			open( pendingLink );
		}

		mapEl.addEventListener( 'mouseover', function () {
			allowDisappear         = false;
			cancelDisappear        = true;
			mapEl.style.visibility = 'visible';
		} );
		mapEl.addEventListener( 'mouseout', scheduleHide );
	}

	// Build the popup of a post on the overview map: image, linked title, date and excerpt.
	// Everything is inserted as text, so the content of a post can never inject markup.
	function buildPopup( item ) {
		var box   = document.createElement( 'div' );
		var title = document.createElement( 'a' );
		var extra;
		box.className = 'geolocation-popup';

		if ( item.image ) {
			var imageLink = document.createElement( 'a' );
			var image     = document.createElement( 'img' );
			imageLink.href = item.url;
			image.src      = item.image;
			image.alt      = '';
			image.loading  = 'lazy';
			imageLink.appendChild( image );
			box.appendChild( imageLink );
		}

		title.className   = 'geolocation-popup-title';
		title.href        = item.url;
		title.textContent = item.title;
		box.appendChild( title );

		if ( item.date ) {
			extra             = document.createElement( 'span' );
			extra.className   = 'geolocation-popup-date';
			extra.textContent = item.date + ( item.length ? ' · ' + item.length : '' );
			box.appendChild( extra );
		}
		if ( item.excerpt ) {
			extra             = document.createElement( 'p' );
			extra.className   = 'geolocation-popup-excerpt';
			extra.textContent = item.excerpt;
			box.appendChild( extra );
		}
		return box;
	}

	// The lines of a route: all locations in the order of their posts, oldest first.
	// Returns { solid: [ line, ... ], dashed: [ line, ... ] }, each line being a list of [ lat, lng ].
	// Recorded tracks of posts are drawn solid and the gaps between them dashed;
	// without any track the locations are connected by one solid line.
	function getRouteLines( el, markers ) {
		var lines = { solid: [], dashed: [] };
		if ( el.getAttribute( 'data-route' ) !== '1' ) {
			return lines;
		}
		var sorted    = markers.slice().sort( function ( a, b ) {
			return ( a.time || 0 ) - ( b.time || 0 );
		} );
		var hasTracks = sorted.some( function ( item ) {
			return Array.isArray( item.track ) && item.track.length > 1;
		} );
		if ( ! hasTracks ) {
			if ( sorted.length > 1 ) {
				lines.solid.push( sorted.map( function ( item ) {
					return [ item.lat, item.lng ];
				} ) );
			}
			return lines;
		}
		var previousEnd = null;
		sorted.forEach( function ( item ) {
			var track = Array.isArray( item.track ) && item.track.length > 1 ? item.track : null;
			var start = track ? track[0] : [ item.lat, item.lng ];
			if ( previousEnd ) {
				lines.dashed.push( [ previousEnd, start ] );
			}
			if ( track ) {
				lines.solid.push( track );
			}
			previousEnd = track ? track[ track.length - 1 ] : start;
		} );
		return lines;
	}

	window.geolocationFrontCommon = {
		settings: settings,
		ready: ready,
		start: start,
		forEachPostMap: forEachPostMap,
		forEachPageMap: forEachPageMap,
		initHoverMap: initHoverMap,
		buildPopup: buildPopup,
		getRouteLines: getRouteLines
	};
}() );
