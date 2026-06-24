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

		return current_user_can( 'edit_page', jha_normalize_post_id( $object_id ) );
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
		return jha_page_is_in_course_template_system( $course_root_id );
	}

	$page = get_post( $page_id );

	if ( ! $page || 'page' !== $page->post_type ) {
		return false;
	}

	if ( ! jha_page_is_in_course_template_system( $course_root_id ) ) {
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
	$post_id        = jha_normalize_post_id( $post_id );
	$course_root_id = jha_get_course_parent_id( $post_id );
	$stored_tree    = jha_get_raw_course_menu_tree_nodes( $course_root_id );

	if ( empty( $stored_tree ) && $post_id !== $course_root_id ) {
		$stored_tree = jha_get_raw_course_menu_tree_nodes( $post_id );
	}

	if ( ! empty( $stored_tree ) ) {
		if ( $post_id === $course_root_id ) {
			return $stored_tree;
		}

		$branch = jha_get_course_menu_tree_branch_nodes( $stored_tree, $post_id );

		if ( ! empty( $branch ) ) {
			return $branch;
		}
	}

	return jha_build_course_lesson_tree( $post_id );
}

/**
 * Decode the raw menu tree stored on a page.
 *
 * @param int $post_id Page ID.
 * @return array<int, array<string, mixed>>
 */
function jha_get_raw_course_menu_tree_nodes( $post_id ) {
	$post_id     = jha_normalize_post_id( $post_id );
	$stored_tree = get_post_meta( $post_id, '_jha_course_menu_tree', true );
	$decoded     = is_string( $stored_tree ) && '' !== $stored_tree ? json_decode( $stored_tree, true ) : null;

	if ( ! is_array( $decoded ) ) {
		return array();
	}

	return jha_sanitize_course_lesson_tree_nodes( $decoded );
}

/**
 * Get the saved child branch for one page inside a menu tree.
 *
 * @param array $nodes   Menu tree nodes.
 * @param int   $page_id Page ID.
 * @return array<int, array<string, mixed>>
 */
function jha_get_course_menu_tree_branch_nodes( $nodes, $page_id ) {
	$page_id = jha_normalize_post_id( $page_id );

	if ( ! $page_id || ! is_array( $nodes ) ) {
		return array();
	}

	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) || empty( $node['token'] ) ) {
			continue;
		}

		$token = (string) $node['token'];
		$type  = isset( $node['type'] ) ? sanitize_key( (string) $node['type'] ) : '';

		if ( 'menu' !== $type && 0 !== strpos( sanitize_key( $token ), 'menu-' ) && absint( $token ) === $page_id ) {
			return isset( $node['children'] ) && is_array( $node['children'] )
				? jha_sanitize_course_lesson_tree_nodes( $node['children'] )
				: array();
		}

		if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
			$branch = jha_get_course_menu_tree_branch_nodes( $node['children'], $page_id );

			if ( ! empty( $branch ) ) {
				return $branch;
			}
		}
	}

	return array();
}

/**
 * Replace one page's child branch inside a saved menu tree.
 *
 * @param array    $nodes            Menu tree nodes.
 * @param int      $branch_parent_id Page whose children should be replaced.
 * @param array    $new_branch_nodes Saved child nodes.
 * @param bool|null $found           Whether the branch parent was found.
 * @return array<int, array<string, mixed>>
 */
function jha_replace_course_menu_tree_branch( $nodes, $branch_parent_id, $new_branch_nodes, &$found = false ) {
	$branch_parent_id = jha_normalize_post_id( $branch_parent_id );
	$updated          = array();

	if ( ! is_array( $nodes ) ) {
		return array();
	}

	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) || empty( $node['token'] ) ) {
			continue;
		}

		$token    = (string) $node['token'];
		$type     = isset( $node['type'] ) ? sanitize_key( (string) $node['type'] ) : '';
		$children = isset( $node['children'] ) && is_array( $node['children'] ) ? $node['children'] : array();

		if ( 'menu' !== $type && 0 !== strpos( sanitize_key( $token ), 'menu-' ) && absint( $token ) === $branch_parent_id ) {
			$node['children'] = $new_branch_nodes;
			$found            = true;
		} elseif ( ! empty( $children ) ) {
			$node['children'] = jha_replace_course_menu_tree_branch( $children, $branch_parent_id, $new_branch_nodes, $found );
		}

		$updated[] = $node;
	}

	return $updated;
}

