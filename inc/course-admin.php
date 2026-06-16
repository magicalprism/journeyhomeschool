<?php
/**
 * Admin controls for the lightweight JHA course template.
 *
 * Adds a menu-label override to pages and a child-page manager to parent
 * course pages. The manager can reorder direct children, create new lesson
 * pages, hide lessons from the course menu, and move lessons to the Trash.
 * Ordering saves to WordPress menu_order, so the front-end course menu uses the
 * same ordering as the Pages hierarchy.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register post meta used to persist lesson manager state in the block editor.
 *
 * @return void
 */
function jha_register_course_lesson_post_meta() {
	$auth_callback = static function ( $allowed, $meta_key, $object_id ) {
		unset( $allowed, $meta_key );

		return current_user_can( 'edit_page', absint( $object_id ) );
	};

	register_post_meta(
		'page',
		'_jha_course_lesson_tree',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'auth_callback'     => $auth_callback,
			'sanitize_callback' => 'jha_sanitize_course_lesson_tree_meta',
		)
	);

	register_post_meta(
		'page',
		'_jha_course_menu_tree',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'auth_callback'     => $auth_callback,
			'sanitize_callback' => 'jha_sanitize_course_lesson_tree_meta',
		)
	);

	register_post_meta(
		'page',
		'_jha_course_new_child_pages',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'auth_callback'     => $auth_callback,
			'sanitize_callback' => 'jha_sanitize_course_new_child_pages_meta',
		)
	);

	register_post_meta(
		'page',
		'_jha_course_child_trash',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'auth_callback'     => $auth_callback,
			'sanitize_callback' => 'sanitize_text_field',
		)
	);

	register_post_meta(
		'page',
		'_jha_course_hidden_lessons',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'auth_callback'     => $auth_callback,
			'sanitize_callback' => 'sanitize_text_field',
		)
	);

	register_post_meta(
		'page',
		'_jha_course_hide_from_menu',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'auth_callback'     => $auth_callback,
			'sanitize_callback' => 'sanitize_text_field',
		)
	);

	register_post_meta(
		'page',
		'_jha_course_show_progress',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'auth_callback'     => $auth_callback,
			'sanitize_callback' => 'jha_sanitize_course_show_progress_meta',
		)
	);
}
add_action( 'init', 'jha_register_course_lesson_post_meta' );

/**
 * Sanitize the course progress sidebar toggle meta value.
 *
 * @param mixed $value Raw meta value.
 * @param int   $post_id Page ID.
 * @return string
 */
function jha_sanitize_course_show_progress_meta( $value, $post_id = 0 ) {
	unset( $post_id );

	return '1' === (string) $value ? '1' : '0';
}

/**
 * Sanitize the JSON lesson tree stored in post meta.
 *
 * @param mixed $value Raw meta value.
 * @return string
 */
function jha_sanitize_course_lesson_tree_meta( $value ) {
	if ( ! is_string( $value ) ) {
		return '';
	}

	$decoded = json_decode( wp_unslash( $value ), true );

	if ( ! is_array( $decoded ) ) {
		return '';
	}

	return wp_json_encode( jha_sanitize_course_lesson_tree_nodes( $decoded ) );
}

/**
 * Sanitize submitted lesson tree nodes recursively.
 *
 * @param array $nodes Lesson tree nodes.
 * @return array<int, array<string, mixed>>
 */
function jha_sanitize_course_lesson_tree_nodes( $nodes ) {
	$sanitized = array();

	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) || ! isset( $node['token'] ) ) {
			continue;
		}

		$token = sanitize_key( (string) $node['token'] );

		if ( '' === $token ) {
			continue;
		}

		$children = isset( $node['children'] ) && is_array( $node['children'] )
			? jha_sanitize_course_lesson_tree_nodes( $node['children'] )
			: array();

		$sanitized_node = array(
			'token'    => $token,
			'children' => $children,
		);

		if ( ( isset( $node['type'] ) && 'menu' === sanitize_key( (string) $node['type'] ) ) || 0 === strpos( $token, 'menu-' ) ) {
			$title = isset( $node['title'] ) ? sanitize_text_field( (string) $node['title'] ) : '';

			if ( '' === $title ) {
				continue;
			}

			$sanitized_node['type']  = 'menu';
			$sanitized_node['title'] = $title;
		}

		$sanitized[] = $sanitized_node;
	}

	return $sanitized;
}

/**
 * Sanitize pending new lesson pages stored in post meta.
 *
 * @param mixed $value Raw meta value.
 * @return string
 */
function jha_sanitize_course_new_child_pages_meta( $value ) {
	if ( ! is_string( $value ) ) {
		return '';
	}

	$decoded = json_decode( wp_unslash( $value ), true );

	if ( ! is_array( $decoded ) ) {
		return '';
	}

	$sanitized = array();

	foreach ( $decoded as $token => $page_data ) {
		$token = sanitize_key( $token );

		if ( is_array( $page_data ) ) {
			$title      = isset( $page_data['title'] ) ? sanitize_text_field( (string) $page_data['title'] ) : '';
			$menu_label = isset( $page_data['menuLabel'] ) ? sanitize_text_field( (string) $page_data['menuLabel'] ) : '';
		} else {
			$title      = sanitize_text_field( (string) $page_data );
			$menu_label = '';
		}

		if ( '' !== $token && '' !== $title ) {
			$sanitized[ $token ] = array(
				'title'     => $title,
				'menuLabel' => $menu_label,
			);
		}
	}

	return wp_json_encode( $sanitized );
}

/**
 * Whether a page belongs to the current course tree.
 *
 * @param int $page_id Page ID.
 * @param int $course_root_id Course root page ID.
 * @return bool
 */
function jha_course_page_is_in_course_tree( $page_id, $course_root_id ) {
	$page_id        = absint( $page_id );
	$course_root_id = absint( $course_root_id );

	if ( ! $page_id || ! $course_root_id ) {
		return false;
	}

	if ( $page_id === $course_root_id ) {
		return true;
	}

	$page = get_post( $page_id );

	if ( ! $page || 'page' !== $page->post_type ) {
		return false;
	}

	return $course_root_id === jha_get_course_parent_id( $page_id );
}

/**
 * Safely fetch a string field from POST data.
 *
 * @param string $key POST field key.
 * @return string
 */
function jha_get_posted_string( $key ) {
	if ( ! isset( $_POST[ $key ] ) || ! is_string( $_POST[ $key ] ) ) {
		return '';
	}

	return wp_unslash( $_POST[ $key ] );
}

/**
 * Check whether POST contains a scalar string field.
 *
 * @param string $key POST field key.
 * @return bool
 */
function jha_has_posted_string( $key ) {
	return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] );
}

/**
 * Register course-related page editor meta boxes.
 *
 * @return void
 */
