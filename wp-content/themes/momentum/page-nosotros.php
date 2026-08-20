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

// Editable desde Admin > General > Nosotros (Momentum_Admin_Content_Pages en
// el plugin momentum-admin); ver inc/page-content-fields.php. El fallback a
// __() preserva el texto original si el campo se vacía por cualquier motivo,
// para que esta página nunca se quede sin título/subtítulo.
$nosotros_page_id = get_queried_object_id();
$hero_title       = get_post_meta($nosotros_page_id, 'nosotros_hero_title', true) ?: __('Nosotros', 'momentum');
$hero_subtitle    = get_post_meta($nosotros_page_id, 'nosotros_hero_subtitle', true) ?: __('Más de 15 años acompañando a familias en Lomas de Cocoyoc.', 'momentum');

get_template_part('template-parts/page-hero', null, [
	'title'    => $hero_title,
	'subtitle' => $hero_subtitle,
]);

get_template_part('template-parts/nosotros/content');

get_footer();
