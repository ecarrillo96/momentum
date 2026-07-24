<?php
/**
 * Single blog post — article detail page.
 *
 * WordPress selects this file automatically for single standard posts
 * (single.php), so the same featured image shown in the blog previews
 * (Home, Blog page, Venta sidebar) also appears inside the article.
 *
 * @package Momentum
 */

get_header();

while (have_posts()) :
	the_post();

	get_template_part('template-parts/page-hero', null, [
		'title'    => get_the_title(),
		'subtitle' => get_the_excerpt(),
	]);
	?>

	<section class="momentum-section">
		<div class="momentum-container">
			<div class="momentum-article-meta">
				<span class="momentum-article-meta__date"><?php the_date(); ?></span>
			</div>

			<?php if (has_post_thumbnail()) : ?>
				<?php
				// Confirmed via Lighthouse: this image is the LCP element on
				// single blog post pages (page-hero above it is text-only).
				// '1536x1536' avoids shipping the raw original at 'full' while
				// staying sharp at the prose's full display width; falls back
				// to 'full' automatically if that intermediate size is missing.
				echo wp_get_attachment_image(
					get_post_thumbnail_id(),
					'1536x1536',
					false,
					[
						'class'         => 'momentum-featured-photo',
						'sizes'         => '(max-width: 1280px) 100vw, 1232px',
						'loading'       => 'eager',
						'fetchpriority' => 'high',
						'decoding'      => 'async',
					]
				);
				?>
			<?php endif; ?>

			<div class="momentum-prose momentum-prose--wide momentum-article-prose">
				<?php the_content(); ?>
			</div>

			<?php if (has_tag()) : ?>
				<div class="momentum-article-tags">
					<?php the_tags('', ' '); ?>
				</div>
			<?php endif; ?>

			<p class="momentum-article-back">
				<a href="<?php echo esc_url(home_url('/blog/')); ?>">&larr; Volver al blog</a>
			</p>
		</div>
	</section>

	<?php get_template_part('template-parts/blog-related'); ?>

	<?php
endwhile;

get_footer();
