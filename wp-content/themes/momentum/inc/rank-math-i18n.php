<?php
/**
 * Rank Math genera canonical/og:url y el título/meta description de fichas
 * a partir de plantillas y datos que no son sensibles al idioma de Polylang.
 * Cada filtro de este archivo corrige, con un hook oficial distinto, un
 * punto donde texto o URLs en español se filtraban a la versión en inglés
 * del sitio. Nada de esto toca la configuración de Rank Math en español -
 * cada filtro solo actúa cuando momentum_current_language() difiere de
 * momentum_default_language().
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Canonical de portada: la lógica de Rank Math para is_front_page() no
 * conoce Polylang y cae al home_url() crudo -siempre la home en español,
 * sin importar el idioma de la vista actual-. Sin esto, la home en inglés
 * quedaba con canonical (y og:url, que Rank Math deriva del mismo canonical
 * -ver Facebook::url() en el plugin, no necesita su propio filtro) apuntando
 * a la home en español: Google puede interpretar la versión en inglés como
 * contenido duplicado y excluirla del índice. momentum_home_url()
 * (inc/language.php) ya resuelve esto para cualquier idioma configurado,
 * sin hardcodear 'en'.
 */
add_filter('rank_math/frontend/canonical', function (string $canonical): string {
	if (! is_front_page() || ! momentum_is_multilingual()) {
		return $canonical;
	}

	return momentum_home_url();
});

/**
 * Título y meta description de fichas de Propiedades/Terrenos, en español
 * e inglés: ver inc/seo-listing-meta.php. Se movieron ahí porque dejaron
 * de ser una corrección "solo para inglés" -el problema de usar texto fijo
 * en vez de la zona real de cada ficha también afectaba a la plantilla en
 * español configurada en Rank Math-, así que ya no encajaban en un archivo
 * pensado específicamente para diferencias de idioma.
 */

/**
 * Nodo raíz del breadcrumb estructurado (JSON-LD BreadcrumbList): Rank Math
 * solo tiene UNA etiqueta de "inicio" para todo el sitio (Ajustes generales
 * > Breadcrumbs > "Home"), sin distinguir idioma -confirmado en vivo: toda
 * página en español mostraba igualmente "Home" en su BreadcrumbList-. Se
 * traduce aquí, en el único punto donde Rank Math expone ese nodo antes de
 * agregarlo al grafo (rank_math/snippet/breadcrumb, ver
 * includes/modules/schema/snippets/class-breadcrumbs.php del plugin), sin
 * duplicar el BreadcrumbList ni tocar la opción global -que sigue siendo
 * correcta para inglés, así que ese idioma no se toca-.
 *
 * Se compara contra el valor de la opción real de Rank Math (no un "Home"
 * fijo aquí) para seguir funcionando aunque un administrador cambie esa
 * etiqueta en el futuro.
 */
add_filter('rank_math/snippet/breadcrumb', function (array $entity): array {
	if (momentum_current_language() !== momentum_default_language()) {
		return $entity;
	}

	if (empty($entity['itemListElement'][0]['item']['name'])) {
		return $entity;
	}

	$general              = get_option('rank-math-options-general');
	$configured_home_label = is_array($general) ? (string) ($general['breadcrumbs_home_label'] ?? '') : '';

	if ($configured_home_label !== '' && $entity['itemListElement'][0]['item']['name'] === $configured_home_label) {
		$entity['itemListElement'][0]['item']['name'] = __('Inicio', 'momentum');
	}

	return $entity;
});
