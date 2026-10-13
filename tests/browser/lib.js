/**
 * Shared settings of the browser checks: where the test instance runs and how its demo content is found.
 *
 * GEOLOCATION_TEST_URL  the address of the instance (default http://127.0.0.1:9400)
 * GEOLOCATION_TEST_DIR  the folder of its WordPress files (default tests/browser/.instance/wordpress)
 */
const fs                = require( 'node:fs' );
const path              = require( 'node:path' );
const { execFileSync }  = require( 'node:child_process' );

const base     = ( process.env.GEOLOCATION_TEST_URL || 'http://127.0.0.1:9400' ).replace( /\/+$/, '' );
const instance = path.resolve( process.env.GEOLOCATION_TEST_DIR || path.join( __dirname, '.instance', 'wordpress' ) );

// The checks write their control images and GPX files to tests/browser/out and use paths relative to this folder.
fs.mkdirSync( path.join( __dirname, 'out' ), { recursive: true } );
process.chdir( __dirname );

let ids = null;

// The id of a post or page of the demo content by its slug. Ids differ from instance to instance, slugs do not.
function id( slug ) {
	if ( ! ids ) {
		// Asked once, in a child process, so the checks can use the ids like constants.
		// The cookie keeps Playground from answering with its redirect which logs in.
		const code = `
			const get = ( type ) => fetch( ${ JSON.stringify( base ) } + '/?rest_route=/wp/v2/' + type + '&per_page=100&_fields=id,slug', { headers: { Cookie: 'playground_auto_login_already_happened=1' } } ).then( ( r ) => r.json() );
			Promise.all( [ get( 'posts' ), get( 'pages' ) ] ).then( ( lists ) => console.log( JSON.stringify( [].concat( ...lists ) ) ) );`;
		ids = {};
		JSON.parse( execFileSync( process.execPath, [ '-e', code ], { encoding: 'utf8' } ) ).forEach( ( item ) => {
			ids[ item.slug ] = item.id;
		} );
	}
	if ( ! ids[ slug ] ) {
		throw new Error( 'The test instance at ' + base + ' has no post or page "' + slug + '". Run "npm run wp:seed" first.' );
	}
	return ids[ slug ];
}

// Choose the map provider on the settings page. A website which does not use Google Maps only has OpenStreetMap
// and no select: Google Maps is offered once an API key is stored in the instance.
async function chooseProvider( page, name ) {
	if ( await page.$( 'select#geolocation_provider' ) ) {
		await page.selectOption( '#geolocation_provider', name );
	} else if ( name !== 'osm' ) {
		throw new Error( 'Google Maps is not offered by the test instance at ' + base + ': it has no API key, see tests/instructions.md.' );
	}
}

module.exports = {
	base,
	instance,
	// Where the proxy plugin stores tiles, and the must-use plugins of the instance.
	cache: path.join( instance, 'wp-content', 'cache', 'osm-tiles' ),
	chooseProvider,
	muPlugins: process.env.GEOLOCATION_TEST_DIR ? path.join( instance, 'wp-content', 'mu-plugins' ) : path.join( __dirname, 'mu-plugins' ),
	id
};
