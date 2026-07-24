<?php
/**
 * Reusable property listing: filters + result grid + pagination.
 *
 * Usage:
 *   get_template_part('template-parts/property-listing', null, [
 *       'fixed_operacion' => 'Venta',
 *   ]);
 *
 *   get_template_part('template-parts/property-listing', null, [
 *       'post_type' => 'terrenos',
 *   ]);
 *
 * Used by: Venta, Renta, Terrenos.
 */

if (! defined('ABSPATH')) {
	exit;
}

$post_type = $args['post_type'] ?? 'propiedades';
$fixed     = [
	'tipo_operacion' => $args['fixed_operacion'] ?? '',
];
$per_page  = 12;

$result = momentum_run_property_query($fixed, $per_page, null, $post_type);
/** @var WP_Query $query */
$query    = $result['query'];
$filters  = $result['filters'];
$paged    = $result['paged'];

$total = $query->found_posts;
$start = $total ? (($paged - 1) * $per_page) + 1 : 0;
$end   = min($paged * $per_page, $total);

$current_url = strtok($_SERVER['REQUEST_URI'], '?');
$has_filters = $filters['codigo'] !== '' || $filters['precio'] !== '' || $filters['recamaras'] !== '' || $filters['orden'] !== 'precio_desc';
?>
<div class="momentum-listing">
	<form class="momentum-filters" method="get" action="<?php echo esc_url($current_url); ?>">
		<div class="momentum-filters__field">
			<label for="filtro-codigo">Buscar por código</label>
			<input type="text" id="filtro-codigo" name="codigo" value="<?php echo esc_attr($filters['codigo']); ?>" placeholder="<?php echo esc_attr($post_type === 'terrenos' ? 'Ej. MT-001' : 'Ej. MC-001'); ?>">
		</div>

		<div class="momentum-filters__field">
			<label for="filtro-precio">Rango de precio</label>
			<select id="filtro-precio" name="precio">
				<?php foreach (momentum_price_range_options($fixed['tipo_operacion']) as $value => $label) : ?>
					<option value="<?php echo esc_attr($value); ?>" <?php selected($filters['precio'], $value); ?>><?php echo esc_html($label); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<?php if ($post_type !== 'terrenos') : ?>
			<div class="momentum-filters__field">
				<label for="filtro-recamaras">Recámaras</label>
				<select id="filtro-recamaras" name="recamaras">
					<?php foreach (momentum_bedroom_options() as $value => $label) : ?>
						<option value="<?php echo esc_attr($value); ?>" <?php selected($filters['recamaras'], $value); ?>><?php echo esc_html($label); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		<?php endif; ?>

		<div class="momentum-filters__field">
			<label for="filtro-orden">Ordenar por</label>
			<select id="filtro-orden" name="orden">
				<?php foreach (momentum_sort_options() as $value => $label) : ?>
					<option value="<?php echo esc_attr($value); ?>" <?php selected($filters['orden'], $value); ?>><?php echo esc_html($label); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<button type="submit" class="momentum-btn momentum-btn--primary">Filtrar</button>

		<?php if ($has_filters) : ?>
			<a class="momentum-btn momentum-btn--outline" href="<?php echo esc_url($current_url); ?>">Limpiar filtro</a>
		<?php endif; ?>
	</form>

	<p class="momentum-listing__count">
		<?php if ($total > 0) : ?>
			Mostrando <?php echo esc_html($start); ?>–<?php echo esc_html($end); ?> de <?php echo esc_html($total); ?> propiedades
		<?php else : ?>
			No se encontraron propiedades con estos filtros.
		<?php endif; ?>
	</p>

	<?php if ($query->have_posts()) : ?>
		<div class="momentum-property-grid">
			<?php $card_index = 0; ?>
			<?php while ($query->have_posts()) : $query->the_post(); ?>
				<?php
				// Only the very first card on the first page is the page's LCP
				// element (verified with Lighthouse); every other card lazy-loads.
				set_query_var('momentum_property_priority', $paged === 1 && $card_index === 0);
				get_template_part('template-parts/property-card');
				$card_index++;
				?>
			<?php endwhile; ?>
		</div>

		<?php momentum_render_property_pagination($paged, (int) $query->max_num_pages); ?>
	<?php endif; ?>

	<?php wp_reset_postdata(); ?>
</div>
