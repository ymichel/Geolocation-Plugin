/**
 * Geolocation: frontend maps using OpenStreetMap (Leaflet).
 *
 * Requires geolocation-front-common.js, which loads Leaflet once a map is needed.
 */
( function () {
	'use strict';

	var common   = window.geolocationFrontCommon;
	var settings = common ? common.settings : {};

	function getMarkerOptions() {
		var options = {
			clickable: false,
			draggable: false
		};
		if ( settings.usePin ) {
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
		return options;
	}

	// Routes and tracks; gaps between recorded tracks are dashed.
	var lineOptions   = { color: '#2b6cb0', weight: 3, opacity: 0.8, interactive: false };
	var dashedOptions = { color: '#2b6cb0', weight: 3, opacity: 0.8, interactive: false, dashArray: '2 8' };

	function createMap( el ) {
		var map = L.map( el );
		L.tileLayer( settings.tilesUrl, {
			attribution: '&copy; <a href="https://osm.org/copyright">OpenStreetMap</a> contributors'
		} ).addTo( map );
		return map;
	}

	if ( ! common ) {
		return;
	}

	// Leaflet is loaded by the common script once a map is needed.
	common.ready( function () {
		common.start( function () {
			if ( typeof L === 'undefined' ) {
				return;
			}

			common.forEachPostMap( function ( el, latLng, track ) {
				var map = createMap( el );
				L.marker( latLng, getMarkerOptions() ).addTo( map );
				if ( track.length ) {
					// Show the whole track of the post instead of the surroundings of its location.
					L.polyline( track, lineOptions ).addTo( map );
					map.fitBounds( track.concat( [ latLng ] ), { padding: [ 20, 20 ] } );
				} else {
					map.setView( latLng, settings.zoom );
				}
			} );

			common.initHoverMap( function ( mapEl ) {
				var map    = createMap( mapEl );
				var marker = null;
				return function ( latLng ) {
					if ( marker ) {
						marker.setLatLng( latLng );
					} else {
						marker = L.marker( latLng, getMarkerOptions() ).addTo( map );
					}
					map.setView( latLng, settings.zoom );
				};
			} );

			common.forEachPageMap( function ( el, markers ) {
				var map    = createMap( el );
				var bounds = [];
				// Markers lying close together are grouped if the cluster library is loaded.
				// The radius is half of the library's default, so only markers that would overlap are grouped.
				var layer  = typeof L.markerClusterGroup === 'function' ? L.markerClusterGroup( { showCoverageOnHover: false, maxClusterRadius: 40 } ) : L.layerGroup();
				markers.forEach( function ( item ) {
					var latLng = [ item.lat, item.lng ];
					L.marker( latLng, getMarkerOptions() ).bindPopup( common.buildPopup( item ), { maxWidth: 260, autoPanPaddingTopLeft: [ 50, 10 ] } ).addTo( layer );
					bounds.push( latLng );
				} );
				var lines = common.getRouteLines( el, markers );
				lines.solid.forEach( function ( line ) {
					// The map shall show the tracks completely.
					bounds = bounds.concat( line );
				} );
				var zoom = parseInt( el.getAttribute( 'data-zoom' ), 10 );
				if ( ! isNaN( zoom ) ) {
					// The zoom level is fixed by the shortcode.
					map.setView( L.latLngBounds( bounds ).getCenter(), zoom );
				} else if ( bounds.length === 1 ) {
					// A single location would be zoomed in to the maximum by fitBounds().
					map.setView( bounds[0], settings.zoom );
				} else {
					map.fitBounds( bounds, { padding: [ 30, 30 ] } );
				}
				map.addLayer( layer );

				lines.solid.forEach( function ( line ) {
					L.polyline( line, lineOptions ).addTo( map );
				} );
				lines.dashed.forEach( function ( line ) {
					L.polyline( line, dashedOptions ).addTo( map );
				} );
			} );
		} );
	} );
}() );
