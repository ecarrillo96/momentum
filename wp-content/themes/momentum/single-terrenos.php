<?php
/**
 * Single Terreno — property detail page.
 *
 * WordPress selects this file automatically for single posts of the
 * "terrenos" CPT (single-{post_type}.php takes priority over single.php).
 *
 * Reutiliza el mismo layout que Propiedades (template-parts/property/detail.php):
 * ese template-part ya oculta recámaras/baños/construcción cuando
 * construccion_m2 <= 0, que es siempre el caso para un terreno.
 *
 * @package Momentum
 */

get_header();

while (have_posts()) :
	the_post();

	get_template_part('template-parts/property/detail');

endwhile;

get_footer();
