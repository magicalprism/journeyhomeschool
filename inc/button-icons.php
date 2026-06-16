<?php
/**
 * SVG icon definitions for the core Button block extension.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get allowed button icon definitions.
 *
 * @return array<string, array{label: string, svg: string}>
 */
function jha_get_button_icon_definitions() {
	return array(
		'chevron-circle-right' => array(
			'label' => __( 'Double chevron right', 'journey-homeschool-academy' ),
			'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" aria-hidden="true"><path fill="currentColor" d="M439.1 278.6c12.5-12.5 12.5-32.8 0-45.3l-160-160c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L371.2 256 233.9 393.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0l160-160zm-352 160l160-160c12.5-12.5 12.5-32.8 0-45.3l-160-160c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L179.2 256 41.9 393.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0z"/></svg>',
		),
		'chevron-double-left'  => array(
			'label' => __( 'Double chevron left', 'journey-homeschool-academy' ),
			'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" aria-hidden="true"><path fill="currentColor" d="M9.4 233.4c-12.5 12.5-12.5 32.8 0 45.3l160 160c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L77.3 256l137.3-137.4c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0l-160 160zm352-160l-160 160c-12.5 12.5-12.5 32.8 0 45.3l160 160c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L269.3 256l137.3-137.4c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0z"/></svg>',
		),
		'arrow-line-right'     => array(
			'label' => __( 'Arrow right', 'journey-homeschool-academy' ),
			'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M42 24H6m24-12l12 12l-12 12"/></svg>',
		),
		'arrow-line-left'      => array(
			'label' => __( 'Arrow left', 'journey-homeschool-academy' ),
			'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4m0 0l6-6m-6 6l6 6"/></svg>',
		),
		'arrow-right-alt'      => array(
			'label' => __( 'Arrow right', 'journey-homeschool-academy' ),
			'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 7l5 5-5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
		),
		'arrow-left-alt'       => array(
			'label' => __( 'Arrow left', 'journey-homeschool-academy' ),
			'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 7l-5 5 5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
		),
		'download'             => array(
			'label' => __( 'Download', 'journey-homeschool-academy' ),
			'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v10m0 0 3.5-3.5M12 14 8.5 10.5M5 18h14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
		),
		'external'             => array(
			'label' => __( 'External link', 'journey-homeschool-academy' ),
			'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 5h5v5M10 14 19 5M19 14v5H5V5h5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
		),
		'controls-play'        => array(
			'label' => __( 'Play', 'journey-homeschool-academy' ),
			'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 7.5v9l8-4.5-8-4.5Z" fill="currentColor"/></svg>',
		),
		'yes'                  => array(
			'label' => __( 'Check', 'journey-homeschool-academy' ),
			'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 12.5 10 16.5 18 8.5" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"/></svg>',
		),
		'info'                 => array(
			'label' => __( 'Info', 'journey-homeschool-academy' ),
			'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.75"/><path d="M12 10v6M12 7h.01" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
		),
		'email'                => array(
			'label' => __( 'Email', 'journey-homeschool-academy' ),
			'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="6" width="16" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="1.75"/><path d="m4 8 8 5 8-5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>',
		),
		'calendar-alt'         => array(
			'label' => __( 'Calendar', 'journey-homeschool-academy' ),
			'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2" fill="none" stroke="currentColor" stroke-width="1.75"/><path d="M8 3v4M16 3v4M4 10h16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>',
		),
		'lock'                 => array(
			'label' => __( 'Lock', 'journey-homeschool-academy' ),
			'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="10" width="12" height="10" rx="2" fill="none" stroke="currentColor" stroke-width="1.75"/><path d="M9 10V8a3 3 0 1 1 6 0v2" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>',
		),
		'unlock'               => array(
			'label' => __( 'Unlock', 'journey-homeschool-academy' ),
			'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="10" width="12" height="10" rx="2" fill="none" stroke="currentColor" stroke-width="1.75"/><path d="M9 10V8a3 3 0 0 1 5.5-1.5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>',
		),
	);
}

/**
 * Sanitize a button icon key.
 *
 * @param string $icon Raw icon key.
 * @return string
 */
function jha_sanitize_button_icon_key( $icon ) {
	$icon = sanitize_key( (string) $icon );
	$defs = jha_get_button_icon_definitions();

	return isset( $defs[ $icon ] ) ? $icon : '';
}

