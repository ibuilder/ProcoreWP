/**
 * Procore Connect block editor integration.
 *
 * Hand-written ES5 against the global `wp.*` runtime, with no build step, so
 * the file that ships is the file that was authored.
 *
 * @package ProcoreConnect
 */

( function ( wp, config ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.element ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var Placeholder = wp.components.Placeholder;
	var ServerSideRender = wp.serverSideRender;

	var variations = ( config && config.variations ) || [];

	/**
	 * Attributes surfaced as controls, in display order.
	 *
	 * Everything else remains reachable through the equivalent shortcode.
	 */
	var CONTROLS = [
		{ key: 'project_id', label: __( 'Project ID', 'procore-connect' ), type: 'number' },
		{ key: 'company_id', label: __( 'Company ID', 'procore-connect' ), type: 'number' },
		{ key: 'endpoint', label: __( 'Endpoint', 'procore-connect' ), type: 'text' },
		{ key: 'field', label: __( 'Field', 'procore-connect' ), type: 'text' },
		{ key: 'label', label: __( 'Label', 'procore-connect' ), type: 'text' },
		{ key: 'title', label: __( 'Heading', 'procore-connect' ), type: 'text' },
		{ key: 'limit', label: __( 'Maximum rows', 'procore-connect' ), type: 'number' },
		{ key: 'status', label: __( 'Filter by status', 'procore-connect' ), type: 'text' },
		{ key: 'orderby', label: __( 'Sort by field', 'procore-connect' ), type: 'text' },
		{ key: 'order', label: __( 'Sort direction', 'procore-connect' ), type: 'select', options: [
			{ label: __( 'Ascending', 'procore-connect' ), value: 'asc' },
			{ label: __( 'Descending', 'procore-connect' ), value: 'desc' },
		] },
		{ key: 'columns', label: __( 'Columns', 'procore-connect' ), type: 'text' },
		{ key: 'width', label: __( 'Image width', 'procore-connect' ), type: 'number' },
		{ key: 'show_email', label: __( 'Show email addresses', 'procore-connect' ), type: 'toggle' },
		{ key: 'all', label: __( 'Fetch every page', 'procore-connect' ), type: 'toggle' },
		{ key: 'class', label: __( 'Extra CSS classes', 'procore-connect' ), type: 'text' },
	];

	/**
	 * Find the variation definition for a shortcode tag.
	 *
	 * @param {string} shortcode Shortcode tag.
	 * @return {Object|null} Variation, or null.
	 */
	function findVariation( shortcode ) {
		for ( var i = 0; i < variations.length; i++ ) {
			if ( variations[ i ].shortcode === shortcode ) {
				return variations[ i ];
			}
		}

		return null;
	}

	/**
	 * Build the inspector controls for the selected shortcode.
	 *
	 * @param {Object}   attributes    Block attributes.
	 * @param {Function} setAttributes Attribute setter.
	 * @return {Array} Control elements.
	 */
	function buildControls( attributes, setAttributes ) {
		var variation = findVariation( attributes.shortcode );
		var supported = variation ? variation.atts : [];
		var atts = attributes.atts || {};

		/**
		 * Persist one attribute.
		 *
		 * @param {string} key   Attribute name.
		 * @param {*}      value New value.
		 */
		function update( key, value ) {
			var next = Object.assign( {}, atts );

			if ( '' === value || false === value || null === value || undefined === value ) {
				delete next[ key ];
			} else {
				next[ key ] = String( value );
			}

			setAttributes( { atts: next } );
		}

		return CONTROLS.filter( function ( control ) {
			return -1 !== supported.indexOf( control.key );
		} ).map( function ( control ) {
			var value = undefined === atts[ control.key ] ? '' : atts[ control.key ];

			if ( 'toggle' === control.type ) {
				return el( ToggleControl, {
					key: control.key,
					label: control.label,
					checked: 'true' === value,
					onChange: function ( next ) {
						update( control.key, next ? 'true' : '' );
					},
					__nextHasNoMarginBottom: true,
				} );
			}

			if ( 'select' === control.type ) {
				return el( SelectControl, {
					key: control.key,
					label: control.label,
					value: value,
					options: [ { label: __( 'Default', 'procore-connect' ), value: '' } ].concat( control.options ),
					onChange: function ( next ) {
						update( control.key, next );
					},
					__nextHasNoMarginBottom: true,
				} );
			}

			return el( TextControl, {
				key: control.key,
				label: control.label,
				type: 'number' === control.type ? 'number' : 'text',
				value: value,
				onChange: function ( next ) {
					update( control.key, next );
				},
				__nextHasNoMarginBottom: true,
			} );
		} );
	}

	wp.blocks.registerBlockType( 'procore-connect/procore', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps ? useBlockProps() : {};
			var variation = findVariation( attributes.shortcode );

			var inspector = el(
				InspectorControls,
				{},
				el(
					PanelBody,
					{ title: __( 'Procore data', 'procore-connect' ), initialOpen: true },
					el( SelectControl, {
						label: __( 'Show', 'procore-connect' ),
						value: attributes.shortcode,
						options: variations.map( function ( item ) {
							return { label: item.title, value: item.shortcode };
						} ),
						onChange: function ( next ) {
							setAttributes( { shortcode: next } );
						},
						help: variation ? variation.description : '',
						__nextHasNoMarginBottom: true,
					} )
				),
				el(
					PanelBody,
					{ title: __( 'Options', 'procore-connect' ), initialOpen: true },
					buildControls( attributes, setAttributes )
				)
			);

			var preview = ServerSideRender
				? el( ServerSideRender, {
						block: 'procore-connect/procore',
						attributes: attributes,
						EmptyResponsePlaceholder: function () {
							return el(
								Placeholder,
								{ icon: 'building', label: __( 'Procore', 'procore-connect' ) },
								__( 'Nothing to display yet. Check the project and company IDs in the block settings.', 'procore-connect' )
							);
						},
				  } )
				: el(
						Placeholder,
						{ icon: 'building', label: __( 'Procore', 'procore-connect' ) },
						variation ? variation.title : attributes.shortcode
				  );

			return el( Fragment, {}, inspector, el( 'div', blockProps, preview ) );
		},

		save: function () {
			// Rendered server-side so cached data and escaping stay on the server.
			return null;
		},

		variations: variations.map( function ( item ) {
			return {
				name: item.name,
				title: item.title,
				description: item.description,
				icon: 'building',
				attributes: { shortcode: item.shortcode },
				scope: [ 'inserter' ],
				isActive: function ( blockAttributes ) {
					return blockAttributes.shortcode === item.shortcode;
				},
			};
		} ),
	} );
} )( window.wp, window.procoreConnectBlocks );
