<?php
/**
 * Venta — compact blog list for the sidebar, below "¿Necesitas asesoría?".
 *
 * Shows the latest blog posts as small link items; clicking one opens the
 * full post (standard WordPress single post template).
 */

if (! defined('ABSPATH')) {
	exit;
}

$blog_query = new WP_Query([
	'post_type'      => 'post',
	'post_status'    => 'publish',
	'posts_per_page' => 3,
	'orderby'        => 'date',
	'order'          => 'DESC',
]);

if (! $blog_query->have_posts()) {
	return;
}
?>
<div class="momentum-sidebar-blog">
	<h3><?php esc_html_e('Inspírate para tu próxima casa', 'momentum'); ?></h3>

	<div class="momentum-sidebar-blog__list">
		<?php while ($blog_query->have_posts()) : $blog_query->the_post(); ?>
			<a class="momentum-sidebar-blog__item" href="<?php the_permalink(); ?>">
				<span class="momentum-sidebar-blog__thumb">
					<?php if (has_post_thumbnail()) : ?>
						<?php
						echo wp_get_attachment_image(
							get_post_thumbnail_id(),
							'thumbnail',
							false,
							[
								'alt'      => momentum_attachment_alt(get_post_thumbnail_id(), get_the_title()),
								'loading'  => 'lazy',
								'decoding' => 'async',
							]
						);
						?>
					<?php endif; ?>
				</span>

				<span class="momentum-sidebar-blog__info">
					<span class="momentum-sidebar-blog__title"><?php the_title(); ?></span>
					<span class="momentum-sidebar-blog__link"><?php esc_html_e('Leer más →', 'momentum'); ?></span>
				</span>
			</a>
		<?php endwhile; ?>
	</div>
</div>
<?php
wp_reset_postdata();