function jha_register_course_admin_meta_boxes() {
	$block_editor_args = array(
		'__block_editor_compatible_meta_box' => true,
	);

	add_meta_box(
		'jha-course-settings',
		__( 'Course Settings', 'journey-homeschool-academy' ),
		'jha_render_course_settings_meta_box',
		'page',
		'normal',
		'high',
		$block_editor_args
	);
}
add_action( 'add_meta_boxes_page', 'jha_register_course_admin_meta_boxes' );

/**
 * Render all course controls together so third-party meta boxes cannot split them.
 *
 * @param WP_Post $post Current page.
 * @return void
 */
function jha_render_course_settings_meta_box( $post ) {
	wp_nonce_field( 'jha_save_course_page_settings', 'jha_course_page_settings_nonce' );
	jha_render_course_toggle_styles();
	?>
	<div class="jha-course-settings-section">
		<h3><?php esc_html_e( 'Course Menu Settings', 'journey-homeschool-academy' ); ?></h3>
		<?php jha_render_course_menu_settings_meta_box( $post ); ?>
	</div>

	<hr>

	<div class="jha-course-settings-section">
		<h3><?php esc_html_e( 'Course Lesson Order', 'journey-homeschool-academy' ); ?></h3>
		<?php jha_render_course_child_order_meta_box( $post ); ?>
	</div>
	<?php
}

/**
 * Render the optional menu label field.
 *
 * @param WP_Post $post Current page.
 * @return void
 */
function jha_render_course_menu_settings_meta_box( $post ) {
	$menu_label      = get_post_meta( $post->ID, '_jha_course_menu_label', true );
	$block_gap       = jha_get_course_block_gap( $post->ID );
	$block_gap_value = (float) str_replace( 'rem', '', $block_gap );
	$show_title      = jha_should_show_course_title( $post->ID );
	$is_course_root  = jha_is_course_root_page( $post->ID );
	$show_progress   = jha_should_show_course_progress( $post->ID );
	?>
	<p>
		<label for="jha-course-menu-label">
			<?php esc_html_e( 'Menu label override', 'journey-homeschool-academy' ); ?>
		</label>
	</p>
	<input
		type="text"
		id="jha-course-menu-label"
		name="jha_course_menu_label"
		value="<?php echo esc_attr( $menu_label ); ?>"
		class="widefat"
		placeholder="<?php echo esc_attr( get_the_title( $post ) ); ?>"
	>
	<p class="description">
		<?php esc_html_e( 'Optional. Used only for the lightweight course sidebar menu; the page title stays unchanged.', 'journey-homeschool-academy' ); ?>
	</p>

	<p>
		<label for="jha-course-block-gap">
			<?php esc_html_e( 'Gutenberg block gap', 'journey-homeschool-academy' ); ?>
		</label>
	</p>
	<input
		type="range"
		id="jha-course-block-gap"
		name="jha_course_block_gap"
		value="<?php echo esc_attr( $block_gap_value ); ?>"
		min="0"
		max="5"
		step="0.5"
	>
	<output for="jha-course-block-gap">
		<?php echo esc_html( $block_gap ); ?>
	</output>
	<p class="description">
		<?php esc_html_e( 'Controls vertical spacing between top-level Gutenberg blocks in this template, from 0rem to 5rem in 0.5rem steps.', 'journey-homeschool-academy' ); ?>
	</p>

	<p>
		<input type="hidden" name="jha_course_show_progress" value="0">
		<label class="jha-course-toggle-control" for="jha-course-show-progress">
			<input
				type="checkbox"
				id="jha-course-show-progress"
				name="jha_course_show_progress"
				value="1"
				<?php checked( $show_progress ); ?>
			>
			<span class="jha-course-toggle-switch" aria-hidden="true"></span>
			<span class="jha-course-toggle-label">
				<?php esc_html_e( 'Show course progress ring in sidebar', 'journey-homeschool-academy' ); ?>
			</span>
		</label>
	</p>
	<p class="description">
		<?php
		if ( $is_course_root ) {
			esc_html_e( 'Defaults to off on the course home page. Turn this on to show the progress ring while students are on the course home page.', 'journey-homeschool-academy' );
		} else {
			esc_html_e( 'Turn this off to hide the course progress ring while students are on this lesson page.', 'journey-homeschool-academy' );
		}
		?>
	</p>

	<p>
		<input type="hidden" name="jha_course_show_title" value="0">
		<label class="jha-course-toggle-control" for="jha-course-show-title">
			<input
				type="checkbox"
				id="jha-course-show-title"
				name="jha_course_show_title"
				value="1"
				<?php checked( $show_title ); ?>
			>
			<span class="jha-course-toggle-switch" aria-hidden="true"></span>
			<span class="jha-course-toggle-label">
				<?php esc_html_e( 'Show page title in template', 'journey-homeschool-academy' ); ?>
			</span>
		</label>
	</p>
	<p class="description">
		<?php esc_html_e( 'Turn this off when the page content already includes its own heading block.', 'journey-homeschool-academy' ); ?>
	</p>
	<?php
}

/**
 * Output shared styles for course settings toggle controls.
 *
 * @return void
 */
function jha_render_course_toggle_styles() {
	static $rendered = false;

	if ( $rendered ) {
		return;
	}

	$rendered = true;
	?>
	<style>
		.jha-course-toggle-control {
			display: inline-flex;
			align-items: center;
			gap: 10px;
			cursor: pointer;
			font-weight: 600;
		}

		.jha-course-toggle-control input {
			position: absolute;
			width: 1px;
			height: 1px;
			padding: 0;
			margin: -1px;
			overflow: hidden;
			clip: rect(0, 0, 0, 0);
			white-space: nowrap;
			border: 0;
		}

		.jha-course-toggle-switch {
			position: relative;
			display: inline-block;
			width: 42px;
			height: 22px;
			border-radius: 999px;
			background: #8c8f94;
			transition: background-color 160ms ease;
		}

		.jha-course-toggle-switch::before {
			position: absolute;
			top: 3px;
			left: 3px;
			width: 16px;
			height: 16px;
			border-radius: 50%;
			background: #fff;
			content: "";
			transition: transform 160ms ease;
		}

		.jha-course-toggle-control input:checked + .jha-course-toggle-switch {
			background: var(--wp-admin-theme-color, #2271b1);
		}

		.jha-course-toggle-control input:checked + .jha-course-toggle-switch::before {
			transform: translateX(20px);
		}

		.jha-course-toggle-control input:focus + .jha-course-toggle-switch {
			box-shadow: 0 0 0 2px #fff, 0 0 0 4px #2271b1;
		}

		.jha-course-toggle-control input:disabled + .jha-course-toggle-switch {
			opacity: 0.55;
			cursor: not-allowed;
		}

		.jha-course-toggle-control input:disabled ~ .jha-course-toggle-label {
			opacity: 0.75;
			cursor: not-allowed;
		}
	</style>
	<?php
}

/**
 * Build the nested lesson tree payload used by the admin sortable UI.
 *
 * @param int   $parent_id Parent page ID.
 * @param int[] $exclude_ids Page IDs already represented in saved tree data.
 * @return array<int, array<string, mixed>>
 */
function jha_build_course_lesson_tree( $parent_id, $exclude_ids = array() ) {
	$tree = array();

	foreach ( jha_get_course_child_pages( $parent_id, true ) as $child ) {
		if ( in_array( absint( $child->ID ), $exclude_ids, true ) ) {
			continue;
		}

		$tree[] = array(
			'token'    => (string) $child->ID,
			'children' => jha_build_course_lesson_tree( $child->ID, $exclude_ids ),
		);
	}

	return $tree;
}

/**
 * Get page IDs represented in an admin lesson tree.
 *
 * @param array $nodes Lesson tree nodes.
 * @return int[]
 */
function jha_get_course_lesson_tree_page_ids( $nodes ) {
	$page_ids = array();

	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) || empty( $node['token'] ) ) {
			continue;
		}

		$page_id = absint( $node['token'] );

		if ( $page_id ) {
			$page_ids[] = $page_id;
		}

		if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
			$page_ids = array_merge( $page_ids, jha_get_course_lesson_tree_page_ids( $node['children'] ) );
		}
	}

	return array_values( array_unique( array_filter( $page_ids ) ) );
}