/**
 * Insert a page branch into a menu tree using the WordPress parent hierarchy.
 *
 * @param array $nodes            Existing menu tree nodes.
 * @param int   $branch_parent_id Page whose children should be stored.
 * @param array $new_branch_nodes Saved child nodes.
 * @param int   $course_root_id   Course root page ID.
 * @return array<int, array<string, mixed>>
 */
function jha_insert_course_menu_tree_branch( $nodes, $branch_parent_id, $new_branch_nodes, $course_root_id ) {
	$branch_parent_id = jha_normalize_post_id( $branch_parent_id );
	$course_root_id   = jha_normalize_post_id( $course_root_id );
	$nodes            = is_array( $nodes ) ? $nodes : array();

	if ( ! $branch_parent_id || ! $course_root_id ) {
		return $nodes;
	}

	if ( $branch_parent_id === $course_root_id ) {
		return $new_branch_nodes;
	}

	$wp_parent_id = wp_get_post_parent_id( $branch_parent_id );

	if ( ! $wp_parent_id ) {
		return $nodes;
	}

	$parent_children = jha_get_course_menu_tree_branch_nodes( $nodes, $wp_parent_id );
	$has_branch      = false;

	foreach ( $parent_children as $index => $child_node ) {
		if ( ! is_array( $child_node ) || empty( $child_node['token'] ) ) {
			continue;
		}

		if ( absint( $child_node['token'] ) === $branch_parent_id ) {
			$parent_children[ $index ]['children'] = $new_branch_nodes;
			$has_branch                            = true;
			break;
		}
	}

	if ( ! $has_branch ) {
		$parent_children[] = array(
			'token'    => (string) $branch_parent_id,
			'children' => $new_branch_nodes,
		);
	}

	$found   = false;
	$updated = jha_replace_course_menu_tree_branch( $nodes, $wp_parent_id, $parent_children, $found );

	if ( $found ) {
		return $updated;
	}

	return jha_insert_course_menu_tree_branch( $nodes, $wp_parent_id, $parent_children, $course_root_id );
}

/**
 * Remove duplicate lesson nodes from a tree by token.
 *
 * @param array $nodes Lesson tree nodes.
 * @return array<int, array<string, mixed>>
 */
function jha_deduplicate_course_lesson_tree_nodes( $nodes ) {
	$deduped = array();
	$seen    = array();

	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) || ! isset( $node['token'] ) ) {
			continue;
		}

		$token = sanitize_key( (string) $node['token'] );

		if ( '' === $token || isset( $seen[ $token ] ) ) {
			continue;
		}

		$seen[ $token ] = true;

		if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
			$node['children'] = jha_deduplicate_course_lesson_tree_nodes( $node['children'] );
		}

		$deduped[] = $node;
	}

	return $deduped;
}

/**
 * Collect lesson-tree tokens recursively.
 *
 * @param array $nodes Lesson tree nodes.
 * @return array<string, bool>
 */
function jha_get_course_lesson_tree_tokens( $nodes ) {
	$tokens = array();

	if ( ! is_array( $nodes ) ) {
		return $tokens;
	}

	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) || ! isset( $node['token'] ) ) {
			continue;
		}

		$token = sanitize_key( (string) $node['token'] );

		if ( '' !== $token ) {
			$tokens[ $token ] = true;
		}

		if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
			$tokens = array_merge( $tokens, jha_get_course_lesson_tree_tokens( $node['children'] ) );
		}
	}

	return $tokens;
}

/**
 * Find an existing child page under a parent by title.
 *
 * Used to avoid creating a second page when AccessAlly or a prior save already
 * created the lesson under the same parent.
 *
 * @param int    $parent_id Parent page ID.
 * @param string $title     Lesson page title.
 * @return int Existing page ID, or 0 when not found.
 */
function jha_find_existing_course_child_page_by_title( $parent_id, $title ) {
	$parent_id = absint( $parent_id );
	$title     = sanitize_text_field( (string) $title );

	if ( ! $parent_id || '' === $title ) {
		return 0;
	}

	foreach ( jha_get_course_child_pages( $parent_id, true ) as $child ) {
		if ( sanitize_text_field( (string) $child->post_title ) === $title ) {
			return absint( $child->ID );
		}
	}

	return 0;
}

/**
 * Ensure every pending new-page token appears in the lesson tree.
 *
 * @param array $lesson_tree Saved lesson tree nodes.
 * @param array $new_pages   Pending new page definitions keyed by token.
 * @return array<int, array<string, mixed>>
 */
