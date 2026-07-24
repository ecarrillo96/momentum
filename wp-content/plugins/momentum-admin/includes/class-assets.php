<?php
/**
 * Encolado de la Biblioteca Multimedia de WordPress. Los assets propios del
 * panel (CSS/JS) se imprimen directamente en templates/layout, ya que estas
 * rutas no pasan por wp_head()/wp_footer() del tema (evita arrastrar scripts
 * y estilos del front-end público al panel).
 */

if (! defined('ABSPATH')) {
	exit;
}

class Momentum_Admin_Assets
{
	/**
	 * admin-panel.js depende de jQuery en TODAS las páginas del panel
	 * (toggle de menú móvil incluido), no solo en Propiedades.
	 */
	public static function enqueue_common(): void
	{
		wp_enqueue_script('jquery');
		wp_enqueue_style('dashicons');
	}

	/**
	 * wp_enqueue_media() encola varios handles hermanos (media-editor,
	 * media-audiovideo, media-views, image-edit, imgareaselect...). No se
	 * imprime ninguno a mano: header.php/footer.php imprimen la cola
	 * completa con wp_print_styles()/wp_print_scripts() sin argumentos,
	 * así siempre coincide con lo que esta función realmente encoló.
	 */
	public static function enqueue_media(): void
	{
		wp_enqueue_media();
	}
}
