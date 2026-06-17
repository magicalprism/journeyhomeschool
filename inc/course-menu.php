<?php
/**
 * Lightweight course sidebar helpers for the JHA Course Template.
 *
 * Course pages use a normal WordPress page hierarchy:
 * - The top-level page is the main course/welcome page.
 * - Child pages are lessons.
 * - Grandchild pages are optional nested lesson content.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the top-level course parent for the current page tree.
 *
 * If the page has no parent, it is the course parent. If it is nested, the
 * highest ancestor is used so every lesson in the tree shares one menu.
 *
 * @param int $post_id Current page ID.
 * @return int Top-level course parent ID.
 */
function jha_get_course_parent_id( $post_id ) {
	$post_id = absint( $post_id );

	if ( ! $post_id ) {
		return 0;
	}

	$ancestors = get_post_ancestors( $post_id );

	if ( empty( $ancestors ) ) {
		return $post_id;
	}

	return absint( end( $ancestors ) );
}

/**
 * Page template slug for the JHA course template system.
 *
 * @return string
 */
function jha_get_course_template_slug() {
	return 'page-templates/course-template.php';
}

/**
 * Whether a page explicitly uses the JHA course template.
 *
 * @param int $post_id Page ID.
 * @return bool
 */
function jha_page_has_course_template( $post_id ) {
	$post_id = absint( $post_id );

	if ( ! $post_id ) {
		return false;
	}

	return jha_get_course_template_slug() === get_page_template_slug( $post_id );
}

/**
 * Whether a page belongs to the JHA course template system.
 *
 * Theme-specific AccessAlly offering sync, menu access rules, and related
 * save hooks only apply inside this system. Legacy pages are left to
 * AccessAlly's native behavior.
 *
 * @param int $post_id Page ID.
 * @return bool
 */
function jha_page_is_in_course_template_system( $post_id ) {
	$post_id = absint( $post_id );

	if ( ! $post_id ) {
		return false;
	}

	if ( jha_page_has_course_template( $post_id ) ) {
		return true;
	}

	$course_root_id = jha_get_course_parent_id( $post_id );

	if ( $course_root_id > 0 && jha_page_has_course_template( $course_root_id ) ) {
		return true;
	}

	if ( $course_root_id === $post_id ) {
		return jha_course_root_has_descendant_with_course_template( $post_id );
	}

	return false;
}

/**
 * Whether any descendant page under a course root uses the JHA course template.
 *
 * @param int $course_root_id Top-level course page ID.
 * @return bool
 */
