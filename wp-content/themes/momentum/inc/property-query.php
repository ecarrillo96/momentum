<?php
/**
 * Shared filtering/query logic for property listing pages
 * (Venta, Renta, Terrenos). Reads whitelisted GET params, builds a
 * WP_Query, and returns everything the listing template needs to render.
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Únicos valores de 'estado' que deben ser visibles al público. El resto
 * del catálogo (Apartada, Vendida, Rentada) sigue existiendo y siendo
 * editable en el admin, pero nunca debe salir en una vista pública.
 */
function momentum_public_estado_values(): array
{
	return ['Disponible', 'Preventa', 'Remate'];
}

function momentum_price_range_options(string $tipo_operacion = '', string $post_type = 'propiedades'): array
{
	if ($post_type === 'terrenos') {
		return [
			''                => __('Cualquier precio', 'momentum'),
			'0-1000000'       => __('Hasta $1,000,000', 'momentum'),
			'1000000-2000000' => __('$1,000,000 - $2,000,000', 'momentum'),
			'2000000-3000000' => __('$2,000,000 - $3,000,000', 'momentum'),
			'3000000-4000000' => __('$3,000,000 - $4,000,000', 'momentum'),
			'4000000-5000000' => __('$4,000,000 - $5,000,000', 'momentum'),
			'5000000-'        => __('Más de $5,000,000', 'momentum'),
		];
	}

	if ($tipo_operacion === 'Renta') {
		return [
			''            => __('Cualquier precio', 'momentum'),
			'0-20000'     => __('Hasta $20,000/mes', 'momentum'),
			'20000-30000' => __('$20,000 - $30,000/mes', 'momentum'),
			'30000-40000' => __('$30,000 - $40,000/mes', 'momentum'),
			'40000-50000' => __('$40,000 - $50,000/mes', 'momentum'),
			'50000-60000' => __('$50,000 - $60,000/mes', 'momentum'),
			'60000-'      => __('Más de $60,000/mes', 'momentum'),
		];
	}

	return [
		''                  => __('Cualquier precio', 'momentum'),
		'0-6000000'         => __('Hasta $6,000,000', 'momentum'),
		'6000000-9000000'   => __('$6,000,000 - $9,000,000', 'momentum'),
		'9000000-12000000'  => __('$9,000,000 - $12,000,000', 'momentum'),
		'12000000-15000000' => __('$12,000,000 - $15,000,000', 'momentum'),
		'15000000-18000000' => __('$15,000,000 - $18,000,000', 'momentum'),
		'18000000-21000000' => __('$18,000,000 - $21,000,000', 'momentum'),
		'21000000-'         => __('Más de $21,000,000', 'momentum'),
	];
}

/**
 * @param array    $fixed          ['tipo_operacion' => 'Venta']
 *                                 Locks a meta value regardless of what's in $_GET.
 * @param int      $per_page
 * @param int|null $paged_override Usa esta página en vez de leer $_GET['pg'].
 *                                 Ver momentum_guard_property_listing_page() para el porqué.
 * @param string   $post_type      'propiedades' (Venta/Renta) o 'terrenos'. $fixed['tipo_operacion']
 *                                 se ignora para 'terrenos' (ese CPT no tiene ese meta).
 */
