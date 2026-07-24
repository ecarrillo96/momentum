<?php
/**
 * Nosotros — about page.
 *
 * WordPress selects this file automatically for the page with slug
 * "nosotros" (page-{slug}.php takes priority over page.php).
 *
 * @package Momentum
 */

get_header();

get_template_part('template-parts/page-hero', null, [
	'title'    => 'Nosotros',
	'subtitle' => 'Más de 15 años acompañando a familias en Lomas de Cocoyoc.',
]);

get_template_part('template-parts/nosotros/content');

get_footer();
