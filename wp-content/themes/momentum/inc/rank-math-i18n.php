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
 * Título y meta description de fichas de Propiedades/Terrenos: las
 * plantillas configuradas en Rank Math (Admin > SEO > Títulos y Meta) son un
 * único valor global en español ("%title% | %customfield(tipo_operacion)%
 * en Lomas de Cocoyoc | Momentum Cocoyoc", etc.) y %customfield() siempre
 * imprime el valor crudo guardado (ej. 'tipo_operacion' = "Venta", nunca
 * traducido) -el resultado final que le llega a este filtro ya trae ese
 * texto en español sustituido, así que no hay nada que "traducir" del
 * string: se reconstruye desde cero con los mismos datos reales de la
 * ficha, usando los getters ya traducidos del tema
 * (momentum_get_tipo_operacion_label()) en vez del valor crudo de postmeta.
 * 'caracteristicas' en una ficha en inglés ya viene en inglés -es uno de los
 * campos que Momentum_Property_Translation_Service traduce con DeepL al
 * crear la traducción-, así que aquí solo se reutiliza tal cual.
 */
add_filter('rank_math/frontend/title', 'momentum_translate_listing_seo_title');
add_filter('rank_math/frontend/description', 'momentum_translate_listing_seo_description');

function momentum_translate_listing_seo_title(string $title): string
{
	if (momentum_current_language() === momentum_default_language() || ! is_singular(['propiedades', 'terrenos'])) {
		return $title;
	}

	$post_id = get_queried_object_id();
	$name    = get_the_title($post_id);

	if (is_singular('terrenos')) {
		return sprintf('%s | Land for Sale in Lomas de Cocoyoc | Momentum Cocoyoc', $name);
	}

	$tipo = momentum_get_tipo_operacion_label(momentum_get_propiedad($post_id)['tipo_operacion']);

	return sprintf('%s | %s in Lomas de Cocoyoc | Momentum Cocoyoc', $name, $tipo);
}

function momentum_translate_listing_seo_description(string $description): string
{
	if (momentum_current_language() === momentum_default_language() || ! is_singular(['propiedades', 'terrenos'])) {
		return $description;
	}

	$post_id = get_queried_object_id();
	$data    = momentum_get_propiedad($post_id);
	$name    = get_the_title($post_id);
	$codigo  = $data['codigo_propiedad'];

	if (is_singular('terrenos')) {
		$caracteristicas = trim((string) get_post_meta($post_id, 'caracteristicas', true));

		return trim(sprintf('%s · %s in Lomas de Cocoyoc. %s', $codigo, $name, $caracteristicas));
	}

	$tipo = momentum_get_tipo_operacion_label($data['tipo_operacion']);

	return sprintf(
		'%s · %s, %s in Lomas de Cocoyoc. %d bedrooms, %d m² built.',
		$codigo,
		$name,
		$tipo,
		(int) $data['recamaras'],
		(int) $data['construccion_m2']
	);
}