/**
 * Get the admin lesson tree from saved menu data, falling back to page hierarchy.
 *
 * @param int $post_id Course root page ID.
 * @return array<int, array<string, mixed>>
 */
function jha_get_course_admin_lesson_tree( $post_id ) {
	$stored_tree = get_post_meta( absint( $post_id ), '_jha_course_menu_tree', true );
	$decoded     = is_string( $stored_tree ) && '' !== $stored_tree ? json_decode( $stored_tree, true ) : null;

	if ( is_array( $decoded ) ) {
		$stored_tree = jha_sanitize_course_lesson_tree_nodes( $decoded );
		$page_ids    = jha_get_course_lesson_tree_page_ids( $stored_tree );

		return array_merge( $stored_tree, jha_build_course_lesson_tree( $post_id, $page_ids ) );
	}

	return jha_build_course_lesson_tree( $post_id );
}

/**
 * Output hidden lesson manager fields for the page save request.
 *
 * @param WP_Post $post Current page.
 * @return void
 */
function jha_render_course_lesson_hidden_fields( $post ) {
	$lesson_tree = wp_json_encode( jha_get_course_admin_lesson_tree( $post->ID ) );
	?>
	<input
		type="hidden"
		id="jha-course-child-order"
		name="jha_course_child_order"
		value="<?php echo esc_attr( $lesson_tree ); ?>"
	>
	<input type="hidden" id="jha-course-child-trash" name="jha_course_child_trash" value="">
	<input type="hidden" id="jha-course-new-child-pages" name="jha_course_new_child_pages" value="">
	<input type="hidden" id="jha-course-hidden-lessons" name="jha_course_hidden_lessons" value="">
	<?php
}
add_action( 'block_editor_meta_box_hidden_fields', 'jha_render_course_lesson_hidden_fields' );

/**
 * Render one lesson manager row and its nested children.
 *
 * @param WP_Post $page Lesson page.
 * @param array   $children_nodes Optional saved child tree nodes.
 * @return void
 */
function jha_render_course_child_order_item( $page, $children_nodes = null ) {
	$children  = is_array( $children_nodes ) ? $children_nodes : jha_get_course_admin_lesson_tree( $page->ID );
	$is_hidden = jha_course_page_is_hidden_from_menu( $page->ID );
	?>
	<li class="jha-course-child-order-item<?php echo $is_hidden ? ' is-hidden-from-menu' : ''; ?>" data-page-id="<?php echo esc_attr( $page->ID ); ?>" data-page-token="<?php echo esc_attr( $page->ID ); ?>">
		<div class="jha-course-child-order-row">
			<span class="dashicons dashicons-menu" aria-hidden="true"></span>
			<span class="jha-course-child-order-title">
				<?php echo esc_html( jha_get_course_menu_label( $page->ID ) ); ?>
			</span>
			<span class="jha-course-child-order-post-title">
				<?php
				printf(
					/* translators: %s: page title. */
					esc_html__( 'Page title: %s', 'journey-homeschool-academy' ),
					esc_html( get_the_title( $page ) )
				);
				?>
			</span>
			<span class="jha-course-child-order-hidden-label">
				<?php esc_html_e( 'Hidden from menu', 'journey-homeschool-academy' ); ?>
			</span>
			<a
				class="button-link jha-course-child-action jha-course-child-edit-page"
				href="<?php echo esc_url( get_edit_post_link( $page->ID, 'raw' ) ); ?>"
				aria-label="<?php esc_attr_e( 'Edit page in WordPress', 'journey-homeschool-academy' ); ?>"
				title="<?php esc_attr_e( 'Edit page in WordPress', 'journey-homeschool-academy' ); ?>"
			>
				<span class="dashicons dashicons-edit" aria-hidden="true"></span>
				<span class="screen-reader-text">
					<?php esc_html_e( 'Edit page in WordPress', 'journey-homeschool-academy' ); ?>
				</span>
			</a>
			<a
				class="button-link jha-course-child-action jha-course-child-view-page"
				href="<?php echo esc_url( get_permalink( $page->ID ) ); ?>"
				target="_blank"
				rel="noopener noreferrer"
				aria-label="<?php esc_attr_e( 'View page on frontend', 'journey-homeschool-academy' ); ?>"
				title="<?php esc_attr_e( 'View page on frontend', 'journey-homeschool-academy' ); ?>"
			>
				<span class="dashicons dashicons-external" aria-hidden="true"></span>
				<span class="screen-reader-text">
					<?php esc_html_e( 'View page on frontend', 'journey-homeschool-academy' ); ?>
				</span>
			</a>
			<button
				type="button"
				class="button-link jha-course-child-action jha-course-child-label-override"
				data-page-id="<?php echo esc_attr( $page->ID ); ?>"
				data-current-label="<?php echo esc_attr( get_post_meta( $page->ID, '_jha_course_menu_label', true ) ); ?>"
				data-default-label="<?php echo esc_attr( get_the_title( $page ) ); ?>"
				aria-label="<?php esc_attr_e( 'Edit course menu label', 'journey-homeschool-academy' ); ?>"
				title="<?php esc_attr_e( 'Edit course menu label', 'journey-homeschool-academy' ); ?>"
			>
				<span class="dashicons dashicons-tag" aria-hidden="true"></span>
				<span class="screen-reader-text">
					<?php esc_html_e( 'Edit course menu label', 'journey-homeschool-academy' ); ?>
				</span>
			</button>
			<button
				type="button"
				class="button-link jha-course-child-action jha-course-child-hide"
				aria-label="<?php echo $is_hidden ? esc_attr__( 'Show in course menu', 'journey-homeschool-academy' ) : esc_attr__( 'Hide from course menu', 'journey-homeschool-academy' ); ?>"
				title="<?php echo $is_hidden ? esc_attr__( 'Show in course menu', 'journey-homeschool-academy' ) : esc_attr__( 'Hide from course menu', 'journey-homeschool-academy' ); ?>"
			>
				<span class="dashicons <?php echo $is_hidden ? 'dashicons-visibility' : 'dashicons-hidden'; ?>" aria-hidden="true"></span>
				<span class="screen-reader-text">
					<?php echo $is_hidden ? esc_html__( 'Show in course menu', 'journey-homeschool-academy' ) : esc_html__( 'Hide from course menu', 'journey-homeschool-academy' ); ?>
				</span>
			</button>
			<button
				type="button"
				class="button-link-delete jha-course-child-action jha-course-child-delete"
				aria-label="<?php esc_attr_e( 'Move page to Trash', 'journey-homeschool-academy' ); ?>"
				title="<?php esc_attr_e( 'Move page to Trash', 'journey-homeschool-academy' ); ?>"
			>
				<span class="dashicons dashicons-trash" aria-hidden="true"></span>
				<span class="screen-reader-text">
					<?php esc_html_e( 'Move page to Trash', 'journey-homeschool-academy' ); ?>
				</span>
			</button>
		</div>

		<ul class="jha-course-child-order-list jha-course-child-order-sublist">
			<?php
			foreach ( $children as $child_node ) {
				jha_render_course_child_order_node( $child_node );
			}
			?>
		</ul>
	</li>
	<?php
}

