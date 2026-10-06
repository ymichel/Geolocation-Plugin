/**
 * Geolocation: map preview on the plugin's settings page.
 *
 * Settings are provided by PHP in window.geolocationSettings.
 * The Google Maps API calls window.geolocationInitMap once it is loaded.
 */
( function () {
	'use strict';

	var settings     = window.geolocationSettings || {};
	var zoomlevel    = settings.zoom;
	var provider     = settings.provider;
	var latLng       = [ 52.5162778, 13.3733267 ];
	var osmMap       = null;
	var osmMarker    = null;
	var googleMap    = null;
	var googleCenter = null;
	var googleMarker = null;

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	function googleAvailable() {
		return typeof google !== 'undefined' && google.maps && google.maps.Map;
	}

	// Remove the current preview so the container can be reused by the other provider.
	function destroyMap() {
		var el = document.getElementById( 'map' );
		if ( osmMap ) {
			osmMap.remove();
		}
		if ( googleMarker ) {
			googleMarker.setMap( null );
		}
		osmMap       = null;
		osmMarker    = null;
		googleMap    = null;
		googleMarker = null;
		if ( el ) {
			el.innerHTML             = '';
			el.style.position        = '';
			el.style.overflow        = '';
			el.style.backgroundColor = '';
		}
	}

	// (Re-)place the single preview marker according to the pin checkbox.
	function setMarker() {
		var usePin = document.getElementById( 'geolocation_wp_pin' ).checked;
		var options;
		if ( googleMap ) {
			if ( googleMarker ) {
				googleMarker.setMap( null );
			}
			options = {
				position: googleCenter,
				map: googleMap,
				title: 'Post Location'
			};
			if ( usePin ) {
				options.icon = settings.pinUrl;
			}
			googleMarker = new google.maps.Marker( options );
		} else if ( osmMap ) {
			if ( osmMarker ) {
				osmMap.removeLayer( osmMarker );
			}
			options = {
				clickable: false,
				draggable: false
			};
			if ( usePin ) {
				options.icon = L.icon( {
					iconUrl: settings.pinUrl,
					shadowUrl: settings.pinShadowUrl,
					iconSize: [ 25, 34 ],
					shadowSize: [ 39, 23 ],
					iconAnchor: [ 5, 34 ],
					shadowAnchor: [ 3, 25 ],
					popupAnchor: [ 12, -30 ]
				} );
			}
			osmMarker = L.marker( latLng, options ).addTo( osmMap );
		}
	}

	function initializeMap() {
		var el = document.getElementById( 'map' );
		destroyMap();
		if ( ! el ) {
			return;
		}
		if ( provider === 'google' ) {
			if ( ! googleAvailable() ) {
				return;
			}
			googleCenter = new google.maps.LatLng( latLng[0], latLng[1] );
			googleMap    = new google.maps.Map( el, {
				zoom: zoomlevel,
				center: googleCenter,
				mapTypeId: google.maps.MapTypeId.ROADMAP
			} );
		} else {
			if ( typeof L === 'undefined' ) {
				return;
			}
			osmMap = L.map( el ).setView( latLng, zoomlevel );
			L.tileLayer( settings.tilesUrl, {
				attribution: '&copy; <a href="https://osm.org/copyright">OpenStreetMap</a> contributors'
			} ).addTo( osmMap );
		}
		setMarker();
	}

	function updateMap() {
		if ( googleMap ) {
			googleMap.setZoom( zoomlevel );
			googleMap.setCenter( googleCenter );
		} else if ( osmMap ) {
			osmMap.setView( latLng, zoomlevel );
		}
	}

	function providerSelected( value ) {
		var googleRow = document.querySelector( '.google-apikey' );
		var osmRow    = document.querySelector( '.osm-urls' );
		provider      = value;
		if ( googleRow && osmRow ) {
			googleRow.style.display = provider === 'google' ? '' : 'none';
			osmRow.style.display    = provider === 'google' ? 'none' : '';
		}
		initializeMap();
	}

	// The Google Maps API may finish loading after the page is ready.
	window.geolocationInitMap = function () {
		if ( provider === 'google' && ! googleMap ) {
			initializeMap();
		}
	};

	ready( function () {
		var providerEl = document.getElementById( 'geolocation_provider' );
		var pinEl      = document.getElementById( 'geolocation_wp_pin' );
		if ( ! providerEl || ! pinEl ) {
			return;
		}

		providerEl.addEventListener( 'change', function () {
			providerSelected( providerEl.value );
		} );
		pinEl.addEventListener( 'click', setMarker );
		document.querySelectorAll( 'input[name="geolocation_default_zoom"]' ).forEach( function ( radio ) {
			radio.addEventListener( 'click', function () {
				zoomlevel = parseInt( radio.value, 10 );
				updateMap();
			} );
		} );

		providerSelected( providerEl.value );
	} );

	// While tiles are pre-cached in the background, the status is refreshed every few seconds.
	// The requests also keep the background tasks of WordPress going on sites with few visitors.
	ready( function () {
		var box = document.getElementById( 'geolocation-precache-status' );
		if ( ! box || box.getAttribute( 'data-running' ) !== '1' || ! window.fetch ) {
			return;
		}
		var timer = window.setInterval( function () {
			window.fetch( box.getAttribute( 'data-url' ), { credentials: 'same-origin' } ).then( function ( response ) {
				return response.json();
			} ).then( function ( result ) {
				if ( ! result || ! result.success ) {
					return;
				}
				// The HTML is built and escaped by the plugin on the server.
				box.innerHTML = result.data.html;
				if ( ! result.data.running ) {
					box.setAttribute( 'data-running', '0' );
					window.clearInterval( timer );
				}
			} ).catch( function () {
				// Try again with the next interval.
			} );
		}, 5000 );
	} );

	// Pre-caching belongs to the proxy: its row follows the checkbox "Use Proxy" at once, before the settings are saved.
	ready( function () {
		var proxy = document.getElementById( 'geolocation_osm_use_proxy' );
		var row   = document.getElementById( 'geolocation-precache-row' );
		if ( ! proxy || ! row ) {
			return;
		}
		proxy.addEventListener( 'change', function () {
			row.style.display = proxy.checked ? '' : 'none';
		} );
	} );

	// The same goes for the address of the proxy and the explanation of the own tiles URL.
	ready( function () {
		var proxy    = document.getElementById( 'geolocation_osm_use_proxy' );
		var used     = document.getElementById( 'geolocation-proxy-tiles' );
		var fallback = document.getElementById( 'geolocation-tiles-fallback' );
		var direct   = document.getElementById( 'geolocation-tiles-direct' );
		var own      = document.getElementById( 'geolocation_osm_tiles_url' );
		if ( ! proxy || ! used || ! fallback || ! direct || ! own ) {
			return;
		}
		proxy.addEventListener( 'change', function () {
			used.style.display     = proxy.checked ? '' : 'none';
			fallback.style.display = proxy.checked ? '' : 'none';
			direct.style.display   = proxy.checked ? 'none' : '';
			// The own address is only a fallback while the proxy is used, so it cannot be changed then.
			own.readOnly = proxy.checked;
		} );
	} );

	// The strict privacy mode shows no maps without the proxy: say so as soon as that combination is chosen.
	ready( function () {
		var strict = document.getElementById( 'geolocation_osm_strict_privacy' );
		var proxy  = document.getElementById( 'geolocation_osm_use_proxy' );
		var hint   = document.getElementById( 'geolocation-strict-hint' );
		if ( ! strict || ! hint ) {
			return;
		}
		function update() {
			var delivers       = proxy && proxy.checked && hint.getAttribute( 'data-proxy' ) === '1';
			hint.style.display = strict.checked && ! delivers ? '' : 'none';
		}
		strict.addEventListener( 'change', update );
		if ( proxy ) {
			proxy.addEventListener( 'change', update );
		}
	} );
}() );
