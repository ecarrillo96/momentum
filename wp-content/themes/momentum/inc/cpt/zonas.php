<?php
/**
 * Custom Post Type: Zonas.
 *
 * Lista administrable de zonas disponibles para el campo 'zona' de
 * Propiedades. Antes era un array fijo dentro de momentum_propiedad_meta_schema();
 * ahora ese array se llena en tiempo real desde este CPT, así que este
 * archivo es la única fuente de verdad.
 *
 * No público, sin UI nativa de wp-admin: la gestión vive por completo en
 * el panel propio (Momentum_Admin_Zonas, en el plugin momentum-admin),
 * igual que Propiedades reutiliza el CPT 'propiedades' de este mismo tema.
 *
 * El post_name (slug) de cada zona es el valor que se guarda en el meta
 * 'zona' de una propiedad -exactamente el mismo formato que ya usaban los
 * valores fijos anteriores (p. ej. "lomas-de-cocoyoc")-, y post_title es la
 * etiqueta visible ("Lomas de Cocoyoc").
 */

if (! defined('ABSPATH')) {
	exit;
}

function momentum_register_cpt_zonas()
{
	register_post_type('zonas', [
		'labels'          => [
			'name'          => 'Zonas',
			'singular_name' => 'Zona',
		],
		'public'          => false,
		'show_ui'         => false,
		'show_in_rest'    => false,
		'has_archive'     => false,
		'rewrite'         => false,
		'query_var'       => false,
		'capability_type' => 'post',
		'supports'        => ['title'],
	]);
}
// Prioridad 5: debe registrarse y sembrarse ANTES de que
// momentum_register_propiedad_meta() (prioridad 10, en propiedades.php) lea
// momentum_get_zonas() por primera vez -esa función cachea su resultado en
// un static, así que si corriera antes de sembrar, quedaría vacía el resto
// del request-.
add_action('init', 'momentum_register_cpt_zonas', 5);

/**
 * Fuente única de verdad de las zonas disponibles: slug (post_name) => Nombre visible.
 * Orden alfabético por nombre, usado tanto por el dropdown de Propiedades
 * como por el listado de Admin > Zonas.
 */
function momentum_get_zonas(): array
{
	static $cache = null;

	if ($cache !== null) {
		return $cache;
	}

	$posts = get_posts([
		'post_type'      => 'zonas',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	]);

	$zonas = [];

	foreach ($posts as $post) {
		$zonas[$post->post_name] = $post->post_title;
	}

	$cache = $zonas;

	return $zonas;
}

/**
 * Devuelve el ID de la zona cuyo nombre coincide (sin distinguir
 * mayúsculas/minúsculas, ya normalizado) con $name, o null si no existe.
 */
function momentum_find_zona_by_name(string $name): ?int
{
	$normalized = mb_strtolower($name);

	$posts = get_posts([
		'post_type'      => 'zonas',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'fields'         => 'ids',
	]);

	foreach ($posts as $id) {
		if (mb_strtolower(get_the_title($id)) === $normalized) {
			return (int) $id;
		}
	}

	return null;
}

/**
 * Cuántas propiedades (post_type 'propiedades') tienen guardado este slug
 * de zona en su meta 'zona'. Usado para bloquear el borrado de una zona en
 * uso -nunca se reasignan ni se borran automáticamente esas propiedades-.
 */
function momentum_count_propiedades_con_zona(string $slug): int
{
	if ($slug === '') {
		return 0;
	}

	$query = new WP_Query([
		'post_type'      => 'propiedades',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'no_found_rows'  => false,
		'fields'         => 'ids',
		'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery
			['key' => 'zona', 'value' => $slug, 'compare' => '='],
		],
	]);

	return (int) $query->found_posts;
}

/**
 * Semilla única (no destructiva, no repetible) de las zonas que antes eran
 * un array fijo en el formulario. Solo corre si nunca se ha ejecutado
 * -controlado por una opción persistente, no por "la lista está vacía"-,
 * para no revivir zonas que un administrador borró intencionalmente.
 */
function momentum_maybe_seed_default_zonas(): void
{
	if (get_option('momentum_zonas_seeded')) {
		return;
	}

	$defaults = [
		'CDMX',
		'Cuautla',
		'Huitzilac',
		'Lomas de Cocoyoc',
		'Oaxtepec',
		'Sitio del Sol',
		'Tequesquitengo',
		'Tlayacapan',
		'Yautepec',
	];

	foreach ($defaults as $name) {
		if (momentum_find_zona_by_name($name)) {
			continue;
		}

		wp_insert_post([
			'post_type'   => 'zonas',
			'post_title'  => $name,
			'post_status' => 'publish',
		]);
	}

	update_option('momentum_zonas_seeded', 1);
}
add_action('init', 'momentum_maybe_seed_default_zonas', 6);
