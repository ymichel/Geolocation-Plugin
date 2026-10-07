/**
 * Geolocation: frontend maps using Google Maps.
 *
 * Requires geolocation-front-common.js.
 * The common script loads the Google Maps API once a map is needed.
 */
( function () {
	'use strict';

	var common   = window.geolocationFrontCommon;
	var settings = common ? common.settings : {};

	function toLatLng( latLng ) {
		return new google.maps.LatLng( latLng[0], latLng[1] );
	}

	function getMarkerOptions( position, map ) {
		var options = {
			position: position,
			map: map,
			title: 'Post Location'
		};
		if ( settings.usePin ) {
			options.icon = settings.pinUrl;
		}
		return options;
	}

	// Draw a route or a track; gaps between recorded tracks are dashed.
	function drawLine( map, line, dashed ) {
		var options = {
			map: map,
			path: line.map( toLatLng ),
			geodesic: true,
			clickable: false,
			strokeColor: '#2b6cb0',
			strokeOpacity: 0.8,
			strokeWeight: 3
		};
		if ( dashed ) {
			options.strokeOpacity = 0;
			options.icons         = [ {
				icon: { path: 'M 0,-1 0,1', strokeColor: '#2b6cb0', strokeOpacity: 0.8, scale: 3 },
				offset: '0',
				repeat: '10px'
			} ];
		}
		return new google.maps.Polyline( options );
	}

	function getMapOptions( center ) {
		return {
			zoom: settings.zoom,
			center: center,
			mapTypeId: google.maps.MapTypeId.ROADMAP
		};
	}

	function init() {
		if ( typeof google === 'undefined' || ! google.maps ) {
			return;
		}

		common.forEachPostMap( function ( el, latLng, track ) {
			var position = toLatLng( latLng );
			var map      = new google.maps.Map( el, getMapOptions( position ) );
			new google.maps.Marker( getMarkerOptions( position, map ) );
			if ( track.length ) {
				// Show the whole track of the post instead of the surroundings of its location.
				var bounds = new google.maps.LatLngBounds();
				bounds.extend( position );
				track.forEach( function ( point ) {
					bounds.extend( toLatLng( point ) );
				} );
				drawLine( map, track, false );
				map.fitBounds( bounds );
			}
		} );

		common.initHoverMap( function ( mapEl ) {
			var center = new google.maps.LatLng( 0.0, 0.0 );
			var map    = new google.maps.Map( mapEl, getMapOptions( center ) );
			var marker = new google.maps.Marker( getMarkerOptions( center, map ) );

			google.maps.event.addListener( map, 'center_changed', function () {
				window.setTimeout( function () {
					map.panTo( marker.getPosition() );
				}, 5000 );
			} );
			google.maps.event.addListener( map, 'click', function () {
				window.location = 'https://maps.google.com/maps?q=' + map.getCenter().lat() + ',+' + map.getCenter().lng();
			} );

			return function ( latLng ) {
				var position = toLatLng( latLng );
				map.setZoom( settings.zoom );
				marker.setPosition( position );
				map.setCenter( position );
			};
		} );

		common.forEachPageMap( function ( el, markers ) {
			var map        = new google.maps.Map( el, { mapTypeId: google.maps.MapTypeId.ROADMAP } );
			var bounds     = new google.maps.LatLngBounds();
			var infoWindow = new google.maps.InfoWindow();
			var mapMarkers = markers.map( function ( item ) {
				var options   = getMarkerOptions( new google.maps.LatLng( item.lat, item.lng ), map );
				options.title = item.title;
				var marker    = new google.maps.Marker( options );
				google.maps.event.addListener( marker, 'click', function () {
					infoWindow.setContent( common.buildPopup( item ) );
					// Without moving the focus, so the theme does not draw a focus outline around the title.
					infoWindow.open( { anchor: marker, map: map, shouldFocus: false } );
				} );
				bounds.extend( marker.getPosition() );
				return marker;
			} );
			var lines = common.getRouteLines( el, markers );
			lines.solid.forEach( function ( line ) {
				// The map shall show the tracks completely.
				line.forEach( function ( point ) {
					bounds.extend( toLatLng( point ) );
				} );
			} );
			var zoom = parseInt( el.getAttribute( 'data-zoom' ), 10 );
			if ( ! isNaN( zoom ) ) {
				// The zoom level is fixed by the shortcode.
				map.setCenter( bounds.getCenter() );
				map.setZoom( zoom );
			} else if ( markers.length === 1 && ! lines.solid.length ) {
				// A single location would be zoomed in to the maximum by fitBounds().
				map.setCenter( bounds.getCenter() );
				map.setZoom( settings.zoom );
			} else {
				map.fitBounds( bounds );
			}
			lines.solid.forEach( function ( line ) {
				drawLine( map, line, false );
			} );
			lines.dashed.forEach( function ( line ) {
				drawLine( map, line, true );
			} );
			// Markers lying close together are grouped if the cluster library is loaded.
			if ( window.markerClusterer && window.markerClusterer.MarkerClusterer ) {
				new window.markerClusterer.MarkerClusterer( {
					map: map,
					markers: mapMarkers,
					algorithmOptions: { radius: 40 }
				} );
			}
		} );
	}

	if ( common ) {
		common.ready( function () {
			common.start( init );
		} );
	}
}() );
