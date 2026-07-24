<?php
/**
 * Salida de <head> para artículos individuales del blog: título, meta
 * description, Open Graph, canónica y datos estructurados Schema.org
 * BlogPosting. No hay Yoast/RankMath/Polylang instalados, así que esto usa
 * los campos SEO propios definidos en inc/cpt/blog.php (momentum_seo_*).
 * Si en el futuro se instala un plugin SEO, basta con desactivar este
 * archivo (quitar el require en functions.php); no toca nada más del sitio.
 */

if (! defined('ABSPATH')) {
	exit;
}

add_filter('pre_get_document_title', function ($title) {
	if (! is_singular('post')) {
		return $title;
	}

	$custom = momentum_articulo_seo_titulo(get_the_ID());

	return $custom !== '' ? $custom : $title;
});

add_action('wp_head', 'momentum_output_articulo_meta_tags');

function momentum_output_articulo_meta_tags()
{
	if (! is_singular('post')) {
		return;
	}

	$post_id     = get_the_ID();
	$description = momentum_articulo_seo_descripcion($post_id);
	$canonical   = (string) get_post_meta($post_id, 'momentum_seo_canonical', true);
	$canonical   = $canonical !== '' ? $canonical : get_permalink($post_id);
	$noindex     = get_post_meta($post_id, 'momentum_seo_noindex', true) === '1';

	$og_titulo = (string) get_post_meta($post_id, 'momentum_seo_og_titulo', true);
	$og_titulo = $og_titulo !== '' ? $og_titulo : momentum_articulo_seo_titulo($post_id);

	$og_desc   = (string) get_post_meta($post_id, 'momentum_seo_og_desc', true);
	$og_desc   = $og_desc !== '' ? $og_desc : $description;

	$og_imagen_id = (int) get_post_meta($post_id, 'momentum_seo_og_imagen', true);
	$og_imagen_id = $og_imagen_id > 0 ? $og_imagen_id : (int) get_post_thumbnail_id($post_id);
	$og_imagen_url = $og_imagen_id ? wp_get_attachment_image_url($og_imagen_id, 'large') : '';

	echo "\n<!-- Momentum Blog SEO -->\n";

	if ($description !== '') {
		printf('<meta name="description" content="%s">' . "\n", esc_attr($description));
	}

	printf('<link rel="canonical" href="%s">' . "\n", esc_url($canonical));

	if ($noindex) {
		echo '<meta name="robots" content="noindex, follow">' . "\n";
	}

	printf('<meta property="og:type" content="article">' . "\n");
	printf('<meta property="og:title" content="%s">' . "\n", esc_attr($og_titulo));

	if ($og_desc !== '') {
		printf('<meta property="og:description" content="%s">' . "\n", esc_attr($og_desc));
	}

	printf('<meta property="og:url" content="%s">' . "\n", esc_url(get_permalink($post_id)));

	if ($og_imagen_url) {
		printf('<meta property="og:image" content="%s">' . "\n", esc_url($og_imagen_url));
	}

	momentum_output_articulo_schema($post_id);
}

function momentum_output_articulo_schema(int $post_id)
{
	$author_id = (int) get_post_field('post_author', $post_id);
	$image_id  = (int) get_post_thumbnail_id($post_id);

	$schema = [
		'@context'         => 'https://schema.org',
		'@type'            => 'BlogPosting',
		'headline'         => get_the_title($post_id),
		'datePublished'    => get_the_date('c', $post_id),
		'dateModified'     => get_the_modified_date('c', $post_id),
		'mainEntityOfPage' => get_permalink($post_id),
		'author'           => [
			'@type' => 'Person',
			'name'  => get_the_author_meta('display_name', $author_id),
		],
		'publisher'        => [
			'@type' => 'Organization',
			'name'  => get_bloginfo('name'),
		],
	];

	if ($image_id) {
		$schema['image'] = [wp_get_attachment_image_url($image_id, 'full')];
	}

	echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>' . "\n";
}