function jha_course_root_has_descendant_with_course_template( $course_root_id ) {
	static $cache = array();

	$course_root_id = absint( $course_root_id );

	if ( ! $course_root_id ) {
		return false;
	}

	if ( isset( $cache[ $course_root_id ] ) ) {
		return $cache[ $course_root_id ];
	}

	foreach (
		get_pages(
			array(
				'child_of'    => $course_root_id,
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		) as $page
	) {
		if ( jha_page_has_course_template( $page->ID ) ) {
			$cache[ $course_root_id ] = true;
			return true;
		}
	}

	$cache[ $course_root_id ] = false;

	return false;
}

/**
 * Resolve the AccessAlly offering key for a course root page.
 *
 * @param int $course_root_id Top-level course page ID.
 * @return string Offering key, or empty string when unavailable.
 */
function jha_resolve_accessally_course_key_for_course_root( $course_root_id ) {
	$course_root_id = absint( $course_root_id );

	if ( ! $course_root_id ) {
		return '';
	}

	$course_key = get_post_meta( $course_root_id, '_accessally_course_key', true );

	if ( is_string( $course_key ) && 0 === strpos( $course_key, '_accessally_offering_' ) ) {
		return $course_key;
	}

	if ( class_exists( 'AccessAllyWizardShared' ) ) {
		$course_key = AccessAllyWizardShared::get_post_parent_offering_key( $course_root_id );

		if ( is_string( $course_key ) && 0 === strpos( $course_key, '_accessally_offering_' ) ) {
			return $course_key;
		}
	}

	foreach ( jha_get_course_child_pages( $course_root_id, true ) as $child_page ) {
		$course_key = get_post_meta( $child_page->ID, '_accessally_course_key', true );

		if ( is_string( $course_key ) && 0 === strpos( $course_key, '_accessally_offering_' ) ) {
			return $course_key;
		}
	}

	return '';
}

/**
 * Get direct child pages ordered like the Pages admin.
 *
 * @param int  $parent_id Parent page ID.
 * @param bool $include_hidden Whether to include pages hidden from the course menu.
 * @return WP_Post[] Child page objects.
 */
function jha_get_course_child_pages( $parent_id, $include_hidden = false ) {
	$pages = get_pages(
		array(
			'child_of'    => 0,
			'parent'      => absint( $parent_id ),
			'post_type'   => 'page',
			'post_status' => 'publish',
			'sort_column' => 'menu_order,post_title',
			'sort_order'  => 'ASC',
		)
	);

	if ( $include_hidden ) {
		return $pages;
	}

	return array_values(
		array_filter(
			$pages,
			static function ( $page ) {
				return ! jha_course_page_is_hidden_from_menu( $page->ID );
			}
		)
	);
}

/**
 * Get the sidebar menu label for a page.
 *
 * Admins can override the label from the page editor without changing the
 * actual page title. Empty overrides fall back to the WordPress page title.
 *
 * @param int $post_id Page ID.
 * @return string Menu label.
 */
function jha_get_course_menu_label( $post_id ) {
	$post_id    = absint( $post_id );
	$menu_label = get_post_meta( $post_id, '_jha_course_menu_label', true );

	if ( is_string( $menu_label ) && '' !== trim( $menu_label ) ) {
		return $menu_label;
	}

	return get_the_title( $post_id );
}

/**
 * Build the saved course menu tree, including menu-only parent items.
 *
 * @param int  $course_parent_id Top-level course parent ID.
 * @param bool $include_hidden Whether to include pages hidden from the course menu.
 * @param bool $filter_access Whether to exclude pages the current user cannot access.
 * @return array<int, array<string, mixed>>
 */
function jha_get_course_menu_tree( $course_parent_id, $include_hidden = false, $filter_access = true ) {
	$course_parent_id = absint( $course_parent_id );

	if ( ! $course_parent_id || ! jha_page_is_in_course_template_system( $course_parent_id ) ) {
		return array();
	}

	$stored_tree      = get_post_meta( $course_parent_id, '_jha_course_menu_tree', true );
	$decoded_tree     = is_string( $stored_tree ) && '' !== $stored_tree ? json_decode( $stored_tree, true ) : null;

	if ( is_array( $decoded_tree ) ) {
		$prepared_tree = jha_prepare_course_menu_tree_nodes( $decoded_tree, $include_hidden, $filter_access );
		$tracked_ids   = jha_collect_course_menu_tree_page_ids_from_raw_nodes( $decoded_tree );

		return array_merge(
			$prepared_tree,
			jha_build_course_menu_tree_from_pages( $course_parent_id, $include_hidden, $tracked_ids, $filter_access )
		);
	}

	return jha_build_course_menu_tree_from_pages( $course_parent_id, $include_hidden, array(), $filter_access );
}

/**
 * Normalize persisted tree nodes into renderable menu nodes.
 *
 * @param array $nodes Raw tree nodes.
 * @param bool  $include_hidden Whether to include pages hidden from the course menu.
 * @param bool  $filter_access Whether to exclude pages the current user cannot access.
 * @return array<int, array<string, mixed>>
 */
function jha_prepare_course_menu_tree_nodes( $nodes, $include_hidden = false, $filter_access = true ) {
	$prepared = array();

	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) || empty( $node['token'] ) ) {
			continue;
		}

		$token    = (string) $node['token'];
		$type     = isset( $node['type'] ) ? sanitize_key( (string) $node['type'] ) : '';
		$children = isset( $node['children'] ) && is_array( $node['children'] )
			? jha_prepare_course_menu_tree_nodes( $node['children'], $include_hidden, $filter_access )
			: array();

		if ( 'menu' === $type || 0 === strpos( $token, 'menu-' ) ) {
			$title = isset( $node['title'] ) ? sanitize_text_field( (string) $node['title'] ) : '';

			if ( '' === $title || ( $filter_access && empty( $children ) ) ) {
				continue;
			}

			$prepared[] = array(
				'type'     => 'menu',
				'token'    => sanitize_key( $token ),
				'title'    => $title,
				'children' => $children,
			);
			continue;
		}

		$page_id = absint( $token );
		$page    = $page_id ? get_post( $page_id ) : null;

		if ( ! $page || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
			continue;
		}

		if ( $filter_access && ! jha_course_page_is_visible_in_menu( $page_id ) ) {
			continue;
		}

		if ( ! $filter_access && ! $include_hidden && jha_course_page_is_hidden_from_menu( $page_id ) ) {
			continue;
		}

		$prepared[] = array(
			'type'     => 'page',
			'token'    => (string) $page_id,
			'page'     => $page,
			'children' => $children,
		);
	}

	return $prepared;
}

/**
 * Collect page IDs from a raw saved menu tree before access filtering.
 *
 * Tracking the full saved tree prevents pages from reappearing through the
 * WordPress hierarchy merge under a different parent branch.
 *
 * @param array $nodes Raw menu tree nodes.
 * @return int[]
 */
function jha_collect_course_menu_tree_page_ids_from_raw_nodes( $nodes ) {
	$page_ids = array();

	if ( ! is_array( $nodes ) ) {
		return $page_ids;
	}

	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) || empty( $node['token'] ) ) {
			continue;
		}

		$token = (string) $node['token'];
		$type  = isset( $node['type'] ) ? sanitize_key( (string) $node['type'] ) : '';

		if ( 'menu' !== $type && 0 !== strpos( $token, 'menu-' ) ) {
			$page_id = absint( $token );

			if ( $page_id ) {
				$page_ids[] = $page_id;
			}
		}

		if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
			$page_ids = array_merge(
				$page_ids,
				jha_collect_course_menu_tree_page_ids_from_raw_nodes( $node['children'] )
			);
		}
	}

	return array_values( array_unique( array_filter( $page_ids ) ) );
}

/**
 * Get all page IDs already represented in a prepared menu tree.
 *
 * @param array $nodes Prepared menu tree nodes.
 * @return int[]
 */
function jha_get_course_menu_tree_page_ids( $nodes ) {
	$page_ids = array();

	foreach ( $nodes as $node ) {
		if ( isset( $node['type'], $node['page'] ) && 'page' === $node['type'] && $node['page'] instanceof WP_Post ) {
			$page_ids[] = absint( $node['page']->ID );
		}

		if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
			$page_ids = array_merge( $page_ids, jha_get_course_menu_tree_page_ids( $node['children'] ) );
		}
	}

	return array_values( array_unique( array_filter( $page_ids ) ) );
}

