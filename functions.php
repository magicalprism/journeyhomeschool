<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once get_stylesheet_directory() . '/inc/course-menu.php';
require_once get_stylesheet_directory() . '/inc/course-admin.php';

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
 * Load the lightweight course template stylesheet only for pages that opt in.
 */
function jha_enqueue_course_template_assets() {
	if ( ! is_page_template( 'page-templates/course-template.php' ) ) {
		return;
	}

	$style_dependencies = array( 'child-style' );

	if ( wp_style_is( 'progressally-frontend-styling', 'enqueued' ) || wp_style_is( 'progressally-frontend-styling', 'registered' ) ) {
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
	if ( ! is_page_template( 'page-templates/course-template.php' ) ) {
		return;
	}
	?>
	<style id="jha-progressally-objective-overrides">
		body .jha-course-template .objective-table,
		body .jha-course-content .objective-table {
			--jha-progressally-objective-color: #2f4867;
			--jha-progressally-objective-gap: 0;
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
			display: inline-grid !important;
			place-items: center !important;
			width: 24px !important;
			height: 24px !important;
			padding: 2px !important;
			margin: 0 !important;
			border-color: #eeeeee !important;
			border-radius: 3px !important;
			background: #eeeeee !important;
			background-color: #eeeeee !important;
			background-image: none !important;
			color: #fff !important;
			line-height: 1 !important;
			transform: none !important;
		}

		body .jha-course-template .objective-table input[type="checkbox"].completion-checkbox:checked + label.progressally-space-click,
		body .jha-course-content .objective-table input[type="checkbox"].completion-checkbox:checked + label.progressally-space-click {
			border-color: var(--jha-progressally-objective-color, #2f4867) !important;
			background: var(--jha-progressally-objective-color, #2f4867) !important;
			background-color: var(--jha-progressally-objective-color, #2f4867) !important;
			background-image: none !important;
		}

		body .jha-course-template .objective-table input[type="checkbox"].completion-checkbox + label.progressally-space-click::before,
		body .jha-course-content .objective-table input[type="checkbox"].completion-checkbox + label.progressally-space-click::before {
			content: "" !important;
			display: none !important;
		}

		body .jha-course-template .objective-table input[type="checkbox"].completion-checkbox:checked + label.progressally-space-click::before,
		body .jha-course-content .objective-table input[type="checkbox"].completion-checkbox:checked + label.progressally-space-click::before {
			content: "" !important;
			display: block !important;
			position: absolute !important;
			top: 50% !important;
			left: 50% !important;
			width: 5px !important;
			height: 11px !important;
			margin: 0 !important;
			padding: 0 !important;
			border: 0 !important;
			border-right: 2px solid #fff !important;
			border-bottom: 2px solid #fff !important;
			background: transparent !important;
			color: #fff !important;
			transform: translate(-50%, -58%) rotate(45deg) !important;
			transform-origin: center !important;
		}

		body .jha-course-template .objective-table input[type="checkbox"].completion-checkbox + label.progressally-space-click::after,
		body .jha-course-content .objective-table input[type="checkbox"].completion-checkbox + label.progressally-space-click::after {
			content: none !important;
			display: none !important;
		}
	</style>
	<?php
}
add_action( 'wp_head', 'jha_print_course_template_progressally_overrides', 999 );

/**
 * Final ProgressAlly objective override for environments with reordered CSS.
 */
function jha_print_progressally_objective_final_overrides() {
	?>
	<style id="jha-progressally-objective-final-overrides">
		body .objective-table {
			--jha-progressally-objective-color: #2f4867;
			--jha-progressally-objective-gap: 0;
		}

		body table.objective-table {
			padding: 0 !important;
			border-collapse: separate !important;
			border-spacing: 0 var(--jha-progressally-objective-gap) !important;
		}

		body .objective-table tr,
		body .objective-table .progressally-flex-row {
			align-items: center !important;
			border: 1px solid #eeeeee !important;
		}

		body .objective-table tr > td {
			padding: 8px 10px !important;
			border-top: 1px solid #eeeeee !important;
			border-bottom: 1px solid #eeeeee !important;
			vertical-align: middle !important;
		}

		body .objective-table tr > td:first-child {
			border-left: 1px solid #eeeeee !important;
		}

		body .objective-table tr > td:last-child {
			border-right: 1px solid #eeeeee !important;
		}

		body .objective-table tr:first-child > td:first-child,
		body .objective-table .progressally-flex-row:first-child {
			border-top-left-radius: 6px !important;
		}

		body .objective-table tr:first-child > td:last-child,
		body .objective-table .progressally-flex-row:first-child {
			border-top-right-radius: 6px !important;
		}

		body .objective-table tr:last-child > td:first-child,
		body .objective-table .progressally-flex-row:last-child {
			border-bottom-left-radius: 6px !important;
		}

		body .objective-table tr:last-child > td:last-child,
		body .objective-table .progressally-flex-row:last-child {
			border-bottom-right-radius: 6px !important;
		}

		body div.objective-table .progressally-flex-row {
			padding: 8px 10px !important;
		}

		body .objective-table .progressally-flex-cell {
			align-items: center !important;
		}

		body .objective-table .objective-number {
			width: 48px !important;
			min-width: 48px !important;
			flex: 0 0 48px !important;
			align-items: flex-start !important;
			padding: 10px 10px 6px !important;
			vertical-align: top !important;
		}

		body .objective-table .objective-description {
			padding: 8px 10px !important;
			line-height: 24px !important;
			vertical-align: middle !important;
		}

		body .objective-table .objective-completion {
			width: 24px !important;
			min-width: 24px !important;
			flex: 0 0 24px !important;
			align-items: flex-start !important;
			justify-content: flex-end !important;
			padding: 10px 10px 6px !important;
			text-align: right !important;
			vertical-align: top !important;
		}

		body .objective-table .pa-objective-circle {
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

		body .objective-table input[type="checkbox"].completion-checkbox {
			position: absolute !important;
			display: none !important;
			width: 0 !important;
			height: 0 !important;
			margin: 0 !important;
			opacity: 0 !important;
			appearance: none !important;
			-webkit-appearance: none !important;
		}

		body .objective-table input[type="checkbox"].completion-checkbox + label.progressally-space-click {
			box-sizing: border-box !important;
			position: relative !important;
			top: auto !important;
			display: inline-grid !important;
			place-items: center !important;
			width: 24px !important;
			height: 24px !important;
			padding: 2px !important;
			margin: 0 !important;
			border-color: #eeeeee !important;
			border-radius: 3px !important;
			background: #eeeeee !important;
			background-color: #eeeeee !important;
			background-image: none !important;
			color: #fff !important;
			line-height: 1 !important;
			transform: none !important;
		}

		body .objective-table input[type="checkbox"].completion-checkbox:checked + label.progressally-space-click {
			border-color: var(--jha-progressally-objective-color, #2f4867) !important;
			background: var(--jha-progressally-objective-color, #2f4867) !important;
			background-color: var(--jha-progressally-objective-color, #2f4867) !important;
			background-image: none !important;
		}

		body .objective-table input[type="checkbox"].completion-checkbox + label.progressally-space-click::before {
			content: "" !important;
			display: none !important;
		}

		body .objective-table input[type="checkbox"].completion-checkbox:checked + label.progressally-space-click::before {
			content: "" !important;
			display: block !important;
			position: absolute !important;
			top: 50% !important;
			left: 50% !important;
			width: 5px !important;
			height: 11px !important;
			margin: 0 !important;
			padding: 0 !important;
			border: 0 !important;
			border-right: 2px solid #fff !important;
			border-bottom: 2px solid #fff !important;
			background: transparent !important;
			color: #fff !important;
			transform: translate(-50%, -58%) rotate(45deg) !important;
			transform-origin: center !important;
		}

		body .objective-table input[type="checkbox"].completion-checkbox + label.progressally-space-click::after {
			content: none !important;
			display: none !important;
		}
	</style>
	<?php
}
add_action( 'wp_footer', 'jha_print_progressally_objective_final_overrides', 999 );

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
	if ( ! is_page_template( 'page-templates/course-template.php' ) ) {
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
