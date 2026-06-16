( function ( blocks, blockEditor, components, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	var RichText = blockEditor.RichText;
	var InspectorControls = blockEditor.InspectorControls;
	var BlockControls = blockEditor.BlockControls;
	var AlignmentToolbar = blockEditor.AlignmentToolbar;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var PanelColorSettings = blockEditor.PanelColorSettings || components.PanelColorSettings;
	var RangeControl = components.RangeControl;
	var SelectControl = components.SelectControl;

	function getBlockStyle( attributes ) {
		return {
			'--jha-icon-callout-bg': attributes.backgroundColor,
			'--jha-icon-callout-text': attributes.textColor,
			'--jha-icon-callout-icon-bg': attributes.iconBackgroundColor,
			'--jha-icon-callout-icon-text': attributes.iconColor,
			'--jha-icon-callout-padding-y': attributes.paddingY + 'px',
			'--jha-icon-callout-padding-x': attributes.paddingX + 'px',
			'--jha-icon-callout-border-radius': attributes.borderRadius + 'px',
			'--jha-icon-callout-border-width': attributes.borderWidth + 'px',
			'--jha-icon-callout-border-style': attributes.borderStyle,
			'--jha-icon-callout-border-color': attributes.borderColor,
			'--jha-icon-callout-text-align': attributes.textAlign,
		};
	}

	function getClassName( attributes ) {
		return 'jha-icon-callout jha-icon-callout--' + attributes.widthMode;
	}

	blocks.registerBlockType( 'jha/icon-callout', {
		title: __( 'Icon Callout', 'journey-homeschool-academy' ),
		description: __( 'A configurable info note with an icon and text.', 'journey-homeschool-academy' ),
		icon: 'info',
		category: 'design',
		attributes: {
			content: {
				type: 'string',
				source: 'html',
				selector: '.jha-icon-callout__content',
				default: __( "You require 70% to pass this lesson's quiz.", 'journey-homeschool-academy' ),
			},
			backgroundColor: {
				type: 'string',
				default: '#eeeeee',
			},
			textColor: {
				type: 'string',
				default: '#2f4867',
			},
			iconBackgroundColor: {
				type: 'string',
				default: '#9cb1ce',
			},
			iconColor: {
				type: 'string',
				default: '#ffffff',
			},
			paddingY: {
				type: 'number',
				default: 12,
			},
			paddingX: {
				type: 'number',
				default: 16,
			},
			borderRadius: {
				type: 'number',
				default: 0,
			},
			borderWidth: {
				type: 'number',
				default: 0,
			},
			borderStyle: {
				type: 'string',
				default: 'none',
			},
			borderColor: {
				type: 'string',
				default: 'transparent',
			},
			widthMode: {
				type: 'string',
				default: 'full',
			},
			textAlign: {
				type: 'string',
				default: 'left',
			},
		},
		supports: {
			html: false,
		},
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps( {
				className: getClassName( attributes ),
				style: getBlockStyle( attributes ),
			} );

			return el(
				'div',
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Layout', 'journey-homeschool-academy' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Width', 'journey-homeschool-academy' ),
							value: attributes.widthMode,
							options: [
								{ label: __( 'Full width', 'journey-homeschool-academy' ), value: 'full' },
								{ label: __( 'Inline', 'journey-homeschool-academy' ), value: 'inline' },
							],
							onChange: function ( widthMode ) {
								setAttributes( { widthMode: widthMode } );
							},
						} ),
						el( RangeControl, {
							label: __( 'Vertical padding', 'journey-homeschool-academy' ),
							value: attributes.paddingY,
							min: 0,
							max: 48,
							onChange: function ( paddingY ) {
								setAttributes( { paddingY: paddingY } );
							},
						} ),
						el( RangeControl, {
							label: __( 'Horizontal padding', 'journey-homeschool-academy' ),
							value: attributes.paddingX,
							min: 0,
							max: 64,
							onChange: function ( paddingX ) {
								setAttributes( { paddingX: paddingX } );
							},
						} ),
						el( RangeControl, {
							label: __( 'Border radius', 'journey-homeschool-academy' ),
							value: attributes.borderRadius,
							min: 0,
							max: 48,
							onChange: function ( borderRadius ) {
								setAttributes( { borderRadius: borderRadius } );
							},
						} )
					),
					el(
						PanelBody,
						{ title: __( 'Border', 'journey-homeschool-academy' ), initialOpen: false },
						el( SelectControl, {
							label: __( 'Border style', 'journey-homeschool-academy' ),
							value: attributes.borderStyle,
							options: [
								{ label: __( 'None', 'journey-homeschool-academy' ), value: 'none' },
								{ label: __( 'Solid', 'journey-homeschool-academy' ), value: 'solid' },
								{ label: __( 'Dashed', 'journey-homeschool-academy' ), value: 'dashed' },
								{ label: __( 'Dotted', 'journey-homeschool-academy' ), value: 'dotted' },
							],
							onChange: function ( borderStyle ) {
								setAttributes( { borderStyle: borderStyle } );
							},
						} ),
						el( RangeControl, {
							label: __( 'Border width', 'journey-homeschool-academy' ),
							value: attributes.borderWidth,
							min: 0,
							max: 12,
							onChange: function ( borderWidth ) {
								setAttributes( { borderWidth: borderWidth } );
							},
						} )
					),
					el( PanelColorSettings, {
						title: __( 'Colors', 'journey-homeschool-academy' ),
						colorSettings: [
							{
								value: attributes.backgroundColor,
								onChange: function ( backgroundColor ) {
									setAttributes( { backgroundColor: backgroundColor || '#eeeeee' } );
								},
								label: __( 'Background', 'journey-homeschool-academy' ),
							},
							{
								value: attributes.textColor,
								onChange: function ( textColor ) {
									setAttributes( { textColor: textColor || '#2f4867' } );
								},
								label: __( 'Text', 'journey-homeschool-academy' ),
							},
							{
								value: attributes.iconBackgroundColor,
								onChange: function ( iconBackgroundColor ) {
									setAttributes( { iconBackgroundColor: iconBackgroundColor || '#9cb1ce' } );
								},
								label: __( 'Icon background', 'journey-homeschool-academy' ),
							},
							{
								value: attributes.iconColor,
								onChange: function ( iconColor ) {
									setAttributes( { iconColor: iconColor || '#ffffff' } );
								},
								label: __( 'Icon', 'journey-homeschool-academy' ),
							},
							{
								value: attributes.borderColor,
								onChange: function ( borderColor ) {
									setAttributes( { borderColor: borderColor || 'transparent' } );
								},
								label: __( 'Border', 'journey-homeschool-academy' ),
							},
						],
					} )
				),
				el(
					BlockControls,
					null,
					el( AlignmentToolbar, {
						value: attributes.textAlign,
						onChange: function ( textAlign ) {
							setAttributes( { textAlign: textAlign || 'left' } );
						},
					} )
				),
				el(
					'div',
					blockProps,
					el( 'span', { className: 'jha-icon-callout__icon', 'aria-hidden': 'true' }, 'i' ),
					el( RichText, {
						tagName: 'div',
						className: 'jha-icon-callout__content',
						value: attributes.content,
						allowedFormats: [ 'core/bold', 'core/italic', 'core/link' ],
						onChange: function ( content ) {
							setAttributes( { content: content } );
						},
						placeholder: __( 'Add callout text...', 'journey-homeschool-academy' ),
					} )
				)
			);
		},
		save: function ( props ) {
			var attributes = props.attributes;
			var blockProps = useBlockProps.save( {
				className: getClassName( attributes ),
				style: getBlockStyle( attributes ),
			} );

			return el(
				'div',
				blockProps,
				el( 'span', { className: 'jha-icon-callout__icon', 'aria-hidden': 'true' }, 'i' ),
				el( RichText.Content, {
					tagName: 'div',
					className: 'jha-icon-callout__content',
					value: attributes.content,
				} )
			);
		},
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