/**
 * Render one saved lesson manager node.
 *
 * @param array $node Saved lesson tree node.
 * @return void
 */
function jha_render_course_child_order_node( $node ) {
	if ( ! is_array( $node ) || empty( $node['token'] ) ) {
		return;
	}

	$token    = sanitize_key( (string) $node['token'] );
	$children = isset( $node['children'] ) && is_array( $node['children'] ) ? $node['children'] : array();

	if ( ( isset( $node['type'] ) && 'menu' === $node['type'] ) || 0 === strpos( $token, 'menu-' ) ) {
		$title = isset( $node['title'] ) ? sanitize_text_field( (string) $node['title'] ) : '';

		if ( '' === $title ) {
			return;
		}
		?>
		<li class="jha-course-child-order-item is-menu-only" data-page-token="<?php echo esc_attr( $token ); ?>" data-node-type="menu" data-menu-title="<?php echo esc_attr( $title ); ?>">
			<div class="jha-course-child-order-row">
				<span class="dashicons dashicons-menu" aria-hidden="true"></span>
				<span class="jha-course-child-order-title"><?php echo esc_html( $title ); ?></span>
				<span class="jha-course-child-order-post-title"><?php esc_html_e( 'Menu-only parent, no page created', 'journey-homeschool-academy' ); ?></span>
				<button type="button" class="button-link-delete jha-course-child-action jha-course-child-delete" aria-label="<?php esc_attr_e( 'Remove menu-only item', 'journey-homeschool-academy' ); ?>" title="<?php esc_attr_e( 'Remove menu-only item', 'journey-homeschool-academy' ); ?>">
					<span class="dashicons dashicons-trash" aria-hidden="true"></span>
					<span class="screen-reader-text"><?php esc_html_e( 'Remove menu-only item', 'journey-homeschool-academy' ); ?></span>
				</button>
			</div>
			<ul class="jha-course-child-order-list jha-course-child-order-sublist">
				<?php
				foreach ( $children as $child_node ) {
					jha_render_course_child_order_node( $child_node );
				}
				?>
			</ul>
		</li>
		<?php
		return;
	}

	$page = get_post( absint( $token ) );

	if ( $page && 'page' === $page->post_type ) {
		jha_render_course_child_order_item( $page, $children );
	}
}

/**
 * Render a sortable list of direct child pages.
 *
 * This appears most often on the top-level course page. Dragging lessons saves
 * both menu_order and page parent relationships, so admins can create nested
 * child/grandchild menu branches from one screen.
 *
 * @param WP_Post $post Current page.
 * @return void
 */
