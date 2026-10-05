/**
 * Geolocation: the block "Geolocation Map" for the block editor.
 *
 * The block is rendered by PHP; attributes, title and description are registered there.
 * Settings and translated texts are provided by PHP in window.geolocationBlock.
 */
( function ( wp ) {
	'use strict';

	var el             = wp.element.createElement;
	var components     = wp.components;
	var blockEditor    = wp.blockEditor;
	var decodeEntities = wp.htmlEntities.decodeEntities;
	var settings       = window.geolocationBlock || {};
	var i18n           = settings.i18n || {};

	// All terms of a taxonomy as [ { id, name } ]; null while they are loading.
	function useTerms( taxonomy ) {
		return wp.data.useSelect( function ( select ) {
			var terms = select( 'core' ).getEntityRecords( 'taxonomy', taxonomy, { per_page: -1, orderby: 'name', _fields: 'id,name' } );
			if ( ! terms ) {
				return null;
			}
			return terms.map( function ( term ) {
				return { id: term.id, name: decodeEntities( term.name ) };
			} );
		}, [ taxonomy ] );
	}

	// Choose terms by their names; the block stores their ids.
	function TermSelect( props ) {
		var terms = useTerms( props.taxonomy );
		if ( ! terms ) {
			return el( components.Spinner );
		}
		var selected = props.value.map( function ( id ) {
			var match = terms.filter( function ( term ) {
				return term.id === id;
			} )[0];
			return match ? match.name : null;
		} ).filter( Boolean );
		return el( components.FormTokenField, {
			label: props.label,
			value: selected,
			suggestions: terms.map( function ( term ) {
				return term.name;
			} ),
			onChange: function ( names ) {
				var ids = [];
				names.forEach( function ( name ) {
					var match = terms.filter( function ( term ) {
						return term.name.toLowerCase() === String( name ).toLowerCase();
					} )[0];
					if ( match && ids.indexOf( match.id ) === -1 ) {
						ids.push( match.id );
					}
				} );
				props.onChange( ids );
			},
			__experimentalExpandOnFocus: true,
			__experimentalShowHowTo: false,
			__next40pxDefaultSize: true,
			__nextHasNoMarginBottom: true
		} );
	}

	// The names of the chosen terms, e.g. for the summary inside the placeholder.
	function TermNames( props ) {
		var terms = useTerms( props.taxonomy );
		var names = ( terms || [] ).filter( function ( term ) {
			return props.value.indexOf( term.id ) !== -1;
		} ).map( function ( term ) {
			return term.name;
		} );
		return names.length ? el( 'div', null, props.label + ': ' + names.join( ', ' ) ) : null;
	}

	function Edit( props ) {
		var attributes = props.attributes;
		var set        = props.setAttributes;
		var filtered   = attributes.categories.length > 0 || attributes.tags.length > 0;
		var height     = attributes.height >= 50 ? attributes.height : ( settings.height || 300 );
		var wide       = attributes.align === 'wide' || attributes.align === 'full';
		var size       = ( attributes.width || ( wide ? '100%' : ( settings.width || 600 ) + 'px' ) ) + ' × ' + height + 'px';

		var inspector = el( blockEditor.InspectorControls, null,
			el( components.PanelBody, { title: i18n.posts },
				el( TermSelect, {
					label: i18n.categories,
					taxonomy: 'category',
					value: attributes.categories,
					onChange: function ( ids ) {
						set( { categories: ids } );
					}
				} ),
				el( 'div', { style: { height: '16px' } } ),
				el( TermSelect, {
					label: i18n.tags,
					taxonomy: 'post_tag',
					value: attributes.tags,
					onChange: function ( ids ) {
						set( { tags: ids } );
					}
				} ),
				el( 'p', { className: 'components-base-control__help', style: { marginTop: '8px' } }, i18n.postsHelp )
			),
			el( components.PanelBody, { title: i18n.map },
				el( components.TextControl, {
					label: i18n.width,
					help: i18n.widthHelp,
					value: attributes.width,
					onChange: function ( value ) {
						set( { width: value.trim() } );
					},
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true
				} ),
				el( components.TextControl, {
					label: i18n.height,
					help: i18n.heightHelp,
					type: 'number',
					min: 50,
					max: 9999,
					value: attributes.height || '',
					onChange: function ( value ) {
						set( { height: parseInt( value, 10 ) || 0 } );
					},
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true
				} ),
				el( components.ToggleControl, {
					label: i18n.fit,
					checked: ! attributes.zoom,
					onChange: function ( fit ) {
						set( { zoom: fit ? 0 : 6 } );
					},
					__nextHasNoMarginBottom: true
				} ),
				attributes.zoom ? el( components.RangeControl, {
					label: i18n.zoom,
					min: 1,
					max: 19,
					value: attributes.zoom,
					onChange: function ( value ) {
						set( { zoom: value || 0 } );
					},
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true
				} ) : null,
				el( components.ToggleControl, {
					label: i18n.route,
					help: i18n.routeHelp,
					checked: attributes.route,
					onChange: function ( value ) {
						set( { route: value } );
					},
					__nextHasNoMarginBottom: true
				} )
			)
		);

		var summary = el( 'div', { className: 'geolocation-block-summary' },
			filtered ? null : el( 'div', null, i18n.allPosts ),
			el( TermNames, { label: i18n.categories, taxonomy: 'category', value: attributes.categories } ),
			el( TermNames, { label: i18n.tags, taxonomy: 'post_tag', value: attributes.tags } ),
			el( 'div', null, size + ( attributes.zoom ? ' · ' + i18n.zoom + ' ' + attributes.zoom : '' ) + ( attributes.route ? ' · ' + i18n.route : '' ) )
		);

		return el( 'div', blockEditor.useBlockProps(),
			inspector,
			el( components.Placeholder, { icon: 'location-alt', label: i18n.title, instructions: i18n.placeholder }, summary )
		);
	}

	wp.blocks.registerBlockType( 'geolocation/map', {
		title: i18n.title,
		edit: Edit,
		save: function () {
			return null;
		}
	} );
}( window.wp ) );
