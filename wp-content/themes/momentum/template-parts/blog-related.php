<?php
/**
 * Single post — otros artículos recientes (excluye el actual). No filtra por
 * categoría: el blog no expone esa taxonomía en la UI.
 */

if (! defined('ABSPATH')) {
	exit;
}

$current_id = get_the_ID();

$query = new WP_Query([
	'post_type'      => 'post',
	'post_status'    => 'publish',
	'posts_per_page' => 3,
	'post__not_in'   => [$current_id],
	'orderby'        => 'date',
	'order'          => 'DESC',
]);

if (! $query->have_posts()) {
	return;
}
?>
<section class="momentum-section momentum-section--light">
	<div class="momentum-container">
		<div class="momentum-section-heading">
			<div>
				<span class="momentum-eyebrow">También te puede interesar</span>
				<h2>Artículos relacionados</h2>
			</div>
		</div>

		<div class="momentum-blog-grid">
			<?php while ($query->have_posts()) : $query->the_post(); ?>
				<article class="momentum-blog-card">
					<a class="momentum-blog-card__media" href="<?php the_permalink(); ?>">
						<?php if (has_post_thumbnail()) : ?>
							<?php
							// Always below the article content — never the LCP element.
							echo wp_get_attachment_image(
								get_post_thumbnail_id(),
								'large',
								false,
								[
									'sizes'    => '(max-width: 680px) 100vw, (max-width: 1024px) 50vw, 392px',
									'loading'  => 'lazy',
									'decoding' => 'async',
								]
							);
							?>
						<?php endif; ?>
					</a>

					<div class="momentum-blog-card__body">
						<h3 class="momentum-blog-card__title">
							<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
						</h3>

						<p class="momentum-blog-card__excerpt"><?php echo esc_html(wp_trim_words(get_the_excerpt(), 20)); ?></p>

						<a class="momentum-blog-card__link" href="<?php the_permalink(); ?>">Leer más →</a>
					</div>
				</article>
			<?php endwhile; ?>
		</div>
	</div>
</section>
<?php
wp_reset_postdata();
