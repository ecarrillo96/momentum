<?php
/**
 * Ajustes de Schema (JSON-LD) de Rank Math para los CPT Propiedades y
 * Terrenos.
 *
 * Contexto: Rank Math Free solo agrega el grafo base (Organization +
 * WebSite + WebPage + BreadcrumbList + ImageObject) a un post type
 * personalizado cuando su "Rich Snippet" por defecto está configurado como
 * Article/BlogPosting/NewsArticle o WooCommerce/EDD Product (ver
 * includes/helpers/class-schema.php del plugin). Un CPT sin ese tipo
 * simplemente no dispara el grafo, sin alternativa nativa para un tipo
 * inmobiliario. Por eso "pt_propiedades_default_rich_snippet" y
 * "pt_terrenos_default_rich_snippet" están en "article" en Rank Math (es lo
 * único que activa el grafo sin instalar WooCommerce ni Pro).
 *
 * Como una ficha de propiedad/terreno no es un artículo, se elimina del
 * JSON-LD final el nodo "Article" (Rank Math lo guarda bajo la key
 * richSnippet) y el nodo "Person" del autor (key ProfilePage) SOLO para
 * esos dos CPT, y se agrega en su lugar un nodo propio
 * schema.org/RealEstateListing con los datos reales de la ficha. Las
 * entradas del blog (post) no pasan por este filtro y conservan su
 * Article + Person normalmente.
 *
 * Por qué RealEstateListing y no Product/Offer sueltos como tipo principal:
 * RealEstateListing es el tipo que schema.org define explícitamente para
 * esto -"A listing that describes a real estate property, either for sale
 * or for rent" (https://schema.org/RealEstateListing)-. `Product` está
 * pensado para artículos de comercio electrónico; usarlo para un inmueble
 * es un uso indebido ya documentado por la comunidad de schema.org/Google,
 * y Google no tiene un resultado enriquecido específico para inmuebles que
 * lo aproveche de cualquier forma -esto no mejora ningún snippet visual en
 * el buscador, solo describe el contenido con más precisión-. `Offer` sí
 * se usa dentro del listado (es una propiedad de uso general, no exclusiva
 * de Product) para modelar precio y disponibilidad de forma honesta,
 * incluyendo el caso vendida/rentada/apartada con su 'availability' real
 * -nunca "en stock" cuando no lo está-.
 *
 * Ningún campo sin dato real capturado se incluye: sin precio no hay
 * 'offers', sin recámaras no hay 'numberOfBedrooms', sin zona no hay
 * 'address', etc. -ver momentum_build_real_estate_listing_schema()-.
 */

if (! defined('ABSPATH')) {
	exit;
}

add_filter('rank_math/json_ld', function (array $data) {
	if (! is_singular(['propiedades', 'terrenos'])) {
		return $data;
	}

	unset($data['richSnippet'], $data['ProfilePage']);

	$post_id = get_queried_object_id();
	$listing = $post_id ? momentum_build_real_estate_listing_schema($post_id, get_post_type($post_id)) : null;

	if ($listing !== null) {
		$data['RealEstateListing'] = $listing;

		// Conecta el nodo nuevo con el WebPage que Rank Math ya arma, para
		// que el grafo quede relacionado (WebPage -> mainEntity ->
		// RealEstateListing) en vez de dos nodos sueltos sin vínculo entre sí.
		if (isset($data['WebPage'])) {
			$data['WebPage']['mainEntity'] = ['@id' => $listing['@id']];
		}
	}

	return $data;
}, 999);

/**
 * @return array|null null si la ficha no tiene ni siquiera un título -caso
 *                     extremo, el formulario ya exige título-, para nunca
 *                     agregar un nodo vacío al grafo.
 */