function jha_render_course_child_order_meta_box( $post ) {
	$children           = jha_get_course_admin_lesson_tree( $post->ID );
	$screen             = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$is_course_template = 'page-templates/course-template.php' === get_page_template_slug( $post );
	?>
	<p>
		<?php esc_html_e( 'Drag pages to reorder them, or drag them into another page to create nested lesson menu items. Use Hide to remove a lesson from the course menu while keeping the page published on the site. Use Trash only when you want to move the page itself to the WordPress Trash and remove it from the site.', 'journey-homeschool-academy' ); ?>
	</p>

	<?php if ( empty( $children ) ) : ?>
		<p class="description">
			<?php esc_html_e( 'No child pages found yet. Add lesson page titles below, then update this page to create them.', 'journey-homeschool-academy' ); ?>
		</p>
	<?php endif; ?>

	<div class="jha-course-lesson-manager">
		<div class="jha-course-add-pages">
			<label for="jha-course-new-child-title">
				<?php esc_html_e( 'Add new lesson page', 'journey-homeschool-academy' ); ?>
			</label>
			<div class="jha-course-add-pages-row">
				<div class="jha-course-add-pages-field">
					<label for="jha-course-new-child-title">
						<?php esc_html_e( 'Title', 'journey-homeschool-academy' ); ?>
					</label>
					<input
						type="text"
						id="jha-course-new-child-title"
						class="regular-text"
						placeholder="<?php esc_attr_e( 'Lesson or menu parent title', 'journey-homeschool-academy' ); ?>"
					>
				</div>
				<div class="jha-course-add-pages-field">
					<label for="jha-course-new-child-menu-label">
						<?php esc_html_e( 'Menu label', 'journey-homeschool-academy' ); ?>
					</label>
					<input
						type="text"
						id="jha-course-new-child-menu-label"
						class="regular-text"
						placeholder="<?php esc_attr_e( 'Optional sidebar label', 'journey-homeschool-academy' ); ?>"
					>
				</div>
			</div>
			<div class="jha-course-add-pages-actions">
				<label class="jha-course-add-page-toggle" for="jha-course-new-child-create-page">
					<input type="checkbox" id="jha-course-new-child-create-page" checked>
					<?php esc_html_e( 'Create page', 'journey-homeschool-academy' ); ?>
				</label>
				<button type="button" class="button" id="jha-course-add-child-page">
					<?php esc_html_e( 'Add', 'journey-homeschool-academy' ); ?>
				</button>
			</div>
			<p class="description">
				<?php esc_html_e( 'Leave Create page checked to create a real lesson page. Uncheck it to add a menu-only parent that can hold child pages but does not create its own page.', 'journey-homeschool-academy' ); ?>
			</p>
		</div>

		<div class="jha-course-existing-pages">
			<label class="screen-reader-text" for="jha-course-lesson-search">
				<?php esc_html_e( 'Search lessons', 'journey-homeschool-academy' ); ?>
			</label>
			<input
				type="search"
				id="jha-course-lesson-search"
				class="widefat"
				placeholder="<?php esc_attr_e( 'Search lessons by menu label or page title', 'journey-homeschool-academy' ); ?>"
				autocomplete="off"
			>
			<p id="jha-course-lesson-search-status" class="description" aria-live="polite"></p>
			<div class="jha-course-child-order-scroll">
				<ul id="jha-course-child-order-list" class="jha-course-child-order-list">
					<?php foreach ( $children as $child ) : ?>
						<?php jha_render_course_child_order_node( $child ); ?>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	</div>

	<?php if ( ! $screen || ! $screen->is_block_editor() ) : ?>
		<?php jha_render_course_lesson_hidden_fields( $post ); ?>
	<?php endif; ?>

	<style>
		.jha-course-lesson-manager {
			display: grid;
			grid-template-columns: minmax(220px, 1fr) minmax(320px, 2fr);
			gap: 24px;
			align-items: start;
		}

		<?php if ( ! $is_course_template ) : ?>
		#jha-course-settings {
			display: none;
		}
		<?php endif; ?>

		#jha-course-settings.closed .inside {
			display: block;
		}

		#jha-course-settings > .hndle,
		#jha-course-settings > .postbox-header {
			display: none;
		}

		.jha-course-child-order-list {
			margin: 0;
		}

		.jha-course-child-order-scroll {
			max-height: 520px;
			padding: 10px;
			overflow-y: auto;
			border: 1px solid #dcdcde;
			background: #f6f7f7;
		}

		.jha-course-child-order-item {
			margin: 0 0 8px;
		}

		.jha-course-child-order-item.is-search-hidden {
			display: none;
		}

		.jha-course-child-order-row {
			display: flex;
			align-items: center;
			gap: 8px;
			padding: 10px 12px;
			border: 1px solid #c3c4c7;
			background: #fff;
			cursor: move;
		}

		.jha-course-child-order-item.is-marked-for-trash > .jha-course-child-order-row {
			opacity: 0.55;
			background: #fcf0f1;
		}

		.jha-course-child-order-item.is-marked-for-trash > .jha-course-child-order-row .jha-course-child-order-title,
		.jha-course-child-order-item.is-marked-for-trash > .jha-course-child-order-row .jha-course-child-order-post-title,
		.jha-course-child-order-item.is-marked-for-trash > .jha-course-child-order-row .jha-course-child-order-hidden-label {
			text-decoration: line-through;
		}

		.jha-course-child-order-item.is-new-lesson > .jha-course-child-order-row {
			background: #f0f6fc;
			border-color: #72aee6;
		}

		.jha-course-child-order-item.is-hidden-from-menu > .jha-course-child-order-row {
			background: #f6f7f7;
		}

		.jha-course-child-order-sublist {
			min-height: 10px;
			margin: 8px 0 0 28px;
			padding-left: 12px;
			border-left: 2px solid #dcdcde;
		}

		.jha-course-child-order-placeholder {
			min-height: 38px;
			margin: 0 0 8px;
			border: 1px dashed #72aee6;
			background: #f0f6fc;
		}

		.jha-course-child-order-title {
			font-weight: 600;
		}

		.jha-course-child-order-post-title {
			margin-left: auto;
			color: #646970;
			font-size: 12px;
		}

		.jha-course-child-order-hidden-label {
			display: none;
			padding: 2px 6px;
			border-radius: 3px;
			background: #dcdcde;
			color: #50575e;
			font-size: 11px;
			font-weight: 600;
			text-transform: uppercase;
			white-space: nowrap;
		}

		.jha-course-child-order-item.is-hidden-from-menu > .jha-course-child-order-row .jha-course-child-order-hidden-label {
			display: inline-block;
		}

		.jha-course-child-action {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 28px;
			height: 28px;
			padding: 0;
			text-decoration: none !important;
			cursor: pointer;
		}

		.jha-course-child-action:hover,
		.jha-course-child-action:focus {
			text-decoration: none;
		}

		.jha-course-child-action .dashicons {
			width: 20px;
			height: 20px;
			font-size: 20px;
			line-height: 1;
		}

		.jha-course-child-delete {
			margin-left: 4px;
		}

		#jha-course-lesson-search {
			margin-bottom: 8px;
		}

		#jha-course-lesson-search-status {
			margin: 0 0 8px;
		}

		.jha-course-add-pages {
			padding: 14px;
			border: 1px solid #dcdcde;
			background: #f6f7f7;
		}

		.jha-course-add-pages label {
			display: block;
			margin-bottom: 6px;
			font-weight: 600;
		}

		.jha-course-add-pages-row {
			display: grid;
			gap: 12px;
		}

		.jha-course-add-pages-field {
			width: 100%;
		}

		.jha-course-add-pages-field label {
			font-size: 12px;
		}

		.jha-course-add-pages-field input {
			width: 100%;
		}

		.jha-course-add-page-toggle {
			display: inline-flex;
			gap: 6px;
			align-items: center;
			margin: 0;
			white-space: nowrap;
		}

		.jha-course-add-pages-actions {
			display: flex;
			gap: 10px;
			align-items: center;
			justify-content: space-between;
			margin-top: 12px;
		}

		@media (max-width: 960px) {
			.jha-course-lesson-manager {
				grid-template-columns: 1fr;
			}
		}
	</style>
	<?php
}

/**
 * Save nested lesson tree rows as page hierarchy.
 *
 * Existing pages are moved to the submitted parent and order. New temporary
 * rows are created as pages at the same point in the tree, then their children
 * are processed beneath the newly created page.
 *
 * @param array  $nodes Lesson tree nodes.
 * @param int    $parent_id Parent page ID for this tree level.
 * @param int    $course_root_id Root course page for validation.
 * @param array  $new_pages Map of temporary token => page data.
 * @param string $template_slug Page template to copy to new pages.
 * @return array<int, array<string, mixed>> Saved tree nodes.
 */
