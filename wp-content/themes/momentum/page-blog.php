<?php
/**
 * Blog — article listing page.
 *
 * WordPress selects this file automatically for the page with slug
 * "blog" (page-{slug}.php takes priority over page.php).
 *
 * @package Momentum
 */

get_header();

get_template_part('template-parts/page-hero', null, [
	'title'    => 'Blog',
	'subtitle' => 'Ideas, tendencias y consejos sobre bienes raíces en Lomas de Cocoyoc y Morelos.',
]);

get_template_part('template-parts/blog-grid', null, [
	'eyebrow'        => 'Blog',
	'heading'        => 'Todos los artículos',
	'subtitle'       => 'Explora nuestras publicaciones más recientes.',
	'posts_per_page' => 9,
	'paginate'       => true,
	'priority_first' => true,
]);

get_footer();
