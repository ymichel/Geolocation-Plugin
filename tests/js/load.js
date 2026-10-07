/**
 * Loads a script of the plugin into Node for the unit tests.
 *
 * The scripts expect a browser, so the few globals they touch while loading are provided here.
 */
const path          = require( 'node:path' );
const { DOMParser } = require( '@xmldom/xmldom' );

// Load js/<name> with the given settings and return what the script published as window[ published ].
function load( name, published, settings ) {
	const file = path.join( __dirname, '..', '..', 'js', name );
	delete require.cache[ require.resolve( file ) ];
	global.window    = Object.assign( {}, settings );
	global.document  = { readyState: 'complete', addEventListener() {}, getElementById: () => null, querySelectorAll: () => [], querySelector: () => null };
	global.DOMParser = DOMParser;
	require( file );
	return global.window[ published ];
}

// A stand-in for an element which only has attributes.
function element( attributes ) {
	return {
		getAttribute: ( name ) => ( Object.prototype.hasOwnProperty.call( attributes, name ) ? attributes[ name ] : null )
	};
}

module.exports = { load, element };
