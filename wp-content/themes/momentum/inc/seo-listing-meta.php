<?php
/**
 * Title y meta description de fichas de Propiedades/Terrenos para Rank
 * Math, en español e inglés, construidos SIEMPRE a partir de los datos
 * reales de la ficha (zona, operación, código, recámaras, construcción,
 * características) -nunca con texto fijo como "Lomas de Cocoyoc"-.
 *
 * Por qué se reemplaza la plantilla de Rank Math en vez de solo
 * "traducirla" (como hacía antes inc/rank-math-i18n.php únicamente para
 * inglés): la plantilla configurada en Rank Math (Admin > SEO > Títulos y
 * Meta) tenía "en Lomas de Cocoyoc" escrito a mano -confirmado leyendo la
 * opción 'rank-math-options-titles' (pt_propiedades_title/description,
 * pt_terrenos_title/description)-, así que ninguna ficha fuera de esa zona
 * mostraba su ubicación real, ni en español ni en inglés. La única forma
 * de usar el campo real 'zona' (guardado como slug, ej. 'tequesquitengo')
 * con su etiqueta legible (momentum_get_zona_label()) es reconstruir el
 * string aquí, con los mismos hooks 'rank_math/frontend/title' y
 * '/description' que el proyecto ya usaba -no se inventa un mecanismo
 * nuevo, se generaliza el que ya existía para que cubra también español-.
 *
 * Todo lo demás (canonical, robots, sitemap, Open Graph) lo sigue
 * generando Rank Math sin cambios: este archivo solo decide el TEXTO del
 * title y la description para estos dos CPT.
 */

if (! defined('ABSPATH')) {
	exit;
}

add_filter('rank_math/frontend/title', 'momentum_seo_listing_title_filter');
add_filter('rank_math/frontend/description', 'momentum_seo_listing_description_filter');

function momentum_seo_listing_title_filter(string $title): string
{
	if (! is_singular(['propiedades', 'terrenos'])) {
		return $title;
	}

	$post_id = get_queried_object_id();

	if (! $post_id) {
		return $title;
	}

	return momentum_seo_listing_title($post_id, get_post_type($post_id));
}

function momentum_seo_listing_description_filter(string $description): string
{
	if (! is_singular(['propiedades', 'terrenos'])) {
		return $description;
	}

	$post_id = get_queried_object_id();

	if (! $post_id) {
		return $description;
	}

	return momentum_seo_listing_description($post_id, get_post_type($post_id));
}

/**
 * Etiqueta legible de la zona real de la ficha ("Tequesquitengo"), o ''
 * si no tiene una zona reconocida capturada. Nunca devuelve un slug crudo
 * ('tequesquitengo') ni un valor inventado. Los nombres de zona son
 * topónimos -iguales en español e inglés-, así que no requieren una
 * versión traducida aparte.
 */
function momentum_seo_zona_label(int $post_id): string
{
	$zona = (string) get_post_meta($post_id, 'zona', true);

	return momentum_get_zona_label($zona);
}

/**
 * Etiqueta de operación ya traducida por el idioma activo de Polylang
 * (momentum_get_tipo_operacion_label() usa __() con el textdomain del
 * tema: "Venta" en español, "For Sale" en inglés gracias a
 * languages/momentum-en_US.po -sin lógica de idioma propia aquí-).
 * Terrenos no tiene campo 'tipo_operacion' (solo se venden), así que se
 * usa el mismo getter con el valor fijo 'Venta' para heredar la misma
 * traducción sin duplicar la cadena.
 */
function momentum_seo_operacion_label(int $post_id, string $post_type): string
{
	if ($post_type === 'terrenos') {
		return momentum_get_tipo_operacion_label('Venta');
	}

	$tipo = (string) get_post_meta($post_id, 'tipo_operacion', true);

	return $tipo !== '' ? momentum_get_tipo_operacion_label($tipo) : '';
}