/**
 * Build a renderable tree from the WordPress page hierarchy.
 *
 * @param int   $parent_id Parent page ID.
 * @param bool  $include_hidden Whether to include pages hidden from the course menu.
 * @param int[] $exclude_ids Page IDs already represented in a saved menu tree.
 * @param bool  $filter_access Whether to exclude pages the current user cannot access.
 * @return array<int, array<string, mixed>>
 */
function jha_build_course_menu_tree_from_pages( $parent_id, $include_hidden = false, $exclude_ids = array(), $filter_access = true ) {
	$tree = array();

	foreach ( jha_get_course_child_pages( $parent_id, $include_hidden ) as $child ) {
		if ( in_array( absint( $child->ID ), $exclude_ids, true ) ) {
			continue;
		}

		if ( $filter_access && ! jha_course_page_is_visible_in_menu( $child->ID ) ) {
			continue;
		}

		$tree[] = array(
			'type'     => 'page',
			'token'    => (string) $child->ID,
			'page'     => $child,
			'children' => jha_build_course_menu_tree_from_pages( $child->ID, $include_hidden, $exclude_ids, $filter_access ),
		);
	}

	return $tree;
}

/**
 * Get the Gutenberg block gap for course page content.
 *
 * Defaults to 3rem so lesson content has generous spacing without admins
 * manually adding spacer blocks between Gutenberg blocks.
 *
 * @param int $post_id Page ID.
 * @return string CSS length.
 */
function jha_get_course_block_gap( $post_id ) {
	$block_gap = get_post_meta( absint( $post_id ), '_jha_course_block_gap', true );

	if ( is_string( $block_gap ) && preg_match( '/^(?:[0-4](?:\.5)?|5(?:\.0)?)rem$/', $block_gap ) ) {
		return $block_gap;
	}

	return '3rem';
}

/**
 * Whether the course template should display the page title.
 *
 * @param int $post_id Page ID.
 * @return bool
 */
function jha_should_show_course_title( $post_id ) {
	return '1' !== get_post_meta( absint( $post_id ), '_jha_course_hide_title', true );
}

/**
 * Whether a course page should be hidden from the sidebar and lesson navigation.
 *
 * @param int $post_id Page ID.
 * @return bool
 */
function jha_course_page_is_hidden_from_menu( $post_id ) {
	return '1' === get_post_meta( absint( $post_id ), '_jha_course_hide_from_menu', true );
}

/**
 * Whether a page stores the course sidebar menu tree.
 *
 * @param int $post_id Page ID.
 * @return bool
 */
function jha_page_owns_course_menu_tree( $post_id ) {
	$post_id = absint( $post_id );

	if ( ! $post_id ) {
		return false;
	}

	$menu_tree = get_post_meta( $post_id, '_jha_course_menu_tree', true );

	if ( is_string( $menu_tree ) && '' !== $menu_tree && '[]' !== $menu_tree ) {
		return true;
	}

	$lesson_tree = get_post_meta( $post_id, '_jha_course_lesson_tree', true );

	return is_string( $lesson_tree ) && '' !== $lesson_tree && '[]' !== $lesson_tree;
}

/**
 * Whether the current page is the top-level course home page.
 *
 * @param int $post_id Page ID.
 * @return bool
 */
function jha_is_course_root_page( $post_id ) {
	$post_id = absint( $post_id );

	if ( ! $post_id ) {
		return false;
	}

	if ( jha_page_owns_course_menu_tree( $post_id ) ) {
		return true;
	}

	if ( wp_get_post_parent_id( $post_id ) ) {
		return false;
	}

	return (
		jha_page_has_course_template( $post_id )
		&& $post_id === jha_get_course_parent_id( $post_id )
		&& ! empty( jha_get_course_child_pages( $post_id, true ) )
	);
}

/**
 * Whether the course progress ring should display for the current page.
 *
 * Explicit meta values always win. The course home page defaults to off; lesson
 * pages default to on unless an editor turns the ring off.
 *
 * @param int $post_id Page ID.
 * @return bool
 */
function jha_should_show_course_progress( $post_id ) {
	$post_id = absint( $post_id );
	$value   = get_post_meta( $post_id, '_jha_course_show_progress', true );

	if ( '1' === $value ) {
		return true;
	}

	if ( '0' === $value ) {
		return false;
	}

	if ( jha_is_course_root_page( $post_id ) ) {
		return false;
	}

	return true;
}

/**
 * Get AccessAlly offering context for a specific page/post ID.
 *
 * Never uses the WordPress parent page ID. The lookup is always keyed to the
 * menu item / lesson page being evaluated.
 *
 * @param int $post_id Page ID.
 * @return array<string, mixed>|false
 */
function jha_get_accessally_offering_page_context( $post_id ) {
	$post_id = absint( $post_id );

	if ( ! $post_id || ! jha_page_is_in_course_template_system( $post_id ) || ! class_exists( 'AccessAllyWizardShared' ) || ! class_exists( 'AccessAllyOfferings' ) ) {
		return false;
	}

	$course_key = AccessAllyWizardShared::get_post_parent_offering_key( $post_id );

	if ( empty( $course_key ) ) {
		return false;
	}

	$offering_settings = AccessAllyOfferings::get_offering_settings( $course_key );

	if ( empty( $offering_settings['pages'] ) || ! is_array( $offering_settings['pages'] ) ) {
		return false;
	}

	foreach ( $offering_settings['pages'] as $page_ordinal => $page_settings ) {
		if ( ! is_array( $page_settings ) || empty( $page_settings['page-template-select'] ) ) {
			continue;
		}

		if ( absint( $page_settings['page-template-select'] ) === $post_id ) {
			return array(
				'course_key'        => $course_key,
				'offering_settings' => $offering_settings,
				'page_ordinal'      => $page_ordinal,
				'page_settings'     => $page_settings,
			);
		}
	}

	return false;
}

