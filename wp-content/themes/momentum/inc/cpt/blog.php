<?php
/**
 * Módulo Blog: usa el post type nativo 'post' (ya consumido por
 * page-blog.php, template-parts/blog-grid.php y single.php), NO un CPT
 * nuevo. El contenido vive en los campos nativos de WordPress (post_title,
 * post_content, post_excerpt, categoría, etiquetas), en español únicamente.
 * Todo lo que WordPress no resuelve de forma nativa (SEO) se guarda como
 * post meta con prefijo 'momentum_', siguiendo el mismo criterio de
 * inc/cpt/propiedades.php.
 *
 * Sin gestión manual de idiomas: si el sitio instala Polylang/WPML más
 * adelante, ese plugin administra las traducciones sobre este mismo post
 * type de forma nativa, sin que este módulo necesite cambios.
 */

if (! defined('ABSPATH')) {
	exit;
}

function momentum_articulo_meta_schema()
{
	return [
		'momentum_destacado'      => ['type' => 'string', 'label' => 'Destacado', 'options' => ['1', '']],
		// Presentacional únicamente: distingue en el listado un borrador que
		// nunca se publicó de uno que se publicó y luego se despublicó, sin
		// inventar un post_status nuevo (post_status sigue siendo 'draft').
		'momentum_unpublished'    => ['type' => 'string', 'label' => 'Despublicado', 'options' => ['1', '']],
		'momentum_seo_titulo_es'  => ['type' => 'string', 'label' => 'Meta title'],
		'momentum_seo_desc_es'    => ['type' => 'string', 'label' => 'Meta description', 'textarea' => true],
		'momentum_seo_keyword'    => ['type' => 'string', 'label' => 'Palabra clave principal'],
		'momentum_seo_canonical'  => ['type' => 'string', 'label' => 'URL canónica'],
		'momentum_seo_noindex'    => ['type' => 'string', 'label' => 'Impedir indexación', 'options' => ['1', '']],
		'momentum_seo_og_titulo'  => ['type' => 'string', 'label' => 'Título para redes sociales'],
		'momentum_seo_og_desc'    => ['type' => 'string', 'label' => 'Descripción para redes sociales', 'textarea' => true],
		'momentum_seo_og_imagen'  => ['type' => 'number', 'label' => 'Imagen para redes sociales'],
	];
}

function momentum_register_articulo_meta()
{
	foreach (momentum_articulo_meta_schema() as $key => $schema) {
		$sanitize_callback = 'sanitize_text_field';

		if ($schema['type'] === 'number') {
			$sanitize_callback = 'absint';
		} elseif (! empty($schema['kses'])) {
			$sanitize_callback = 'wp_kses_post';
		} elseif (! empty($schema['textarea'])) {
			$sanitize_callback = 'sanitize_textarea_field';
		}

		register_post_meta('post', $key, [
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
add_action('init', 'momentum_register_articulo_meta');

/**
 * El título del artículo es el único H1 de la página pública (lo imprime
 * template-parts/page-hero.php). TinyMCE ya no ofrece "Encabezado 1" en su
 * selector de formato (ver form.php), pero un h1 pegado desde otra fuente
 * pasaría wp_kses_post() igual, así que se degrada aquí como defensa en
 * profundidad antes de guardar.
 */
function momentum_downgrade_h1(string $html): string
{
	return preg_replace(['/<h1(\s[^>]*)?>/i', '/<\/h1>/i'], ['<h2$1>', '</h2>'], $html);
}

function momentum_articulo_is_destacado(int $post_id): bool
{
	return get_post_meta($post_id, 'momentum_destacado', true) === '1';
}

/**
 * Estado mostrado al administrador: Publicado / Despublicado / Borrador.
 * post_status sigue siendo nativo ('publish' o 'draft'); 'Despublicado' es
 * una etiqueta de presentación (ver comentario en momentum_articulo_meta_schema()).
 */
function momentum_articulo_display_status(int $post_id): string
{
	$status = get_post_status($post_id);

	if ($status === 'publish') {
		return 'Publicado';
	}

	if ($status === 'draft' && get_post_meta($post_id, 'momentum_unpublished', true) === '1') {
		return 'Despublicado';
	}

	return 'Borrador';
}

/**
 * SEO: helpers usados tanto por el panel admin como por inc/blog-seo.php
 * (front-end). Centralizados aquí para no duplicar la lógica de "usa el
 * campo SEO si existe, si no cae al valor nativo del post".
 */
function momentum_articulo_seo_titulo(int $post_id): string
{
	$value = (string) get_post_meta($post_id, 'momentum_seo_titulo_es', true);

	return $value !== '' ? $value : get_the_title($post_id);
}

function momentum_articulo_seo_descripcion(int $post_id): string
{
	$value = (string) get_post_meta($post_id, 'momentum_seo_desc_es', true);

	if ($value !== '') {
		return $value;
	}

	$excerpt = get_the_excerpt($post_id);

	return $excerpt !== '' ? wp_strip_all_tags($excerpt) : '';
}