function jha_save_course_lesson_tree( $nodes, $parent_id, $course_root_id, $new_pages, $template_slug ) {
	$saved_nodes = array();

	foreach ( $nodes as $index => $node ) {
		if ( ! is_array( $node ) || ! isset( $node['token'] ) || '' === (string) $node['token'] ) {
			continue;
		}

		$raw_token = (string) $node['token'];
		$token     = sanitize_key( $raw_token );
		$children  = isset( $node['children'] ) && is_array( $node['children'] ) ? $node['children'] : array();
		$page_id   = 0;

		if ( ( isset( $node['type'] ) && 'menu' === sanitize_key( (string) $node['type'] ) ) || 0 === strpos( $token, 'menu-' ) ) {
			$title = isset( $node['title'] ) ? sanitize_text_field( (string) $node['title'] ) : '';

			if ( '' === $title ) {
				continue;
			}

			$saved_nodes[] = array(
				'type'     => 'menu',
				'token'    => $token,
				'title'    => $title,
				'children' => jha_save_course_lesson_tree( $children, $parent_id, $course_root_id, $new_pages, $template_slug ),
			);
			continue;
		}

		if ( isset( $new_pages[ $token ] ) || isset( $new_pages[ $raw_token ] ) ) {
			$new_page_data = $new_pages[ $token ] ?? $new_pages[ $raw_token ];
			$new_title     = is_array( $new_page_data ) && isset( $new_page_data['title'] )
				? $new_page_data['title']
				: (string) $new_page_data;
			$new_label     = is_array( $new_page_data ) && isset( $new_page_data['menuLabel'] )
				? $new_page_data['menuLabel']
				: '';
			$page_id = wp_insert_post(
				array(
					'post_title'   => $new_title,
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_parent'  => absint( $parent_id ),
					'post_content' => '',
					'menu_order'   => $index,
				),
				true
			);

			if ( is_wp_error( $page_id ) ) {
				continue;
			}

			if ( ! empty( $template_slug ) && 'default' !== $template_slug ) {
				update_post_meta( $page_id, '_wp_page_template', $template_slug );
			}

			if ( '' !== $new_label ) {
				update_post_meta( $page_id, '_jha_course_menu_label', $new_label );
			}

			jha_sync_new_lesson_to_accessally_offering( $page_id, $parent_id, $course_root_id, $new_title );
		} else {
			$page_id = absint( $raw_token );

			if ( ! $page_id ) {
				continue;
			}

			if ( ! jha_course_page_is_in_course_tree( $page_id, $course_root_id ) ) {
				continue;
			}

			wp_update_post(
				array(
					'ID'          => $page_id,
					'post_parent' => absint( $parent_id ),
					'menu_order'  => $index,
				)
			);
		}

		$saved_nodes[] = array(
			'token'    => (string) $page_id,
			'children' => jha_save_course_lesson_tree( $children, $page_id, $course_root_id, $new_pages, $template_slug ),
		);
	}

	return $saved_nodes;
}

/**
 * Find the AccessAlly offering settings for a course root page.
 *
 * @param int $course_root_id Root course page ID.
 * @return array{key:string,settings:array<string,mixed>}|false
 */
function jha_get_accessally_offering_for_course_root( $course_root_id ) {
	$course_root_id = absint( $course_root_id );

	if ( ! $course_root_id || ! class_exists( 'AccessAllyOfferings' ) ) {
		return false;
	}

	$course_key = get_post_meta( $course_root_id, '_accessally_course_key', true );

	if ( is_string( $course_key ) && 0 === strpos( $course_key, '_accessally_offering_' ) ) {
		$offering_settings = AccessAllyOfferings::get_offering_settings( $course_key );

		if ( is_array( $offering_settings ) ) {
			return array(
				'key'      => $course_key,
				'settings' => $offering_settings,
			);
		}
	}

	foreach ( AccessAllyOfferings::get_all_offering_settings() as $offering_key => $offering_settings ) {
		if ( 0 !== strpos( (string) $offering_key, '_accessally_offering_' ) || empty( $offering_settings['pages'] ) || ! is_array( $offering_settings['pages'] ) ) {
			continue;
		}

		foreach ( $offering_settings['pages'] as $page_settings ) {
			if ( ! is_array( $page_settings ) || empty( $page_settings['page-template-select'] ) ) {
				continue;
			}

			if ( absint( $page_settings['page-template-select'] ) === $course_root_id ) {
				return array(
					'key'      => (string) $offering_key,
					'settings' => $offering_settings,
				);
			}
		}
	}

	return false;
}

/**
 * Get the AccessAlly module ID that should contain a synced lesson page.
 *
 * @param array<string,mixed> $offering_settings AccessAlly offering settings.
 * @param int                 $parent_id Parent WordPress page ID.
 * @return string
 */
function jha_get_accessally_module_for_course_lesson( $offering_settings, $parent_id ) {
	$parent_id = absint( $parent_id );

	if ( $parent_id && ! empty( $offering_settings['pages'] ) && is_array( $offering_settings['pages'] ) ) {
		foreach ( $offering_settings['pages'] as $page_settings ) {
			if ( ! is_array( $page_settings ) || empty( $page_settings['page-template-select'] ) ) {
				continue;
			}

			if ( absint( $page_settings['page-template-select'] ) === $parent_id && isset( $page_settings['module'] ) ) {
				return (string) $page_settings['module'];
			}
		}
	}

	return '0';
}

/**
 * Add a newly-created lesson page to the matching AccessAlly Offering page list.
 *
 * @param int    $page_id New lesson page ID.
 * @param int    $parent_id Parent WordPress page ID.
 * @param int    $course_root_id Root course page ID.
 * @param string $title Lesson title.
 * @return void
 */
function jha_sync_new_lesson_to_accessally_offering( $page_id, $parent_id, $course_root_id, $title ) {
	$page_id = absint( $page_id );

	if (
		! $page_id ||
		! class_exists( 'AccessAllyOfferings' ) ||
		! class_exists( 'AccessAllyUtilities' )
	) {
		return;
	}

	$offering = jha_get_accessally_offering_for_course_root( $course_root_id );

	if ( ! $offering || empty( $offering['key'] ) || empty( $offering['settings'] ) || ! is_array( $offering['settings'] ) ) {
		return;
	}

	$offering_key      = $offering['key'];
	$offering_settings = $offering['settings'];
	$page_ids          = array();

	if ( ! empty( $offering_settings['pages'] ) && is_array( $offering_settings['pages'] ) ) {
		foreach ( $offering_settings['pages'] as $page_settings ) {
			if ( is_array( $page_settings ) && ! empty( $page_settings['page-template-select'] ) ) {
				$page_ids[] = absint( $page_settings['page-template-select'] );
			}
		}
	}

	if ( in_array( $page_id, $page_ids, true ) ) {
		return;
	}

	$page_ordinal = 0;

	if ( ! empty( $offering_settings['pages'] ) && is_array( $offering_settings['pages'] ) ) {
		$page_ordinal = max( array_map( 'absint', array_keys( $offering_settings['pages'] ) ) ) + 1;
	}

	$offering_settings['pages'][ $page_ordinal ] = array(
		'type'                       => 'page',
		'name'                       => sanitize_text_field( $title ),
		'page-template-select'       => (string) $page_id,
		'select-type'                => 'existing',
		'status'                     => 'success',
		'module'                     => jha_get_accessally_module_for_course_lesson( $offering_settings, $parent_id ),
		'template'                   => '',
		'communityally-group-id'     => '',
		'checked-progress-release'   => 'no',
		'checked-automatic-shortcode' => 'no',
	);

	if ( empty( $offering_settings['page-order'] ) || ! is_array( $offering_settings['page-order'] ) ) {
		$offering_settings['page-order'] = array_keys( $offering_settings['pages'] );
	}

	$offering_settings['page-order'][] = (string) $page_ordinal;
	$offering_settings['page-order']   = array_values( array_unique( $offering_settings['page-order'] ) );

	AccessAllyUtilities::safe_set_settings( $offering_key, $offering_settings, array(), true, false );
	update_post_meta( $page_id, '_accessally_course_key', $offering_key );

	if ( class_exists( 'AccessAllyWizardShared' ) ) {
		AccessAllyWizardShared::assign_page_course_navigation_entries( $offering_settings, $offering_settings['pages'], true );
	}

	do_action( 'accessally_offering_updated', $offering_key, $offering_settings, $offering_settings );
}

