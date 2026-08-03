<?php
/**
 * Property detail — 3 similar properties (same tipo_operacion, same post
 * type), via WP_Query. Reutilizado por Terrenos: 'tipo_operacion' no aplica
 * ahí (ese CPT no guarda ese meta, $data['tipo_operacion'] llega vacío), así
 * que esa condición se omite y solo queda post_type + estado.
 */

if (! defined('ABSPATH')) {
	exit;
}

$current_id = get_the_ID();
$data       = momentum_get_propiedad($current_id);

$meta_query = [
	'relation' => 'AND',
	['key' => 'estado', 'value' => momentum_public_estado_values(), 'compare' => 'IN'],
];

if (! empty($data['tipo_operacion'])) {
	$meta_query[] = ['key' => 'tipo_operacion', 'value' => $data['tipo_operacion'], 'compare' => '='];
}

$query = new WP_Query([
	'post_type'      => get_post_type($current_id),
	'post_status'    => 'publish',
	'posts_per_page' => 3,
	'post__not_in'   => [$current_id],
	'orderby'        => 'rand',
	'meta_query'     => $meta_query,
]);

if (! $query->have_posts()) {
	return;
}
?>
<section class="momentum-section momentum-section--light">
	<div class="momentum-container">
		<div class="momentum-section-heading">
			<div>
				<span class="momentum-eyebrow"><?php esc_html_e('También te puede interesar', 'momentum'); ?></span>
				<h2><?php esc_html_e('Propiedades similares', 'momentum'); ?></h2>
			</div>
		</div>

		<div class="momentum-property-grid">
			<?php while ($query->have_posts()) : $query->the_post(); ?>
				<?php get_template_part('template-parts/property-card'); ?>
			<?php endwhile; ?>
		</div>
	</div>
</section>
<?php
wp_reset_postdata();
