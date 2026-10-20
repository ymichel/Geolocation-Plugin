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
		var track;
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
			var first = track ? track[0] : [ item.lat, item.lng ];
			if ( previousEnd ) {
				lines.dashed.push( [ previousEnd, first ] );
			}
			if ( track ) {
				lines.solid.push( track );
			}
			previousEnd = track ? track[ track.length - 1 ] : first;
		} );
		return lines;
	}

	// The distance between two positions [ lat, lng ] in kilometres.
	function distance( from, to ) {
		var rad  = Math.PI / 180;
		var half = Math.pow( Math.sin( ( to[0] - from[0] ) * rad / 2 ), 2 ) + Math.cos( from[0] * rad ) * Math.cos( to[0] * rad ) * Math.pow( Math.sin( ( to[1] - from[1] ) * rad / 2 ), 2 );
		return 2 * 6371.0088 * Math.asin( Math.min( 1, Math.sqrt( half ) ) );
	}

	// The position at a share of the length of a track (0 = start, 1 = end); null for a track without length.
	function pointAlong( track, share ) {
		var lengths = [ 0 ];
		var i, target, part;
		for ( i = 1; i < track.length; i++ ) {
			lengths.push( lengths[ i - 1 ] + distance( track[ i - 1 ], track[ i ] ) );
		}
		if ( track.length < 2 || lengths[ lengths.length - 1 ] <= 0 ) {
			return null;
		}
		target = Math.max( 0, Math.min( 1, share ) ) * lengths[ lengths.length - 1 ];
		for ( i = 1; i < lengths.length - 1 && lengths[ i ] < target; i++ ) {
			// Find the section of the track which contains the target.
		}
		part = lengths[ i ] > lengths[ i - 1 ] ? ( target - lengths[ i - 1 ] ) / ( lengths[ i ] - lengths[ i - 1 ] ) : 0;
		return [ track[ i - 1 ][0] + ( track[ i ][0] - track[ i - 1 ][0] ) * part, track[ i - 1 ][1] + ( track[ i ][1] - track[ i - 1 ][1] ) * part ];
	}

	// The elevation of a profile (equal distances) at a share of its length, rounded to metres.
	function profileValueAt( profile, share ) {
		var position = Math.max( 0, Math.min( 1, share ) ) * ( profile.length - 1 );
		var index    = Math.min( profile.length - 2, Math.floor( position ) );
		return Math.round( profile[ index ] + ( profile[ index + 1 ] - profile[ index ] ) * ( position - index ) );
	}

	// The maps of posts which can show a position of their track, with the functions to show and hide it.
	var trackMaps = [];

	function registerTrackMap( el, api ) {
		trackMaps.push( { el: el, api: api } );
	}

	// The elevation profile below the map of a post follows the pointer, the finger or the arrow keys,
	// and the map shows the same place of the track.
	function initProfiles() {
		document.querySelectorAll( 'svg.geolocation-elevation[data-profile]' ).forEach( function ( svg ) {
			var profile, track, mapEl, sibling, box, ns, cursor, dot, label, current;
			try {
				profile = JSON.parse( svg.getAttribute( 'data-profile' ) );
			} catch ( e ) {
				return;
			}
			if ( ! Array.isArray( profile ) || profile.length < 2 ) {
				return;
			}
			var km     = parseFloat( svg.getAttribute( 'data-km' ) ) || 0;
			var first  = parseFloat( svg.getAttribute( 'data-start' ) );
			var last   = parseFloat( svg.getAttribute( 'data-end' ) );
			var width  = svg.viewBox.baseVal.width;
			var height = svg.viewBox.baseVal.height;
			var top    = 6;
			var bottom = height - 18;
			var min    = Math.min.apply( null, profile );
			var range  = Math.max( 1, Math.max.apply( null, profile ) - min );
			var lang   = document.documentElement.lang || undefined;

			// The map of the same post comes before the details of its track.
			box     = svg.closest( '.geolocation-track-details' );
			sibling = box ? box.previousElementSibling : null;
			while ( sibling && ! sibling.classList.contains( 'geolocation-map' ) ) {
				sibling = sibling.previousElementSibling;
			}
			mapEl = sibling;
			track = mapEl ? parseTrack( mapEl ) : [];

			ns     = 'http://www.w3.org/2000/svg';
			cursor = document.createElementNS( ns, 'line' );
			dot    = document.createElementNS( ns, 'circle' );
			label  = document.createElementNS( ns, 'text' );
			cursor.setAttribute( 'y1', top );
			cursor.setAttribute( 'y2', bottom );
			cursor.setAttribute( 'class', 'geolocation-elevation-cursor' );
			dot.setAttribute( 'r', 4 );
			dot.setAttribute( 'class', 'geolocation-elevation-dot' );
			label.setAttribute( 'y', top + 11 );
			label.setAttribute( 'class', 'geolocation-elevation-label' );
			[ cursor, dot, label ].forEach( function ( element ) {
				element.style.display = 'none';
				svg.appendChild( element );
			} );
			svg.setAttribute( 'tabindex', '0' );

			function map() {
				var found = trackMaps.filter( function ( entry ) {
					return entry.el === mapEl;
				} )[0];
				return found ? found.api : null;
			}

			function show( share ) {
				var x         = share * width;
				var elevation = profileValueAt( profile, share );
				var y         = bottom - ( elevation - min ) * ( bottom - top ) / range;
				var api       = map();
				current       = share;
				cursor.setAttribute( 'x1', x );
				cursor.setAttribute( 'x2', x );
				dot.setAttribute( 'cx', x );
				dot.setAttribute( 'cy', y );
				label.textContent = ( share * km ).toLocaleString( lang, { minimumFractionDigits: 1, maximumFractionDigits: 1 } ) + ' km · ' + elevation.toLocaleString( lang ) + ' m';
				// The label stays inside the image.
				label.setAttribute( 'x', share < 0.5 ? x + 6 : x - 6 );
				label.setAttribute( 'text-anchor', share < 0.5 ? 'start' : 'end' );
				[ cursor, dot, label ].forEach( function ( element ) {
					element.style.display = '';
				} );
				if ( ! api ) {
					return;
				}
				// The hidden ends of a shortened track are not shown on the map.
				if ( last > first && share >= first && share <= last && track.length > 1 ) {
					api.show( pointAlong( track, ( share - first ) / ( last - first ) ) );
				} else {
					api.hide();
				}
			}

			function hide() {
				var api = map();
				current = null;
				[ cursor, dot, label ].forEach( function ( element ) {
					element.style.display = 'none';
				} );
				if ( api ) {
					api.hide();
				}
			}

			function fromPointer( event ) {
				var rect = svg.getBoundingClientRect();
				if ( rect.width > 0 ) {
					show( Math.max( 0, Math.min( 1, ( event.clientX - rect.left ) / rect.width ) ) );
				}
			}

			svg.addEventListener( 'pointermove', fromPointer );
			svg.addEventListener( 'pointerdown', fromPointer );
			// A finger leaves the image when it is lifted: the place stays until the visitor touches something else.
			svg.addEventListener( 'pointerleave', function ( event ) {
				if ( 'mouse' === event.pointerType ) {
					hide();
				}
			} );
			document.addEventListener( 'pointerdown', function ( event ) {
				if ( null !== current && undefined !== current && ! svg.contains( event.target ) ) {
					hide();
				}
			} );
			svg.addEventListener( 'focus', function () {
				// Only when reached with the keyboard: a tap focuses the image as well and has shown its place already.
				if ( svg.matches( ':focus-visible' ) ) {
					show( current === null || current === undefined ? Math.max( 0, first ) : current );
				}
			} );
			svg.addEventListener( 'blur', hide );
			svg.addEventListener( 'keydown', function ( event ) {
				var step = { ArrowLeft: -0.01, ArrowRight: 0.01, Home: -1, End: 1 }[ event.key ];
				if ( step ) {
					event.preventDefault();
					show( Math.max( 0, Math.min( 1, ( current || 0 ) + step ) ) );
				}
			} );
		} );
	}

	ready( initProfiles );

	window.geolocationFrontCommon = {
		settings: settings,
		ready: ready,
		start: start,
		parseLatLng: parseLatLng,
		parseTrack: parseTrack,
		pointAlong: pointAlong,
		profileValueAt: profileValueAt,
		registerTrackMap: registerTrackMap,
		forEachPostMap: forEachPostMap,
		forEachPageMap: forEachPageMap,
		initHoverMap: initHoverMap,
		buildPopup: buildPopup,
		getRouteLines: getRouteLines
	};
}() );
