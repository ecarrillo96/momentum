<?php
/**
 * Reusable blog preview grid (image + title + excerpt + "Leer más").
 *
 * Usage:
 *   get_template_part('template-parts/blog-grid', null, [
 *       'heading'        => 'Todos los artículos',
 *       'posts_per_page' => 9,
 *   ]);
 *
 * Used by: Home (below "Lo que hacemos"), Blog page.
 */

if (! defined('ABSPATH')) {
	exit;
}

$eyebrow    = $args['eyebrow'] ?? __('Blog', 'momentum');
$heading    = $args['heading'] ?? __('Artículos para tu decisión de compra', 'momentum');
$subtitle   = $args['subtitle'] ?? __('Consejos y contexto sobre Lomas de Cocoyoc y la región para ayudarte a decidir mejor.', 'momentum');
$per_page   = $args['posts_per_page'] ?? 3;

// Only the Blog page sets this: its page-hero has no image, so the first
// card's image is that page's LCP element (confirmed with Lighthouse). On
// Home this grid sits far below the hero video, so it stays lazy there.
$priority_first = $args['priority_first'] ?? false;

// Same 3/2/1-column grid and 1280px container as property-card.php.
$image_sizes = '(max-width: 680px) 100vw, (max-width: 1024px) 50vw, 392px';

// Paginación es opt-in (usada solo por la página Blog) para no cambiar el
// bloque compacto que Home ya muestra sin args.
$paginate   = $args['paginate'] ?? false;

// Fondo oscuro opt-in: solo Home lo usa (alterna con las secciones claras
// de alrededor); la página Blog completa se queda con fondo claro.
$dark       = $args['dark'] ?? false;

$paged      = $paginate && isset($_GET['pg']) ? max(1, absint($_GET['pg'])) : 1;

$query_args = [
	'post_type'      => 'post',
	'post_status'    => 'publish',
	'posts_per_page' => $per_page,
	'paged'          => $paged,
	'orderby'        => 'date',
	'order'          => 'DESC',
	// Sin esto, en un idioma sin posts traducidos WP_Query devolvía los
	// posts en español igual (WordPress no filtra por idioma por su cuenta)
	// -inc/property-query.php ya hace esto mismo para propiedades/terrenos,
	// por la misma razón, ver el comentario allá-.
	'lang'           => momentum_current_language(),
];

$blog_query = new WP_Query($query_args);

// En modo compacto (Home, sin paginar) no hay nada que mostrar si el
// idioma actual no tiene posts: la sección entera se omite, igual que
// siempre. En la página de Blog dedicada ($paginate = true) el visitante ya
// navegó ahí a propósito, así que en vez de desaparecer sin explicación se
// deja caer al mensaje "No se encontraron artículos" que ya existe más abajo.
if (! $blog_query->have_posts() && ! $paginate) {
	return;
}
?>

<section class="momentum-section<?php echo $dark ? ' momentum-section--dark' : ''; ?>">
	<div class="momentum-container">
		<div class="momentum-section-heading">
			<div>
				<span class="momentum-eyebrow"><?php echo esc_html($eyebrow); ?></span>
				<h2><?php echo esc_html($heading); ?></h2>
				<p><?php echo esc_html($subtitle); ?></p>
			</div>

			<?php if (! $paginate) : ?>
				<a class="momentum-section-heading__link" href="<?php echo esc_url(momentum_url_for_page('blog')); ?>"><?php esc_html_e('Ver todos los artículos →', 'momentum'); ?></a>
			<?php endif; ?>
		</div>

		<?php if ($blog_query->have_posts()) : ?>
			<div class="momentum-blog-grid">
				<?php $card_index = 0; ?>
				<?php while ($blog_query->have_posts()) : $blog_query->the_post(); ?>
					<?php $is_priority = $priority_first && $paged === 1 && $card_index === 0; ?>
					<article class="momentum-blog-card">
						<a class="momentum-blog-card__media" href="<?php the_permalink(); ?>">
							<?php if (has_post_thumbnail()) : ?>
								<?php
								$thumbnail_id  = get_post_thumbnail_id();
								$thumbnail_alt = momentum_attachment_alt($thumbnail_id, get_the_title());

								echo wp_get_attachment_image(
									$thumbnail_id,
									'large',
									false,
									$is_priority
										? [
											'alt'           => $thumbnail_alt,
											'sizes'         => $image_sizes,
											'loading'       => 'eager',
											'fetchpriority' => 'high',
											'decoding'      => 'async',
										]
										: [
											'alt'      => $thumbnail_alt,
											'sizes'    => $image_sizes,
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

							<a class="momentum-blog-card__link" href="<?php the_permalink(); ?>"><?php esc_html_e('Leer más →', 'momentum'); ?></a>
						</div>
					</article>
					<?php $card_index++; ?>
				<?php endwhile; ?>
			</div>

			<?php if ($paginate) : ?>
				<?php
				$pagination = paginate_links([
					'base'      => add_query_arg('pg', '%#%'),
					'format'    => '',
					'current'   => $paged,
					'total'     => $blog_query->max_num_pages,
					'prev_text' => '‹',
					'next_text' => '›',
					'type'      => 'plain',
				]);
				?>
				<?php if ($pagination) : ?>
					<nav class="momentum-pagination" aria-label="<?php esc_attr_e('Paginación del blog', 'momentum'); ?>">
						<?php echo wp_kses_post($pagination); ?>
					</nav>
				<?php endif; ?>
			<?php endif; ?>
		<?php else : ?>
			<p class="momentum-blog-empty"><?php esc_html_e('No se encontraron artículos.', 'momentum'); ?></p>
		<?php endif; ?>
	</div>
</section>
<?php
wp_reset_postdata();
