<?php
/**
 * Restringe el selector nativo de WordPress "Imagen destacada" a solo subir
 * archivos nuevos (nunca elegir uno ya existente en la biblioteca de
 * medios), en las pantallas de edición nativas de Propiedades, Terrenos y
 * Blog (que reutiliza el post type nativo 'post').
 */

if (! defined('ABSPATH')) {
	exit;
}

function momentum_enqueue_featured_image_lock(string $hook): void
{
	if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
		return;
	}

	if (! in_array(get_post_type(), ['propiedades', 'terrenos', 'post'], true)) {
		return;
	}

	wp_enqueue_media();
	wp_enqueue_script(
		'momentum-admin-featured-image-lock',
		get_stylesheet_directory_uri() . '/assets/js/admin-featured-image-lock.js',
		['jquery', 'media-editor'],
		wp_get_theme()->get('Version'),
		true
	);

	// lockToUploadOnly (JS) ya bloquea funcionalmente el cambio a "Biblioteca
	// de medios", pero la pestaña seguía siendo visible; esto la oculta del
	// todo. id confirmado en el core de WP: wp.media.view.MenuItem pone
	// id="menu-item-<state>" (wp-includes/js/media-views.js).
	wp_add_inline_style('media-views', '.media-frame-router #menu-item-browse { display: none !important; }');
}
add_action('admin_enqueue_scripts', 'momentum_enqueue_featured_image_lock');
