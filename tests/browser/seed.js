/**
 * Creates or resets the demo content of the test instance and the settings the checks start from.
 *
 * Posts and pages with the slugs of the demo content are overwritten. A stored Google Maps API key is kept.
 * Also writes out/munich-venice.gpx, the track of the post "Munich to Venice by bike", for screenshot-1.js.
 */
const fs           = require( 'node:fs' );
const path         = require( 'node:path' );
const { chromium } = require( 'playwright-core' );
const { base }     = require( './lib' );

( async () => {
	const browser = await chromium.launch();
	const page    = await ( await browser.newContext() ).newPage();
	// Playground logs in on the first request.
	await page.goto( base + '/wp-admin/', { waitUntil: 'load' } );
	const answer = await page.evaluate( () => fetch( '/wp-admin/admin-ajax.php?action=geolocation_test&do=seed', { credentials: 'include' } ).then( ( r ) => r.text() ) );
	await browser.close();

	let result;
	try {
		result = JSON.parse( answer );
	} catch ( e ) {
		throw new Error( 'The test helper did not answer. Is the instance running at ' + base + ', started with "npm run wp:start"? Answer: ' + answer.slice( 0, 200 ), { cause: e } );
	}
	if ( ! result.success || result.data.error || result.data.errors ) {
		throw new Error( 'Seeding failed: ' + JSON.stringify( result.data ) );
	}

	const content = JSON.parse( fs.readFileSync( path.join( __dirname, 'mu-plugins', 'geolocation-test-content.json' ), 'utf8' ) );
	const track   = JSON.parse( content.posts.find( ( post ) => post.slug === 'munich-to-venice-by-bike' ).meta.geo_track );
	fs.writeFileSync( 'out/munich-venice.gpx', '<?xml version="1.0"?><gpx version="1.1" xmlns="http://www.topografix.com/GPX/1/1"><trk><name>Munich to Venice</name><trkseg>' + track.map( ( point ) => '<trkpt lat="' + point[0] + '" lon="' + point[1] + '"/>' ).join( '' ) + '</trkseg></trk></gpx>' );

	console.log( 'created: ' + ( result.data.created.join( ', ' ) || '-' ) );
	console.log( 'reset:   ' + ( result.data.updated.join( ', ' ) || '-' ) );
	console.log( 'proxy plugin active: ' + result.data.proxy + ', Google Maps API key stored: ' + result.data.state.apiKey );
} )().catch( ( error ) => {
	console.error( error.message );
	process.exit( 1 );
} );
