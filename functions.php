<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once get_stylesheet_directory() . '/inc/course-menu.php';
require_once get_stylesheet_directory() . '/inc/course-admin.php';
require_once get_stylesheet_directory() . '/inc/button-icons.php';

if ( defined( 'JHA_LESSON_META_DEBUG' ) && JHA_LESSON_META_DEBUG ) {
	require_once get_stylesheet_directory() . '/inc/course-meta-debug.php';
}

/**
 * Ensure pages can use featured images for course sidebar logos.
 */
function jha_course_template_setup() {
	$supported_post_types = get_theme_support( 'post-thumbnails' );

	if ( true === $supported_post_types ) {
		return;
	}

	$post_types = array( 'post', 'page' );

	if ( is_array( $supported_post_types ) && isset( $supported_post_types[0] ) && is_array( $supported_post_types[0] ) ) {
		$post_types = array_unique( array_merge( $supported_post_types[0], array( 'page' ) ) );
	}

	add_theme_support( 'post-thumbnails', $post_types );
}
add_action( 'after_setup_theme', 'jha_course_template_setup', 11 );

function my_theme_enqueue_styles() {
	$parent_style = 'parent-style';
	$parent_theme = wp_get_theme()->parent();
	$parent_version = $parent_theme ? $parent_theme->get( 'Version' ) : wp_get_theme()->get( 'Version' );

	wp_enqueue_style( $parent_style, get_template_directory_uri() . '/style.css', array(), $parent_version );
	wp_enqueue_style(
		'child-style',
		get_stylesheet_directory_uri() . '/style.css',
		array( $parent_style ),
		wp_get_theme()->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'my_theme_enqueue_styles' );

/**
 * ProgressAlly's Gutenberg style depends on this handle even when the plugin
 * has no generated frontend styling file, so provide a fallback handle without
 * blocking the real generated stylesheet when it exists.
 */
function jha_register_progressally_frontend_style_fallback() {
	if ( wp_style_is( 'progressally-frontend-styling', 'registered' ) ) {
		return;
	}

	$generated_css_path = WP_CONTENT_DIR . '/progressally-css/progressally-style.css';
	$generated_css_url  = content_url( 'progressally-css/progressally-style.css' );
	$generated_version  = file_exists( $generated_css_path ) ? (string) filemtime( $generated_css_path ) : wp_get_theme()->get( 'Version' );

	if ( file_exists( $generated_css_path ) ) {
		wp_register_style( 'progressally-frontend-styling', $generated_css_url, array(), $generated_version );
		return;
	}

	wp_register_style( 'progressally-frontend-styling', false, array(), wp_get_theme()->get( 'Version' ) );
}
add_action( 'init', 'jha_register_progressally_frontend_style_fallback', 9 );

/**
 * Register custom editor blocks provided by the child theme.
 */
function jha_register_theme_blocks() {
	$block_style_path = get_stylesheet_directory() . '/assets/css/icon-callout.css';
	$block_script_path = get_stylesheet_directory() . '/assets/js/icon-callout-block.js';

	wp_register_style(
		'jha-icon-callout-block',
		get_stylesheet_directory_uri() . '/assets/css/icon-callout.css',
		array(),
		file_exists( $block_style_path ) ? (string) filemtime( $block_style_path ) : wp_get_theme()->get( 'Version' )
	);

	wp_register_script(
		'jha-icon-callout-block',
		get_stylesheet_directory_uri() . '/assets/js/icon-callout-block.js',
		array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n' ),
		file_exists( $block_script_path ) ? (string) filemtime( $block_script_path ) : wp_get_theme()->get( 'Version' ),
		true
	);

	register_block_type(
		'jha/icon-callout',
		array(
			'editor_script' => 'jha-icon-callout-block',
			'editor_style'  => 'jha-icon-callout-block',
			'style'         => 'jha-icon-callout-block',
		)
	);
}
add_action( 'init', 'jha_register_theme_blocks' );

/**
 * Extend the core Button block with optional SVG icons.
 */
function jha_register_button_icon_extension() {
	$script_path = get_stylesheet_directory() . '/assets/js/button-icon-extension.js';
	$style_path  = get_stylesheet_directory() . '/assets/css/button-icon.css';

	wp_register_script(
		'jha-button-icon-extension',
		get_stylesheet_directory_uri() . '/assets/js/button-icon-extension.js',
		array( 'wp-blocks', 'wp-hooks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
		file_exists( $script_path ) ? (string) filemtime( $script_path ) : wp_get_theme()->get( 'Version' ),
		true
	);

	wp_register_style(
		'jha-button-icon',
		get_stylesheet_directory_uri() . '/assets/css/button-icon.css',
		array(),
		file_exists( $style_path ) ? (string) filemtime( $style_path ) : wp_get_theme()->get( 'Version' )
	);

	wp_register_style( 'jha-button-icon-masks', false, array( 'jha-button-icon' ), wp_get_theme()->get( 'Version' ) );
	wp_add_inline_style( 'jha-button-icon-masks', jha_get_button_icon_mask_css() );
}
add_action( 'init', 'jha_register_button_icon_extension' );

/**
 * Get button icon options for the block editor script.
 *
 * @return array<int, array{label: string, value: string, mask?: string}>
 */
function jha_get_button_icon_editor_options() {
	$options = array(
		array(
			'label' => __( 'None', 'journey-homeschool-academy' ),
			'value' => '',
		),
	);

	foreach ( jha_get_button_icon_definitions() as $key => $definition ) {
		$options[] = array(
			'label' => $definition['label'],
			'value' => $key,
			'mask'  => jha_get_button_icon_mask_data_uri( $definition['svg'] ),
		);
	}

	return $options;
}

/**
 * Load button icon extension assets in the block editor.
 *
 * @return void
 */
function jha_enqueue_button_icon_extension_editor_assets() {
	wp_enqueue_script( 'jha-button-icon-extension' );
	wp_enqueue_style( 'jha-button-icon' );
	wp_enqueue_style( 'jha-button-icon-masks' );

	wp_localize_script(
		'jha-button-icon-extension',
		'jhaButtonIcons',
		array(
			'options' => jha_get_button_icon_editor_options(),
		)
	);
}
add_action( 'enqueue_block_editor_assets', 'jha_enqueue_button_icon_extension_editor_assets' );

/**
 * Load button icon styles on the frontend site-wide.
 *
 * @return void
 */
function jha_enqueue_button_icon_frontend_assets() {
	if ( is_admin() ) {
		return;
	}

	wp_enqueue_style( 'jha-button-icon' );
	wp_enqueue_style( 'jha-button-icon-masks' );
}
add_action( 'wp_enqueue_scripts', 'jha_enqueue_button_icon_frontend_assets', 20 );

/**
 * Apply button icon attributes to rendered button blocks.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block Block data.
 * @return string
 */
function jha_render_button_icon_block( $block_content, $block ) {
	if ( empty( $block['blockName'] ) || 'core/button' !== $block['blockName'] ) {
		return $block_content;
	}

	$attributes = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

	return jha_apply_button_block_customizations( $block_content, $attributes );
}
add_filter( 'render_block', 'jha_render_button_icon_block', 10, 2 );

/**
 * Load the lightweight course template stylesheet only for pages that opt in.
 */
function jha_enqueue_course_template_assets() {
	if ( ! is_page_template( jha_get_course_template_slug() ) ) {
		return;
	}

	$style_dependencies = array( 'child-style' );
	$generated_css_path = WP_CONTENT_DIR . '/progressally-css/progressally-style.css';

	if ( file_exists( $generated_css_path ) ) {
		wp_enqueue_style(
			'jha-progressally-frontend-styling',
			content_url( 'progressally-css/progressally-style.css' ),
			array(),
			(string) filemtime( $generated_css_path )
		);

		$style_dependencies[] = 'jha-progressally-frontend-styling';
	} elseif ( wp_style_is( 'progressally-frontend-styling', 'enqueued' ) || wp_style_is( 'progressally-frontend-styling', 'registered' ) ) {
		$style_dependencies[] = 'progressally-frontend-styling';
	}

	wp_enqueue_style(
		'jha-course-template',
		get_stylesheet_directory_uri() . '/assets/css/course-template.css',
		$style_dependencies,
		wp_get_theme()->get( 'Version' )
	);

	wp_enqueue_script(
		'jha-course-menu',
		get_stylesheet_directory_uri() . '/assets/js/course-menu.js',
		array(),
		wp_get_theme()->get( 'Version' ),
		true
	);

	wp_localize_script(
		'jha-course-menu',
		'jhaCourseMenu',
		array(
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'progressNonce' => wp_create_nonce( 'jha_course_progress' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'jha_enqueue_course_template_assets', 20 );

/**
 * Print critical ProgressAlly objective overrides after generated plugin styles.
 *
 * Staging can load AccessAlly/ProgressAlly styling after the child stylesheet,
 * so these rules are printed late in the head on course template pages.
 */
function jha_print_course_template_progressally_overrides() {
	if ( ! is_page_template( jha_get_course_template_slug() ) ) {
		return;
	}

	$style_id = 'wp_footer' === current_filter()
		? 'jha-progressally-objective-final-overrides'
		: 'jha-progressally-objective-overrides';
	?>
	<style id="<?php echo esc_attr( $style_id ); ?>">
		body .jha-course-template .objective-table,
		body .jha-course-content .objective-table {
			--jha-progressally-objective-color: #2f4867;
			--jha-progressally-objective-gap: 0;
			--jha-progressally-checkmark-icon: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='20 6 9 17 4 12'/%3E%3C/svg%3E");
		}

		body .jha-course-template table.objective-table,
		body .jha-course-content table.objective-table {
			padding: 0 !important;
		}

		body .jha-course-template .objective-table tr,
		body .jha-course-template .objective-table .progressally-flex-row,
		body .jha-course-content .objective-table tr,
		body .jha-course-content .objective-table .progressally-flex-row {
			align-items: center !important;
			border: 1px solid #eeeeee !important;
		}

		body .jha-course-template .objective-table tr > td,
		body .jha-course-content .objective-table tr > td {
			padding: 8px 10px !important;
			border-top: 1px solid #eeeeee !important;
			border-bottom: 1px solid #eeeeee !important;
			vertical-align: middle !important;
		}

		body .jha-course-template .objective-table tr > td:first-child,
		body .jha-course-content .objective-table tr > td:first-child {
			border-left: 1px solid #eeeeee !important;
		}

		body .jha-course-template .objective-table tr > td:last-child,
		body .jha-course-content .objective-table tr > td:last-child {
			border-right: 1px solid #eeeeee !important;
		}

		body .jha-course-template .objective-table tr:first-child > td:first-child,
		body .jha-course-template .objective-table .progressally-flex-row:first-child,
		body .jha-course-content .objective-table tr:first-child > td:first-child,
		body .jha-course-content .objective-table .progressally-flex-row:first-child {
			border-top-left-radius: 6px !important;
		}

		body .jha-course-template .objective-table tr:first-child > td:last-child,
		body .jha-course-template .objective-table .progressally-flex-row:first-child,
		body .jha-course-content .objective-table tr:first-child > td:last-child,
		body .jha-course-content .objective-table .progressally-flex-row:first-child {
			border-top-right-radius: 6px !important;
		}

		body .jha-course-template .objective-table tr:last-child > td:first-child,
		body .jha-course-template .objective-table .progressally-flex-row:last-child,
		body .jha-course-content .objective-table tr:last-child > td:first-child,
		body .jha-course-content .objective-table .progressally-flex-row:last-child {
			border-bottom-left-radius: 6px !important;
		}

		body .jha-course-template .objective-table tr:last-child > td:last-child,
		body .jha-course-template .objective-table .progressally-flex-row:last-child,
		body .jha-course-content .objective-table tr:last-child > td:last-child,
		body .jha-course-content .objective-table .progressally-flex-row:last-child {
			border-bottom-right-radius: 6px !important;
		}

		body .jha-course-template div.objective-table .progressally-flex-row,
		body .jha-course-content div.objective-table .progressally-flex-row {
			padding: 8px 10px !important;
		}

		body .jha-course-template .objective-table .progressally-flex-cell,
		body .jha-course-content .objective-table .progressally-flex-cell {
			align-items: center !important;
		}

		body .jha-course-template .objective-table .objective-number,
		body .jha-course-content .objective-table .objective-number {
			width: 48px !important;
			min-width: 48px !important;
			flex: 0 0 48px !important;
			align-items: flex-start !important;
			background-image: none !important;
			padding: 10px 10px 6px !important;
			vertical-align: top !important;
		}

		body .jha-course-template .objective-table .objective-description,
		body .jha-course-content .objective-table .objective-description {
			padding: 8px 10px !important;
			line-height: 24px !important;
			vertical-align: middle !important;
		}

		body .jha-course-template .objective-table .objective-completion,
		body .jha-course-content .objective-table .objective-completion {
			width: 24px !important;
			min-width: 24px !important;
			flex: 0 0 24px !important;
			align-items: flex-start !important;
			justify-content: flex-end !important;
			padding: 10px 10px 6px !important;
			text-align: right !important;
			vertical-align: top !important;
		}

		body .jha-course-template .objective-table .pa-objective-circle,
		body .jha-course-content .objective-table .pa-objective-circle {
			box-sizing: border-box !important;
			display: inline-block !important;
			flex: 0 0 28px !important;
			aspect-ratio: 1 / 1 !important;
			width: 28px !important;
			height: 28px !important;
			min-width: 28px !important;
			min-height: 28px !important;
			padding: 0 !important;
			border-color: var(--jha-progressally-objective-color, #2f4867) !important;
			border-radius: 999px !important;
			background-color: var(--jha-progressally-objective-color, #2f4867) !important;
			color: #fff !important;
			font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif !important;
			font-size: 13px !important;
			font-style: normal !important;
			font-weight: 700 !important;
			letter-spacing: 0 !important;
			line-height: 28px !important;
			text-align: center !important;
			text-indent: 1px !important;
		}

		body .jha-course-template .objective-table input[type="checkbox"].completion-checkbox,
		body .jha-course-content .objective-table input[type="checkbox"].completion-checkbox {
			position: absolute !important;
			display: none !important;
			width: 0 !important;
			height: 0 !important;
			margin: 0 !important;
			opacity: 0 !important;
			appearance: none !important;
			-webkit-appearance: none !important;
		}

		body .jha-course-template .objective-table input[type="checkbox"].completion-checkbox + label.progressally-space-click,
		body .jha-course-content .objective-table input[type="checkbox"].completion-checkbox + label.progressally-space-click {
			box-sizing: border-box !important;
			position: relative !important;
			top: auto !important;
			display: inline-flex !important;
			flex: 0 0 24px !important;
			align-items: center !important;
			justify-content: center !important;
			width: 24px !important;
			min-width: 24px !important;
			height: 24px !important;
			min-height: 24px !important;
			padding: 0 !important;
			margin: 0 !important;
			border: 2px solid #eeeeee !important;
			border-radius: 3px !important;
			background: #eeeeee !important;
			background-color: #eeeeee !important;
			background-image: none !important;
			color: transparent !important;
			font-size: 0 !important;
			line-height: 1 !important;
			overflow: hidden !important;
			transform: none !important;
		}

		body .jha-course-template .objective-table input[type="checkbox"].completion-checkbox:checked + label.progressally-space-click,
		body .jha-course-content .objective-table input[type="checkbox"].completion-checkbox:checked + label.progressally-space-click {
			border-color: var(--jha-progressally-objective-color, #2f4867) !important;
			background: var(--jha-progressally-objective-color, #2f4867) !important;
			background-color: var(--jha-progressally-objective-color, #2f4867) !important;
			background-image: var(--jha-progressally-checkmark-icon) !important;
			background-position: center center !important;
			background-repeat: no-repeat !important;
			background-size: 14px 14px !important;
		}

		body .jha-course-template .objective-table input[type="checkbox"].completion-checkbox + label.progressally-space-click::before,
		body .jha-course-template .objective-table input[type="checkbox"].completion-checkbox + label.progressally-space-click::after,
		body .jha-course-content .objective-table input[type="checkbox"].completion-checkbox + label.progressally-space-click::before,
		body .jha-course-content .objective-table input[type="checkbox"].completion-checkbox + label.progressally-space-click::after {
			content: none !important;
			display: none !important;
			width: 0 !important;
			height: 0 !important;
			margin: 0 !important;
			padding: 0 !important;
			border: 0 !important;
			background: none !important;
			font-family: inherit !important;
			font-size: 0 !important;
			line-height: 0 !important;
			transform: none !important;
		}
	</style>
	<?php
}
add_action( 'wp_head', 'jha_print_course_template_progressally_overrides', 999 );
add_action( 'wp_footer', 'jha_print_course_template_progressally_overrides', 999 );

/**
 * SmartVideo conditionally loads its player script by scanning page content.
 *
 * Course pages use a custom PHP template, so force the player script there in
 * case SmartVideo's scanner misses Gutenberg blocks in this layout.
 *
 * @param bool $force_load Whether SmartVideo should force-load its script.
 * @return bool
 */
function jha_force_smartvideo_on_course_template( $force_load ) {
	if ( is_page_template( 'page-templates/course-template.php' ) ) {
		return true;
	}

	return $force_load;
}
add_filter( 'smartvideo_force_load_script', 'jha_force_smartvideo_on_course_template' );

/**
 * Ensure SmartVideo block styles load on the course template.
 *
 * The course content column uses flexbox; SmartVideo's Gutenberg block styles
 * are required so the player wrapper gets a stable width before Swarmify init.
 *
 * @return void
 */
function jha_enqueue_smartvideo_block_styles_on_course_template() {
	if ( ! is_page_template( jha_get_course_template_slug() ) ) {
		return;
	}

	if ( wp_style_is( 'smartvideo-gutenberg-block-style', 'registered' ) ) {
		wp_enqueue_style( 'smartvideo-gutenberg-block-style' );
	}
}
add_action( 'wp_enqueue_scripts', 'jha_enqueue_smartvideo_block_styles_on_course_template', 20 );

// Remove excluded search posts from Google / Search.
function add_meta_for_search_excluded() {
	global $post;

	if ( $post instanceof WP_Post && in_array( $post->ID, array_map( 'absint', get_option( 'sep_exclude', array() ) ), true ) ) {
		echo '<meta name="robots" content="noindex,nofollow" />' . "\n";
	}
}
//add_action('wp_head', 'add_meta_for_search_excluded');


// Conditional Nav Menu.

function wpc_wp_nav_menu_args( $args = array() ) {
	if ( ! is_array( $args ) || empty( $args['theme_location'] ) ) {
		return $args;
	}

	if ( is_user_logged_in() ) {
		if ( 'secondary-menu' === $args['theme_location'] ) {
			$args['menu'] = 'logged-in-secondary';
		}
	} elseif ( 'secondary' === $args['theme_location'] ) {
		$args['menu'] = 'logged-out-secondary';
	}

	return $args;
}
add_filter( 'wp_nav_menu_args', 'wpc_wp_nav_menu_args' );

// Full Size Blog Featured Image.
function wpc_remove_height_cropping( $height ) {
	return '9999';
}
function wpc_remove_width_cropping( $width ) {
	return '9999';
}

add_filter( 'et_pb_blog_image_height', 'wpc_remove_height_cropping' );
add_filter( 'et_pb_blog_image_width', 'wpc_remove_width_cropping' );

add_action( 'wp_enqueue_scripts', 'kdac_enqueue_footer_script' );

/**
 * Add FormKit trigger attributes to specific nav links.
 *
 * @return void
 */
function kdac_enqueue_footer_script() {
	wp_enqueue_script( 'jquery' );
	wp_add_inline_script(
		'jquery',
		"(function($) {
			$('#menu-item-44612 a, #menu-item-72964 a').attr('data-formkit-toggle', 'c181d95360');
		})(jQuery);"
	);
}