function momentum_run_property_query(array $fixed = [], int $per_page = 10, ?int $paged_override = null, string $post_type = 'propiedades'): array
{
	$filters = [
		'precio' => isset($_GET['precio']) && array_key_exists($_GET['precio'], momentum_price_range_options($fixed['tipo_operacion'] ?? '', $post_type)) ? $_GET['precio'] : '',
		'codigo' => isset($_GET['codigo']) ? trim(sanitize_text_field(wp_unslash($_GET['codigo']))) : '',
	];

	$paged = $paged_override ?? (isset($_GET['pg']) ? max(1, absint($_GET['pg'])) : 1);

	$meta_query = [
		'relation' => 'AND',
		['key' => 'estado', 'value' => momentum_public_estado_values(), 'compare' => 'IN'],
	];

	if (! empty($fixed['tipo_operacion'])) {
		$meta_query[] = ['key' => 'tipo_operacion', 'value' => $fixed['tipo_operacion'], 'compare' => '='];
	}

	if ($filters['precio']) {
		[$min, $max] = array_pad(explode('-', $filters['precio']), 2, '');

		if ($min !== '' && $max !== '') {
			$meta_query[] = ['key' => 'precio', 'value' => [(float) $min, (float) $max], 'compare' => 'BETWEEN', 'type' => 'NUMERIC'];
		} elseif ($min !== '') {
			$meta_query[] = ['key' => 'precio', 'value' => (float) $min, 'compare' => '>=', 'type' => 'NUMERIC'];
		}
	}

	if ($filters['codigo'] !== '') {
		$meta_query[] = ['key' => 'codigo_propiedad', 'value' => $filters['codigo'], 'compare' => 'LIKE'];
	}

	$query_args = [
		'post_type'      => $post_type,
		'post_status'    => 'publish',
		'posts_per_page' => $per_page,
		'paged'          => $paged,
		'meta_query'     => $meta_query,
		// Sin esto, esta WP_Query (no es "la query principal", así que el
		// filtrado automático de idioma de Polylang no la alcanza) devuelve
		// fichas de todos los idiomas mezcladas -confirmado en vivo: sin
		// 'lang', la misma consulta ignoraba el idioma actual por completo-.
		// momentum_current_language() ya cae a 'es' sin Polylang activo, y
		// WP_Query simplemente ignora 'lang' si Polylang no está cargado.
		'lang'           => momentum_current_language(),
	];

	// Siempre ordenado de mayor a menor precio. 'ID' => 'DESC' como criterio
	// secundario estable: sin él, dos propiedades con el mismo precio podrían
	// intercalarse de forma distinta entre páginas si MySQL no garantiza el
	// orden de los empates.
	$query_args['orderby']  = ['meta_value_num' => 'DESC', 'ID' => 'DESC'];
	$query_args['meta_key'] = 'precio';

	return [
		'query'   => new WP_Query($query_args),
		'filters' => $filters,
		'paged'   => $paged,
	];
}

/**
 * El archivo nativo /propiedades/ (has_archive) no tiene template propio,
 * así que corre la query principal de WordPress sin filtrar. Este hook la
 * restringe a las mismas reglas de visibilidad pública que el resto del
 * sitio (publicada + estado en la lista permitida).
 */
function momentum_filter_propiedades_archive_query(WP_Query $query): void
{
	if (is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive('propiedades')) {
		return;
	}

	$meta_query   = (array) $query->get('meta_query');
	$meta_query[] = ['key' => 'estado', 'value' => momentum_public_estado_values(), 'compare' => 'IN'];

	$query->set('meta_query', $meta_query);
	$query->set('post_status', 'publish');
	// Explícito por seguridad, aunque sea la query principal (donde Polylang
	// sí suele filtrar por idioma solo): mismo motivo que en
	// momentum_run_property_query() arriba.
	$query->set('lang', momentum_current_language());
}
add_action('pre_get_posts', 'momentum_filter_propiedades_archive_query');

/**
 * Debe llamarse ANTES de get_header() en la página que renderiza el listado
 * (evita "headers already sent" al redirigir). Corrige ?pg= fuera de rango
 * -0, negativo, o mayor al total de páginas- llevando al usuario a la página
 * válida más cercana, en vez de mostrar una grilla vacía o repetida.
 *
 * @param array  $fixed     Mismos args que momentum_run_property_query().
 * @param int    $per_page
 * @param string $post_type Mismo argumento que momentum_run_property_query().
 */
function momentum_guard_property_listing_page(array $fixed, int $per_page, string $post_type = 'propiedades'): void
{
	$requested = isset($_GET['pg']) ? (int) $_GET['pg'] : 1;

	if ($requested < 1) {
		wp_safe_redirect(remove_query_arg('pg'));
		exit;
	}

	// $paged_override = 1 (no la página pedida) evita un bug real de
	// MySQL/MariaDB: con meta_query (JOIN + GROUP BY) y un LIMIT/OFFSET que
	// excede las filas disponibles, SQL_CALC_FOUND_ROWS puede devolver
	// found_posts=0 en vez del total real, lo que rompería esta validación
	// justo para el caso que necesita detectar (páginas fuera de rango).
	$result      = momentum_run_property_query($fixed, $per_page, 1, $post_type);
	$total_pages = (int) $result['query']->max_num_pages;

	if ($total_pages > 0 && $requested > $total_pages) {
		$target = $total_pages > 1 ? add_query_arg('pg', $total_pages) : remove_query_arg('pg');
		wp_safe_redirect($target);
		exit;
	}
}

/**
 * Ventana de páginas a mostrar (números + huecos), replicando el mismo
 * algoritmo end_size/mid_size que ya usaba paginate_links() en este
 * proyecto, para no cambiar la densidad visual ya establecida.
 *
 * @return array<int, int|null> Items en orden; null = hueco (puntos suspensivos).
 */
