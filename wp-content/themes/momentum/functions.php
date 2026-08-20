<?php
/**
 * Momentum — Blocksy child theme.
 */

if (! defined('ABSPATH')) {
	exit;
}

require get_stylesheet_directory() . '/inc/language.php';
require get_stylesheet_directory() . '/inc/enqueue.php';
require get_stylesheet_directory() . '/inc/helpers.php';
require get_stylesheet_directory() . '/inc/performance.php';
require get_stylesheet_directory() . '/inc/site-settings.php';
require get_stylesheet_directory() . '/inc/seo-integrations.php';
require get_stylesheet_directory() . '/inc/property-query.php';
require get_stylesheet_directory() . '/inc/property-availability.php';
require get_stylesheet_directory() . '/inc/contact-form.php';
require get_stylesheet_directory() . '/inc/cpt/zonas.php';
require get_stylesheet_directory() . '/inc/cpt/propiedades.php';
require get_stylesheet_directory() . '/inc/cpt/terrenos.php';
require get_stylesheet_directory() . '/inc/cpt/blog.php';
require get_stylesheet_directory() . '/inc/legacy-redirects.php';
require get_stylesheet_directory() . '/inc/page-content-fields.php';
require get_stylesheet_directory() . '/inc/admin-media-restrict.php';
require get_stylesheet_directory() . '/inc/translation-provider.php';
require get_stylesheet_directory() . '/inc/translation-usage.php';
require get_stylesheet_directory() . '/inc/translation-log.php';
require get_stylesheet_directory() . '/inc/class-property-translation-service.php';
require get_stylesheet_directory() . '/inc/class-blog-translation-service.php';

/*
 * Único punto donde se enganchan estos hooks: toda la lógica vive en
 * Momentum_Property_Translation_Service (ver el archivo requerido arriba),
 * esto es solo la delegación. Prioridad 30, después de
 * momentum_save_propiedad_meta()/momentum_save_terreno_meta() (prioridad
 * 10, en inc/cpt/*.php) para leer la meta ya guardada de la ficha en
 * español, y después de momentum_invalidate_rank_math_sitemap_cache()
 * (prioridad 20, en inc/rank-math-availability.php).
 *
 * 'handle_save_hook' (no 'handle_save' directo): ese wrapper no hace nada
 * si Momentum_Admin_Properties/Terrenos (plugin momentum-admin) ya avisó
 * que disparará la sincronización él mismo, después de guardar la imagen
 * destacada -que ahí se guarda de forma procedural, después de este mismo
 * hook, no antes-. Ver el comentario de
 * Momentum_Property_Translation_Service::$external_trigger_pending.
 */
add_action('save_post_propiedades', ['Momentum_Property_Translation_Service', 'handle_save_hook'], 30, 3);
add_action('save_post_terrenos', ['Momentum_Property_Translation_Service', 'handle_save_hook'], 30, 3);

/*
 * Misma prioridad (30) y mismo criterio que los dos hooks de arriba: debe
 * correr después de que el post ya esté guardado. A diferencia de
 * Propiedades/Terrenos, el guardado de meta del Blog hecho desde
 * /admin/blog/ (Momentum_Admin_Blog::handle_save(), en el plugin
 * momentum-admin) NO ocurre vía este mismo hook -ocurre de forma procedural
 * después de wp_insert_post()/wp_update_post()-, así que se llama a
 * 'handle_save_hook' (no 'handle_save' directo): ese wrapper no hace nada
 * si Momentum_Admin_Blog ya avisó que disparará la traducción él mismo una
 * vez que termine de guardar imagen destacada/etiquetas/meta propia (ver
 * Momentum_Blog_Translation_Service::defer_auto_trigger()). Para guardados
 * nativos de wp-admin (que no pasan por el plugin), este hook basta solo.
 */
add_action('save_post_post', ['Momentum_Blog_Translation_Service', 'handle_save_hook'], 30, 3);
// inc/blog-seo.php desactivado: Rank Math SEO ya genera title, meta
// description, canonical, Open Graph y Schema para las entradas del blog
// (ver comentario en ese archivo). Mantenerlo activo duplicaba esas
// etiquetas. Los campos momentum_seo_* siguen existiendo, solo dejaron
// de imprimirse en el <head>.
// require get_stylesheet_directory() . '/inc/blog-seo.php';
require get_stylesheet_directory() . '/inc/rank-math-schema.php';
require get_stylesheet_directory() . '/inc/rank-math-availability.php';
require get_stylesheet_directory() . '/inc/rank-math-i18n.php';
require get_stylesheet_directory() . '/inc/rank-math-blog-category.php';
require get_stylesheet_directory() . '/inc/rank-math-pagination.php';
require get_stylesheet_directory() . '/inc/seo-listing-meta.php';

add_action('after_setup_theme', function () {
	add_theme_support('post-thumbnails');
});
