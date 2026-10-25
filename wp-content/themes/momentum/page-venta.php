<?php
/**
 * Venta — property listing page.
 *
 * WordPress selects this file automatically for the page with slug
 * "venta" (page-{slug}.php takes priority over page.php).
 *
 * @package Momentum
 */

// Corrige ?pg= fuera de rango antes de emitir cualquier salida (get_header()
// ya envía HTML, así que un redirect después de eso fallaría).
momentum_guard_property_listing_page(['tipo_operacion' => 'Venta'], 10);

get_header();

get_template_part('template-parts/page-hero', null, [
	'title' => __('Propiedades en venta', 'momentum'),
]);
?>

<section class="momentum-section momentum-listing-section">
	<div class="momentum-container momentum-listing-layout">
		<div class="momentum-listing-layout__main">
			<?php
			get_template_part('template-parts/property-listing', null, [
				'fixed_operacion' => 'Venta',
			]);
			?>
		</div>

		<aside class="momentum-listing-layout__sidebar">
			<?php get_template_part('template-parts/sidebar-cta'); ?>
			<?php get_template_part('template-parts/sidebar-cta-sell'); ?>
			<?php get_template_part('template-parts/venta-blog'); ?>
		</aside>
	</div>
</section>

<?php
get_footer();