/**
 * "{Operación} en {Zona}" / "{Operación}" / "{Zona}" / "" -degradación
 * segura cuando falta uno de los dos datos, para nunca dejar un "en"
 * colgado ni una frase vacía-. Usa _x() con contexto propio (no __())
 * porque el mismo texto en español ("%1$s en %2$s") necesita una
 * traducción distinta según dónde se use: aquí siempre precede a un lugar
 * ("Venta en Tequesquitengo" -> "For Sale in Tequesquitengo", "in" es
 * correcto), pero en momentum_seo_listing_description() el mismo patrón
 * une el NOMBRE del inmueble con esta frase, y "in For Sale in
 * Tequesquitengo" no es inglés correcto -esa otra llamada usa su propio
 * contexto con una traducción distinta (coma en vez de "in")-.
 */
function momentum_seo_operacion_zona_phrase(string $operacion, string $zona): string
{
	if ($operacion !== '' && $zona !== '') {
		/* translators: 1: tipo de operación (ej. "Venta"/"For Sale"), 2: nombre de la zona (ej. "Tequesquitengo"). */
		return sprintf(_x('%1$s en %2$s', 'operación y zona del inmueble', 'momentum'), $operacion, $zona);
	}

	return $operacion !== '' ? $operacion : $zona;
}

/**
 * "{Título} | {Operación} en {Zona}" sin la marca -las fichas individuales
 * pueden omitirla, ver encargo-. Nunca corta palabras con substr(): si el
 * título propio de la ficha ya es largo, el resultado puede superar 60
 * caracteres -se prioriza no truncar el nombre del inmueble ni perder la
 * zona/operación-.
 */
function momentum_seo_listing_title(int $post_id, string $post_type): string
{
	$name = get_the_title($post_id);
	$zona = momentum_seo_zona_for_suffix($name, momentum_seo_zona_label($post_id));

	$suffix = momentum_seo_operacion_zona_phrase(
		momentum_seo_operacion_label($post_id, $post_type),
		$zona
	);

	if ($name === '') {
		return $suffix;
	}

	return $suffix !== '' ? sprintf('%s | %s', $name, $suffix) : $name;
}

/**
 * "{Código} · {Título} en {Operación} en {Zona}. {detalles}." Los
 * detalles varían por CPT: recámaras + m² construcción (solo si son > 0,
 * un valor en 0 normalmente significa "no capturado" en este esquema, no
 * "cero recámaras reales") para propiedades; características para
 * terrenos. Ningún segmento vacío deja separadores dobles ni un punto
 * suelto.
 */
function momentum_seo_listing_description(int $post_id, string $post_type): string
{
	$codigo = trim((string) get_post_meta($post_id, 'codigo_propiedad', true));
	$name   = get_the_title($post_id);
	$zona   = momentum_seo_zona_for_suffix($name, momentum_seo_zona_label($post_id));

	$suffix = momentum_seo_operacion_zona_phrase(
		momentum_seo_operacion_label($post_id, $post_type),
		$zona
	);

	/* translators: 1: título del inmueble, 2: "{operación} en {zona}" o equivalente parcial. */
	$headline = trim($suffix !== '' ? sprintf(_x('%1$s en %2$s', 'nombre del inmueble y su operación/zona', 'momentum'), $name, $suffix) : $name);
	$headline = $headline !== '' ? rtrim($headline, '.') . '.' : '';

	$lead = implode(' · ', array_filter([$codigo, $headline]));

	if ($post_type === 'terrenos') {
		$caracteristicas = trim((string) get_post_meta($post_id, 'caracteristicas', true));
		$caracteristicas = $caracteristicas !== '' ? preg_replace('/\s*\n+\s*/', ' ', $caracteristicas) : '';

		return trim(implode(' ', array_filter([$lead, $caracteristicas])));
	}

	$recamaras    = (int) get_post_meta($post_id, 'recamaras', true);
	$construccion = (int) get_post_meta($post_id, 'construccion_m2', true);

	$spec_bits = [];

	if ($recamaras > 0) {
		/* translators: %d: número de recámaras. */
		$spec_bits[] = sprintf(_n('%d recámara', '%d recámaras', $recamaras, 'momentum'), $recamaras);
	}

	if ($construccion > 0) {
		/* translators: %d: metros cuadrados de construcción. */
		$spec_bits[] = sprintf(__('%d m² construcción', 'momentum'), $construccion);
	}

	$specs = $spec_bits !== [] ? implode(', ', $spec_bits) . '.' : '';

	return trim(implode(' ', array_filter([$lead, $specs])));
}
