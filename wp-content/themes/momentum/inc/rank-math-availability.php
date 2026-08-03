<?php
/**
 * Rank Math: propiedades/terrenos con 'estado' no público (Vendida, Rentada,
 * Apartada — ver momentum_public_estado_values() en inc/property-query.php)
 * mantienen su URL y responden 200, pero:
 *
 *  - dejan de anunciarse como "index" en el <meta name="robots"> (pasan a
 *    "noindex, follow": Google puede seguir sus enlaces internos/salientes,
 *    pero deja de indexar la ficha).
 *  - se excluyen del sitemap XML de Rank Math.
 *
 * Ambos puntos usan filtros oficiales de Rank Math (no se toca ningún
 * archivo del plugin) y la misma fuente de verdad que ya usan los listados:
 * momentum_is_property_available() / momentum_public_estado_values()
 * (inc/property-availability.php, inc/property-query.php).
 *
 * El aviso visible en la página (template-parts/property/availability-notice.php)
 * y el CTA condicional (template-parts/property/contact-form.php) consultan
 * exactamente la misma función, así que las cuatro señales (robots, sitemap,
 * aviso, CTA) nunca pueden quedar desincronizadas entre sí.
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * <meta name="robots">: noindex para la ficha individual de una propiedad/
 * terreno no disponible. 'follow' se deja explícito para no perder el valor
 * de los enlaces internos (propiedades similares, breadcrumbs, etc.) que esa
 * página sigue teniendo. No se toca nada cuando la propiedad SÍ está
 * disponible -Rank Math sigue decidiendo index/follow como hasta ahora, por
 * si un admin configuró algo manualmente en el metabox de esa ficha.
 */
add_filter('rank_math/frontend/robots', function (array $robots): array {
	if (! is_singular(['propiedades', 'terrenos'])) {
		return $robots;
	}

	if (momentum_is_property_available(get_queried_object_id())) {
		return $robots;
	}

	$robots['index']  = 'noindex';
	$robots['follow'] = 'follow';

	return $robots;
});

/**
 * Fragmento SQL reutilizado por los 4 filtros de sitemap de abajo: un JOIN
 * contra el meta 'estado' + un WHERE que solo deja pasar los valores de
 * momentum_public_estado_values(). Alias propio ('pm_momentum_estado') para
 * no chocar con el JOIN que Rank Math ya arma internamente contra
 * 'rank_math_robots' (alias 'pm') en la misma query.
 *
 * @param string $post_types Tal como lo pasa Rank Math: un solo post type
 *                            por llamada (cada CPT tiene su propio archivo
 *                            de sitemap), pero se acepta array por si una
 *                            versión futura del plugin lo agrupa.
 */
function momentum_rank_math_sitemap_applies_to(string $post_types): bool
{
	return in_array($post_types, ['propiedades', 'terrenos'], true);
}

function momentum_rank_math_sitemap_estado_join(string $join, $post_types): string
{
	if (! is_string($post_types) || ! momentum_rank_math_sitemap_applies_to($post_types)) {
		return $join;
	}

	global $wpdb;

	return $join . " LEFT JOIN {$wpdb->postmeta} AS pm_momentum_estado ON ( p.ID = pm_momentum_estado.post_id AND pm_momentum_estado.meta_key = 'estado' )";
}
add_filter('rank_math/sitemap/get_posts/join', 'momentum_rank_math_sitemap_estado_join', 10, 2);
add_filter('rank_math/sitemap/post_count/join', 'momentum_rank_math_sitemap_estado_join', 10, 2);

function momentum_rank_math_sitemap_estado_where(string $where, $post_types): string
{
	if (! is_string($post_types) || ! momentum_rank_math_sitemap_applies_to($post_types)) {
		return $where;
	}

	global $wpdb;

	$placeholders = implode(',', array_fill(0, count(momentum_public_estado_values()), '%s'));

	return $where . $wpdb->prepare(
		" AND pm_momentum_estado.meta_value IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		momentum_public_estado_values()
	);
}
add_filter('rank_math/sitemap/get_posts/where', 'momentum_rank_math_sitemap_estado_where', 10, 2);
add_filter('rank_math/sitemap/post_count/where', 'momentum_rank_math_sitemap_estado_where', 10, 2);

/**
 * Rank Math solo invalida su caché de sitemap (archivo/transient) cuando
 * `RankMath\Sitemap\Cache_Watcher` está enganchado, y ese watcher solo se
 * instancia si `is_admin() || wp_doing_cron()` (ver Sitemap::__construct()
 * en el plugin). El panel propio del cliente (/admin/propiedades/,
 * /admin/terrenos/) es una ruta de FRONTEND (rewrite + template_redirect,
 * no wp-admin), así que `is_admin()` es false ahí -y también lo es en
 * cualquier guardado por WP-CLI (el seeder de pruebas, futuras migraciones).
 * En ambos casos, sin este hook, la caché del sitemap NO se invalida
 * -confirmado en pruebas: hasta 100 días de TTL en el peor caso- y una
 * propiedad recién marcada Vendida/Rentada podría seguir apareciendo en el
 * sitemap XML mucho después de guardarse.
 *
 * Se invalida directo contra Cache (no Cache_Watcher::invalidate_post(),
 * que solo encola el borrado para el hook 'shutdown' que ese watcher
 * registra en su constructor -si el watcher nunca se instanció, nadie
 * vacía la cola-). class_exists() por si Rank Math se desactiva.
 */
add_action('save_post_propiedades', 'momentum_invalidate_rank_math_sitemap_cache', 20);
add_action('save_post_terrenos', 'momentum_invalidate_rank_math_sitemap_cache', 20);

function momentum_invalidate_rank_math_sitemap_cache(int $post_id): void
{
	if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
		return;
	}

	if (! class_exists('\RankMath\Sitemap\Cache')) {
		return;
	}

	\RankMath\Sitemap\Cache::invalidate_storage(get_post_type($post_id));
}
