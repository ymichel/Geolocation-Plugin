/**
 * ESLint configuration for the plugin's own scripts. Bundled libraries are not checked.
 */
const js      = require( '@eslint/js' );
const globals = require( 'globals' );

module.exports = [
	{
		ignores: [ 'js/leaflet.js', 'js/leaflet.markercluster.js', 'js/markerclusterer.min.js', 'node_modules/', 'vendor/', '.claude/' ]
	},
	js.configs.recommended,
	{
		// The scripts are delivered as they are, without a build step.
		files: [ 'js/**/*.js' ],
		languageOptions: {
			ecmaVersion: 2017,
			sourceType: 'script',
			globals: Object.assign( {}, globals.browser, { L: 'readonly', google: 'readonly', wp: 'readonly' } )
		},
		rules: {
			eqeqeq: 'error',
			'no-implicit-globals': 'error',
			'no-shadow': 'error',
			'no-unused-vars': [ 'error', { caughtErrors: 'none' } ],
			'no-use-before-define': [ 'error', { functions: false } ],
			strict: [ 'error', 'function' ]
		}
	},
	{
		files: [ 'tests/js/**/*.js', 'eslint.config.js' ],
		languageOptions: {
			ecmaVersion: 2022,
			sourceType: 'commonjs',
			globals: globals.node
		}
	}
];