function momentum_pagination_window(int $current, int $total, int $end_size, int $mid_size): array
{
	if ($total <= 1) {
		return $total === 1 ? [1] : [];
	}

	$pages = [];

	for ($i = 1; $i <= $total; $i++) {
		$in_start = $i <= $end_size;
		$in_end   = $i > $total - $end_size;
		$in_mid   = $i >= $current - $mid_size && $i <= $current + $mid_size;

		if ($in_start || $in_end || $in_mid) {
			$pages[] = $i;
		}
	}

	$items = [];
	$prev  = 0;

	foreach ($pages as $page) {
		if ($prev && $page - $prev > 1) {
			$items[] = null;
		}
		$items[] = $page;
		$prev    = $page;
	}

	return $items;
}

/**
 * Barra de paginación profesional: Primera/Anterior/números/Siguiente/Última,
 * con estados disabled, página actual resaltada (aria-current) y aria-label
 * en cada control. Conserva cualquier filtro presente en la URL actual
 * (add_query_arg()/remove_query_arg() sin URL explícita operan sobre la
 * request actual), solo modifica 'pg'.
 */
function momentum_render_property_pagination(int $current, int $total_pages): void
{
	if ($total_pages <= 1) {
		return;
	}

	$url_for = static function (int $page): string {
		return $page <= 1 ? remove_query_arg('pg') : add_query_arg('pg', $page);
	};

	$control = static function (string $symbol, string $label, ?string $href): string {
		if ($href === null) {
			return sprintf(
				'<span class="momentum-pagination__control is-disabled" aria-disabled="true" aria-label="%s">%s</span>',
				esc_attr($label),
				esc_html($symbol)
			);
		}

		return sprintf(
			'<a class="momentum-pagination__control" href="%s" aria-label="%s">%s</a>',
			esc_url($href),
			esc_attr($label),
			esc_html($symbol)
		);
	};

	$number_link = static function (int $page, int $current) use ($url_for): string {
		if ($page === $current) {
			return sprintf(
				'<span class="momentum-pagination__number is-current" aria-current="page" aria-label="%s">%s</span>',
				esc_attr(sprintf(
					/* translators: %d: número de página, para el aria-label de la página actual en la paginación */
					__('Página %d, página actual', 'momentum'),
					$page
				)),
				esc_html((string) $page)
			);
		}

		return sprintf(
			'<a class="momentum-pagination__number" href="%s" aria-label="%s">%s</a>',
			esc_url($url_for($page)),
			esc_attr(sprintf(
				/* translators: %d: número de página, para el aria-label de un link de paginación */
				__('Ir a la página %d', 'momentum'),
				$page
			)),
			esc_html((string) $page)
		);
	};

	$dots = '<span class="momentum-pagination__dots" aria-hidden="true">&hellip;</span>';

	$render_numbers = static function (array $window) use ($number_link, $dots, $current): string {
		$html = '';
		foreach ($window as $item) {
			$html .= $item === null ? $dots : $number_link($item, $current);
		}

		return $html;
	};

	$is_first = $current <= 1;
	$is_last  = $current >= $total_pages;

	// Desktop conserva la densidad previa (end_size=1, mid_size=2); en móvil
	// se reduce a solo la página actual + extremos, con puntos suspensivos
	// donde haga falta, para nunca generar scroll horizontal.
	$desktop_window = momentum_pagination_window($current, $total_pages, 1, 2);
	$mobile_window  = momentum_pagination_window($current, $total_pages, 1, 0);
	?>
	<nav class="momentum-pagination" aria-label="<?php esc_attr_e('Paginación de propiedades', 'momentum'); ?>">
		<?php echo $control('«', __('Ir a la primera página', 'momentum'), $is_first ? null : $url_for(1)); ?>
		<?php echo $control('‹', __('Ir a la página anterior', 'momentum'), $is_first ? null : $url_for($current - 1)); ?>

		<div class="momentum-pagination__numbers momentum-pagination__numbers--desktop">
			<?php echo $render_numbers($desktop_window); ?>
		</div>
		<div class="momentum-pagination__numbers momentum-pagination__numbers--mobile">
			<?php echo $render_numbers($mobile_window); ?>
		</div>

		<?php echo $control('›', __('Ir a la página siguiente', 'momentum'), $is_last ? null : $url_for($current + 1)); ?>
		<?php echo $control('»', __('Ir a la última página', 'momentum'), $is_last ? null : $url_for($total_pages)); ?>
	</nav>
	<?php
}