/**
 * Save course menu label and direct child page order.
 *
 * @param int $post_id Current page ID.
 * @return void
 */
function jha_save_course_page_settings( $post_id ) {
	static $processed_lesson_trees = array();

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_page', $post_id ) ) {
		return;
	}

	$edited_post_id = 0;

	if ( '' !== jha_get_posted_string( 'post_ID' ) ) {
		$edited_post_id = absint( jha_get_posted_string( 'post_ID' ) );
	} elseif ( '' !== jha_get_posted_string( 'post_id' ) ) {
		$edited_post_id = absint( jha_get_posted_string( 'post_id' ) );
	}

	$is_primary_editor_save = ! $edited_post_id || $edited_post_id === absint( $post_id );
	$settings_nonce         = jha_get_posted_string( 'jha_course_page_settings_nonce' );
	$has_settings_nonce     = '' !== $settings_nonce && wp_verify_nonce(
		sanitize_text_field( $settings_nonce ),
		'jha_save_course_page_settings'
	);

	if ( $has_settings_nonce ) {
		// Menu settings only save from the classic/meta box POST request.
	} elseif ( ! $is_primary_editor_save || ! get_post_meta( $post_id, '_jha_course_lesson_tree', true ) ) {
		return;
	}

	if ( $has_settings_nonce && jha_has_posted_string( 'jha_course_menu_label' ) ) {
		$menu_label = sanitize_text_field( jha_get_posted_string( 'jha_course_menu_label' ) );

		if ( '' === $menu_label ) {
			delete_post_meta( $post_id, '_jha_course_menu_label' );
		} else {
			update_post_meta( $post_id, '_jha_course_menu_label', $menu_label );
		}
	}

	if ( $has_settings_nonce && jha_has_posted_string( 'jha_course_show_title' ) ) {
		if ( '1' === jha_get_posted_string( 'jha_course_show_title' ) ) {
			delete_post_meta( $post_id, '_jha_course_hide_title' );
		} else {
			update_post_meta( $post_id, '_jha_course_hide_title', '1' );
		}
	}

	if ( $has_settings_nonce && jha_has_posted_string( 'jha_course_block_gap' ) ) {
		$block_gap = sanitize_text_field( jha_get_posted_string( 'jha_course_block_gap' ) );

		if ( '' === $block_gap ) {
			delete_post_meta( $post_id, '_jha_course_block_gap' );
		} elseif ( is_numeric( $block_gap ) ) {
			$block_gap_value = max( 0, min( 5, round( (float) $block_gap * 2 ) / 2 ) );
			$block_gap_rem   = rtrim( rtrim( (string) $block_gap_value, '0' ), '.' ) . 'rem';

			update_post_meta( $post_id, '_jha_course_block_gap', $block_gap_rem );
		}
	}

	if ( $has_settings_nonce && jha_has_posted_string( 'jha_course_show_progress' ) ) {
		if ( '1' === jha_get_posted_string( 'jha_course_show_progress' ) ) {
			update_post_meta( $post_id, '_jha_course_show_progress', '1' );
		} else {
			update_post_meta( $post_id, '_jha_course_show_progress', '0' );
		}
	}

	if ( ! $is_primary_editor_save ) {
		return;
	}

	if ( isset( $processed_lesson_trees[ $post_id ] ) ) {
		return;
	}

	$template_slug   = get_page_template_slug( $post_id );
	$course_root_id  = jha_get_course_parent_id( $post_id );
	$new_pages       = array();
	$lesson_tree     = array();
	$hidden_ids      = array();
	$trash_raw       = '';
	$hidden_raw      = '';
	$new_pages_raw   = '';
	$lesson_tree_raw = '';

	if ( '' !== jha_get_posted_string( 'jha_course_new_child_pages' ) ) {
		$new_pages_raw = jha_get_posted_string( 'jha_course_new_child_pages' );
	} else {
		$new_pages_raw = (string) get_post_meta( $post_id, '_jha_course_new_child_pages', true );
	}

	if ( '' !== jha_get_posted_string( 'jha_course_child_order' ) ) {
		$lesson_tree_raw = jha_get_posted_string( 'jha_course_child_order' );
	} else {
		$lesson_tree_raw = (string) get_post_meta( $post_id, '_jha_course_lesson_tree', true );
	}

	if ( '' !== jha_get_posted_string( 'jha_course_child_trash' ) ) {
		$trash_raw = sanitize_text_field( jha_get_posted_string( 'jha_course_child_trash' ) );
	} else {
		$trash_raw = sanitize_text_field( (string) get_post_meta( $post_id, '_jha_course_child_trash', true ) );
	}

	if ( '' !== jha_get_posted_string( 'jha_course_hidden_lessons' ) ) {
		$hidden_raw = sanitize_text_field( jha_get_posted_string( 'jha_course_hidden_lessons' ) );
	} else {
		$hidden_raw = sanitize_text_field( (string) get_post_meta( $post_id, '_jha_course_hidden_lessons', true ) );
	}

	if ( ! $has_settings_nonce && '' === $lesson_tree_raw && '' === $new_pages_raw && '' === $trash_raw && '' === $hidden_raw ) {
		return;
	}

	if ( '' !== $new_pages_raw ) {
		$decoded_new_pages = json_decode( $new_pages_raw, true );

		if ( is_array( $decoded_new_pages ) ) {
			foreach ( $decoded_new_pages as $token => $page_data ) {
				$clean_token = sanitize_key( $token );

				if ( is_array( $page_data ) ) {
					$title      = isset( $page_data['title'] ) ? sanitize_text_field( (string) $page_data['title'] ) : '';
					$menu_label = isset( $page_data['menuLabel'] ) ? sanitize_text_field( (string) $page_data['menuLabel'] ) : '';
				} else {
					$title      = sanitize_text_field( (string) $page_data );
					$menu_label = '';
				}

				if ( '' !== $clean_token && '' !== $title ) {
					$new_pages[ $clean_token ] = array(
						'title'     => $title,
						'menuLabel' => $menu_label,
					);
				}
			}
		}
	}

	if ( '' !== $trash_raw ) {
		$trash_ids = array_filter( array_map( 'absint', explode( ',', $trash_raw ) ) );

		remove_action( 'save_post_page', 'jha_save_course_page_settings' );

		foreach ( $trash_ids as $trash_id ) {
			if ( ! jha_course_page_is_in_course_tree( $trash_id, $course_root_id ) ) {
				continue;
			}

			wp_trash_post( $trash_id );
		}

		add_action( 'save_post_page', 'jha_save_course_page_settings' );
	}

	if ( '' !== $hidden_raw ) {
		$hidden_ids = array_filter( array_map( 'absint', explode( ',', $hidden_raw ) ) );
	}

	if ( '' !== $lesson_tree_raw ) {
		$decoded_tree = json_decode( $lesson_tree_raw, true );

		if ( is_array( $decoded_tree ) ) {
			$lesson_tree = jha_sanitize_course_lesson_tree_nodes( $decoded_tree );
		}
	}

	if ( empty( $lesson_tree ) && ! empty( $new_pages ) ) {
		foreach ( array_keys( $new_pages ) as $token ) {
			$lesson_tree[] = array(
				'token'    => $token,
				'children' => array(),
			);
		}
	}

	if ( empty( $lesson_tree ) && empty( $new_pages ) && '' === $trash_raw && '' === $hidden_raw ) {
		return;
	}

	if ( empty( $lesson_tree ) && empty( $new_pages ) ) {
		delete_post_meta( $post_id, '_jha_course_lesson_tree' );
		delete_post_meta( $post_id, '_jha_course_new_child_pages' );
		delete_post_meta( $post_id, '_jha_course_child_trash' );
		return;
	}

	$processed_lesson_trees[ $post_id ] = true;

	remove_action( 'save_post_page', 'jha_save_course_page_settings' );

	$saved_tree = ! empty( $lesson_tree )
		? jha_save_course_lesson_tree( $lesson_tree, $post_id, $course_root_id, $new_pages, $template_slug )
		: array();

	update_post_meta( $post_id, '_jha_course_menu_tree', wp_json_encode( $saved_tree ) );

	foreach ( jha_get_course_navigation_pages( $post_id, true, false ) as $course_page ) {
		if ( absint( $course_page->ID ) === absint( $post_id ) ) {
			continue;
		}

		if ( in_array( absint( $course_page->ID ), $hidden_ids, true ) ) {
			update_post_meta( $course_page->ID, '_jha_course_hide_from_menu', '1' );
		} else {
			delete_post_meta( $course_page->ID, '_jha_course_hide_from_menu' );
		}
	}

	delete_post_meta( $post_id, '_jha_course_lesson_tree' );
	delete_post_meta( $post_id, '_jha_course_new_child_pages' );
	delete_post_meta( $post_id, '_jha_course_child_trash' );
	delete_post_meta( $post_id, '_jha_course_hidden_lessons' );

	add_action( 'save_post_page', 'jha_save_course_page_settings' );
}
add_action( 'save_post_page', 'jha_save_course_page_settings' );

