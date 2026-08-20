<?php
/**
 * Construcción — page with the same compact hero used by Asesoría/Nosotros.
 *
 * WordPress selects this file automatically for the page with slug
 * "construccion" (page-{slug}.php takes priority over page.php).
 *
 * @package Momentum
 */

get_header();

// Editable desde Admin > General > Construcción (Momentum_Admin_Content_Pages
// en el plugin momentum-admin); ver inc/page-content-fields.php. El fallback
// a __() preserva el texto original si el campo se vacía por cualquier
// motivo, para que esta página nunca se quede sin título/subtítulo.
$construccion_page_id = get_queried_object_id();
$hero_title           = get_post_meta($construccion_page_id, 'construccion_hero_title', true) ?: __('Construcción a la medida', 'momentum');
$hero_subtitle        = get_post_meta($construccion_page_id, 'construccion_hero_subtitle', true) ?: __('Diseño y construcción de vivienda en Lomas de Cocoyoc, de principio a fin, con acompañamiento en cada etapa del proyecto.', 'momentum');

get_template_part('template-parts/page-hero', null, [
	'title'    => $hero_title,
	'subtitle' => $hero_subtitle,
]);

get_template_part('template-parts/construccion/intro');

get_footer();