function jha_merge_new_pages_into_lesson_tree( $lesson_tree, $new_pages ) {
	if ( empty( $new_pages ) ) {
		return is_array( $lesson_tree ) ? $lesson_tree : array();
	}

	$lesson_tree = is_array( $lesson_tree ) ? $lesson_tree : array();
	$tokens      = jha_get_course_lesson_tree_tokens( $lesson_tree );

	foreach ( array_keys( $new_pages ) as $token ) {
		$clean_token = sanitize_key( (string) $token );

		if ( '' === $clean_token || isset( $tokens[ $clean_token ] ) ) {
			continue;
		}

		$lesson_tree[] = array(
			'token'    => $clean_token,
			'children' => array(),
		);
		$tokens[ $clean_token ] = true;
	}

	return $lesson_tree;
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
	$children                  = jha_get_course_admin_lesson_tree( $post->ID );
	$screen                    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$show_course_settings = jha_page_is_in_course_template_system( $post->ID );
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

		<?php if ( ! $show_course_settings ) : ?>
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
 * Post IDs whose AccessAlly/ProgressAlly save_post handlers should be skipped.
 *
 * @return array<int, bool>
 */
function &jha_ally_suppressed_save_post_ids() {
	static $ids = array();

	return $ids;
}

/**
 * Skip ally save_post handlers for one programmatic lesson update.
 *
 * @param mixed $post_id Post ID.
 * @return void
 */
function jha_suppress_ally_save_for_post( $post_id ) {
	$post_id = jha_normalize_post_id( $post_id );

	if ( $post_id ) {
		jha_ally_suppressed_save_post_ids()[ $post_id ] = true;
	}
}

/**
 * Restore ally save_post handlers for one post ID.
 *
 * @param mixed $post_id Post ID.
 * @return void
 */
function jha_unsuppress_ally_save_for_post( $post_id ) {
	$post_id = jha_normalize_post_id( $post_id );

	if ( $post_id ) {
		unset( jha_ally_suppressed_save_post_ids()[ $post_id ] );
	}
}

/**
 * Whether ally save_post handlers are suppressed for a post ID.
 *
 * @param mixed $post_id Post ID.
 * @return bool
 */
function jha_is_ally_save_suppressed_for_post( $post_id ) {
	$post_id = jha_normalize_post_id( $post_id );

	return $post_id && isset( jha_ally_suppressed_save_post_ids()[ $post_id ] );
}

/**
 * Install guarded AccessAlly/ProgressAlly save_post handlers.
 *
 * Programmatic lesson updates suppress ally saves per child post ID only,
 * so the page being edited in the block editor can still save ProgressAlly tags.
 *
 * @return void
 */
function jha_install_ally_save_post_guards() {
	static $installed = false;

	if ( $installed ) {
		return;
	}

	$installed = true;

	if ( class_exists( 'ProgressAllyTaskDefinition' ) ) {
		remove_action( 'save_post', array( 'ProgressAllyTaskDefinition', 'save_postdata' ) );
		add_action( 'save_post', 'jha_guarded_progressally_save_postdata', 10, 1 );
	}

	if ( class_exists( 'AccessAlly' ) ) {
		remove_action( 'save_post', array( 'AccessAlly', 'save_post_permission_meta' ) );
		add_action( 'save_post', 'jha_guarded_accessally_save_post_permission_meta', 10, 1 );
	}
}
add_action( 'init', 'jha_install_ally_save_post_guards', 20 );

/**
 * Guarded ProgressAlly save handler.
 *
 * @param int $post_id Post ID.
 * @return mixed
 */
function jha_guarded_progressally_save_postdata( $post_id ) {
	$post_id = jha_normalize_post_id( $post_id );
	$ids     = &jha_ally_suppressed_save_post_ids();

	if ( isset( $ids['__next_insert__'] ) ) {
		unset( $ids['__next_insert__'] );

		if ( $post_id ) {
			$ids[ $post_id ] = true;
		}

		return $post_id;
	}

	if ( jha_is_ally_save_suppressed_for_post( $post_id ) ) {
		return $post_id;
	}

	return ProgressAllyTaskDefinition::save_postdata( $post_id );
}

/**
 * Guarded AccessAlly permission save handler.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function jha_guarded_accessally_save_post_permission_meta( $post_id ) {
	$post_id = jha_normalize_post_id( $post_id );
	$ids     = &jha_ally_suppressed_save_post_ids();

	if ( isset( $ids['__next_insert__'] ) ) {
		unset( $ids['__next_insert__'] );

		if ( $post_id ) {
			$ids[ $post_id ] = true;
		}

		return;
	}

	if ( jha_is_ally_save_suppressed_for_post( $post_id ) ) {
		return;
	}

	AccessAlly::save_post_permission_meta( $post_id );
}

/**
 * Pause AccessAlly/ProgressAlly save_post handlers during programmatic lesson updates
 * inside the JHA course template system.
 *
 * Parent page saves include ally metabox POST data. wp_insert_post/wp_update_post on
 * child lessons during the same request would otherwise copy the parent's permission
 * tags and ProgressAlly settings onto every child page.
 *
 * @param int $post_id Post ID being updated programmatically.
 * @return void
 */
function jha_pause_ally_lesson_save_hooks( $post_id = 0 ) {
	jha_suppress_ally_save_for_post( $post_id );
}

/**
 * Resume AccessAlly/ProgressAlly save_post handlers paused by jha_pause_ally_lesson_save_hooks().
 *
 * @param int $post_id Post ID that was updated programmatically.
 * @return void
 */
function jha_resume_ally_lesson_save_hooks( $post_id = 0 ) {
	jha_unsuppress_ally_save_for_post( $post_id );
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
	static $created_tokens = array();

	$saved_nodes = array();

	if ( function_exists( 'jha_lesson_meta_debug_log' ) ) {
		jha_lesson_meta_debug_log(
			'jha_save_course_lesson_tree_start',
			array(
				'parent_id'      => absint( $parent_id ),
				'course_root_id' => absint( $course_root_id ),
				'node_count'     => is_array( $nodes ) ? count( $nodes ) : 0,
				'template_slug'  => (string) $template_slug,
			)
		);
	}

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

		if ( isset( $created_tokens[ $token ] ) || isset( $created_tokens[ $raw_token ] ) ) {
			$page_id = $created_tokens[ $token ] ?? $created_tokens[ $raw_token ];
		} elseif ( isset( $new_pages[ $token ] ) || isset( $new_pages[ $raw_token ] ) ) {
			$new_page_data = $new_pages[ $token ] ?? $new_pages[ $raw_token ];
			$new_title     = is_array( $new_page_data ) && isset( $new_page_data['title'] )
				? $new_page_data['title']
				: (string) $new_page_data;
			$new_label     = is_array( $new_page_data ) && isset( $new_page_data['menuLabel'] )
				? $new_page_data['menuLabel']
				: '';
			$page_id       = jha_find_existing_course_child_page_by_title( $parent_id, $new_title );

			if ( $page_id ) {
				jha_pause_ally_lesson_save_hooks( $page_id );
				wp_update_post(
					array(
						'ID'          => $page_id,
						'post_parent' => absint( $parent_id ),
						'menu_order'  => $index,
					)
				);
				jha_resume_ally_lesson_save_hooks( $page_id );
			} else {
				jha_ally_suppressed_save_post_ids()['__next_insert__'] = true;
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
					unset( jha_ally_suppressed_save_post_ids()['__next_insert__'] );
				} else {
					jha_unsuppress_ally_save_for_post( $page_id );
				}

				if ( is_wp_error( $page_id ) ) {
					continue;
				}
			}

			if ( ! empty( $template_slug ) && 'default' !== $template_slug ) {
				update_post_meta( $page_id, '_wp_page_template', $template_slug );
			}

			if ( '' !== $new_label ) {
				update_post_meta( $page_id, '_jha_course_menu_label', $new_label );
			}

			jha_sync_new_lesson_to_accessally_offering( $page_id, $parent_id, $course_root_id, $new_title );

			$created_tokens[ $token ]     = $page_id;
			$created_tokens[ $raw_token ]  = $page_id;
			unset( $new_pages[ $token ], $new_pages[ $raw_token ] );
		} else {
			$page_id = absint( $raw_token );

			if ( ! $page_id ) {
				continue;
			}

			if ( ! jha_course_page_is_in_course_tree( $page_id, $course_root_id ) ) {
				continue;
			}

			if ( function_exists( 'jha_lesson_meta_debug_log' ) ) {
				$existing_page = get_post( $page_id );
				jha_lesson_meta_debug_log(
					'jha_save_course_lesson_tree_wp_update_post',
					array(
						'post_id'          => $page_id,
						'post_title'       => $existing_page instanceof WP_Post ? $existing_page->post_title : '',
						'old_post_parent'  => $existing_page instanceof WP_Post ? absint( $existing_page->post_parent ) : 0,
						'new_post_parent'  => absint( $parent_id ),
						'old_menu_order'   => $existing_page instanceof WP_Post ? (int) $existing_page->menu_order : 0,
						'new_menu_order'   => (int) $index,
					)
				);
			}

			jha_pause_ally_lesson_save_hooks( $page_id );
			wp_update_post(
				array(
					'ID'          => $page_id,
					'post_parent' => absint( $parent_id ),
					'menu_order'  => $index,
				)
			);
			jha_resume_ally_lesson_save_hooks( $page_id );
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
	$course_root_id = absint( jha_get_course_parent_id( absint( $course_root_id ) ) );

	if ( ! $course_root_id || ! jha_page_is_in_course_template_system( $course_root_id ) || ! class_exists( 'AccessAllyOfferings' ) ) {
		return false;
	}

	$course_key = jha_resolve_accessally_course_key_for_course_root( $course_root_id );

	if ( $course_key ) {
		$offering_settings = AccessAllyOfferings::get_offering_settings( $course_key );

		if ( is_array( $offering_settings ) ) {
			return array(
				'key'      => $course_key,
				'settings' => $offering_settings,
			);
		}
	}

	$course_page_ids = array( $course_root_id );

	if ( function_exists( 'jha_get_course_navigation_pages' ) ) {
		foreach ( jha_get_course_navigation_pages( $course_root_id, true, false ) as $course_page ) {
			$course_page_ids[] = absint( $course_page->ID );
		}
	}

	$course_page_ids = array_values( array_unique( array_filter( $course_page_ids ) ) );

	foreach ( AccessAllyOfferings::get_all_offering_settings() as $offering_key => $offering_settings ) {
		if ( 0 !== strpos( (string) $offering_key, '_accessally_offering_' ) || empty( $offering_settings['pages'] ) || ! is_array( $offering_settings['pages'] ) ) {
			continue;
		}

		foreach ( $offering_settings['pages'] as $page_settings ) {
			if ( ! is_array( $page_settings ) || empty( $page_settings['page-template-select'] ) ) {
				continue;
			}

			if ( in_array( absint( $page_settings['page-template-select'] ), $course_page_ids, true ) ) {
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
		! jha_page_is_in_course_template_system( $course_root_id ) ||
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
		'select-action'              => 'existing',
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

	if ( function_exists( 'jha_lesson_meta_debug_log' ) ) {
		jha_lesson_meta_debug_log(
			'jha_sync_new_lesson_to_accessally_offering',
			array(
				'page_id'        => $page_id,
				'parent_id'      => absint( $parent_id ),
				'course_root_id' => absint( $course_root_id ),
				'offering_key'   => $offering_key,
				'page_ordinal'   => $page_ordinal,
			)
		);
	}

	AccessAllyUtilities::safe_set_settings( $offering_key, $offering_settings, array(), true, false );
	update_post_meta( $page_id, '_accessally_course_key', $offering_key );

	if ( class_exists( 'AccessAllyWizardShared' ) ) {
		AccessAllyWizardShared::assign_page_course_navigation_entries( $offering_settings, $offering_settings['pages'], true );
	}

	do_action( 'accessally_offering_updated', $offering_key, $offering_settings, $offering_settings );
}

/**
 * Whether a page has new/trash/hidden lesson-manager changes waiting to be processed.
 *
 * @param int $post_id Page ID.
 * @return bool
 */
function jha_course_page_has_actionable_lesson_manager_state( $post_id ) {
	$post_id = absint( $post_id );

	if ( ! $post_id ) {
		return false;
	}

	$new_pages = get_post_meta( $post_id, '_jha_course_new_child_pages', true );
	$trash     = get_post_meta( $post_id, '_jha_course_child_trash', true );
	$hidden    = get_post_meta( $post_id, '_jha_course_hidden_lessons', true );

	return ( is_string( $new_pages ) && '' !== $new_pages && '{}' !== $new_pages )
		|| ( is_string( $trash ) && '' !== $trash )
		|| ( is_string( $hidden ) && '' !== $hidden );
}

/**
 * Whether a page has a non-empty lesson tree payload in editor meta.
 *
 * @param int $post_id Page ID.
 * @return bool
 */
function jha_course_page_has_lesson_tree_meta_for_save( $post_id ) {
	$lesson_tree = get_post_meta( absint( $post_id ), '_jha_course_lesson_tree', true );

	return is_string( $lesson_tree ) && '' !== $lesson_tree && '[]' !== $lesson_tree;
}

/**
 * Get the saved lesson tree that should match editor lesson-tree meta.
 *
 * @param int $post_id Page ID.
 * @return array<int, array<string, mixed>>
 */
function jha_get_saved_lesson_tree_for_comparison( $post_id ) {
	$post_id        = jha_normalize_post_id( $post_id );
	$course_root_id = jha_get_course_parent_id( $post_id );
	$stored_tree    = jha_get_raw_course_menu_tree_nodes( $course_root_id );

	if ( $post_id === $course_root_id ) {
		if ( ! empty( $stored_tree ) ) {
			return $stored_tree;
		}

		return jha_build_course_lesson_tree( $post_id );
	}

	if ( ! empty( $stored_tree ) ) {
		$branch = jha_get_course_menu_tree_branch_nodes( $stored_tree, $post_id );

		if ( ! empty( $branch ) ) {
			return $branch;
		}
	}

	$orphan_tree = jha_get_raw_course_menu_tree_nodes( $post_id );

	if ( ! empty( $orphan_tree ) ) {
		return $orphan_tree;
	}

	return jha_build_course_lesson_tree( $post_id );
}

/**
 * Whether incoming lesson-tree meta differs from the saved course menu tree.
 *
 * @param int $post_id Page ID.
 * @return bool
 */
function jha_course_incoming_lesson_tree_differs_from_saved( $post_id ) {
	$post_id = jha_normalize_post_id( $post_id );

	if ( ! $post_id ) {
		return false;
	}

	$lesson_tree_raw = (string) get_post_meta( $post_id, '_jha_course_lesson_tree', true );

	if ( '' === $lesson_tree_raw || '[]' === $lesson_tree_raw ) {
		return false;
	}

	$decoded = json_decode( $lesson_tree_raw, true );

	if ( ! is_array( $decoded ) ) {
		return true;
	}

	$incoming = jha_sanitize_course_lesson_tree_nodes( $decoded );
	$saved    = jha_get_saved_lesson_tree_for_comparison( $post_id );

	return wp_json_encode( $incoming ) !== wp_json_encode( $saved );
}

/**
 * Remove editor lesson-tree meta when it matches the already-saved menu tree.
 *
 * @param int $post_id Page ID.
 * @return bool True when stale meta was removed.
 */
function jha_cleanup_stale_course_lesson_tree_meta( $post_id ) {
	$post_id = jha_normalize_post_id( $post_id );

	if ( ! $post_id || jha_course_page_has_actionable_lesson_manager_state( $post_id ) ) {
		return false;
	}

	$lesson_tree_raw = (string) get_post_meta( $post_id, '_jha_course_lesson_tree', true );

	if ( '' === $lesson_tree_raw || '[]' === $lesson_tree_raw ) {
		delete_post_meta( $post_id, '_jha_course_lesson_tree' );
		delete_post_meta( $post_id, '_jha_course_new_child_pages' );
		return '' !== $lesson_tree_raw;
	}

	$decoded = json_decode( $lesson_tree_raw, true );

	if ( ! is_array( $decoded ) ) {
		delete_post_meta( $post_id, '_jha_course_lesson_tree' );
		delete_post_meta( $post_id, '_jha_course_new_child_pages' );
		return true;
	}

	if ( ! jha_course_incoming_lesson_tree_differs_from_saved( $post_id ) ) {
		delete_post_meta( $post_id, '_jha_course_lesson_tree' );
		delete_post_meta( $post_id, '_jha_course_new_child_pages' );
		return true;
	}

	return false;
}

/**
 * Whether lesson-manager processing should run for this save request.
 *
 * @param int  $post_id Page ID.
 * @param bool $has_settings_nonce Whether the classic course settings nonce verified.
 * @param bool $from_rest_after Whether this call came from rest_after_insert_page.
 * @return bool
 */
function jha_course_page_should_run_lesson_manager_save( $post_id, $has_settings_nonce, $from_rest_after = false ) {
	if ( $has_settings_nonce ) {
		return true;
	}

	if ( jha_course_page_has_actionable_lesson_manager_state( $post_id ) ) {
		return true;
	}

	if ( $from_rest_after && jha_course_page_has_lesson_tree_meta_for_save( $post_id ) ) {
		if ( jha_cleanup_stale_course_lesson_tree_meta( $post_id ) ) {
			return false;
		}

		return jha_course_incoming_lesson_tree_differs_from_saved( $post_id );
	}

	if ( '' !== jha_get_posted_string( 'jha_course_child_order' ) ) {
		return true;
	}

	return false;
}

/**
 * Whether a page has lesson-manager state waiting to be processed.
 *
 * @param int $post_id Page ID.
 * @return bool
 */
function jha_course_page_has_pending_lesson_manager_state( $post_id ) {
	return jha_course_page_should_run_lesson_manager_save( $post_id, false, true );
}

/**
 * Save course menu label and direct child page order.
 *
 * @param int  $post_id Current page ID.
 * @param bool $from_rest_after Whether this call came from rest_after_insert_page.
 * @return void
 */
function jha_save_course_page_settings( $post_id, $from_rest_after = false ) {
	static $processed_lesson_trees = array();

	if ( function_exists( 'jha_lesson_meta_debug_log' ) ) {
		jha_lesson_meta_debug_log(
			'jha_save_course_page_settings_enter',
			array(
				'post_id' => absint( $post_id ),
			)
		);
	}

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
	} elseif ( ! $is_primary_editor_save || ! jha_course_page_should_run_lesson_manager_save( $post_id, false, $from_rest_after ) ) {
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

	if ( ! jha_page_is_in_course_template_system( $post_id ) ) {
		return;
	}

	// Block editor lesson saves run after REST meta via rest_after_insert_page, not save_post.
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST && ! $from_rest_after ) {
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

		remove_action( 'save_post_page', 'jha_save_course_page_settings', 99 );

		foreach ( $trash_ids as $trash_id ) {
			if ( ! jha_course_page_is_in_course_tree( $trash_id, $course_root_id ) ) {
				continue;
			}

			if ( function_exists( 'jha_lesson_meta_debug_log' ) ) {
				jha_lesson_meta_debug_log(
					'jha_save_course_page_settings_wp_trash_post',
					array(
						'trash_id'       => absint( $trash_id ),
						'course_root_id' => absint( $course_root_id ),
						'saved_on_post'  => absint( $post_id ),
					)
				);
			}

			jha_pause_ally_lesson_save_hooks( $trash_id );
			wp_trash_post( $trash_id );
			jha_resume_ally_lesson_save_hooks( $trash_id );
		}

		add_action( 'save_post_page', 'jha_save_course_page_settings', 99, 1 );
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

	$lesson_tree = jha_merge_new_pages_into_lesson_tree( $lesson_tree, $new_pages );
	$lesson_tree = jha_deduplicate_course_lesson_tree_nodes( $lesson_tree );

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

	delete_post_meta( $post_id, '_jha_course_lesson_tree' );
	delete_post_meta( $post_id, '_jha_course_new_child_pages' );
	delete_post_meta( $post_id, '_jha_course_child_trash' );
	delete_post_meta( $post_id, '_jha_course_hidden_lessons' );

	if ( ! empty( $new_pages ) ) {
		update_post_meta( $course_root_id, '_jha_course_lesson_manager_reload', '1' );

		if ( $post_id !== $course_root_id ) {
			update_post_meta( $post_id, '_jha_course_lesson_manager_reload', '1' );
		}
	}

	remove_action( 'save_post_page', 'jha_save_course_page_settings', 99 );

	$lesson_parent_id = jha_normalize_post_id( $post_id );
	$root_tree        = jha_get_raw_course_menu_tree_nodes( $course_root_id );

	if ( empty( $root_tree ) && $post_id !== $course_root_id ) {
		$orphan_tree = jha_get_raw_course_menu_tree_nodes( $post_id );

		if ( ! empty( $orphan_tree ) ) {
			$root_tree = $orphan_tree;
		}
	}

	$saved_branch = ! empty( $lesson_tree )
		? jha_save_course_lesson_tree( $lesson_tree, $lesson_parent_id, $course_root_id, $new_pages, $template_slug )
		: array();

	if ( $lesson_parent_id === $course_root_id ) {
		$saved_tree = $saved_branch;
	} else {
		$found      = false;
		$saved_tree = jha_replace_course_menu_tree_branch( $root_tree, $lesson_parent_id, $saved_branch, $found );

		if ( ! $found ) {
			$saved_tree = jha_insert_course_menu_tree_branch( $root_tree, $lesson_parent_id, $saved_branch, $course_root_id );
		}
	}

	update_post_meta( $course_root_id, '_jha_course_menu_tree', wp_json_encode( $saved_tree ) );

	if ( $post_id !== $course_root_id ) {
		delete_post_meta( $post_id, '_jha_course_menu_tree' );
	}

	foreach ( jha_get_course_navigation_pages( $course_root_id, true, false ) as $course_page ) {
		if ( ! $course_page instanceof WP_Post ) {
			continue;
		}

		if ( absint( $course_page->ID ) === absint( $post_id ) ) {
			continue;
		}

		if ( in_array( absint( $course_page->ID ), $hidden_ids, true ) ) {
			update_post_meta( $course_page->ID, '_jha_course_hide_from_menu', '1' );
		} else {
			delete_post_meta( $course_page->ID, '_jha_course_hide_from_menu' );
		}
	}

	add_action( 'save_post_page', 'jha_save_course_page_settings', 99, 1 );
}
add_action( 'save_post_page', 'jha_save_course_page_settings', 99, 1 );

/**
 * Process lesson-manager saves after block editor REST meta is stored.
 *
 * @param WP_Post         $post Saved page.
 * @param WP_REST_Request $request REST request.
 * @param bool            $creating Whether this created a new page.
 * @return void
 */
function jha_rest_save_course_page_settings( $post, $request, $creating ) {
	unset( $creating );

	if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
		return;
	}

	if ( wp_is_post_autosave( $post->ID ) || wp_is_post_revision( $post->ID ) ) {
		return;
	}

	if ( $request instanceof WP_REST_Request && $request->get_param( 'autosave' ) ) {
		return;
	}

	if ( ! jha_course_page_should_run_lesson_manager_save( $post->ID, false, true ) ) {
		jha_cleanup_stale_course_lesson_tree_meta( $post->ID );
		return;
	}

	jha_save_course_page_settings( $post->ID, true );
}
add_action( 'rest_after_insert_page', 'jha_rest_save_course_page_settings', 10, 3 );

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

	if ( ! jha_page_has_course_template( $post->ID ) ) {
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
 * Tell the block editor when a lesson-manager save created new child pages.
 *
 * @return void
 */
function jha_ajax_course_lesson_manager_needs_reload() {
	check_ajax_referer( 'jha_course_admin', 'nonce' );

	$post_id = isset( $_POST['postId'] ) ? absint( wp_unslash( $_POST['postId'] ) ) : 0;

	if ( ! $post_id || ! current_user_can( 'edit_page', $post_id ) ) {
		wp_send_json_error();
	}

	$needs_reload = '1' === get_post_meta( $post_id, '_jha_course_lesson_manager_reload', true );

	if ( $needs_reload ) {
		delete_post_meta( $post_id, '_jha_course_lesson_manager_reload' );
	}

	wp_send_json_success(
		array(
			'reload' => $needs_reload,
		)
	);
}
add_action( 'wp_ajax_jha_course_lesson_manager_needs_reload', 'jha_ajax_course_lesson_manager_needs_reload' );

/**
 * Load admin sorting behavior only on the page editor.
 *
 * @param string $hook_suffix Current admin screen.
 * @return void
 */
function jha_enqueue_course_admin_assets( $hook_suffix = '' ) {
	static $localized = false;

	$screen = get_current_screen();

	if ( ! $screen || 'page' !== $screen->post_type ) {
		return;
	}

	if (
		'post.php' !== $hook_suffix &&
		'post-new.php' !== $hook_suffix &&
		'edit.php' !== $hook_suffix &&
		'post' !== $screen->base &&
		'page' !== $screen->base
	) {
		return;
	}

	wp_enqueue_script( 'jquery-ui-sortable' );
	$course_admin_script_path = get_stylesheet_directory() . '/assets/js/course-admin.js';
	wp_enqueue_script(
		'jha-course-admin',
		get_stylesheet_directory_uri() . '/assets/js/course-admin.js',
		array( 'jquery', 'jquery-ui-sortable', 'wp-hooks', 'wp-data' ),
		file_exists( $course_admin_script_path ) ? (string) filemtime( $course_admin_script_path ) : wp_get_theme()->get( 'Version' ),
		true
	);

	if ( $localized ) {
		return;
	}

	$localized = true;
	global $post;

	$current_post_id = ( $post instanceof WP_Post ) ? absint( $post->ID ) : 0;

	if ( ! $current_post_id && isset( $_GET['post'] ) ) {
		$current_post_id = absint( wp_unslash( $_GET['post'] ) );
	}

	wp_localize_script(
		'jha-course-admin',
		'jhaCourseAdmin',
		array(
			'ajaxUrl'                    => admin_url( 'admin-ajax.php' ),
			'nonce'                      => wp_create_nonce( 'jha_course_admin' ),
			'postId'                     => $current_post_id,
			'courseTemplateSlug'         => jha_get_course_template_slug(),
			'isInCourseTemplateSystem'   => $current_post_id ? jha_page_is_in_course_template_system( $current_post_id ) : false,
			'i18n'                       => array(
				'menuLabelPrompt' => __( 'Enter the course menu label override. Leave blank to use the page title.', 'journey-homeschool-academy' ),
				'saveFailed'      => __( 'The course menu label could not be saved.', 'journey-homeschool-academy' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'jha_enqueue_course_admin_assets' );
add_action( 'enqueue_block_editor_assets', 'jha_enqueue_course_admin_assets' );
