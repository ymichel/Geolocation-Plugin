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

// The EXIF block of a photo taken at the given position (north and east).
function exif( lat, lng ) {
  const tiff = Buffer.alloc(128);
  const rational = (value, offset) => {
    const deg = Math.floor(value); const min = Math.floor((value - deg) * 60); const sec = Math.round(((value - deg) * 60 - min) * 6000);
    [[deg, 1], [min, 1], [sec, 100]].forEach((pair, i) => { tiff.writeUInt32BE(pair[0], offset + i * 8); tiff.writeUInt32BE(pair[1], offset + i * 8 + 4); });
  };
  const entry = (offset, tag, type, count, value) => { tiff.writeUInt16BE(tag, offset); tiff.writeUInt16BE(type, offset + 2); tiff.writeUInt32BE(count, offset + 4); if (typeof value === 'string') tiff.write(value, offset + 8, 'ascii'); else tiff.writeUInt32BE(value, offset + 8); };
  tiff.write('MM', 0, 'ascii'); tiff.writeUInt16BE(42, 2); tiff.writeUInt32BE(8, 4);
  // The first directory only points to the GPS directory.
  tiff.writeUInt16BE(1, 8); entry(10, 0x8825, 4, 1, 26);
  tiff.writeUInt16BE(4, 26); entry(28, 1, 2, 2, 'N'); entry(40, 2, 5, 3, 80); entry(52, 3, 2, 2, 'E'); entry(64, 4, 5, 3, 104);
  rational(lat, 80); rational(lng, 104);
  const head = Buffer.alloc(10); head.writeUInt16BE(0xFFE1, 0); head.writeUInt16BE(8 + tiff.length, 2); head.write('Exif', 4, 'ascii');
  return Buffer.concat([head, tiff]);
}

module.exports = {
	base,
	instance,
	// Where the proxy plugin stores tiles, and the must-use plugins of the instance.
	cache: path.join( instance, 'wp-content', 'cache', 'osm-tiles' ),
	chooseProvider,
	exif,
	muPlugins: process.env.GEOLOCATION_TEST_DIR ? path.join( instance, 'wp-content', 'mu-plugins' ) : path.join( __dirname, 'mu-plugins' ),
	id
};