/**
 * Encode SVG markup for use in a CSS mask image.
 *
 * @param string $svg SVG markup.
 * @return string
 */
function jha_get_button_icon_mask_data_uri( $svg ) {
	return 'data:image/svg+xml,' . rawurlencode( $svg );
}

/**
 * Build inline CSS for button icon masks.
 *
 * @return string
 */
function jha_get_button_icon_mask_css() {
	$css = '';

	foreach ( jha_get_button_icon_definitions() as $key => $definition ) {
		$mask = jha_get_button_icon_mask_data_uri( $definition['svg'] );

		$css .= sprintf(
			'.jha-button-icon-glyph--%1$s{-webkit-mask-image:url("%2$s");mask-image:url("%2$s");}',
			esc_attr( $key ),
			esc_attr( $mask )
		);
	}

	return $css;
}

/**
 * Sanitize button icon size in em units.
 *
 * @param mixed $size Raw size value.
 * @return string
 */
function jha_sanitize_button_icon_size( $size ) {
	$size = is_numeric( $size ) ? (float) $size : 1.05;

	return (string) round( max( 0.5, min( 2.5, $size ) ), 2 );
}

/**
 * Sanitize per-side button link padding in em units.
 *
 * @param mixed $value Raw padding value.
 * @return string|null
 */
function jha_sanitize_button_padding_side( $value ) {
	if ( null === $value || '' === $value ) {
		return null;
	}

	if ( ! is_numeric( $value ) ) {
		return null;
	}

	return (string) round( max( 0, min( 5, (float) $value ) ), 2 );
}

/**
 * Build inline padding styles for the button link.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function jha_get_button_link_padding_style( $attributes ) {
	$sides = array(
		'top'    => 'jhaButtonPaddingTop',
		'right'  => 'jhaButtonPaddingRight',
		'bottom' => 'jhaButtonPaddingBottom',
		'left'   => 'jhaButtonPaddingLeft',
	);

	$styles = array();

	foreach ( $sides as $side => $attribute_key ) {
		$value = jha_sanitize_button_padding_side( $attributes[ $attribute_key ] ?? null );

		if ( null !== $value ) {
			$styles[] = 'padding-' . $side . ': ' . $value . 'em';
		}
	}

	return implode( '; ', $styles );
}

/**
 * Merge two inline style strings.
 *
 * @param string $existing Existing inline style.
 * @param string $addition Style declarations to append.
 * @return string
 */
function jha_merge_inline_style( $existing, $addition ) {
	$existing = trim( (string) $existing );
	$addition = trim( (string) $addition );

	if ( '' === $addition ) {
		return $existing;
	}

	if ( '' === $existing ) {
		return $addition;
	}

	return rtrim( $existing, ';' ) . '; ' . $addition;
}

/**
 * Apply custom button link padding to rendered button HTML.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $attributes Block attributes.
 * @return string
 */
function jha_apply_button_padding_to_markup( $block_content, $attributes ) {
	$padding_style = jha_get_button_link_padding_style( $attributes );

	if ( '' === $padding_style || ! class_exists( 'WP_HTML_Tag_Processor', false ) ) {
		return $block_content;
	}

	$processor = new WP_HTML_Tag_Processor( $block_content );

	if ( ! $processor->next_tag( array( 'class_name' => 'wp-block-button__link' ) ) ) {
		return $block_content;
	}

	$existing_style = $processor->get_attribute( 'style' );
	$processor->set_attribute( 'style', jha_merge_inline_style( $existing_style, $padding_style ) );
	$processor->add_class( 'jha-button-has-custom-padding' );

	$updated = $processor->get_updated_html();

	return is_string( $updated ) ? $updated : $block_content;
}

/**
 * Sanitize padding between icon glyph and circle border in em units.
 *
 * @param mixed $padding Raw padding value.
 * @return string
 */
function jha_sanitize_button_icon_circle_padding( $padding ) {
	$padding = is_numeric( $padding ) ? (float) $padding : 0.35;

	return (string) round( max( 0, min( 1, $padding ) ), 2 );
}

/**
 * Register button icon attributes on the server so the editor can save them.
 *
 * @param array  $args Block type registration arguments.
 * @param string $block_type Block type name.
 * @return array
 */
