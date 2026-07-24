<?php
/**
 * Home — Propiedades destacadas.
 *
 * Pulls the latest published propiedades via WP_Query. No static arrays,
 * so this section reflects real content as soon as it exists.
 */

if (! defined('ABSPATH')) {
	exit;
}

$query = new WP_Query([
	'post_type'      => 'propiedades',
	'post_status'    => 'publish',
	'posts_per_page' => 6,
	'orderby'        => 'date',
	'order'          => 'DESC',
	'meta_query'     => [
		['key' => 'estado', 'value' => momentum_public_estado_values(), 'compare' => 'IN'],
	],
]);
?>
<section class="momentum-section">
	<div class="momentum-container">
		<div class="momentum-section-heading">
			<div>
				<span class="momentum-eyebrow">Propiedades destacadas</span>
				<h2>Selección de propiedades disponibles</h2>
				<p>Una muestra de las casas, departamentos y terrenos disponibles actualmente en Lomas de Cocoyoc.</p>
			</div>
		</div>

		<?php if ($query->have_posts()) : ?>
			<div class="momentum-property-grid">
				<?php while ($query->have_posts()) : $query->the_post(); ?>
					<?php get_template_part('template-parts/property-card'); ?>
				<?php endwhile; ?>
			</div>
		<?php endif; ?>

		<?php wp_reset_postdata(); ?>
	</div>
</section>
