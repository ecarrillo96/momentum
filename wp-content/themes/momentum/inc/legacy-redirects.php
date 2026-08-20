<?php
/**
 * Redirecciones 301 para rutas que dejaron de resolver por un cambio de
 * arquitectura, para no perder el valor SEO de enlaces externos o resultados
 * de búsqueda ya indexados apuntando a la URL anterior.
 *
 * /propiedades/ (y su variante /en/propiedades/) era el archivo nativo del
 * CPT 'propiedades', desactivado en inc/cpt/propiedades.php (has_archive
 * ahora false, mismo criterio que 'terrenos') porque duplicaba /venta/ y
 * /renta/ sin metadatos SEO propios. Las fichas individuales
 * (/propiedades/{slug}/) NO se tocan aquí: has_archive solo controla el
 * índice/listado, no las rutas de detalle, que el CPT sigue generando igual
 * que antes.
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Coincide únicamente con la URL exacta del archivo desactivado -con o sin
 * slash final-, nunca con /propiedades/{algo}/ (fichas individuales) ni con
 * ninguna otra subruta. El idioma se detecta del propio path (prefijo
 * 'en/'), no de pll_current_language(): una vez desactivado el archivo, la
 * URL ya no tiene ninguna regla de reescritura que la haga coincidir, así
 * que no hay garantía de en qué punto del ciclo de Polylang se ejecuta este
 * redirect frente a su detección de idioma.
 */
function momentum_redirect_legacy_propiedades_archive(): void
{
	$request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
	$path        = trim((string) wp_parse_url($request_uri, PHP_URL_PATH), '/');

	if ($path === 'propiedades') {
		$lang = 'es';
	} elseif ($path === 'en/propiedades') {
		$lang = 'en';
	} else {
		return;
	}

	$venta     = get_page_by_path('venta');
	$target_id = $venta ? momentum_get_translation($venta->ID, $lang) : 0;
	$target    = $target_id ? get_permalink($target_id) : '';

	wp_safe_redirect($target !== '' ? $target : home_url('/venta/'), 301);
	exit;
}
add_action('template_redirect', 'momentum_redirect_legacy_propiedades_archive');
