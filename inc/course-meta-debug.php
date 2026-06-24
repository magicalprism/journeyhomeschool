<?php
/**
 * Temporary diagnostics for course/lesson post-meta and hierarchy changes.
 *
 * Enable in wp-config.php:
 * define( 'JHA_LESSON_META_DEBUG', true );
 *
 * Logs are written to the PHP error log (WP_DEBUG_LOG recommended).
 *
 * @package Journey_Homeschool_Academy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether lesson meta debug logging is enabled.
 *
 * @return bool
 */
function jha_lesson_meta_debug_enabled() {
	return defined( 'JHA_LESSON_META_DEBUG' ) && JHA_LESSON_META_DEBUG;
}

/**
 * Write a structured debug line to the error log.
 *
 * @param string               $message Log message.
 * @param array<string, mixed> $context Optional context.
 * @return void
 */
function jha_lesson_meta_debug_log( $message, $context = array() ) {
	if ( ! jha_lesson_meta_debug_enabled() ) {
		return;
	}

	$parts = array(
		'[LESSON META DEBUG]',
		gmdate( 'Y-m-d H:i:s' ),
		'user=' . get_current_user_id(),
		'uri=' . ( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' ),
		$message,
	);

	if ( ! empty( $context ) ) {
		$encoded = wp_json_encode( $context );

		if ( is_string( $encoded ) ) {
			$parts[] = $encoded;
		}
	}

	$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 8 );
	$trace = array_map(
		static function ( $frame ) {
			$file = isset( $frame['file'] ) ? basename( (string) $frame['file'] ) : '';
			$line = isset( $frame['line'] ) ? (int) $frame['line'] : 0;
			$func = isset( $frame['function'] ) ? (string) $frame['function'] : '';

			return $file . ':' . $line . ' ' . $func . '()';
		},
		$trace
	);

	$parts[] = 'trace=' . implode( ' > ', $trace );

	error_log( implode( ' | ', $parts ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
}

/**
 * Summarize a meta value for logging without dumping huge payloads.
 *
 * @param mixed $value Meta value.
 * @return array<string, mixed>
 */
function jha_lesson_meta_debug_summarize_value( $value ) {
	if ( is_array( $value ) || is_object( $value ) ) {
		$encoded = wp_json_encode( $value );

		return array(
			'type' => 'structured',
			'len'  => is_string( $encoded ) ? strlen( $encoded ) : 0,
			'hash' => is_string( $encoded ) ? substr( md5( $encoded ), 0, 12 ) : '',
		);
	}

	$string = is_scalar( $value ) ? (string) $value : '';

	return array(
		'type' => 'scalar',
		'len'  => strlen( $string ),
		'hash' => '' !== $string ? substr( md5( $string ), 0, 12 ) : '',
	);
}

/**
 * Whether a meta key should be logged.
 *
 * @param string $meta_key Meta key.
 * @param int    $post_id  Post ID.
 * @return bool
 */
function jha_lesson_meta_debug_should_log_meta_key( $meta_key, $post_id ) {
	$meta_key = (string) $meta_key;
	$needles  = array( 'progressally', 'accessally', 'objective', 'quiz', 'ally' );

	foreach ( $needles as $needle ) {
		if ( false !== stripos( $meta_key, $needle ) ) {
			return true;
		}
	}

	if ( 0 === strpos( $meta_key, '_jha_course_' ) ) {
		return true;
	}

	$post_type = get_post_type( $post_id );

	return 'page' === $post_type;
}

/**
 * Log post-meta updates/deletes initiated while debug mode is on.
 *
 * @param mixed  $check      Short-circuit value.
 * @param int    $object_id  Post ID.
 * @param string $meta_key   Meta key.
 * @param mixed  $meta_value Meta value (update only).
 * @return mixed
 */
function jha_lesson_meta_debug_filter_post_meta_change( $check, $object_id, $meta_key, $meta_value = null ) {
	if ( ! jha_lesson_meta_debug_enabled() ) {
		return $check;
	}

	$object_id = jha_normalize_post_id( $object_id );

	if ( ! $object_id || ! jha_lesson_meta_debug_should_log_meta_key( (string) $meta_key, $object_id ) ) {
		return $check;
	}

	$action   = null === $meta_value ? 'delete_post_meta' : 'update_post_meta';
	$old_val  = get_post_meta( $object_id, $meta_key, true );
	$new_info = null === $meta_value ? array() : jha_lesson_meta_debug_summarize_value( $meta_value );

	jha_lesson_meta_debug_log(
		$action,
		array(
			'hook'       => current_filter(),
			'post_id'    => $object_id,
			'post_title' => get_the_title( $object_id ),
			'meta_key'   => $meta_key,
			'old'        => jha_lesson_meta_debug_summarize_value( $old_val ),
			'new'        => $new_info,
		)
	);

	return $check;
}
add_filter( 'update_post_metadata', 'jha_lesson_meta_debug_filter_post_meta_change', 10, 4 );
add_filter( 'delete_post_metadata', 'jha_lesson_meta_debug_filter_post_meta_change', 10, 3 );

/**
 * Log page insert/update operations that may affect lesson hierarchy.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 * @return void
 */
function jha_lesson_meta_debug_log_post_save( $post_id, $post ) {
	if ( ! jha_lesson_meta_debug_enabled() || ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
		return;
	}

	jha_lesson_meta_debug_log(
		'save_post_page',
		array(
			'hook'         => current_filter(),
			'post_id'      => absint( $post_id ),
			'post_title'   => $post->post_title,
			'post_parent'  => absint( $post->post_parent ),
			'menu_order'   => (int) $post->menu_order,
			'template'     => get_page_template_slug( $post_id ),
			'has_lesson_tree_meta' => (bool) get_post_meta( $post_id, '_jha_course_lesson_tree', true ),
		)
	);
}
add_action( 'save_post_page', 'jha_lesson_meta_debug_log_post_save', 1, 2 );

/**
 * Log wp_update_post / wp_insert_post calls for pages.
 *
 * @param int          $post_id Post ID.
 * @param WP_Post|null $post    Post object.
 * @return void
 */
function jha_lesson_meta_debug_log_wp_insert_post( $post_id, $post ) {
	if ( ! jha_lesson_meta_debug_enabled() || ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
		return;
	}

	jha_lesson_meta_debug_log(
		'wp_insert_post',
		array(
			'post_id'     => absint( $post_id ),
			'post_title'  => $post->post_title,
			'post_parent' => absint( $post->post_parent ),
			'menu_order'  => (int) $post->menu_order,
		)
	);
}
add_action( 'wp_insert_post', 'jha_lesson_meta_debug_log_wp_insert_post', 10, 2 );

/**
 * Export ProgressAlly/AccessAlly-related meta for before/after comparison.
 *
 * @param int[] $post_ids Page IDs.
 * @return array<int, array<string, mixed>>
 */
function jha_lesson_meta_debug_snapshot_postmeta( $post_ids ) {
	global $wpdb;

	$post_ids = array_values( array_filter( array_map( 'absint', (array) $post_ids ) ) );

	if ( empty( $post_ids ) ) {
		return array();
	}

	$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
	$sql          = "
		SELECT meta_id, post_id, meta_key, meta_value
		FROM {$wpdb->postmeta}
		WHERE post_id IN ($placeholders)
		  AND (
			meta_key LIKE '%progress%'
			OR meta_key LIKE '%access%'
			OR meta_key LIKE '%objective%'
			OR meta_key LIKE '%quiz%'
			OR meta_key LIKE '%ally%'
			OR meta_key LIKE '_jha_course_%'
		  )
		ORDER BY post_id, meta_key, meta_id
	";

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $post_ids ), ARRAY_A );

	$snapshot = array();

	foreach ( (array) $rows as $row ) {
		$post_id = absint( $row['post_id'] ?? 0 );

		if ( ! $post_id ) {
			continue;
		}

		if ( ! isset( $snapshot[ $post_id ] ) ) {
			$snapshot[ $post_id ] = array(
				'postmeta'   => array(),
				'objectives' => array(),
			);
		}

		$snapshot[ $post_id ]['postmeta'][] = array(
			'meta_id'  => absint( $row['meta_id'] ?? 0 ),
			'meta_key' => (string) ( $row['meta_key'] ?? '' ),
			'summary'  => jha_lesson_meta_debug_summarize_value( $row['meta_value'] ?? '' ),
		);
	}

	$objective_table = $wpdb->prefix . 'pa_post_objective';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $objective_table ) ) === $objective_table ) {
		$objective_sql = "
			SELECT id, post_id, objective_id, mapped_post_id, objective_type
			FROM {$objective_table}
			WHERE post_id IN ($placeholders)
			ORDER BY post_id, objective_id
		";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$objective_rows = $wpdb->get_results( $wpdb->prepare( $objective_sql, $post_ids ), ARRAY_A );

		foreach ( (array) $objective_rows as $row ) {
			$post_id = absint( $row['post_id'] ?? 0 );

			if ( ! $post_id ) {
				continue;
			}

			if ( ! isset( $snapshot[ $post_id ] ) ) {
				$snapshot[ $post_id ] = array(
					'postmeta'   => array(),
					'objectives' => array(),
				);
			}

			$snapshot[ $post_id ]['objectives'][] = array(
				'id'              => absint( $row['id'] ?? 0 ),
				'objective_id'    => absint( $row['objective_id'] ?? 0 ),
				'mapped_post_id'  => (int) ( $row['mapped_post_id'] ?? 0 ),
				'objective_type'  => (int) ( $row['objective_type'] ?? 0 ),
			);
		}
	}

	return $snapshot;
}

