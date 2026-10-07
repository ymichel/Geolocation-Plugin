/**
 * Unit tests for reading GPX files in the post editor (js/geolocation-admin-common.js).
 */
const test             = require( 'node:test' );
const assert           = require( 'node:assert/strict' );
const { load }         = require( './load' );

const admin = load( 'geolocation-admin-common.js', 'geolocationAdminCommon', {} );

// Build a GPX file from [ lat, lng, elevation ] positions; without an elevation the tag is left out.
function gpx( positions, tag ) {
	const name   = tag || 'trkpt';
	const points = positions.map( ( p ) => '<' + name + ' lat="' + p[0] + '" lon="' + p[1] + '">' + ( p.length > 2 ? '<ele>' + p[2] + '</ele>' : '' ) + '</' + name + '>' ).join( '' );
	return '<?xml version="1.0"?><gpx xmlns="http://www.topografix.com/GPX/1/1" version="1.1"><trk><trkseg>' + points + '</trkseg></trk></gpx>';
}

test( 'distance between two positions in kilometres', () => {
	// Berlin to Hamburg.
	const km = admin.distance( [ 52.52, 13.405 ], [ 53.5511, 9.9937 ] );
	assert.ok( Math.abs( km - 255.3 ) < 1, 'got ' + km );
	assert.equal( admin.distance( [ 48, 11 ], [ 48, 11 ] ), 0 );
	// One degree of latitude.
	assert.ok( Math.abs( admin.distance( [ 0, 0 ], [ 1, 0 ] ) - 111.2 ) < 0.1 );
} );

test( 'simplify keeps the shape of a track', () => {
	// Positions on a straight line are dropped, the corner is kept.
	const points = [ [ 0, 0 ], [ 0, 0.001 ], [ 0, 0.002 ], [ 0, 0.003 ], [ 0.003, 0.003 ] ];
	assert.deepEqual( admin.simplify( points, 0.00002 ), [ [ 0, 0 ], [ 0, 0.003 ], [ 0.003, 0.003 ] ] );
	// A small detour is kept with a small tolerance and dropped with a large one.
	const detour = [ [ 0, 0 ], [ 0.0005, 0.001 ], [ 0, 0.002 ] ];
	assert.equal( admin.simplify( detour, 0.0001 ).length, 3 );
	assert.equal( admin.simplify( detour, 0.001 ).length, 2 );
	// Start and end always remain.
	assert.deepEqual( admin.simplify( [ [ 1, 1 ], [ 2, 2 ] ], 1 ), [ [ 1, 1 ], [ 2, 2 ] ] );
} );

test( 'elevations: ascent and descent ignore changes below five metres', () => {
	const result = admin.describeElevation( [ 100, 103, 101, 104, 110, 108, 100 ], [ 0, 1, 2, 3, 4, 5, 6 ] );
	// 100 -> 110 counts as ascent, 110 -> 100 as descent; the steps of two and three metres do not.
	assert.equal( result.up, 10 );
	assert.equal( result.down, 10 );
} );

test( 'elevations: the profile has 200 values at equal distances', () => {
	const result = admin.describeElevation( [ 0, 100, 50 ], [ 0, 10, 20 ] );
	assert.equal( result.profile.length, 200 );
	assert.equal( result.profile[0], 0 );
	assert.equal( result.profile[199], 50 );
	// Half of the way is the second position.
	assert.ok( Math.abs( result.profile[100] - 100 ) <= 1, 'got ' + result.profile[100] );
	assert.equal( Math.max( ...result.profile ) <= 100, true );
} );

test( 'elevations: a track without length has no profile', () => {
	assert.equal( admin.describeElevation( [ 10, 20 ], [ 0, 0 ] ), null );
} );

test( 'GPX: positions, length and elevations are read', () => {
	const track = admin.parseGpx( gpx( [ [ 48.13, 11.58, 520 ], [ 48.14, 11.58, 540 ], [ 48.15, 11.58, 530 ] ] ) );
	assert.deepEqual( track.points, [ [ 48.13, 11.58 ], [ 48.14, 11.58 ], [ 48.15, 11.58 ] ] );
	assert.ok( Math.abs( track.km - 2.224 ) < 0.01, 'got ' + track.km );
	assert.equal( track.elevation.up, 20 );
	assert.equal( track.elevation.down, 10 );
	assert.equal( track.elevation.profile.length, 200 );
} );

test( 'GPX: a route is read if the file has no track', () => {
	const track = admin.parseGpx( gpx( [ [ 50, 8 ], [ 50.1, 8.1 ] ], 'rtept' ) );
	assert.equal( track.points.length, 2 );
	assert.equal( track.elevation, null );
} );

test( 'GPX: no profile if an elevation is missing or most of them are zero', () => {
	assert.equal( admin.parseGpx( gpx( [ [ 48, 11, 500 ], [ 48.1, 11 ], [ 48.2, 11, 520 ] ] ) ).elevation, null );
	assert.equal( admin.parseGpx( gpx( [ [ 48, 11, 0 ], [ 48.1, 11, 0 ], [ 48.2, 11, 520 ] ] ) ).elevation, null );
	assert.notEqual( admin.parseGpx( gpx( [ [ 48, 11, 0 ], [ 48.1, 11, 510 ], [ 48.2, 11, 520 ] ] ) ).elevation, null );
} );

test( 'GPX: invalid positions are skipped, coordinates are rounded to five decimals', () => {
	const track = admin.parseGpx( gpx( [ [ 48.123456789, 11.987654321 ], [ 95, 11 ], [ 'x', 11 ], [ 48.2, 190 ], [ 48.2, 11.9 ] ] ) );
	assert.deepEqual( track.points, [ [ 48.12346, 11.98765 ], [ 48.2, 11.9 ] ] );
} );

test( 'GPX: files without at least two positions are refused', () => {
	assert.equal( admin.parseGpx( gpx( [ [ 48, 11 ] ] ) ), null );
	assert.equal( admin.parseGpx( '<gpx></gpx>' ), null );
	assert.equal( admin.parseGpx( 'this is not a GPX file' ), null );
} );

test( 'GPX: long tracks are reduced to at most 1000 positions and keep their ends', () => {
	const positions = [];
	for ( let i = 0; i < 5000; i++ ) {
		// A winding line, so the positions are not simply on one straight line.
		positions.push( [ 47 + i * 0.0004, 11 + Math.sin( i / 7 ) * 0.002, 500 + ( i % 50 ) ] );
	}
	const track = admin.parseGpx( gpx( positions ) );
	assert.ok( track.points.length <= 1000 && track.points.length > 100, 'got ' + track.points.length );
	assert.deepEqual( track.points[0], [ 47, 11 ] );
	assert.deepEqual( track.points[ track.points.length - 1 ], [ Math.round( positions[4999][0] * 1e5 ) / 1e5, Math.round( positions[4999][1] * 1e5 ) / 1e5 ] );
	// The length is measured on the whole track, not on the reduced one.
	assert.ok( track.km > 220, 'got ' + track.km );
} );