/**
 * Add a quick menu-label override action to Course Template pages in the Pages list.
 *
 * @param array<string, string> $actions Existing row actions.
 * @param WP_Post              $post Current row post.
 * @return array<string, string>
 */
function jha_add_course_menu_label_row_action( $actions, $post ) {
	if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
		return $actions;
	}

	if ( 'page-templates/course-template.php' !== get_page_template_slug( $post ) ) {
		return $actions;
	}

	if ( ! current_user_can( 'edit_page', $post->ID ) ) {
		return $actions;
	}

	$menu_label = get_post_meta( $post->ID, '_jha_course_menu_label', true );

	$actions['jha_course_menu_label'] = sprintf(
		'<button type="button" class="button-link jha-course-list-label-override" data-page-id="%1$d" data-current-label="%2$s" data-default-label="%3$s" aria-label="%4$s" title="%4$s"><span class="dashicons dashicons-edit-page" aria-hidden="true"></span><span class="screen-reader-text">%4$s</span></button>',
		absint( $post->ID ),
		esc_attr( is_string( $menu_label ) ? $menu_label : '' ),
		esc_attr( get_the_title( $post ) ),
		esc_attr__( 'Edit course menu label', 'journey-homeschool-academy' )
	);

	return $actions;
}
add_filter( 'page_row_actions', 'jha_add_course_menu_label_row_action', 10, 2 );

/**
 * Save a course menu-label override from the Pages list quick action.
 *
 * @return void
 */
function jha_ajax_update_course_menu_label() {
	check_ajax_referer( 'jha_course_admin', 'nonce' );

	$post_id = isset( $_POST['postId'] ) ? absint( $_POST['postId'] ) : 0;

	if ( ! $post_id || ! current_user_can( 'edit_page', $post_id ) ) {
		wp_send_json_error(
			array( 'message' => __( 'You do not have permission to edit this page.', 'journey-homeschool-academy' ) ),
			403
		);
	}

	$label = isset( $_POST['label'] ) && is_string( $_POST['label'] )
		? sanitize_text_field( wp_unslash( $_POST['label'] ) )
		: '';

	if ( '' === $label ) {
		delete_post_meta( $post_id, '_jha_course_menu_label' );
	} else {
		update_post_meta( $post_id, '_jha_course_menu_label', $label );
	}

	wp_send_json_success(
		array(
			'label'      => jha_get_course_menu_label( $post_id ),
			'rawLabel'   => $label,
			'default'    => get_the_title( $post_id ),
			'message'    => __( 'Course menu label updated.', 'journey-homeschool-academy' ),
		)
	);
}
add_action( 'wp_ajax_jha_update_course_menu_label', 'jha_ajax_update_course_menu_label' );

/**
 * Load admin sorting behavior only on the page editor.
 *
 * @param string $hook_suffix Current admin screen.
 * @return void
 */
function jha_enqueue_course_admin_assets( $hook_suffix = '' ) {
	$screen = get_current_screen();

	if ( ! $screen || 'page' !== $screen->post_type ) {
		return;
	}

	if ( 'post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix && 'edit.php' !== $hook_suffix && 'page' !== $screen->base ) {
		return;
	}

	wp_enqueue_script( 'jquery-ui-sortable' );
	wp_enqueue_script(
		'jha-course-admin',
		get_stylesheet_directory_uri() . '/assets/js/course-admin.js',
		array( 'jquery', 'jquery-ui-sortable', 'wp-hooks', 'wp-data' ),
		wp_get_theme()->get( 'Version' ),
		true
	);
	wp_localize_script(
		'jha-course-admin',
		'jhaCourseAdmin',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'jha_course_admin' ),
			'i18n'    => array(
				'menuLabelPrompt' => __( 'Enter the course menu label override. Leave blank to use the page title.', 'journey-homeschool-academy' ),
				'saveFailed'      => __( 'The course menu label could not be saved.', 'journey-homeschool-academy' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'jha_enqueue_course_admin_assets' );
add_action( 'enqueue_block_editor_assets', 'jha_enqueue_course_admin_assets' );