/**
 * Admin-ajax helper: snapshot postmeta for diagnostic comparison.
 *
 * POST: nonce, affectedId, controlId
 *
 * @return void
 */
function jha_lesson_meta_debug_ajax_snapshot() {
	if ( ! jha_lesson_meta_debug_enabled() || ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Debug mode disabled or insufficient permissions.' ), 403 );
	}

	check_ajax_referer( 'jha_lesson_meta_debug', 'nonce' );

	$affected_id = isset( $_POST['affectedId'] ) ? absint( wp_unslash( $_POST['affectedId'] ) ) : 0;
	$control_id  = isset( $_POST['controlId'] ) ? absint( wp_unslash( $_POST['controlId'] ) ) : 0;
	$post_ids    = array_filter( array( $affected_id, $control_id ) );

	if ( empty( $post_ids ) ) {
		wp_send_json_error( array( 'message' => 'Provide at least one page ID.' ), 400 );
	}

	$snapshot = jha_lesson_meta_debug_snapshot_postmeta( $post_ids );

	jha_lesson_meta_debug_log(
		'diagnostic_snapshot',
		array(
			'post_ids' => $post_ids,
			'rows'     => array_sum(
				array_map(
					static function ( $entry ) {
						if ( ! is_array( $entry ) ) {
							return 0;
						}

						return count( $entry['postmeta'] ?? array() ) + count( $entry['objectives'] ?? array() );
					},
					$snapshot
				)
			),
		)
	);

	wp_send_json_success(
		array(
			'snapshot'  => $snapshot,
			'timestamp' => gmdate( 'c' ),
		)
	);
}
add_action( 'wp_ajax_jha_lesson_meta_debug_snapshot', 'jha_lesson_meta_debug_ajax_snapshot' );
