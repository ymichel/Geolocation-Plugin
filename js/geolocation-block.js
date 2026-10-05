/**
 * Geolocation: the blocks "Geolocation Map" and "Post Location" for the block editor.
 *
 * The blocks are rendered by PHP; attributes, title and description are registered there.
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

	// The preview of a block: a small page of the plugin, shown in a frame, which runs the scripts of the website.
	// The frame only reacts to the mouse while the block is selected, so a click on it selects the block.
	function Preview( props ) {
		var useState  = wp.element.useState;
		var useEffect = wp.element.useEffect;
		var useRef    = wp.element.useRef;
		var frame     = useRef( null );
		var query     = JSON.stringify( props.attributes );
		var state     = useState( query );
		var height    = useState( props.height );
		// Reload the preview after the post and its meta boxes have been saved.
		var saving    = wp.data.useSelect( function ( select ) {
			var editPost = select( 'core/edit-post' );
			var editor   = select( 'core/editor' );
			return !! ( ( editPost && editPost.isSavingMetaBoxes && editPost.isSavingMetaBoxes() ) || ( editor && editor.isSavingPost && editor.isSavingPost() ) );
		}, [] );
		var version   = useState( 0 );
		var wasSaving = useRef( false );

		// Wait until the author stops changing the settings.
		useEffect( function () {
			var timer = setTimeout( function () {
				state[1]( query );
			}, 500 );
			return function () {
				clearTimeout( timer );
			};
		}, [ query ] );

		useEffect( function () {
			if ( wasSaving.current && ! saving ) {
				version[1]( version[0] + 1 );
			}
			wasSaving.current = saving;
		}, [ saving ] );

		// The page inside the frame reports its height.
		useEffect( function () {
			var view = frame.current ? frame.current.ownerDocument.defaultView : null;
			function onMessage( event ) {
				if ( frame.current && event.source === frame.current.contentWindow && event.data && event.data.geolocationPreviewHeight > 0 ) {
					height[1]( Math.min( 2000, event.data.geolocationPreviewHeight ) );
				}
			}
			if ( ! view ) {
				return;
			}
			view.addEventListener( 'message', onMessage );
			return function () {
				view.removeEventListener( 'message', onMessage );
			};
		}, [] );

		return el( 'iframe', {
			ref: frame,
			title: i18n.previewTitle,
			src: settings.preview + '&block=' + props.block + '&post=' + ( props.postId || 0 ) + '&v=' + version[0] + '&atts=' + encodeURIComponent( state[0] ),
			style: {
				display: 'block',
				width: '100%',
				height: height[0] + 'px',
				border: 0,
				pointerEvents: props.isSelected ? 'auto' : 'none'
			}
		} );
	}

	function Edit( props ) {
		var attributes = props.attributes;
		var set        = props.setAttributes;
		var height     = attributes.height >= 50 ? attributes.height : ( settings.height || 300 );

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

		if ( ! settings.preview ) {
			return el( 'div', blockEditor.useBlockProps(),
				inspector,
				el( components.Placeholder, { icon: 'location-alt', label: i18n.title, instructions: i18n.placeholder } )
			);
		}
		return el( 'div', blockEditor.useBlockProps(),
			inspector,
			el( Preview, { block: 'map', attributes: attributes, height: height, isSelected: props.isSelected } )
		);
	}

	// The block "Post Location": the location of the post, wherever the author places it.
	function EditLocation( props ) {
		var attributes = props.attributes;
		var set        = props.setAttributes;
		var display    = attributes.display || settings.display;
		var postId     = wp.data.useSelect( function ( select ) {
			var editor = select( 'core/editor' );
			return ( props.context && props.context.postId ) || ( editor && editor.getCurrentPostId ? editor.getCurrentPostId() : 0 );
		}, [ props.context && props.context.postId ] );

		var inspector = el( blockEditor.InspectorControls, null,
			el( components.PanelBody, { title: i18n.display },
				el( components.SelectControl, {
					label: i18n.display,
					value: attributes.display,
					options: [
						{ value: '', label: i18n.asSettings },
						{ value: 'plain', label: i18n.plain },
						{ value: 'link', label: i18n.link },
						{ value: 'map', label: i18n.simpleMap }
					],
					onChange: function ( value ) {
						set( { display: value } );
					},
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true
				} )
			),
			display === 'map' ? el( components.PanelBody, { title: i18n.map },
				el( components.TextControl, {
					label: i18n.width,
					help: i18n.heightHelp,
					type: 'number',
					min: 50,
					max: 9999,
					value: attributes.width || '',
					onChange: function ( value ) {
						set( { width: parseInt( value, 10 ) || 0 } );
					},
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true
				} ),
				el( components.TextControl, {
					label: i18n.heightOnly,
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
				} )
			) : null
		);

		if ( ! settings.preview || ! postId ) {
			return el( 'div', blockEditor.useBlockProps(),
				inspector,
				el( components.Placeholder, { icon: 'location', label: i18n.locationTitle, instructions: i18n.locationHelp } )
			);
		}
		return el( 'div', blockEditor.useBlockProps(),
			inspector,
			el( Preview, { block: 'location', postId: postId, attributes: attributes, height: display === 'map' ? 260 : 60, isSelected: props.isSelected } )
		);
	}

	wp.blocks.registerBlockType( 'geolocation/location', {
		title: i18n.locationTitle,
		edit: EditLocation,
		save: function () {
			return null;
		}
	} );

	wp.blocks.registerBlockType( 'geolocation/map', {
		title: i18n.title,
		edit: Edit,
		save: function () {
			return null;
		}
	} );
}( window.wp ) );
