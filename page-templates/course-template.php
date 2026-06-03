<?php
/**
 * Template Name: Course Template
 *
 * Lightweight course/lesson page template.
 *
 * Assign this template in the WordPress Page editor for the top-level course
 * page and any nested lesson pages. Content remains editable in Gutenberg and
 * can include AccessAlly blocks or shortcodes because the template outputs
 * normal the_content().
 *
 * @package Journey_Homeschool_Academy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="main-content" class="jha-course-template" role="main">
	<?php
	while ( have_posts() ) :
		the_post();

		// The course parent is the top-level page in the current page tree.
		$course_parent_id = jha_get_course_parent_id( get_the_ID() );
		$block_gap        = jha_get_course_block_gap( get_the_ID() );
		?>

		<div class="jha-course-layout">
			<?php jha_render_course_sidebar( $course_parent_id, get_the_ID() ); ?>

			<article id="post-<?php the_ID(); ?>" <?php post_class( 'jha-course-main' ); ?>>
				<div class="jha-course-content" style="<?php echo esc_attr( '--jha-course-block-gap: ' . $block_gap . ';' ); ?>">
					<?php
					if ( jha_should_show_course_title( get_the_ID() ) ) {
						the_title( '<h1 class="jha-course-title">', '</h1>' );
					}

					the_content();

					wp_link_pages(
						array(
							'before' => '<nav class="page-links" aria-label="' . esc_attr__( 'Page', 'journey-homeschool-academy' ) . '">',
							'after'  => '</nav>',
						)
					);
					?>
				</div>
			</article>
		</div>

	<?php endwhile; ?>
</main>

<?php
get_footer();
