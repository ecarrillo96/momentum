<?php
/**
 * Momentum — Blocksy child theme.
 */

if (! defined('ABSPATH')) {
	exit;
}

require get_stylesheet_directory() . '/inc/enqueue.php';
require get_stylesheet_directory() . '/inc/helpers.php';
require get_stylesheet_directory() . '/inc/performance.php';
require get_stylesheet_directory() . '/inc/site-settings.php';
require get_stylesheet_directory() . '/inc/property-query.php';
require get_stylesheet_directory() . '/inc/contact-form.php';
require get_stylesheet_directory() . '/inc/cpt/zonas.php';
require get_stylesheet_directory() . '/inc/cpt/propiedades.php';
require get_stylesheet_directory() . '/inc/cpt/terrenos.php';
require get_stylesheet_directory() . '/inc/cpt/blog.php';
// inc/blog-seo.php desactivado: Rank Math SEO ya genera title, meta
// description, canonical, Open Graph y Schema para las entradas del blog
// (ver comentario en ese archivo). Mantenerlo activo duplicaba esas
// etiquetas. Los campos momentum_seo_* siguen existiendo, solo dejaron
// de imprimirse en el <head>.
// require get_stylesheet_directory() . '/inc/blog-seo.php';
require get_stylesheet_directory() . '/inc/rank-math-schema.php';

add_action('after_setup_theme', function () {
	add_theme_support('post-thumbnails');
});
