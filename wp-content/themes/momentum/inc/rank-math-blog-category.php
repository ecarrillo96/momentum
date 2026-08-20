<?php
/**
 * La categoría "por defecto" de WordPress (la que se asigna sola a
 * cualquier entrada sin categoría propia -"Sin categoría" en español,
 * "Uncategorized" en su traducción de Polylang-) es indexable de fábrica,
 * sin meta description propia y sin contenido único: el sitio no usa un
 * esquema de categorías real -todas las entradas actuales caen ahí por
 * defecto-, así que ese archivo solo duplica lo que /blog/ ya cubre mejor
 * (título, H1 y meta description propios).
 *
 * Se marca noindex dinámicamente -comparando siempre contra la opción
 * real 'default_category', nunca un ID o slug fijo- para que:
 *   - si el sitio empieza a usar categorías reales, esas SÍ sigan siendo
 *     indexables sin cambiar nada aquí;
 *   - si un administrador cambia cuál es la categoría por defecto, este
 *     filtro seguía aplicando a la correcta.
 */

if (! defined('ABSPATH')) {
	exit;
}

add_filter('rank_math/frontend/robots', function (array $robots): array {
	if (! is_category()) {
		return $robots;
	}

	if (! momentum_is_default_blog_category(get_queried_object_id())) {
		return $robots;
	}

	$robots['index'] = 'noindex';

	return $robots;
});

function momentum_is_default_blog_category(int $term_id): bool
{
	$default_category = (int) get_option('default_category');

	if ($term_id === $default_category) {
		return true;
	}

	// La traducción de Polylang de la categoría por defecto (ej.
	// "Uncategorized" en inglés) es un término DISTINTO enlazado al
	// original -sin esto, solo el idioma por defecto quedaría cubierto-.
	if (function_exists('pll_get_term')) {
		foreach (momentum_get_secondary_languages() as $lang) {
			if ((int) pll_get_term($default_category, $lang) === $term_id) {
				return true;
			}
		}
	}

	return false;
}
