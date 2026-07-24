<?php
/**
 * Renta — property listing page.
 *
 * WordPress selects this file automatically for the page with slug
 * "renta" (page-{slug}.php takes priority over page.php).
 *
 * @package Momentum
 */

// Corrige ?pg= fuera de rango antes de emitir cualquier salida (get_header()
// ya envía HTML, así que un redirect después de eso fallaría).
momentum_guard_property_listing_page(['tipo_operacion' => 'Renta'], 10);

get_header();

get_template_part('template-parts/page-hero', null, [
	'title'    => 'Propiedades en renta',
	'subtitle' => 'Encuentra la casa o terreno ideal en Lomas de Cocoyoc',
]);
?>

<section class="momentum-section momentum-listing-section">
	<div class="momentum-container momentum-listing-layout">
		<div class="momentum-listing-layout__main">
			<?php
			get_template_part('template-parts/property-listing', null, [
				'fixed_operacion' => 'Renta',
			]);
			?>
		</div>

		<aside class="momentum-listing-layout__sidebar">
			<?php get_template_part('template-parts/sidebar-cta'); ?>
			<?php get_template_part('template-parts/venta-blog'); ?>
		</aside>
	</div>
</section>

<?php
get_footer();