function jha_register_button_icon_block_attributes( $args, $block_type ) {
	if ( 'core/button' !== $block_type ) {
		return $args;
	}

	if ( ! isset( $args['attributes'] ) || ! is_array( $args['attributes'] ) ) {
		$args['attributes'] = array();
	}

	$args['attributes']['jhaButtonIcon'] = array(
		'type'    => 'string',
		'default' => '',
	);
	$args['attributes']['jhaButtonIconPosition'] = array(
		'type'    => 'string',
		'default' => 'before',
	);
	$args['attributes']['jhaButtonIconVisibility'] = array(
		'type'    => 'string',
		'default' => 'always',
	);
	$args['attributes']['jhaButtonIconInCircle'] = array(
		'type'    => 'boolean',
		'default' => false,
	);
	$args['attributes']['jhaButtonIconSize'] = array(
		'type'    => 'number',
		'default' => 1.05,
	);
	$args['attributes']['jhaButtonIconCirclePadding'] = array(
		'type'    => 'number',
		'default' => 0.35,
	);
	$args['attributes']['jhaButtonPaddingTop'] = array(
		'type' => 'number',
	);
	$args['attributes']['jhaButtonPaddingRight'] = array(
		'type' => 'number',
	);
	$args['attributes']['jhaButtonPaddingBottom'] = array(
		'type' => 'number',
	);
	$args['attributes']['jhaButtonPaddingLeft'] = array(
		'type' => 'number',
	);

	return $args;
}
add_filter( 'register_block_type_args', 'jha_register_button_icon_block_attributes', 10, 2 );

/**
 * Normalize button icon block settings from block attributes.
 *
 * @param array $attributes Block attributes.
 * @return array{icon: string, position: string, visibility: string, in_circle: bool, icon_size: string, circle_padding: string}
 */
function jha_get_button_icon_block_settings( $attributes ) {
	$icon = isset( $attributes['jhaButtonIcon'] ) ? jha_sanitize_button_icon_key( (string) $attributes['jhaButtonIcon'] ) : '';
	$position = isset( $attributes['jhaButtonIconPosition'] ) ? sanitize_key( (string) $attributes['jhaButtonIconPosition'] ) : 'before';
	$visibility = isset( $attributes['jhaButtonIconVisibility'] ) ? sanitize_key( (string) $attributes['jhaButtonIconVisibility'] ) : 'always';

	if ( ! in_array( $position, array( 'before', 'after' ), true ) ) {
		$position = 'before';
	}

	if ( ! in_array( $visibility, array( 'always', 'hover' ), true ) ) {
		$visibility = 'always';
	}

	return array(
		'icon'            => $icon,
		'position'        => $position,
		'visibility'      => $visibility,
		'in_circle'       => ! empty( $attributes['jhaButtonIconInCircle'] ),
		'icon_size'       => jha_sanitize_button_icon_size( $attributes['jhaButtonIconSize'] ?? 1.05 ),
		'circle_padding'  => jha_sanitize_button_icon_circle_padding( $attributes['jhaButtonIconCirclePadding'] ?? 0.35 ),
	);
}

/**
 * Build inline CSS custom properties for an icon wrapper.
 *
 * @param array $settings Normalized icon settings.
 * @return string
 */
function jha_get_button_icon_wrap_style( $settings ) {
	$styles = array(
		'--jha-button-icon-size: ' . $settings['icon_size'] . 'em',
	);

	if ( ! empty( $settings['in_circle'] ) ) {
		$styles[] = '--jha-button-icon-circle-padding: ' . $settings['circle_padding'] . 'em';
	}

	return implode( '; ', $styles );
}

/**
 * Build button icon markup for the frontend and editor.
 *
 * @param array $settings Normalized icon settings.
 * @return string
 */
function jha_build_button_icon_markup( $settings ) {
	if ( empty( $settings['icon'] ) ) {
		return '';
	}

	$glyph = sprintf(
		'<span class="jha-button-icon-glyph jha-button-icon-glyph--%1$s" aria-hidden="true"></span>',
		esc_attr( $settings['icon'] )
	);

	$wrap_class = 'jha-button-icon-wrap';

	if ( ! empty( $settings['in_circle'] ) ) {
		$wrap_class .= ' jha-button-icon-wrap--circle';
	}

	return sprintf(
		'<span class="%1$s" style="%2$s">%3$s</span>',
		esc_attr( $wrap_class ),
		esc_attr( jha_get_button_icon_wrap_style( $settings ) ),
		$glyph
	);
}

