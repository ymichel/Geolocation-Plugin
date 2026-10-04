/**
 * Geolocation: frontend maps using Google Maps.
 *
 * Requires geolocation-front-common.js.
 * The Google Maps API calls window.geolocationInitMap once it is loaded.
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

	function getMapOptions( center ) {
		return {
			zoom: settings.zoom,
			center: center,
			mapTypeId: google.maps.MapTypeId.ROADMAP
		};
	}

	window.geolocationInitMap = function () {
		if ( ! common || typeof google === 'undefined' || ! google.maps ) {
			return;
		}

		common.forEachPostMap( function ( el, latLng ) {
			var position = toLatLng( latLng );
			var map      = new google.maps.Map( el, getMapOptions( position ) );
			new google.maps.Marker( getMarkerOptions( position, map ) );
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
			var map    = new google.maps.Map( el, { mapTypeId: google.maps.MapTypeId.ROADMAP } );
			var bounds = new google.maps.LatLngBounds();
			markers.forEach( function ( item ) {
				var options   = getMarkerOptions( new google.maps.LatLng( item.lat, item.lng ), map );
				options.title = item.title;
				options.url   = item.url;
				bounds.extend( new google.maps.Marker( options ).getPosition() );
			} );
			if ( markers.length === 1 ) {
				// A single location would be zoomed in to the maximum by fitBounds().
				map.setCenter( bounds.getCenter() );
				map.setZoom( settings.zoom );
			} else {
				map.fitBounds( bounds );
			}
		} );
	};
}() );
