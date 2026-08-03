<?php
/**
 * Construcción — page with a large aerial-style hero image.
 *
 * WordPress selects this file automatically for the page with slug
 * "construccion" (page-{slug}.php takes priority over page.php).
 *
 * @package Momentum
 */

get_header();

get_template_part('template-parts/hero-image', null, [
	'image_id' => 48,
	'title'    => __('Construcción a la medida', 'momentum'),
	'subtitle' => __('Diseño y construcción de vivienda en Lomas de Cocoyoc, de principio a fin, con acompañamiento en cada etapa del proyecto.', 'momentum'),
]);

get_template_part('template-parts/construccion/intro');

get_footer();