/**
 * Get required AccessAlly tags for one offering page ordinal.
 *
 * Mirrors AccessAllyOfferings::generate_individual_page_required_permission_tags()
 * so menu access can be evaluated per lesson page ID.
 *
 * @param array<string, mixed> $offering_settings Offering settings.
 * @param int|string           $page_ordinal Offering page ordinal.
 * @return int[]
 */
function jha_get_accessally_offering_required_tag_ids( $offering_settings, $page_ordinal ) {
	if ( empty( $offering_settings ) || ! is_array( $offering_settings ) ) {
		return array();
	}

	if ( empty( $offering_settings['pages'][ $page_ordinal ] ) || ! is_array( $offering_settings['pages'][ $page_ordinal ] ) ) {
		return array();
	}

	$page_settings  = $offering_settings['pages'][ $page_ordinal ];
	$page_module_id = $page_settings['module'] ?? '';
	$required_tags  = array();

	if ( class_exists( 'AccessAllyOfferings' ) && method_exists( 'AccessAllyOfferings', 'get_configured_tag_value' ) ) {
		$instant_access_tag_id = AccessAllyOfferings::get_configured_tag_value( $offering_settings['tags']['instant'] ?? array() );

		if ( $instant_access_tag_id ) {
			$required_tags[] = $instant_access_tag_id;
		}

		$module_specific_permission_tag = false;

		if ( '0' !== (string) $page_module_id ) {
			$tag_key = 'module-' . $page_module_id;

			if ( isset( $offering_settings['tags'][ $tag_key ] ) ) {
				$module_specific_permission_tag = AccessAllyOfferings::get_configured_tag_value( $offering_settings['tags'][ $tag_key ] );
			}
		}

		$base_access_tag = AccessAllyOfferings::get_configured_tag_value( $offering_settings['tags']['base'] ?? array() );

		if ( empty( $module_specific_permission_tag ) ) {
			if ( ! empty( $base_access_tag ) ) {
				$required_tags[] = $base_access_tag;
			}
		} else {
			$required_tags[] = $module_specific_permission_tag;
		}
	}

	return array_values( array_unique( array_filter( array_map( 'absint', $required_tags ) ) ) );
}

/**
 * Get forbidden AccessAlly tags for an offering.
 *
 * @param array<string, mixed> $offering_settings Offering settings.
 * @return int[]
 */
function jha_get_accessally_offering_forbidden_tag_ids( $offering_settings ) {
	if ( empty( $offering_settings ) || ! is_array( $offering_settings ) ) {
		return array();
	}

	if ( ! class_exists( 'AccessAllyOfferings' ) || ! method_exists( 'AccessAllyOfferings', 'get_configured_tag_value' ) ) {
		return array();
	}

	$revoke_tag_id = AccessAllyOfferings::get_configured_tag_value( $offering_settings['tags']['revoke'] ?? array() );

	return $revoke_tag_id ? array( absint( $revoke_tag_id ) ) : array();
}

/**
 * Whether a user matches AccessAlly required/forbidden tag rules.
 *
 * @param int[] $required_tag_ids Required tag IDs.
 * @param int[] $forbidden_tag_ids Forbidden tag IDs.
 * @param int   $user_id User ID.
 * @return bool
 */
