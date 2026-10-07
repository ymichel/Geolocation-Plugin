/**
 * Unit tests for the provider independent helpers of the frontend maps (js/geolocation-front-common.js).
 */
const test              = require( 'node:test' );
const assert            = require( 'node:assert/strict' );
const { load, element } = require( './load' );

const front = load( 'geolocation-front-common.js', 'geolocationFrontCommon', { geolocationFront: { zoom: 12 } } );

test( 'the settings provided by PHP are passed on', () => {
	assert.equal( front.settings.zoom, 12 );
} );

test( 'a position is read from an element', () => {
	assert.deepEqual( front.parseLatLng( element( { 'data-geolocation': '52.52,13.405' } ) ), [ 52.52, 13.405 ] );
	assert.deepEqual( front.parseLatLng( element( { 'data-geolocation': '-33.9,-70.6' } ) ), [ -33.9, -70.6 ] );
	// Former versions used the name attribute.
	assert.deepEqual( front.parseLatLng( element( { name: '1,2' } ) ), [ 1, 2 ] );
	assert.equal( front.parseLatLng( element( {} ) ), null );
	assert.equal( front.parseLatLng( element( { 'data-geolocation': '52.52' } ) ), null );
	assert.equal( front.parseLatLng( element( { 'data-geolocation': 'a,b' } ) ), null );
	assert.equal( front.parseLatLng( element( { 'data-geolocation': '1,2,3' } ) ), null );
} );

test( 'the track of a post is read from an element', () => {
	assert.deepEqual( front.parseTrack( element( { 'data-track': '[[1,2],[3,4]]' } ) ), [ [ 1, 2 ], [ 3, 4 ] ] );
	// Missing, broken, too short or not a list: no track.
	assert.deepEqual( front.parseTrack( element( {} ) ), [] );
	assert.deepEqual( front.parseTrack( element( { 'data-track': '[[1,2' } ) ), [] );
	assert.deepEqual( front.parseTrack( element( { 'data-track': '[[1,2]]' } ) ), [] );
	assert.deepEqual( front.parseTrack( element( { 'data-track': '{"a":1}' } ) ), [] );
} );

const route = element( { 'data-route': '1' } );

test( 'route: nothing is drawn without the route attribute', () => {
	const markers = [ { lat: 1, lng: 1, time: 1 }, { lat: 2, lng: 2, time: 2 } ];
	assert.deepEqual( front.getRouteLines( element( {} ), markers ), { solid: [], dashed: [] } );
} );

test( 'route: without tracks the locations are connected in the order of the posts', () => {
	const markers = [ { lat: 3, lng: 3, time: 30 }, { lat: 1, lng: 1, time: 10 }, { lat: 2, lng: 2, time: 20 } ];
	assert.deepEqual( front.getRouteLines( route, markers ), { solid: [ [ [ 1, 1 ], [ 2, 2 ], [ 3, 3 ] ] ], dashed: [] } );
	// The list of markers itself keeps its order.
	assert.equal( markers[0].time, 30 );
	// A single location is no route.
	assert.deepEqual( front.getRouteLines( route, [ markers[0] ] ), { solid: [], dashed: [] } );
} );

test( 'route: tracks are solid, the gaps between the posts are dashed', () => {
	const markers = [
		{ lat: 0, lng: 0, time: 1 },
		{ lat: 2, lng: 2, time: 2, track: [ [ 1, 1 ], [ 1.5, 1.5 ], [ 2, 2 ] ] },
		{ lat: 5, lng: 5, time: 3 },
		{ lat: 7, lng: 7, time: 4, track: [ [ 6, 6 ], [ 7, 7 ] ] }
	];
	const lines = front.getRouteLines( route, markers );
	assert.deepEqual( lines.solid, [ [ [ 1, 1 ], [ 1.5, 1.5 ], [ 2, 2 ] ], [ [ 6, 6 ], [ 7, 7 ] ] ] );
	assert.deepEqual( lines.dashed, [
		// From the first location to the start of the first track,
		[ [ 0, 0 ], [ 1, 1 ] ],
		// from its end to the next location,
		[ [ 2, 2 ], [ 5, 5 ] ],
		// and on to the start of the second track.
		[ [ 5, 5 ], [ 6, 6 ] ]
	] );
} );

test( 'route: a track of a single position is treated as a location', () => {
	const markers = [ { lat: 1, lng: 1, time: 1, track: [ [ 9, 9 ] ] }, { lat: 2, lng: 2, time: 2 } ];
	assert.deepEqual( front.getRouteLines( route, markers ), { solid: [ [ [ 1, 1 ], [ 2, 2 ] ] ], dashed: [] } );
} );