function momentum_build_real_estate_listing_schema(int $post_id, string $post_type): ?array
{
	$title = get_the_title($post_id);

	if ($title === '') {
		return null;
	}

	$permalink = get_permalink($post_id);
	$codigo    = trim((string) get_post_meta($post_id, 'codigo_propiedad', true));
	$zona      = momentum_get_zona_label((string) get_post_meta($post_id, 'zona', true));
	$image_id  = get_post_thumbnail_id($post_id);

	$about = [
		'@type' => 'Place',
		'name'  => $title,
	];

	// Solo localidad + país -zonas como "CDMX" no son parte del estado de
	// Morelos donde operan las demás, así que no se inventa un
	// 'addressRegion' fijo que sería incorrecto para esa zona-.
	if ($zona !== '') {
		$about['address'] = [
			'@type'           => 'PostalAddress',
			'addressLocality' => $zona,
			'addressCountry'  => 'MX',
		];
	}

	if ($post_type === 'propiedades') {
		$recamaras = (int) get_post_meta($post_id, 'recamaras', true);

		if ($recamaras > 0) {
			$about['numberOfBedrooms'] = $recamaras;
		}

		$construccion_m2 = (float) get_post_meta($post_id, 'construccion_m2', true);

		if ($construccion_m2 > 0) {
			$about['floorSize'] = [
				'@type'    => 'QuantitativeValue',
				'value'    => $construccion_m2,
				'unitCode' => 'MTK', // UN/CEFACT: metro cuadrado.
			];
		}
	}

	$terreno_m2 = (float) get_post_meta($post_id, 'terreno_m2', true);

	if ($terreno_m2 > 0) {
		$about['lotSize'] = [
			'@type'    => 'QuantitativeValue',
			'value'    => $terreno_m2,
			'unitCode' => 'MTK',
		];
	}

	$listing = [
		'@type'      => 'RealEstateListing',
		'@id'        => $permalink . '#listing',
		'url'        => $permalink,
		'name'       => $title,
		'datePosted' => get_the_date('c', $post_id),
		'inLanguage' => momentum_current_language(),
		'about'      => $about,
	];

	// Misma description que title/meta description -ver
	// inc/seo-listing-meta.php-, para no mantener dos generadores de texto
	// distintos que puedan desincronizarse entre sí.
	$description = momentum_seo_listing_description($post_id, $post_type);

	if ($description !== '') {
		$listing['description'] = $description;
	}

	if ($codigo !== '') {
		$listing['identifier'] = $codigo;
	}

	if ($image_id) {
		// Referencia al ImageObject que Rank Math ya agrega al grafo para la
		// imagen destacada -mismo criterio "conservar los nodos existentes,
		// relacionar por @id" que el resto de este archivo-, sin duplicar
		// sus datos (ancho/alto/tipo) en un segundo nodo de imagen.
		$image_url = wp_get_attachment_url($image_id);

		if ($image_url) {
			$listing['image'] = ['@id' => $image_url];
		}
	}

	$precio = (float) get_post_meta($post_id, 'precio', true);

	if ($precio > 0) {
		$estado = (string) get_post_meta($post_id, 'estado', true);

		$listing['offers'] = [
			'@type'         => 'Offer',
			'url'           => $permalink,
			'price'         => $precio,
			'priceCurrency' => 'MXN',
			'availability'  => 'https://schema.org/' . momentum_schema_availability_for_estado($estado),
		];
	}

	return $listing;
}

/**
 * Disponibilidad real del inmueble para 'offers.availability' -nunca
 * "InStock" para un estado que el propio proyecto ya trata como no
 * público (ver momentum_public_estado_values(), inc/property-query.php)-.
 * 'Reserved' es el valor de schema.org/ItemAvailability más cercano al
 * concepto de "Apartada"; no existe un valor específico para "rentada",
 * así que se usa 'SoldOut' -ya no está disponible para la transacción
 * ofrecida, que es lo que esa enumeración representa-.
 */
function momentum_schema_availability_for_estado(string $estado): string
{
	if (! momentum_is_public_property_status($estado)) {
		if ($estado === 'Apartada') {
			return 'Reserved';
		}

		return 'SoldOut'; // Vendida, Rentada, o cualquier otro valor no público.
	}

	return 'InStock'; // Disponible, Preventa, Remate.
}
