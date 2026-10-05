/**
 * Geolocation: post editor meta box using Google Maps.
 *
 * Requires geolocation-admin-common.js.
 * The Google Maps API calls window.geolocationInitMap once it is loaded.
 */
( function () {
	'use strict';

	var common = window.geolocationAdminCommon;

	window.geolocationInitMap = function () {
		var map;
		var marker;
		var geocoder;
		var hasLocation;
		var trackLine = null;
		var ui = ! common || typeof google === 'undefined' || ! google.maps ? null : common.init( { geocode: geocode, setLocation: setLocation, showTrack: showTrack } );
		if ( ! ui ) {
			return;
		}

		var data   = ui.data;
		var center = ui.hasLocation ? new google.maps.LatLng( data.latitude, data.longitude ) : new google.maps.LatLng( 52.5162778, 13.3733267 );

		function reverseGeocode( location ) {
			geocoder.geocode( { location: location }, function ( results, status ) {
				if ( status === google.maps.GeocoderStatus.OK && results[1] ) {
					ui.setAddress( results[1].formatted_address );
				}
			} );
		}

		function placeMarker( location ) {
			marker.setPosition( location );
			map.setCenter( location );
			ui.setPosition( location.lat(), location.lng() );
			reverseGeocode( location );
		}

		// Move the marker to the position reported by the browser.
		function setLocation( lat, lng ) {
			placeMarker( new google.maps.LatLng( lat, lng ) );
			if ( ! hasLocation ) {
				map.setZoom( data.zoom );
				hasLocation = true;
			}
		}

		// Show the map section containing the marker and the track.
		function fitTrack() {
			var bounds = new google.maps.LatLngBounds();
			bounds.extend( marker.getPosition() );
			trackLine.getPath().forEach( function ( position ) {
				bounds.extend( position );
			} );
			map.fitBounds( bounds );
		}

		// Draw the track of the post; an empty list removes it.
		function showTrack( points ) {
			if ( trackLine ) {
				trackLine.setMap( null );
				trackLine = null;
			}
			if ( points.length > 1 ) {
				// Not clickable, so a click on the line still sets the location.
				trackLine = new google.maps.Polyline( {
					map: map,
					path: points.map( function ( point ) {
						return new google.maps.LatLng( point[0], point[1] );
					} ),
					geodesic: true,
					clickable: false,
					strokeColor: '#2b6cb0',
					strokeOpacity: 0.8,
					strokeWeight: 3
				} );
				fitTrack();
			}
		}

		function geocode( address ) {
			geocoder.geocode( { address: address }, function ( results, status ) {
				if ( status === google.maps.GeocoderStatus.OK && results[0] ) {
					placeMarker( results[0].geometry.location );
					if ( ! hasLocation ) {
						map.setZoom( data.zoom );
						hasLocation = true;
					}
				}
			} );
		}

		hasLocation = ui.hasLocation;
		geocoder    = new google.maps.Geocoder();
		map         = new google.maps.Map( ui.els.map, {
			zoom: hasLocation ? data.zoom : 1,
			center: center,
			mapTypeId: google.maps.MapTypeId.ROADMAP
		} );

		var markerOptions = {
			position: center,
			map: map,
			draggable: true,
			title: 'Post Location'
		};
		if ( data.usePin ) {
			markerOptions.icon = data.pinUrl;
		}
		marker = new google.maps.Marker( markerOptions );
		google.maps.event.addListener( marker, 'dragend', function ( event ) {
			placeMarker( event.latLng );
		} );

		if ( ui.needsAddress ) {
			reverseGeocode( center );
		}

		// The meta box may be collapsed or hidden at first: re-center once the map gets its size.
		if ( 'ResizeObserver' in window ) {
			new ResizeObserver( function () {
				if ( trackLine ) {
					fitTrack();
				} else {
					map.setCenter( marker.getPosition() );
				}
			} ).observe( ui.els.map );
		}
		showTrack( ui.track );

		google.maps.event.addListener( map, 'click', function ( event ) {
			if ( ui.isEnabled() ) {
				placeMarker( event.latLng );
			}
		} );
	};
}() );
