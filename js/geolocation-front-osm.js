/**
 * Geolocation: frontend maps using OpenStreetMap (Leaflet).
 *
 * Requires geolocation-front-common.js.
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

	common.ready( function () {
		if ( typeof L === 'undefined' ) {
			return;
		}

		common.forEachPostMap( function ( el, latLng ) {
			var map = createMap( el );
			L.marker( latLng, getMarkerOptions() ).addTo( map );
			map.setView( latLng, settings.zoom );
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
			markers.forEach( function ( item ) {
				var latLng = [ item.lat, item.lng ];
				var link   = document.createElement( 'a' );
				link.href        = item.url;
				link.textContent = item.title;
				L.marker( latLng, getMarkerOptions() ).addTo( map ).bindPopup( link );
				bounds.push( latLng );
			} );
			if ( bounds.length === 1 ) {
				// A single location would be zoomed in to the maximum by fitBounds().
				map.setView( bounds[0], settings.zoom );
			} else {
				map.fitBounds( bounds );
			}
		} );
	} );
}() );
