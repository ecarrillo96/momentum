<?php
/**
 * Canonical autorreferencial para las páginas 2+ de los listados de
 * Venta/Renta/Terrenos (?pg=2, ?pg=3, ...).
 *
 * La paginación de estos listados es un parámetro propio del proyecto
 * ('pg', ver momentum_run_property_query() en inc/property-query.php), no
 * el ?paged= nativo de WordPress -Rank Math no tiene forma de saber que
 * "/venta/?pg=2" es una página distinta con propiedades distintas a
 * "/venta/", así que su canonical caía siempre en la página 1-.
 *
 * Cada página paginada muestra propiedades REALMENTE distintas (no es
 * contenido duplicado del filtro/parámetro tipo utm_source, que sí debe
 * seguir limpiándose): la guía actual de Google trata cada página de una
 * serie paginada como una URL independiente y recomienda canonical
 * autorreferencial por página, no "todo apunta a la página 1" -por eso se
 * corrige aquí en vez de dejarlo como estaba-.
 *
 * Por qué se lee $_GET['pg'] aquí, en 'wp' a prioridad 5, en vez de
 * confiar en momentum_guard_property_listing_page() (que ya valida y
 * acota el número de página): Rank_Math\Frontend\Paper::get_canonical()
 * memoriza el canonical la PRIMERA vez que se pide -disparado desde
 * Frontend::integrations(), enganchado a 'wp' a la prioridad por
 * defecto (10)-, que ocurre MUCHO antes de que WordPress cargue el
 * archivo de plantilla (page-venta.php, etc.) donde vive esa función de
 * validación. Confirmado en pruebas: para cuando la plantilla llegaba a
 * ejecutarse, Rank Math ya había calculado y guardado en caché el
 * canonical sin paginar. No hace falta repetir aquí la validación de
 * rango: si el número pedido está fuera de rango, el guard de la
 * plantilla redirige la respuesta completa un momento después -el
 * canonical calculado aquí para esa página inválida nunca llega a
 * mostrarse a un usuario real-.
 */

if (! defined('ABSPATH')) {
	exit;
}

add_action('wp', 'momentum_expose_listing_paged_early', 5);

function momentum_expose_listing_paged_early(): void
{
	if (! isset($_GET['pg'])) {
		return;
	}

	$GLOBALS['momentum_listing_paged'] = max(1, absint($_GET['pg']));
}

add_filter('rank_math/frontend/canonical', function (string $canonical): string {
	$paged = $GLOBALS['momentum_listing_paged'] ?? 1;

	if ($paged <= 1) {
		return $canonical;
	}

	return add_query_arg('pg', $paged, $canonical);
});
