/**
 * Runs browser checks one after another against the test instance and tells whether they passed.
 *
 * node tests/browser/run.js                 the checks which run in CI (list below)
 * node tests/browser/run.js test-track ...  the named checks
 *
 * A check fails when it ends with an error or an exit code, when it reports JavaScript errors of a page in its
 * list "errors", or when one of its expectations is not met (list "failed", see report() in lib.js). A failed
 * check is repeated once after "npm run wp:seed", as the instance and the external servers it uses (map tiles,
 * Nominatim, Wikimedia) sometimes stumble. The output of every check is written to out/<check>.log and a summary
 * in Markdown to out/summary.md.
 */
const fs            = require( 'node:fs' );
const path          = require( 'node:path' );
const { spawnSync } = require( 'node:child_process' );

// Not in this list: the checks for Google Maps, which need an API key; the checks for pre-caching, which request
// many new tiles from the OpenStreetMap servers; the screenshots, which check nothing.
const CHECKS = [
	'test-api-location',
	'test-block-preview',
	'test-block',
	'test-bulk-location',
	'test-classic-editor',
	'test-convert-shortcodes',
	'test-derive-locations',
	'test-editor-location',
	'test-elevation-follow',
	'test-google-offer',
	'test-help-tab',
	'test-languages',
	'test-lazy-library',
	'test-location-block',
	'test-map-link-align',
	'test-map-link',
	'test-osm-proxy-fallback',
	'test-osm-proxy',
	'test-overview-map',
	'test-precache-row',
	'test-precache-switch',
	'test-proxy-fields',
	'test-query-loop',
	'test-remove-location',
	'test-route',
	'test-shortcode-attributes',
	'test-strict-admin',
	'test-strict-hint',
	'test-strict-privacy',
	'test-tiles-field',
	'test-track-switches',
	'test-track',
	'test-upgrade-uninstall'
];

const out    = path.join( __dirname, 'out' );
const checks = process.argv.slice( 2 ).map( ( name ) => name.replace( /\.js$/, '' ) );

fs.mkdirSync( out, { recursive: true } );

// Run a script of this folder and return what it printed and its exit code.
function node( script ) {
	const run = spawnSync( process.execPath, [ path.join( __dirname, script + '.js' ) ], { encoding: 'utf8', timeout: 15 * 60 * 1000, maxBuffer: 64 * 1024 * 1024 } );
	return { output: ( run.stdout || '' ) + ( run.stderr || '' ), status: run.error ? run.error.message : run.status, stdout: run.stdout || '' };
}

// The reasons why a run failed, empty if it passed.
function problems( run ) {
	const found = [];
	if ( 0 !== run.status ) {
		found.push( 'exit code ' + run.status );
	}
	// The checks print their result as one JSON object.
	const start = run.stdout.indexOf( '{' );
	let result  = null;
	try {
		result = JSON.parse( run.stdout.slice( start, run.stdout.lastIndexOf( '}' ) + 1 ) );
	} catch ( e ) {
		found.push( 'no result printed' );
	}
	if ( result && Array.isArray( result.errors ) && result.errors.length ) {
		found.push( 'JavaScript errors: ' + result.errors.join( ' | ' ).slice( 0, 300 ) );
	}
	if ( result && Array.isArray( result.failed ) && result.failed.length ) {
		found.push( 'not met: ' + result.failed.join( '; ' ) );
	}
	return found;
}

const rows   = [];
let failures = 0;
( checks.length ? checks : CHECKS ).forEach( ( check ) => {
	const started = Date.now();
	let run       = node( check );
	let found     = problems( run );
	let note      = '';
	if ( found.length ) {
		const first = found;
		node( 'seed' );
		run   = node( check );
		found = problems( run );
		note  = found.length ? '' : ' (passed on the second attempt; first: ' + first.join( ', ' ) + ')';
	}
	fs.writeFileSync( path.join( out, check + '.log' ), run.output );
	const seconds = Math.round( ( Date.now() - started ) / 1000 );
	const status  = found.length ? 'failed' : 'passed';
	failures     += found.length ? 1 : 0;
	console.log( status + '  ' + check + '  ' + seconds + ' s' + ( found.length ? '\n        ' + found.join( '\n        ' ) : note ) );
	rows.push( '| ' + ( found.length ? '❌' : '✅' ) + ' | `' + check + '` | ' + seconds + ' s | ' + ( found.join( '<br>' ) || note.trim() ).replace( /\|/g, '\\|' ) + ' |' );
} );

fs.writeFileSync( path.join( out, 'summary.md' ), '| | Check | Time | Problems |\n|---|---|---|---|\n' + rows.join( '\n' ) + '\n' );
console.log( '\n' + ( rows.length - failures ) + ' of ' + rows.length + ' checks passed.' );
process.exitCode = failures ? 1 : 0;