function jha_user_matches_accessally_tag_rules( $required_tag_ids, $forbidden_tag_ids, $user_id ) {
	if ( ! class_exists( 'AccessAllyUserPermission' ) ) {
		return true;
	}

	$user_tags = AccessAllyUserPermission::get_cleaned_user_tags( $user_id );

	if ( ! is_array( $user_tags ) ) {
		return false;
	}

	$required_tag_ids  = array_values( array_filter( array_map( 'absint', (array) $required_tag_ids ) ) );
	$forbidden_tag_ids = array_values( array_filter( array_map( 'absint', (array) $forbidden_tag_ids ) ) );

	if ( ! empty( $required_tag_ids ) ) {
		$common_tags = array_intersect( $user_tags['clean'], $required_tag_ids );

		if ( empty( $common_tags ) ) {
			return false;
		}
	}

	if ( ! empty( $forbidden_tag_ids ) ) {
		$common_tags = array_intersect( $user_tags['clean'], $forbidden_tag_ids );

		if ( ! empty( $common_tags ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Whether the current user can access a course page through AccessAlly.
 *
 * Always evaluates the passed page/post ID for that specific lesson or menu
 * item. Parent/module page IDs must never be substituted here.
 *
 * @param int $post_id Page ID for the menu item or lesson being checked.
 * @return bool
 */
function jha_user_can_access_course_page( $post_id ) {
	$post_id = absint( $post_id );

	if ( ! $post_id ) {
		return false;
	}

	if ( ! class_exists( 'AccessAlly' ) || ! method_exists( 'AccessAlly', 'can_current_user_read' ) ) {
		return true;
	}

	$user_id = class_exists( 'AccessAllyBackendShared' )
		? AccessAllyBackendShared::get_current_user_id()
		: get_current_user_id();

	if ( class_exists( 'AccessAllyUserPermission' ) && AccessAllyUserPermission::can_user_access_all_posts( $user_id ) ) {
		return true;
	}

	if ( ! jha_page_is_in_course_template_system( $post_id ) ) {
		return (bool) AccessAlly::can_current_user_read( $post_id, $user_id );
	}

	$permission = class_exists( 'AccessAllyPostPermission' )
		? AccessAllyPostPermission::get_post_permission( $post_id )
		: array();

	if ( is_array( $permission ) && 'yes' === ( $permission['require-login'] ?? 'no' ) ) {
		return (bool) AccessAlly::can_current_user_read( $post_id, $user_id );
	}

	$offering_context = jha_get_accessally_offering_page_context( $post_id );

	if ( $offering_context ) {
		$required_tag_ids = jha_get_accessally_offering_required_tag_ids(
			$offering_context['offering_settings'],
			$offering_context['page_ordinal']
		);
		$forbidden_tag_ids = jha_get_accessally_offering_forbidden_tag_ids( $offering_context['offering_settings'] );

		if ( ! empty( $required_tag_ids ) || ! empty( $forbidden_tag_ids ) ) {
			return jha_user_matches_accessally_tag_rules( $required_tag_ids, $forbidden_tag_ids, $user_id );
		}
	}

	return (bool) AccessAlly::can_current_user_read( $post_id, $user_id );
}

/**
 * Whether a course page should appear in the frontend sidebar menu.
 *
 * @param int $post_id Page ID.
 * @return bool
 */
function jha_course_page_is_visible_in_menu( $post_id ) {
	if ( jha_course_page_is_hidden_from_menu( $post_id ) ) {
		return false;
	}

	return jha_user_can_access_course_page( $post_id );
}

/**
 * Get the AccessAlly "Available" badge image for a course page.
 *
 * AccessAlly stores the available badge URL in the page permission settings as
 * `enable-url`. This is used only as a fallback when no course logo or featured
 * image has been set.
 *
 * @param int $post_id Page ID.
 * @return string Badge image HTML, or empty string if unavailable.
 */
function jha_get_accessally_available_badge_html( $post_id ) {
	$post_id        = absint( $post_id );
	$course_root_id = jha_get_course_parent_id( $post_id );

	if ( ! $post_id || ! $course_root_id ) {
		return '';
	}

	$badge_url = '';

	if ( class_exists( 'AccessAllyPostPermission' ) ) {
		$permission = AccessAllyPostPermission::get_post_permission( $course_root_id );
	} else {
		$permission = get_post_meta( $course_root_id, '_accessally_post_permission', true );
	}

	if ( is_array( $permission ) && ! empty( $permission['enable-url'] ) && is_string( $permission['enable-url'] ) ) {
		$badge_url = $permission['enable-url'];
	}

	if ( '' === trim( $badge_url ) && $post_id !== $course_root_id ) {
		if ( class_exists( 'AccessAllyPostPermission' ) ) {
			$permission = AccessAllyPostPermission::get_post_permission( $post_id );
		} else {
			$permission = get_post_meta( $post_id, '_accessally_post_permission', true );
		}

		if ( is_array( $permission ) && ! empty( $permission['enable-url'] ) && is_string( $permission['enable-url'] ) ) {
			$badge_url = $permission['enable-url'];
		}
	}

	if ( '' === trim( $badge_url ) && jha_page_is_in_course_template_system( $course_root_id ) && function_exists( 'jha_get_accessally_offering_for_course_root' ) ) {
		$offering = jha_get_accessally_offering_for_course_root( $course_root_id );

		if (
			is_array( $offering ) &&
			! empty( $offering['settings']['enabled-icon-url'] ) &&
			is_string( $offering['settings']['enabled-icon-url'] )
		) {
			$badge_url = $offering['settings']['enabled-icon-url'];
		}
	}

	if ( '' === trim( $badge_url ) && jha_page_is_in_course_template_system( $course_root_id ) && function_exists( 'jha_get_course_navigation_pages' ) ) {
		foreach ( jha_get_course_navigation_pages( $course_root_id, true, false ) as $course_page ) {
			$page_id = absint( $course_page->ID );

			if ( ! $page_id ) {
				continue;
			}

			if ( class_exists( 'AccessAllyPostPermission' ) ) {
				$permission = AccessAllyPostPermission::get_post_permission( $page_id );
			} else {
				$permission = get_post_meta( $page_id, '_accessally_post_permission', true );
			}

			if ( is_array( $permission ) && ! empty( $permission['enable-url'] ) && is_string( $permission['enable-url'] ) ) {
				$badge_url = $permission['enable-url'];
				break;
			}
		}
	}

	if ( '' === trim( $badge_url ) ) {
		return '';
	}

	return sprintf(
		'<img class="jha-course-logo-image" src="%s" alt="%s">',
		esc_url( $badge_url ),
		esc_attr( get_the_title( $course_root_id ) )
	);
}

/**
 * Render the course logo from ACF first, then the course featured image.
 *
 * Admins can set an optional ACF image field named course_sidebar_logo on the
 * top-level course page. If that field is absent or empty, the page featured
 * image is used. If neither exists, the AccessAlly "Available" badge is used.
 *
 * @param int $course_parent_id Top-level course parent ID.
 * @return void
 */
function jha_render_course_logo( $course_parent_id ) {
	$course_parent_id = absint( $course_parent_id );
	$logo_html        = '';

	if ( function_exists( 'get_field' ) ) {
		$acf_logo = get_field( 'course_sidebar_logo', $course_parent_id );

		if ( is_array( $acf_logo ) && ! empty( $acf_logo['ID'] ) ) {
			$logo_html = wp_get_attachment_image(
				absint( $acf_logo['ID'] ),
				'medium',
				false,
				array( 'class' => 'jha-course-logo-image' )
			);
		} elseif ( is_array( $acf_logo ) && ! empty( $acf_logo['url'] ) ) {
			$logo_html = sprintf(
				'<img class="jha-course-logo-image" src="%s" alt="%s">',
				esc_url( $acf_logo['url'] ),
				esc_attr( ! empty( $acf_logo['alt'] ) ? $acf_logo['alt'] : get_the_title( $course_parent_id ) )
			);
		} elseif ( is_numeric( $acf_logo ) ) {
			$logo_html = wp_get_attachment_image(
				absint( $acf_logo ),
				'medium',
				false,
				array( 'class' => 'jha-course-logo-image' )
			);
		} elseif ( is_string( $acf_logo ) && ! empty( $acf_logo ) ) {
			$logo_html = sprintf(
				'<img class="jha-course-logo-image" src="%s" alt="%s">',
				esc_url( $acf_logo ),
				esc_attr( get_the_title( $course_parent_id ) )
			);
		}
	}

	if ( empty( $logo_html ) && has_post_thumbnail( $course_parent_id ) ) {
		$logo_html = get_the_post_thumbnail(
			$course_parent_id,
			'medium',
			array( 'class' => 'jha-course-logo-image' )
		);
	}

	if ( empty( $logo_html ) ) {
		$logo_html = jha_get_accessally_available_badge_html( $course_parent_id );
	}

	if ( empty( $logo_html ) ) {
		return;
	}

	printf(
		'<div class="jha-course-logo"><a href="%1$s" aria-label="%2$s">%3$s</a></div>',
		esc_url( get_permalink( $course_parent_id ) ),
		esc_attr( sprintf( __( 'Go to %s course home', 'journey-homeschool-academy' ), get_the_title( $course_parent_id ) ) ),
		$logo_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	);
}

/**
 * Get an integer value from a ProgressAlly count shortcode.
 *
 * @param string $shortcode Shortcode to render.
 * @return int
 */
function jha_get_progressally_count_from_shortcode( $shortcode ) {
	$output = do_shortcode( $shortcode );
	$text   = trim( wp_strip_all_tags( $output ) );

	if ( preg_match( '/\d+/', $text, $matches ) ) {
		return absint( $matches[0] );
	}

	return 0;
}

/**
 * Render the AccessAlly/ProgressAlly course progress tracker.
 *
 * The built-in ProgressAlly pie chart calculates from one post ID. This custom
 * tracker sums objective counts from visible course pages only, so lessons
 * hidden from the menu are also excluded from the course progress calculation.
 *
 * @param int $course_parent_id Top-level course parent ID.
 * @return void
 */
function jha_render_course_progress_tracker( $course_parent_id ) {
	$course_parent_id = absint( $course_parent_id );
	$progress_markup  = jha_get_course_progress_tracker_markup( $course_parent_id );

	if ( '' === $progress_markup ) {
		return;
	}

	printf(
		'<div class="jha-course-progress-tracker" data-course-id="%1$d" aria-label="%2$s">%3$s</div>',
		$course_parent_id,
		esc_attr__( 'Course progress', 'journey-homeschool-academy' ),
		$progress_markup // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is generated and escaped below.
	);
}

/**
 * Get the course progress tracker inner markup.
 *
 * @param int $course_parent_id Top-level course parent ID.
 * @return string
 */
function jha_get_course_progress_tracker_markup( $course_parent_id ) {
	$course_parent_id = absint( $course_parent_id );

	if (
		! $course_parent_id ||
		! jha_page_is_in_course_template_system( $course_parent_id ) ||
		! shortcode_exists( 'progressally_objective_count' ) ||
		! shortcode_exists( 'progressally_objective_completed_count' )
	) {
		return '';
	}

	$total_objectives     = 0;
	$completed_objectives = 0;
	$course_pages         = jha_get_course_navigation_pages( $course_parent_id );

	foreach ( $course_pages as $course_page ) {
		$page_id = absint( $course_page->ID );

		if ( ! $page_id ) {
			continue;
		}

		$total_objectives += jha_get_progressally_count_from_shortcode(
			sprintf(
				'[progressally_objective_count post_id="%1$d"]',
				$page_id
			)
		);

		$completed_objectives += jha_get_progressally_count_from_shortcode(
			sprintf(
				'[progressally_objective_completed_count post_id="%1$d"]',
				$page_id
			)
		);
	}

	if ( ! $total_objectives ) {
		return '';
	}

	$completed_objectives = min( $completed_objectives, $total_objectives );
	$progress_percent     = (int) round( ( $completed_objectives / $total_objectives ) * 100 );

	return sprintf(
		'<div class="jha-course-progress-ring" style="%1$s"><span>%2$s</span></div>',
		esc_attr( '--jha-course-progress-percent: ' . $progress_percent . '%;' ),
		esc_html( $progress_percent . '%' )
	);
}

/**
 * Refresh course progress after ProgressAlly objectives update via AJAX.
 *
 * @return void
 */
function jha_ajax_get_course_progress_tracker() {
	check_ajax_referer( 'jha_course_progress', 'nonce' );

	$course_id = isset( $_POST['courseId'] ) ? absint( wp_unslash( $_POST['courseId'] ) ) : 0;

	if ( ! $course_id ) {
		wp_send_json_error();
	}

	wp_send_json_success(
		array(
			'html' => jha_get_course_progress_tracker_markup( $course_id ),
		)
	);
}
add_action( 'wp_ajax_jha_get_course_progress_tracker', 'jha_ajax_get_course_progress_tracker' );
add_action( 'wp_ajax_nopriv_jha_get_course_progress_tracker', 'jha_ajax_get_course_progress_tracker' );

/**
 * Render one course menu item and any expanded nested children.
 *
 * Nested children stay collapsed unless their parent is the active page or an
 * ancestor of the active page. This keeps the first version readable while
 * still supporting sub-lessons.
 *
 * @param WP_Post $page Page object to render.
 * @param int     $current_post_id Current page ID.
 * @param int[]   $current_ancestors Ancestor IDs for the current page.
 * @return void
 */
function jha_render_course_menu_item( $page, $current_post_id, $current_ancestors ) {
	$page_id = absint( $page->ID );

	if ( ! jha_course_page_is_visible_in_menu( $page_id ) ) {
		return;
	}

	$children     = jha_get_course_child_pages( $page_id );
	$is_active    = ( $page_id === absint( $current_post_id ) );
	$is_ancestor  = in_array( $page_id, $current_ancestors, true );
	$has_children = ! empty( $children );

	$classes = array( 'jha-course-menu-item' );

	if ( $is_active ) {
		$classes[] = 'is-active';
	}

	if ( $is_ancestor ) {
		$classes[] = 'is-ancestor';
	}

	if ( $has_children ) {
		$classes[] = 'has-children';
	}

	if ( $has_children && ( $is_active || $is_ancestor ) ) {
		$classes[] = 'is-expanded';
	}

	$submenu_id = 'jha-course-submenu-' . $page_id;

	printf(
		'<li class="%1$s"><a class="jha-course-menu-link" href="%2$s">%3$s</a>',
		esc_attr( implode( ' ', $classes ) ),
		esc_url( get_permalink( $page_id ) ),
		esc_html( jha_get_course_menu_label( $page_id ) )
	);

	if ( $has_children ) {
		printf(
			'<button class="jha-course-submenu-toggle" type="button" aria-expanded="%1$s" aria-controls="%2$s"><span class="screen-reader-text">%3$s</span></button>',
			$is_active || $is_ancestor ? 'true' : 'false',
			esc_attr( $submenu_id ),
			esc_html__( 'Toggle sub-lessons', 'journey-homeschool-academy' )
		);

		echo '<ul id="' . esc_attr( $submenu_id ) . '" class="jha-course-submenu">';

		foreach ( $children as $child ) {
			jha_render_course_menu_item( $child, $current_post_id, $current_ancestors );
		}

		echo '</ul>';
	}

	echo '</li>';
}

/**
 * Whether a menu tree node contains the current page or one of its ancestors.
 *
 * @param array $node Menu tree node.
 * @param int   $current_post_id Current page ID.
 * @param int[] $current_ancestors Ancestor IDs for the current page.
 * @return bool
 */
function jha_course_menu_node_contains_current( $node, $current_post_id, $current_ancestors ) {
	if ( ! is_array( $node ) ) {
		return false;
	}

	if ( isset( $node['type'], $node['page'] ) && 'page' === $node['type'] && $node['page'] instanceof WP_Post ) {
		$page_id = absint( $node['page']->ID );

		if ( $page_id === absint( $current_post_id ) || in_array( $page_id, $current_ancestors, true ) ) {
			return true;
		}
	}

	if ( empty( $node['children'] ) || ! is_array( $node['children'] ) ) {
		return false;
	}

	foreach ( $node['children'] as $child ) {
		if ( jha_course_menu_node_contains_current( $child, $current_post_id, $current_ancestors ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Render one course menu tree node. Nodes can be real pages or menu-only labels.
 *
 * @param array $node Current menu node.
 * @param int   $current_post_id Current page ID.
 * @param int[] $current_ancestors Ancestor IDs for the current page.
 * @return void
 */
function jha_render_course_menu_node( $node, $current_post_id, $current_ancestors ) {
	if ( ! is_array( $node ) || empty( $node['type'] ) ) {
		return;
	}

	$children     = isset( $node['children'] ) && is_array( $node['children'] ) ? $node['children'] : array();
	$has_children = ! empty( $children );
	$classes      = array( 'jha-course-menu-item' );
	$token        = sanitize_key( (string) ( $node['token'] ?? wp_unique_id( 'menu-' ) ) );
	$submenu_id   = 'jha-course-submenu-' . $token;
	$is_active         = false;
	$is_ancestor       = false;
	$contains_current  = jha_course_menu_node_contains_current( $node, $current_post_id, $current_ancestors );
	$should_be_expanded = $has_children && $contains_current;

	if ( 'page' === $node['type'] && ! empty( $node['page'] ) && $node['page'] instanceof WP_Post ) {
		$page_id = absint( $node['page']->ID );

		if ( ! jha_course_page_is_visible_in_menu( $page_id ) ) {
			return;
		}

		$is_active   = ( $page_id === absint( $current_post_id ) );
		$is_ancestor = in_array( $page_id, $current_ancestors, true );

		if ( $is_active ) {
			$classes[] = 'is-active';
		}

		if ( $is_ancestor ) {
			$classes[] = 'is-ancestor';
		}

		if ( $has_children ) {
			$classes[] = 'has-children';
		}

		if ( $should_be_expanded ) {
			$classes[] = 'is-expanded';
		}

		printf(
			'<li class="%1$s"><a class="jha-course-menu-link" href="%2$s">%3$s</a>',
			esc_attr( implode( ' ', $classes ) ),
			esc_url( get_permalink( $page_id ) ),
			esc_html( jha_get_course_menu_label( $page_id ) )
		);
	} else {
		if ( $has_children ) {
			$classes[] = 'has-children';
		}

		if ( $should_be_expanded ) {
			$classes[] = 'is-expanded';
		}

		printf(
			'<li class="%1$s"><span class="jha-course-menu-link jha-course-menu-label">%2$s</span>',
			esc_attr( implode( ' ', $classes ) ),
			esc_html( $node['title'] ?? '' )
		);
	}

	if ( $has_children ) {
		printf(
			'<button class="jha-course-submenu-toggle" type="button" aria-expanded="%1$s" aria-controls="%2$s"><span class="screen-reader-text">%3$s</span></button>',
			$should_be_expanded ? 'true' : 'false',
			esc_attr( $submenu_id ),
			esc_html__( 'Toggle sub-lessons', 'journey-homeschool-academy' )
		);

		echo '<ul id="' . esc_attr( $submenu_id ) . '" class="jha-course-submenu">';

		foreach ( $children as $child ) {
			jha_render_course_menu_node( $child, $current_post_id, $current_ancestors );
		}

		echo '</ul>';
	}

	echo '</li>';
}

/**
 * Add a page and all of its children to a flat course navigation list.
 *
 * The order matches the sidebar menu: parent first, then nested children.
 *
 * @param int   $parent_id Parent page ID.
 * @param array $pages Flat page list passed by reference.
 * @param bool  $include_hidden Whether to include pages hidden from the course menu.
 * @param bool  $filter_access Whether to exclude pages the current user cannot access.
 * @return void
 */
function jha_append_course_navigation_pages( $parent_id, &$pages, $include_hidden = false, $filter_access = true ) {
	foreach ( jha_get_course_menu_tree( $parent_id, $include_hidden, $filter_access ) as $node ) {
		jha_append_course_navigation_node_pages( $node, $pages );
	}
}

/**
 * Add all real page nodes from a menu tree to a flat course navigation list.
 *
 * @param array $node Current menu node.
 * @param array $pages Flat page list passed by reference.
 * @return void
 */
function jha_append_course_navigation_node_pages( $node, &$pages ) {
	if ( ! is_array( $node ) ) {
		return;
	}

	if ( isset( $node['type'], $node['page'] ) && 'page' === $node['type'] && $node['page'] instanceof WP_Post ) {
		$pages[] = $node['page'];
	}

	if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
		foreach ( $node['children'] as $child ) {
			jha_append_course_navigation_node_pages( $child, $pages );
		}
	}
}

/**
 * Get all course pages in the same order shown in the sidebar menu.
 *
 * @param int  $course_parent_id Top-level course parent ID.
 * @param bool $include_hidden Whether to include pages hidden from the course menu.
 * @param bool $filter_access Whether to exclude pages the current user cannot access.
 * @return WP_Post[] Ordered course pages.
 */
function jha_get_course_navigation_pages( $course_parent_id, $include_hidden = false, $filter_access = true ) {
	$course_parent_id = absint( $course_parent_id );
	$course_parent    = get_post( $course_parent_id );
	$pages            = array();

	if ( ! $course_parent_id || ! jha_page_is_in_course_template_system( $course_parent_id ) ) {
		return $pages;
	}

	if ( $course_parent && 'page' === $course_parent->post_type ) {
		if ( ! $filter_access || jha_user_can_access_course_page( $course_parent_id ) ) {
			$pages[] = $course_parent;
		}
	}

	jha_append_course_navigation_pages( $course_parent_id, $pages, $include_hidden, $filter_access );

	return $pages;
}

/**
 * Render the full course sidebar menu.
 *
 * The top-level course parent is rendered first as the welcome/course home
 * item. Direct children are rendered below it as lesson items, with nested
 * child pages available inside their active branch.
 *
 * @param int $course_parent_id Top-level course parent ID.
 * @param int $current_post_id Current page ID.
 * @return void
 */
function jha_render_course_sidebar( $course_parent_id, $current_post_id ) {
	$course_parent_id  = absint( $course_parent_id );
	$current_post_id   = absint( $current_post_id );
	$current_ancestors = array_map( 'absint', get_post_ancestors( $current_post_id ) );
	$children          = jha_get_course_menu_tree( $course_parent_id );
	$parent_classes    = array( 'jha-course-menu-item', 'jha-course-parent-item' );

	if ( $course_parent_id === $current_post_id ) {
		$parent_classes[] = 'is-active';
	}

	if ( in_array( $course_parent_id, $current_ancestors, true ) ) {
		$parent_classes[] = 'is-ancestor';
	}

	if ( ! empty( $children ) ) {
		$parent_classes[] = 'has-children';
	}

	echo '<aside class="jha-course-sidebar" aria-label="' . esc_attr__( 'Course menu', 'journey-homeschool-academy' ) . '">';
	jha_render_course_logo( $course_parent_id );
	echo '<nav class="jha-course-nav" aria-label="' . esc_attr__( 'Course lessons', 'journey-homeschool-academy' ) . '">';
	echo '<button class="jha-course-mobile-menu-toggle" type="button" aria-expanded="false" aria-controls="jha-course-lessons-panel">' . esc_html__( 'Lessons', 'journey-homeschool-academy' ) . '</button>';
	echo '<div id="jha-course-lessons-panel" class="jha-course-mobile-menu-panel">';
	echo '<ul class="jha-course-menu">';

	printf(
		'<li class="%1$s"><a class="jha-course-menu-link" href="%2$s">%3$s</a></li>',
		esc_attr( implode( ' ', $parent_classes ) ),
		esc_url( get_permalink( $course_parent_id ) ),
		esc_html( jha_get_course_menu_label( $course_parent_id ) )
	);

	foreach ( $children as $child ) {
		jha_render_course_menu_node( $child, $current_post_id, $current_ancestors );
	}

	echo '</ul>';

	if ( jha_should_show_course_progress( $current_post_id ) ) {
		jha_render_course_progress_tracker( $course_parent_id );
	}

	echo '</div>';
	echo '</nav>';
	echo '</aside>';
}