/**
 * Update an existing icon wrapper with the latest settings.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $settings Normalized icon settings.
 * @return string
 */
function jha_update_button_icon_wrap_markup( $block_content, $settings ) {
	if ( ! class_exists( 'WP_HTML_Tag_Processor', false ) ) {
		return $block_content;
	}

	$processor = new WP_HTML_Tag_Processor( $block_content );

	if ( ! $processor->next_tag( array( 'class_name' => 'jha-button-icon-wrap' ) ) ) {
		return $block_content;
	}

	$wrap_class = 'jha-button-icon-wrap';

	if ( ! empty( $settings['in_circle'] ) ) {
		$wrap_class .= ' jha-button-icon-wrap--circle';
	}

	$processor->set_attribute( 'class', $wrap_class );
	$processor->set_attribute( 'style', jha_get_button_icon_wrap_style( $settings ) );

	$updated = $processor->get_updated_html();

	if ( ! empty( $settings['icon'] ) ) {
		$updated = preg_replace(
			'/class="jha-button-icon-glyph jha-button-icon-glyph--[^"]*"/',
			'class="jha-button-icon-glyph jha-button-icon-glyph--' . esc_attr( $settings['icon'] ) . '"',
			$updated,
			1
		);
	}

	return is_string( $updated ) ? $updated : $block_content;
}

/**
 * Inject button icon markup into the button link HTML.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $settings Normalized icon settings.
 * @return string
 */
function jha_inject_button_icon_into_link( $block_content, $settings ) {
	if ( empty( $settings['icon'] ) ) {
		return $block_content;
	}

	if ( false !== strpos( $block_content, 'jha-button-icon-wrap' ) ) {
		return jha_update_button_icon_wrap_markup( $block_content, $settings );
	}

	$markup = jha_build_button_icon_markup( $settings );

	if ( '' === $markup ) {
		return $block_content;
	}

	$pattern = '/(<(?:a|button)\b[^>]*\bwp-block-button__link\b[^>]*>)(.*?)(<\/(?:a|button)>)/is';

	if ( ! preg_match( $pattern, $block_content, $matches ) ) {
		return $block_content;
	}

	$replacement = 'before' === $settings['position']
		? $matches[1] . $markup . $matches[2] . $matches[3]
		: $matches[1] . $matches[2] . $markup . $matches[3];

	$updated = preg_replace( $pattern, $replacement, $block_content, 1 );

	return is_string( $updated ) ? $updated : $block_content;
}

/**
 * Apply button icon data attributes to rendered button block HTML.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $attributes Block attributes.
 * @return string
 */
function jha_apply_button_icon_attributes_to_markup( $block_content, $attributes ) {
	$settings = jha_get_button_icon_block_settings( $attributes );

	if ( '' === $settings['icon'] || ! class_exists( 'WP_HTML_Tag_Processor', false ) ) {
		return $block_content;
	}

	$processor = new WP_HTML_Tag_Processor( $block_content );

	if ( ! $processor->next_tag( array( 'class_name' => 'wp-block-button' ) ) ) {
		return $block_content;
	}

	$processor->add_class( 'jha-button-has-icon' );
	$processor->add_class( 'jha-button-icon-' . $settings['position'] );

	if ( 'hover' === $settings['visibility'] ) {
		$processor->add_class( 'jha-button-icon-hover' );
	}

	if ( $settings['in_circle'] ) {
		$processor->add_class( 'jha-button-icon-in-circle' );
		$processor->set_attribute( 'data-jha-icon-in-circle', 'true' );
	}

	$processor->set_attribute( 'data-jha-icon', $settings['icon'] );
	$processor->set_attribute( 'data-jha-icon-position', $settings['position'] );
	$processor->set_attribute( 'data-jha-icon-visibility', $settings['visibility'] );

	$block_content = $processor->get_updated_html();

	return jha_inject_button_icon_into_link( $block_content, $settings );
}

/**
 * Apply all custom button block settings to rendered HTML.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $attributes Block attributes.
 * @return string
 */
function jha_apply_button_block_customizations( $block_content, $attributes ) {
	$block_content = jha_apply_button_padding_to_markup( $block_content, $attributes );

	return jha_apply_button_icon_attributes_to_markup( $block_content, $attributes );
}
