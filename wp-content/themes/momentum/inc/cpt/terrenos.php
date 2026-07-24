<?php
/**
 * Custom Post Type: Terrenos.
 *
 * CPT independiente (no reutiliza 'propiedades' con un flag interno) para
 * mantener separados los listados, el admin y las URLs de Terrenos frente a
 * Propiedades, sin tocar sus datos. Reutiliza, sin embargo, los MISMOS
 * nombres de meta key que 'propiedades' para los campos que ambos comparten
 * (precio, zona, estado, taxes_usd, video_url, video_url_en, caracteristicas,
 * galeria, terreno_m2, codigo_propiedad): así momentum_get_propiedad() y los
 * componentes públicos existentes (tarjeta, header/galería/video/mapa de
 * detalle) funcionan para un terreno sin ninguna modificación.
 *
 * Sin UI nativa de wp-admin (mismo criterio que 'zonas' en inc/cpt/zonas.php):
 * la gestión vive por completo en el panel propio, /admin/terrenos/
 * (Momentum_Admin_Terrenos, en el plugin momentum-admin).
 *
 * has_archive es false a propósito: ya existe una Página publicada con slug
 * "terrenos" (el listado público /terrenos/, con su propio page-terrenos.php
 * en el tema, igual que Venta/Renta). Si el CPT tuviera archivo nativo,
 * competiría por esa misma URL. Con has_archive=false el slug del CPT solo
 * genera rutas de detalle (/terrenos/{slug-del-terreno}/), sin tocar la
 * página "Terrenos" en sí.
 */

if (! defined('ABSPATH')) {
	exit;
}

function momentum_register_cpt_terrenos()
{
	$labels = [
		'name'                  => 'Terrenos',
		'singular_name'         => 'Terreno',
		'menu_name'             => 'Terrenos',
		'add_new_item'          => 'Añadir nuevo terreno',
		'edit_item'             => 'Editar terreno',
		'view_item'             => 'Ver terreno',
		'search_items'          => 'Buscar terrenos',
		'not_found'             => 'No se encontraron terrenos',
		'not_found_in_trash'    => 'No hay terrenos en la papelera',
		'featured_image'        => 'Imagen principal',
		'set_featured_image'    => 'Establecer imagen principal',
		'remove_featured_image' => 'Quitar imagen principal',
		'use_featured_image'    => 'Usar como imagen principal',
	];

	register_post_type('terrenos', [
		'labels'              => $labels,
		'public'              => true,
		'publicly_queryable'  => true,
		'show_ui'             => false,
		'show_in_menu'        => false,
		'show_in_nav_menus'   => false,
		'show_in_admin_bar'   => false,
		'show_in_rest'        => false,
		'has_archive'         => false,
		'exclude_from_search' => false,
		'capability_type'     => 'post',
		'supports'            => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'author'],
		'rewrite'             => ['slug' => 'terrenos', 'with_front' => false],
	]);
}
// Misma prioridad que 'propiedades' (10): 'zonas' (prioridad 5) ya está
// registrado y sembrado para cuando esto corre, y momentum_terreno_meta_schema()
// depende de momentum_propiedad_meta_schema() (definida en propiedades.php,
// cargado antes que este archivo en functions.php).
add_action('init', 'momentum_register_cpt_terrenos', 10);

/**
 * Subconjunto reutilizado (no reescrito) de momentum_propiedad_meta_schema():
 * mismos tipos, mismas opciones de 'estado' y mismo options_map de 'zona' que
 * Propiedades -incluyendo valores que no aplican estrictamente a un terreno
 * (p. ej. 'Rentada' en estado)-, porque el criterio explícito de este módulo
 * es reutilizar el vocabulario existente, no inventar uno nuevo.
 *
 * Deliberadamente NO incluye 'tipo_operacion', 'recamaras', 'banos' ni
 * 'construccion_m2' (campos exclusivos de casa): al no existir ese meta en un
 * terreno, get_post_meta() devuelve '' / 0, y los componentes compartidos
 * (property-card.php, template-parts/property/header.php) ya ocultan esas
 * filas cuando construccion_m2 <= 0, sin necesidad de tocarlos.
 */
function momentum_terreno_meta_schema(): array
{
	$propiedad_schema = momentum_propiedad_meta_schema();

	$keys = [
		'codigo_propiedad',
		'precio',
		'terreno_m2',
		'estado',
		'zona',
		'taxes_usd',
		'video_url',
		'video_url_en',
		'caracteristicas',
		'galeria',
	];

	return array_intersect_key($propiedad_schema, array_flip($keys));
}

function momentum_register_terreno_meta()
{
	foreach (momentum_terreno_meta_schema() as $key => $schema) {
		$sanitize_callback = 'sanitize_text_field';

		if (! empty($schema['options_map'])) {
			$sanitize_callback = 'momentum_sanitize_zona';
		} elseif (! empty($schema['url'])) {
			$sanitize_callback = 'momentum_sanitize_video_url';
		} elseif ($schema['type'] === 'number') {
			$sanitize_callback = ! empty($schema['nonnegative']) ? 'momentum_sanitize_nonnegative_number' : 'momentum_sanitize_meta_number';
		} elseif (! empty($schema['textarea'])) {
			$sanitize_callback = 'sanitize_textarea_field';
		} elseif (! empty($schema['gallery'])) {
			$sanitize_callback = 'momentum_sanitize_gallery_ids';
		}

		register_post_meta('terrenos', $key, [
			'type'              => $schema['type'],
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => $sanitize_callback,
			'auth_callback'     => function () {
				return current_user_can('edit_posts');
			},
		]);
	}
}
add_action('init', 'momentum_register_terreno_meta');

/**
 * Guarda la meta compartida (precio, zona, estado, codigo_propiedad, etc.)
 * reutilizando momentum_save_meta_fields() (extraída de inc/cpt/propiedades.php).
 * 'codigo_propiedad' se guarda igual que en Propiedades: campo de texto
 * libre que el administrador escribe a mano (placeholder "MT-001" en
 * templates/terrenos/form.php), sin generación automática ni bloqueo de
 * edición.
 */
function momentum_save_terreno_meta($post_id)
{
	if (
		! isset($_POST['momentum_terreno_meta_nonce'])
		|| ! wp_verify_nonce($_POST['momentum_terreno_meta_nonce'], 'momentum_terreno_meta_save')
	) {
		return;
	}

	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}

	if (! current_user_can('edit_post', $post_id)) {
		return;
	}

	momentum_save_meta_fields($post_id, momentum_terreno_meta_schema());
}
add_action('save_post_terrenos', 'momentum_save_terreno_meta');

/**
 * Registrar un CPT nuevo con una estructura de 'rewrite' distinta requiere
 * un flush_rewrite_rules() para que /terrenos/{slug}/ resuelva -de lo
 * contrario WordPress sigue usando las reglas cacheadas de antes de este
 * cambio-. Se ejecuta una sola vez (mismo criterio que
 * momentum_maybe_seed_default_zonas() en inc/cpt/zonas.php: una opción
 * persistente como guardia, no "si la lista está vacía"), para no pagar el
 * costo de un flush en cada request.
 */
function momentum_maybe_flush_terrenos_rewrite_rules(): void
{
	if (get_option('momentum_terrenos_rewrite_flushed')) {
		return;
	}

	flush_rewrite_rules();
	update_option('momentum_terrenos_rewrite_flushed', 1);
}
add_action('init', 'momentum_maybe_flush_terrenos_rewrite_rules', 20);
