( function ( blocks, hooks, element, blockEditor, components, i18n ) {
	var el = element.createElement;
	var Fragment = element.Fragment;
	var __ = i18n.__;
	var addFilter = hooks.addFilter;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var ToggleControl = components.ToggleControl;
	var RangeControl = components.RangeControl;
	var Button = components.Button;
	var BaseControl = components.BaseControl;
	var useEffect = element.useEffect;
	var cloneElement = element.cloneElement;
	var Children = element.Children;

	var ICON_OPTIONS = ( window.jhaButtonIcons && window.jhaButtonIcons.options ) || [
		{ label: __( 'None', 'journey-homeschool-academy' ), value: '' },
		{ label: __( 'Double chevron right', 'journey-homeschool-academy' ), value: 'chevron-circle-right' },
	];

	function IconPicker( props ) {
		var label = props.label;
		var value = props.value || '';
		var options = props.options || [];
		var onChange = props.onChange;

		return el(
			BaseControl,
			{ label: label, className: 'jha-button-icon-picker-control' },
			el(
				'div',
				{ className: 'jha-button-icon-picker', role: 'listbox', 'aria-label': label },
				options.map( function ( option ) {
					var isSelected = value === option.value;

					return el(
						Button,
						{
							key: option.value || 'none',
							className: 'jha-button-icon-picker__option',
							role: 'option',
							'aria-label': option.label,
							'aria-selected': isSelected,
							isPressed: isSelected,
							onClick: function () {
								onChange( option.value );
							},
						},
						option.value
							? el( 'span', {
									className: 'jha-button-icon-picker__icon',
									style: option.mask
										? {
												WebkitMaskImage: 'url("' + option.mask + '")',
												maskImage: 'url("' + option.mask + '")',
										  }
										: null,
									'aria-hidden': true,
							  } )
							: el(
									'span',
									{ className: 'jha-button-icon-picker__none', 'aria-hidden': true },
									'—'
							  )
					);
				} )
			)
		);
	}

	function hasSelectedIcon( attributes ) {
		return !! ( attributes.jhaButtonIcon && String( attributes.jhaButtonIcon ).trim() );
	}

	function getWrapperProps( attributes ) {
		if ( ! hasSelectedIcon( attributes ) ) {
			return {};
		}

		var position = attributes.jhaButtonIconPosition || 'before';
		var visibility = attributes.jhaButtonIconVisibility || 'always';
		var className = [
			'jha-button-has-icon',
			'jha-button-icon-' + position,
		];

		if ( 'hover' === visibility ) {
			className.push( 'jha-button-icon-hover' );
		}

		if ( attributes.jhaButtonIconInCircle ) {
			className.push( 'jha-button-icon-in-circle' );
		}

		var props = {
			className: className.join( ' ' ),
			'data-jha-icon': attributes.jhaButtonIcon,
			'data-jha-icon-position': position,
			'data-jha-icon-visibility': visibility,
		};

		if ( attributes.jhaButtonIconInCircle ) {
			props['data-jha-icon-in-circle'] = 'true';
		}

		return props;
	}

	function getIconSize( attributes ) {
		var size = parseFloat( attributes.jhaButtonIconSize );

		if ( isNaN( size ) ) {
			return 1.05;
		}

		return Math.max( 0.5, Math.min( 2.5, size ) );
	}

	function getCirclePadding( attributes ) {
		var padding = parseFloat( attributes.jhaButtonIconCirclePadding );

		if ( isNaN( padding ) ) {
			return 0.35;
		}

		return Math.max( 0, Math.min( 1, padding ) );
	}

	var PADDING_SIDES = [
		{ key: 'jhaButtonPaddingTop', label: __( 'Top', 'journey-homeschool-academy' ) },
		{ key: 'jhaButtonPaddingRight', label: __( 'Right', 'journey-homeschool-academy' ) },
		{ key: 'jhaButtonPaddingBottom', label: __( 'Bottom', 'journey-homeschool-academy' ) },
		{ key: 'jhaButtonPaddingLeft', label: __( 'Left', 'journey-homeschool-academy' ) },
	];

	function getPaddingSide( value ) {
		if ( null === value || undefined === value || '' === value ) {
			return null;
		}

		var padding = parseFloat( value );

		if ( isNaN( padding ) ) {
			return null;
		}

		return Math.max( 0, Math.min( 5, padding ) );
	}

	function hasCustomPadding( attributes ) {
		return PADDING_SIDES.some( function ( side ) {
			return null !== getPaddingSide( attributes[ side.key ] );
		} );
	}

	function getButtonPaddingStyleObject( attributes ) {
		var style = {};

		PADDING_SIDES.forEach( function ( side ) {
			var value = getPaddingSide( attributes[ side.key ] );

			if ( null !== value ) {
				style[ side.key.replace( 'jhaButtonPadding', 'padding' ) ] = value + 'em';
			}
		} );

		return style;
	}

	function applyPaddingToLink( linkElement, attributes ) {
		if ( ! linkElement || ! linkElement.props ) {
			return linkElement;
		}

		var paddingStyle = getButtonPaddingStyleObject( attributes );
		var nextStyle = Object.assign( {}, linkElement.props.style || {} );

		delete nextStyle.paddingTop;
		delete nextStyle.paddingRight;
		delete nextStyle.paddingBottom;
		delete nextStyle.paddingLeft;

		Object.keys( paddingStyle ).forEach( function ( property ) {
			nextStyle[ property ] = paddingStyle[ property ];
		} );

		var linkClassName = [ linkElement.props.className, 'jha-button-has-custom-padding' ].filter( Boolean ).join( ' ' );

		if ( ! hasCustomPadding( attributes ) ) {
			linkClassName = String( linkElement.props.className || '' )
				.split( ' ' )
				.filter( function ( className ) {
					return 'jha-button-has-custom-padding' !== className;
				} )
				.join( ' ' );
		}

		return cloneElement( linkElement, {
			className: linkClassName || undefined,
			style: Object.keys( nextStyle ).length ? nextStyle : undefined,
		} );
	}

	function syncEditorButtonPadding( link, attributes ) {
		if ( ! link ) {
			return;
		}

		var paddingStyle = getButtonPaddingStyleObject( attributes );

		link.style.paddingTop = paddingStyle.paddingTop || '';
		link.style.paddingRight = paddingStyle.paddingRight || '';
		link.style.paddingBottom = paddingStyle.paddingBottom || '';
		link.style.paddingLeft = paddingStyle.paddingLeft || '';
		link.classList.toggle( 'jha-button-has-custom-padding', hasCustomPadding( attributes ) );
	}

	function getIconWrapStyle( attributes ) {
		var style = '--jha-button-icon-size: ' + getIconSize( attributes ) + 'em';

		if ( attributes.jhaButtonIconInCircle ) {
			style += '; --jha-button-icon-circle-padding: ' + getCirclePadding( attributes ) + 'em';
		}

		return style;
	}

	function buildIconElement( attributes ) {
		if ( ! hasSelectedIcon( attributes ) ) {
			return null;
		}

		var wrapClass = 'jha-button-icon-wrap';

		if ( attributes.jhaButtonIconInCircle ) {
			wrapClass += ' jha-button-icon-wrap--circle';
		}

		return el(
			'span',
			{
				className: wrapClass,
				style: getIconWrapStyle( attributes ),
			},
			el( 'span', {
				className: 'jha-button-icon-glyph jha-button-icon-glyph--' + attributes.jhaButtonIcon,
				'aria-hidden': true,
			} )
		);
	}

	function injectIconIntoLink( linkElement, attributes ) {
		if ( ! linkElement || ! linkElement.props || ! hasSelectedIcon( attributes ) ) {
			return linkElement;
		}

		var iconElement = buildIconElement( attributes );
		var children = Children.toArray( linkElement.props.children ).filter( function ( child ) {
			return ! child || ! child.props || ! child.props.className || -1 === String( child.props.className ).indexOf( 'jha-button-icon-wrap' );
		} );

		if ( 'after' === ( attributes.jhaButtonIconPosition || 'before' ) ) {
			children.push( iconElement );
		} else {
			children.unshift( iconElement );
		}

		return cloneElement( linkElement, linkElement.props, children );
	}

	function syncEditorButtonCustomization( clientId, attributes ) {
		var blockNode = document.getElementById( 'block-' + clientId );

		if ( ! blockNode ) {
			return;
		}

		var link = blockNode.querySelector( '.wp-block-button__link' );

		if ( ! link ) {
			return;
		}

		syncEditorButtonPadding( link, attributes );

		var existing = link.querySelector( '.jha-button-icon-wrap' );

		if ( ! hasSelectedIcon( attributes ) ) {
			if ( existing ) {
				existing.remove();
			}

			return;
		}
		var wrapClass = 'jha-button-icon-wrap' + ( attributes.jhaButtonIconInCircle ? ' jha-button-icon-wrap--circle' : '' );
		var glyphClass = 'jha-button-icon-glyph jha-button-icon-glyph--' + attributes.jhaButtonIcon;
		var position = attributes.jhaButtonIconPosition || 'before';

		if ( existing ) {
			existing.className = wrapClass;
			existing.setAttribute( 'style', getIconWrapStyle( attributes ) );
			var glyph = existing.querySelector( '.jha-button-icon-glyph' );

			if ( glyph ) {
				glyph.className = glyphClass;
			}

			return;
		}

		var wrap = document.createElement( 'span' );
		wrap.className = wrapClass;
		wrap.setAttribute( 'style', getIconWrapStyle( attributes ) );
		var glyphNode = document.createElement( 'span' );
		glyphNode.className = glyphClass;
		glyphNode.setAttribute( 'aria-hidden', 'true' );
		wrap.appendChild( glyphNode );

		if ( 'after' === position ) {
			link.appendChild( wrap );
		} else {
			link.insertBefore( wrap, link.firstChild );
		}
	}

	addFilter( 'blocks.registerBlockType', 'jha/button-icon-attributes', function ( settings, name ) {
		if ( 'core/button' !== name ) {
			return settings;
		}

		return Object.assign( {}, settings, {
			attributes: Object.assign( {}, settings.attributes || {}, {
				jhaButtonIcon: {
					type: 'string',
					default: '',
				},
				jhaButtonIconPosition: {
					type: 'string',
					default: 'before',
				},
				jhaButtonIconVisibility: {
					type: 'string',
					default: 'always',
				},
				jhaButtonIconInCircle: {
					type: 'boolean',
					default: false,
				},
				jhaButtonIconSize: {
					type: 'number',
					default: 1.05,
				},
				jhaButtonIconCirclePadding: {
					type: 'number',
					default: 0.35,
				},
				jhaButtonPaddingTop: {
					type: 'number',
				},
				jhaButtonPaddingRight: {
					type: 'number',
				},
				jhaButtonPaddingBottom: {
					type: 'number',
				},
				jhaButtonPaddingLeft: {
					type: 'number',
				},
			} ),
		} );
	} );

	function renderIconSettingsControls( attributes, setAttributes ) {
		if ( ! hasSelectedIcon( attributes ) ) {
			return el(
				'p',
				{ className: 'jha-button-icon-settings-help' },
				__( 'Choose an icon above to configure position, size, and other options.', 'journey-homeschool-academy' )
			);
		}

		var controls = [
			el( SelectControl, {
				key: 'position',
				label: __( 'Icon position', 'journey-homeschool-academy' ),
				value: attributes.jhaButtonIconPosition || 'before',
				options: [
					{
						label: __( 'Before text', 'journey-homeschool-academy' ),
						value: 'before',
					},
					{
						label: __( 'After text', 'journey-homeschool-academy' ),
						value: 'after',
					},
				],
				onChange: function ( value ) {
					setAttributes( { jhaButtonIconPosition: value } );
				},
			} ),
			el( SelectControl, {
				key: 'visibility',
				label: __( 'Icon visibility', 'journey-homeschool-academy' ),
				value: attributes.jhaButtonIconVisibility || 'always',
				options: [
					{
						label: __( 'Always visible', 'journey-homeschool-academy' ),
						value: 'always',
					},
					{
						label: __( 'Only on hover', 'journey-homeschool-academy' ),
						value: 'hover',
					},
				],
				onChange: function ( value ) {
					setAttributes( { jhaButtonIconVisibility: value } );
				},
			} ),
			el( ToggleControl, {
				key: 'circle',
				label: __( 'Icon in circle', 'journey-homeschool-academy' ),
				checked: !! attributes.jhaButtonIconInCircle,
				onChange: function ( value ) {
					setAttributes( { jhaButtonIconInCircle: value } );
				},
			} ),
		];

		if ( RangeControl ) {
			controls.push(
				el( RangeControl, {
					key: 'size',
					label: __( 'Icon size', 'journey-homeschool-academy' ),
					value: getIconSize( attributes ),
					onChange: function ( value ) {
						setAttributes( { jhaButtonIconSize: value } );
					},
					min: 0.5,
					max: 2.5,
					step: 0.05,
					allowReset: true,
					resetFallbackValue: 1.05,
				} )
			);

			if ( attributes.jhaButtonIconInCircle ) {
				controls.push(
					el( RangeControl, {
						key: 'padding',
						label: __( 'Circle padding', 'journey-homeschool-academy' ),
						help: __( 'Space between the icon and the circle border.', 'journey-homeschool-academy' ),
						value: getCirclePadding( attributes ),
						onChange: function ( value ) {
							setAttributes( { jhaButtonIconCirclePadding: value } );
						},
						min: 0,
						max: 1,
						step: 0.05,
						allowReset: true,
						resetFallbackValue: 0.35,
					} )
				);
			}
		}

		return el( Fragment, null, controls );
	}

	function renderButtonPaddingControls( attributes, setAttributes ) {
		if ( ! RangeControl ) {
			return null;
		}

		return el(
			Fragment,
			null,
			PADDING_SIDES.map( function ( side ) {
				var value = getPaddingSide( attributes[ side.key ] );

				return el( RangeControl, {
					key: side.key,
					label: side.label,
					value: null === value ? undefined : value,
					onChange: function ( nextValue ) {
						var update = {};

						if ( null === nextValue || undefined === nextValue ) {
							update[ side.key ] = undefined;
						} else {
							update[ side.key ] = nextValue;
						}

						setAttributes( update );
					},
					min: 0,
					max: 5,
					step: 0.05,
					allowReset: true,
				} );
			} )
		);
	}

	function ButtonIconBlockEdit( props ) {
		var BlockEdit = props.blockEdit;
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;
		var selectedIcon = hasSelectedIcon( attributes );

		useEffect(
			function () {
				syncEditorButtonCustomization( props.clientId, attributes );
			},
			[
				props.clientId,
				attributes.jhaButtonIcon,
				attributes.jhaButtonIconInCircle,
				attributes.jhaButtonIconPosition,
				attributes.jhaButtonIconVisibility,
				attributes.jhaButtonIconSize,
				attributes.jhaButtonIconCirclePadding,
				attributes.jhaButtonPaddingTop,
				attributes.jhaButtonPaddingRight,
				attributes.jhaButtonPaddingBottom,
				attributes.jhaButtonPaddingLeft,
			]
		);

		return el(
			Fragment,
			null,
			el( BlockEdit, props ),
			el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{
						title: __( 'Button Icon', 'journey-homeschool-academy' ),
						initialOpen: true,
					},
					el( IconPicker, {
						label: __( 'Icon', 'journey-homeschool-academy' ),
						value: attributes.jhaButtonIcon || '',
						options: ICON_OPTIONS,
						onChange: function ( value ) {
							if ( ! value ) {
								setAttributes( { jhaButtonIcon: '' } );
								return;
							}

							setAttributes( {
								jhaButtonIcon: value,
								jhaButtonIconPosition: attributes.jhaButtonIconPosition || 'before',
								jhaButtonIconVisibility: attributes.jhaButtonIconVisibility || 'always',
								jhaButtonIconInCircle: !! attributes.jhaButtonIconInCircle,
								jhaButtonIconSize: getIconSize( attributes ),
								jhaButtonIconCirclePadding: getCirclePadding( attributes ),
							} );
						},
					} )
				),
				el(
					PanelBody,
					{
						title: __( 'Button Icon Settings', 'journey-homeschool-academy' ),
						initialOpen: selectedIcon,
					},
					renderIconSettingsControls( attributes, setAttributes )
				),
				el(
					PanelBody,
					{
						title: __( 'Button Padding', 'journey-homeschool-academy' ),
						initialOpen: hasCustomPadding( attributes ),
					},
					el(
						'p',
						{ className: 'jha-button-icon-settings-help' },
						__( 'Adjust padding on each side of the button. Reset a slider to use the theme default for that side.', 'journey-homeschool-academy' )
					),
					renderButtonPaddingControls( attributes, setAttributes )
				)
			)
		);
	}

	addFilter( 'editor.BlockEdit', 'jha/button-icon-controls', function ( BlockEdit ) {
		return function ( props ) {
			if ( 'core/button' !== props.name ) {
				return el( BlockEdit, props );
			}

			return el( ButtonIconBlockEdit, Object.assign( {}, props, { blockEdit: BlockEdit } ) );
		};
	} );

	addFilter( 'blocks.getSaveElement', 'jha/button-icon-save-element', function ( element, blockType, attributes ) {
		if ( 'core/button' !== blockType.name || ! element || ! element.props ) {
			return element;
		}

		var linkElement = element.props.children;

		if ( ! linkElement ) {
			return element;
		}

		var updatedLink = applyPaddingToLink(
			hasSelectedIcon( attributes ) ? injectIconIntoLink( linkElement, attributes ) : linkElement,
			attributes
		);

		return cloneElement(
			element,
			element.props,
			updatedLink
		);
	} );

	addFilter( 'blocks.getSaveContent.extraProps', 'jha/button-icon-save-props', function ( extraProps, blockType, attributes ) {
		if ( 'core/button' !== blockType.name ) {
			return extraProps;
		}

		var iconProps = getWrapperProps( attributes );

		if ( ! iconProps.className ) {
			return extraProps;
		}

		return Object.assign( {}, extraProps, iconProps, {
			className: [ extraProps.className, iconProps.className ].filter( Boolean ).join( ' ' ),
		} );
	} );

	addFilter( 'editor.BlockListBlock', 'jha/button-icon-editor-classes', function ( BlockListBlock ) {
		return function ( props ) {
			if ( 'core/button' !== props.name ) {
				return el( BlockListBlock, props );
			}

			var iconProps = getWrapperProps( props.attributes );

			if ( ! iconProps.className ) {
				return el( BlockListBlock, props );
			}

			return el( BlockListBlock, Object.assign( {}, props, {
				wrapperProps: Object.assign( {}, props.wrapperProps || {}, iconProps, {
					className: [ props.wrapperProps && props.wrapperProps.className, iconProps.className ].filter( Boolean ).join( ' ' ),
				} ),
			} ) );
		};
	} );
}(
	window.wp.blocks,
	window.wp.hooks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n
) );
