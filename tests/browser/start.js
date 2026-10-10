/**
 * Starts the test instance: WordPress Playground with this checkout mounted as the plugin "geolocation",
 * the test helper as must-use plugin and the proxy plugin for OpenStreetMap tiles installed.
 *
 * The WordPress files and the database are kept in tests/browser/.instance, so the instance survives a restart.
 * Delete that folder to start from scratch. The port is 9400; "--port=9401" or GEOLOCATION_TEST_PORT changes it.
 */
const fs        = require( 'node:fs' );
const path      = require( 'node:path' );
const { spawn } = require( 'node:child_process' );

const repo      = path.resolve( __dirname, '..', '..' );
const wordpress = path.join( __dirname, '.instance', 'wordpress' );
const argument  = process.argv.find( ( value ) => /^--port=\d+$/.test( value ) );
const port      = argument ? argument.slice( 7 ) : ( process.env.GEOLOCATION_TEST_PORT || '9400' );

fs.mkdirSync( wordpress, { recursive: true } );

// The first start downloads WordPress into the folder, later starts use what is there.
const mode = fs.existsSync( path.join( wordpress, 'wp-load.php' ) ) ? 'install-from-existing-files-if-needed' : 'download-and-install';

const child = spawn(
	'npx',
	[
		'--yes',
		'@wp-playground/cli@latest',
		'server',
		'--mount-before-install=' + wordpress + ':/wordpress',
		'--mount=' + repo + ':/wordpress/wp-content/plugins/geolocation',
		'--mount=' + path.join( __dirname, 'mu-plugins' ) + ':/wordpress/wp-content/mu-plugins',
		'--wordpress-install-mode=' + mode,
		'--blueprint=' + path.join( __dirname, 'blueprint.json' ),
		'--login',
		'--port=' + port
	],
	{ stdio: 'inherit', shell: process.platform === 'win32' }
);
child.on( 'exit', ( code ) => process.exit( code === null ? 1 : code ) );
