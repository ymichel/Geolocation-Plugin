/**
 * Geolocation: post editor meta box using OpenStreetMap (Leaflet / Nominatim).
 *
 * Requires geolocation-admin-common.js.
 */
( function () {
	'use strict';

	var common = window.geolocationAdminCommon;
	if ( ! common ) {
		return;
	}

	common.ready( function () {
		var map;
		var marker;
		var trackLine = null;
		var ui = typeof L === 'undefined' ? null : common.init( { geocode: geocode, setLocation: setLocation, showTrack: showTrack } );
		if ( ! ui ) {
			return;
		}

		var data          = ui.data;
		var defaultCenter = [ 52.5162778, 13.3733267 ];
		var markerOptions = {
			draggable: true
		};
		if ( data.usePin ) {
			markerOptions.icon = L.icon( {
				iconUrl: data.pinUrl,
				shadowUrl: data.pinShadowUrl,
				iconSize: [ 25, 34 ],
				shadowSize: [ 39, 23 ],
				iconAnchor: [ 5, 34 ],
				shadowAnchor: [ 3, 25 ],
				popupAnchor: [ 12, -30 ]
			} );
		}

		function request( path, onSuccess ) {
			var xhr = new XMLHttpRequest();
			// In strict privacy mode the request goes to this site, which asks the geocoding service.
			xhr.open( 'GET', data.geocodeUrl ? data.geocodeUrl + '&path=' + encodeURIComponent( path ) : data.nominatimUrl + path, true );
			xhr.onload = function () {
				if ( this.status >= 200 && this.status < 400 ) {
					var result;
					try {
						result = JSON.parse( this.response );
					} catch ( e ) {
						result = null;
					}
					onSuccess( result );
				}
			};
			xhr.send();
		}

		function reverseGeocode( lat, lon ) {
			request(
				'/reverse?format=json&accept-language=' + encodeURIComponent( data.language ) + '&lat=' + encodeURIComponent( lat ) + '&lon=' + encodeURIComponent( lon ),
				function ( result ) {
					if ( result && result.display_name ) {
						ui.setAddress( result.display_name );
					}
				}
			);
		}

		// Move the marker to a position picked on the map, by dragging or by the browser's location.
		function setLocation( lat, lng, center ) {
			ui.setPosition( lat, lng );
			marker.setLatLng( [ lat, lng ] );
			if ( center ) {
				map.setView( marker.getLatLng(), Math.max( map.getZoom(), data.zoom ) );
			}
			reverseGeocode( lat, lng );
		}

		// Show the map section containing the marker and the track.
		function fitTrack() {
			map.fitBounds( trackLine.getBounds().extend( marker.getLatLng() ), { padding: [ 20, 20 ], animate: false } );
		}

		// Draw the track of the post; an empty list removes it.
		function showTrack( points ) {
			if ( trackLine ) {
				map.removeLayer( trackLine );
				trackLine = null;
			}
			if ( points.length > 1 ) {
				// Not interactive, so a click on the line still sets the location.
				trackLine = L.polyline( points, { color: '#2b6cb0', weight: 3, opacity: 0.8, interactive: false } ).addTo( map );
				fitTrack();
			}
		}

		function geocode( address ) {
			request(
				'/search?format=json&accept-language=' + encodeURIComponent( data.language ) + '&limit=1&q=' + encodeURIComponent( address ),
				function ( result ) {
					if ( ! Array.isArray( result ) || result.length === 0 ) {
						return;
					}
					ui.setPosition( result[0].lat, result[0].lon );
					marker.setLatLng( [ result[0].lat, result[0].lon ] );
					map.setView( marker.getLatLng(), map.getZoom() );
					reverseGeocode( result[0].lat, result[0].lon );
				}
			);
		}

		map    = L.map( ui.els.map ).setView( defaultCenter, data.zoom );
		marker = L.marker( defaultCenter, markerOptions ).addTo( map );
		// Without tiles (strict privacy mode without the proxy) the map stays hidden; the location is set by its address.
		if ( ! data.mapBlocked ) {
			L.tileLayer( data.tilesUrl, {
				attribution: '&copy; <a href="https://osm.org/copyright">OpenStreetMap</a> contributors'
			} ).addTo( map );
		}

		map.on( 'click', function ( event ) {
			if ( ui.isEnabled() ) {
				setLocation( event.latlng.lat, event.latlng.lng, false );
			}
		} );
		marker.on( 'dragend', function () {
			var position = marker.getLatLng();
			setLocation( position.lat, position.lng, false );
		} );

		if ( ui.hasLocation ) {
			marker.setLatLng( [ data.latitude, data.longitude ] );
			map.setView( marker.getLatLng(), data.zoom );
			if ( ui.needsAddress ) {
				reverseGeocode( data.latitude, data.longitude );
			}
		}

		// The meta box may be collapsed or hidden at first: re-center once the map gets its size.
		function recenter() {
			map.invalidateSize( { animate: false, pan: false } );
			if ( trackLine ) {
				fitTrack();
			} else {
				map.setView( marker.getLatLng(), map.getZoom(), { animate: false } );
			}
		}
		showTrack( ui.track );
		setTimeout( recenter, 100 );
		if ( 'ResizeObserver' in window ) {
			new ResizeObserver( recenter ).observe( ui.els.map );
		}
	} );
}() );
