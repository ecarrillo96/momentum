<?php
/**
 * Capa de reportes del panel de Estadísticas: un método público por sección
 * del dashboard. Aquí, y SOLO aquí, se deciden las dimensiones/métricas de
 * cada consulta -Momentum_GA4_Client nunca recibe nada del navegador, y
 * Momentum_GA4_Ajax solo puede pedir uno de los nombres de reporte
 * definidos en REPORTS (ver ese archivo)-.
 *
 * También vive aquí el cruce de pagePath (tal como lo reporta GA4) contra
 * los posts reales de WordPress para Propiedades/Terrenos, y la separación
 * de tráfico español/inglés por el prefijo /en/ de la URL -confirmado
 * inspeccionando el sitio real, no asumido (ver docs/09-sistema-idioma.md
 * y docs/18-panel-google-analytics.md)-.
 */

if (! defined('ABSPATH')) {
	exit;
}

class Momentum_GA4_Reports
{
	private const SUMMARY_METRICS = [
		'activeUsers', 'totalUsers', 'newUsers', 'sessions', 'engagedSessions',
		'engagementRate', 'screenPageViews', 'eventCount', 'averageSessionDuration',
	];

	/**
	 * Eventos que el negocio quiere rastrear, con variantes de nombre
	 * plausibles a buscar en lo que GA4 reporte realmente -nunca se inventan
	 * si no aparecen, ver get_events()-.
	 */
	private const RECOMMENDED_EVENTS = [
		'whatsapp_click'  => ['label' => 'Clic en WhatsApp', 'patterns' => ['whatsapp']],
		'contact_form'    => ['label' => 'Envío de formulario de contacto', 'patterns' => ['contact_form', 'form_submit', 'generate_lead']],
		'phone_click'     => ['label' => 'Clic en teléfono', 'patterns' => ['phone_click', 'tel_click', 'click_phone']],
		'email_click'     => ['label' => 'Clic en correo electrónico', 'patterns' => ['email_click', 'mailto_click', 'click_email']],
		'property_view'   => ['label' => 'Consulta de propiedad', 'patterns' => ['property_view', 'view_propiedad', 'view_item']],
		'terreno_view'    => ['label' => 'Consulta de terreno', 'patterns' => ['terreno_view', 'view_terreno']],
		'language_switch' => ['label' => 'Cambio de idioma', 'patterns' => ['language_switch', 'select_language', 'idioma']],
	];

	// -----------------------------------------------------------------
	// Rango de fechas
	// -----------------------------------------------------------------

	/**
	 * @return array{start: DateTimeImmutable, end: DateTimeImmutable}|null null = periodo/fechas inválidos.
	 */
	public static function resolve_range(string $period, string $custom_start = '', string $custom_end = ''): ?array
	{
		$tz    = wp_timezone();
		$today = new DateTimeImmutable('today', $tz);

		switch ($period) {
			case 'today':
				return ['start' => $today, 'end' => $today];
			case 'yesterday':
				$d = $today->modify('-1 day');

				return ['start' => $d, 'end' => $d];
			case '7d':
				return ['start' => $today->modify('-6 days'), 'end' => $today];
			case '28d':
				return ['start' => $today->modify('-27 days'), 'end' => $today];
			case '30d':
				return ['start' => $today->modify('-29 days'), 'end' => $today];
			case '90d':
				return ['start' => $today->modify('-89 days'), 'end' => $today];
			case 'custom':
				if (! self::is_valid_date($custom_start) || ! self::is_valid_date($custom_end)) {
					return null;
				}

				$start = new DateTimeImmutable($custom_start, $tz);
				$end   = new DateTimeImmutable($custom_end, $tz);

				if ($start > $end || $start->diff($end)->days > 400) {
					return null;
				}

				return ['start' => $start, 'end' => $end];
			default:
				return null;
		}
	}

	private static function is_valid_date(string $value): bool
	{
		if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
			return false;
		}

		$d = DateTimeImmutable::createFromFormat('Y-m-d', $value);

		return $d instanceof DateTimeImmutable && $d->format('Y-m-d') === $value;
	}

	/**
	 * Periodo inmediatamente anterior, de la misma duración -método estándar
	 * de "comparar contra el periodo anterior".
	 */
	private static function previous_range(array $range): array
	{
		$length     = $range['start']->diff($range['end'])->days + 1;
		$prev_end   = $range['start']->modify('-1 day');
		$prev_start = $prev_end->modify('-' . ($length - 1) . ' days');

		return ['start' => $prev_start, 'end' => $prev_end];
	}

	private static function fmt(DateTimeImmutable $d): string
	{
		return $d->format('Y-m-d');
	}

	// -----------------------------------------------------------------
	// Infraestructura compartida: caché + candado + llamada + errores
	// -----------------------------------------------------------------

	private static function cached_call(string $report_name, array $cache_params, callable $build_request): array
	{
		$cache_key = Momentum_GA4_Cache::key($report_name, $cache_params);
		$cached    = Momentum_GA4_Cache::get($cache_key);

		if ($cached !== null) {
			$cached['meta']['from_cache'] = true;

			return $cached;
		}

		if (! Momentum_GA4_Cache::acquire_lock($cache_key)) {
			return [
				'success'       => false,
				'error_code'    => 'locked',
				'error_message' => 'Ya hay una consulta en curso para este reporte. Intenta de nuevo en unos segundos.',
				'data'          => null,
				'meta'          => ['cached_at' => null, 'from_cache' => false],
			];
		}

		try {
			$request_body = $build_request();
			$raw          = Momentum_GA4_Client::run_report($request_body);

			if ($raw === null) {
				$result = self::error_result(Momentum_GA4_Client::get_last_error());
				Momentum_GA4_Diagnostics::record_check(false, $result['error_message']);

				return $result;
			}

			Momentum_GA4_Diagnostics::record_check(true);

			$result = [
				'success'       => true,
				'error_code'    => '',
				'error_message' => '',
				'data'          => $raw,
			];

			Momentum_GA4_Cache::set($cache_key, $result);
			$result['meta']['from_cache'] = false;

			return $result;
		} finally {
			Momentum_GA4_Cache::release_lock($cache_key);
		}
	}

	private static function error_result(string $code): array
	{
		$messages = [
			'not_configured'  => 'Google Analytics todavía no está configurado. Define MOMENTUM_GA4_PROPERTY_ID y las credenciales de la cuenta de servicio en wp-config.php.',
			'invalid_key'     => 'La private key del archivo de credenciales no es válida. Vuelve a descargar el JSON de la cuenta de servicio.',
			'auth'            => 'No se pudo autenticar con Google. Verifica que la cuenta de servicio y sus credenciales sean correctas.',
			'network'         => 'No se pudo conectar con Google Analytics en este momento. Intenta de nuevo en unos minutos.',
			'permission'      => 'La cuenta de servicio no tiene acceso de lectura a esta propiedad de GA4, o la API de Analytics Data no está habilitada en el proyecto de Google Cloud.',
			'quota'           => 'Se alcanzó el límite de solicitudes de Google Analytics. Intenta de nuevo más tarde.',
			'bad_request'     => 'La consulta enviada a Google Analytics no es válida (posible combinación de métrica/dimensión incompatible, o rango de fechas fuera de lo permitido).',
			'invalid_response' => 'Google Analytics devolvió una respuesta inesperada.',
		];

		$code = $code ?: 'invalid_response';

		return [
			'success'       => false,
			'error_code'    => $code,
			'error_message' => $messages[$code] ?? $messages['invalid_response'],
			'data'          => null,
			'meta'          => ['cached_at' => null, 'from_cache' => false],
		];
	}

	// -----------------------------------------------------------------
	// Resumen principal + comparación
	// -----------------------------------------------------------------

	public static function get_summary(string $period, bool $compare = false, string $custom_start = '', string $custom_end = ''): array
	{
		$range = self::resolve_range($period, $custom_start, $custom_end);

		if ($range === null) {
			return self::error_result('bad_request');
		}

		$date_ranges = [[
			'startDate' => self::fmt($range['start']),
			'endDate'   => self::fmt($range['end']),
		]];

		if ($compare) {
			$prev          = self::previous_range($range);
			$date_ranges[] = [
				'startDate' => self::fmt($prev['start']),
				'endDate'   => self::fmt($prev['end']),
			];
		}

		$cache_params = [
			'period'  => $period,
			'start'   => self::fmt($range['start']),
			'end'     => self::fmt($range['end']),
			'compare' => $compare,
		];

		$result = self::cached_call('summary', $cache_params, static function () use ($date_ranges) {
			return [
				'dateRanges' => $date_ranges,
				'metrics'    => array_map(static fn (string $m): array => ['name' => $m], self::SUMMARY_METRICS),
			];
		});

		if ($result['success']) {
			$result['data'] = self::normalize_summary($result['data'], $compare);
		}

		return $result;
	}

	private static function normalize_summary(array $raw, bool $compare): array
	{
		$dim_headers    = array_column($raw['dimensionHeaders'] ?? [], 'name');
		$metric_headers = array_column($raw['metricHeaders'] ?? [], 'name');
		$date_range_idx = array_search('dateRange', $dim_headers, true);

		$totals = ['date_range_0' => [], 'date_range_1' => []];

		foreach ($raw['rows'] ?? [] as $row) {
			$range_key = 'date_range_0';

			if ($date_range_idx !== false && isset($row['dimensionValues'][$date_range_idx]['value'])) {
				$range_key = $row['dimensionValues'][$date_range_idx]['value'];
			}

			$values = [];

			foreach ($row['metricValues'] ?? [] as $i => $mv) {
				$name           = $metric_headers[$i] ?? ('metric_' . $i);
				$values[$name] = is_numeric($mv['value'] ?? null) ? (float) $mv['value'] : 0.0;
			}

			$totals[$range_key] = $values;
		}

		foreach (['date_range_0', 'date_range_1'] as $key) {
			if (empty($totals[$key])) {
				$totals[$key] = array_fill_keys(self::SUMMARY_METRICS, 0.0);
			}
		}

		$current  = $totals['date_range_0'];
		$previous = $totals['date_range_1'];

		$cards = [];

		foreach (self::SUMMARY_METRICS as $metric) {
			$cur = $current[$metric] ?? 0.0;

			$card = ['metric' => $metric, 'current' => $cur];

			if ($compare) {
				$prev    = $previous[$metric] ?? 0.0;
				$diff    = $cur - $prev;
				// Sin base para calcular %: se deja null (nunca se divide entre
				// cero) y el frontend muestra "—" en vez de un porcentaje falso.
				$percent = $prev > 0 ? round(($diff / $prev) * 100, 1) : null;

				$card['previous']  = $prev;
				$card['diff']      = $diff;
				$card['percent']   = $percent;
				$card['direction'] = $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'flat');
			}

			$cards[$metric] = $card;
		}

		return $cards;
	}

	// -----------------------------------------------------------------
	// Gráfica diaria
	// -----------------------------------------------------------------

	public static function get_timeseries(string $period, string $custom_start = '', string $custom_end = ''): array
	{
		$range = self::resolve_range($period, $custom_start, $custom_end);

		if ($range === null) {
			return self::error_result('bad_request');
		}

		$cache_params = ['start' => self::fmt($range['start']), 'end' => self::fmt($range['end'])];

		$result = self::cached_call('timeseries', $cache_params, static function () use ($range) {
			return [
				'dateRanges' => [['startDate' => self::fmt($range['start']), 'endDate' => self::fmt($range['end'])]],
				'dimensions' => [['name' => 'date']],
				'metrics'    => [['name' => 'activeUsers'], ['name' => 'sessions'], ['name' => 'screenPageViews']],
				'orderBys'   => [['dimension' => ['dimensionName' => 'date']]],
				'limit'      => 400,
			];
		});

		if ($result['success']) {
			$result['data'] = self::normalize_timeseries($result['data']);
		}

		return $result;
	}

	private static function normalize_timeseries(array $raw): array
	{
		$metric_headers = array_column($raw['metricHeaders'] ?? [], 'name');
		$points         = [];

		foreach ($raw['rows'] ?? [] as $row) {
			$raw_date = $row['dimensionValues'][0]['value'] ?? '';
			$date     = self::format_ga4_date($raw_date);
			$values   = ['date' => $date];

			foreach ($row['metricValues'] ?? [] as $i => $mv) {
				$name           = $metric_headers[$i] ?? ('metric_' . $i);
				$values[$name] = is_numeric($mv['value'] ?? null) ? (float) $mv['value'] : 0.0;
			}

			$points[] = $values;
		}

		return $points;
	}

	/**
	 * GA4 devuelve 'date' como 'YYYYMMDD' sin separadores.
	 */
	private static function format_ga4_date(string $raw): string
	{
		if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $raw, $m)) {
			return "{$m[1]}-{$m[2]}-{$m[3]}";
		}

		return $raw;
	}

	// -----------------------------------------------------------------
	// Páginas más visitadas
	// -----------------------------------------------------------------

	public static function get_top_pages(string $period, string $custom_start = '', string $custom_end = ''): array
	{
		return self::fetch_pages_report('top_pages', $period, $custom_start, $custom_end, null);
	}

	public static function get_top_properties(string $period, string $custom_start = '', string $custom_end = ''): array
	{
		$result = self::fetch_pages_report('top_properties', $period, $custom_start, $custom_end, 'propiedades');

		if ($result['success']) {
			$result['data'] = self::attach_post_matches($result['data'], 'propiedades');
		}

		return $result;
	}

	public static function get_top_terrenos(string $period, string $custom_start = '', string $custom_end = ''): array
	{
		$result = self::fetch_pages_report('top_terrenos', $period, $custom_start, $custom_end, 'terrenos');

		if ($result['success']) {
			$result['data'] = self::attach_post_matches($result['data'], 'terrenos');
		}

		return $result;
	}

	/**
	 * @param string|null $path_segment Si se pasa ('propiedades'|'terrenos'),
	 *                                   filtra pagePath a rutas que contengan
	 *                                   "/{segmento}/". Si es null, trae
	 *                                   páginas en general, excluyendo rutas
	 *                                   técnicas.
	 */
	private static function fetch_pages_report(string $report_name, string $period, string $custom_start, string $custom_end, ?string $path_segment): array
	{
		$range = self::resolve_range($period, $custom_start, $custom_end);

		if ($range === null) {
			return self::error_result('bad_request');
		}

		$cache_params = ['start' => self::fmt($range['start']), 'end' => self::fmt($range['end']), 'segment' => $path_segment];

		$result = self::cached_call($report_name, $cache_params, static function () use ($range, $path_segment) {
			$filter_expressions = [];

			if ($path_segment !== null) {
				$filter_expressions[] = [
					'filter' => [
						'fieldName'    => 'pagePath',
						'stringFilter' => ['matchType' => 'CONTAINS', 'value' => '/' . $path_segment . '/'],
					],
				];
			} else {
				// Excluye rutas técnicas del listado general de páginas.
				foreach (['wp-admin', 'wp-login.php', 'preview=true'] as $needle) {
					$filter_expressions[] = [
						'notExpression' => [
							'filter' => [
								'fieldName'    => 'pagePath',
								'stringFilter' => ['matchType' => 'CONTAINS', 'value' => $needle],
							],
						],
					];
				}
			}

			return [
				'dateRanges'      => [['startDate' => self::fmt($range['start']), 'endDate' => self::fmt($range['end'])]],
				'dimensions'      => [['name' => 'pageTitle'], ['name' => 'pagePath']],
				'metrics'         => [
					['name' => 'screenPageViews'],
					['name' => 'totalUsers'],
					['name' => 'sessions'],
					['name' => 'userEngagementDuration'],
					['name' => 'engagementRate'],
				],
				'dimensionFilter' => ['andGroup' => ['expressions' => $filter_expressions]],
				'orderBys'        => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]],
				'limit'           => 50,
			];
		});

		if ($result['success']) {
			$result['data'] = self::normalize_pages($result['data']);
		}

		return $result;
	}

	/**
	 * Convierte filas crudas de un reporte de páginas (pageTitle/pagePath +
	 * métricas) al formato tabular usado por el dashboard, con tiempo medio
	 * de interacción ya calculado (evita división entre cero).
	 */
	private static function normalize_pages(array $raw): array
	{
		$metric_headers = array_column($raw['metricHeaders'] ?? [], 'name');
		$rows           = [];

		foreach ($raw['rows'] ?? [] as $row) {
			$title = $row['dimensionValues'][0]['value'] ?? '';
			$path  = $row['dimensionValues'][1]['value'] ?? '';

			$values = [];
			foreach ($row['metricValues'] ?? [] as $i => $mv) {
				$name           = $metric_headers[$i] ?? ('metric_' . $i);
				$values[$name] = is_numeric($mv['value'] ?? null) ? (float) $mv['value'] : 0.0;
			}

			$views                    = $values['screenPageViews'] ?? 0.0;
			$engagement_duration_total = $values['userEngagementDuration'] ?? 0.0;

			$rows[] = [
				'title'                  => $title,
				'path'                   => $path,
				'views'                  => $views,
				'users'                  => $values['totalUsers'] ?? 0.0,
				'sessions'               => $values['sessions'] ?? 0.0,
				// Promedio derivado: duración total de interacción / vistas.
				// $views > 0 siempre que exista la fila (GA4 no devuelve filas
				// con 0 vistas), pero se protege igual contra división entre cero.
				'avg_engagement_seconds' => $views > 0 ? round($engagement_duration_total / $views, 1) : 0.0,
				'engagement_rate'        => round(($values['engagementRate'] ?? 0.0) * 100, 1),
			];
		}

		return $rows;
	}

	/**
	 * Cruza cada fila (pagePath) con el post real de WordPress: quita el
	 * prefijo /en/ si existe, espera exactamente "{segmento}/{slug}", y
	 * busca por slug (post_name es único entre idiomas -son posts distintos-).
	 */
	private static function attach_post_matches(array $rows, string $post_type): array
	{
		foreach ($rows as &$row) {
			$match = self::match_post_by_path($row['path'], $post_type);

			$row['post_id']    = $match['id'] ?? null;
			$row['wp_title']   = $match['title'] ?? null;
			$row['edit_link']  = $match['edit_link'] ?? null;
			$row['is_english'] = self::is_english_path($row['path']);
		}
		unset($row);

		return $rows;
	}

	/**
	 * @return array{id:int,title:string,edit_link:string|null}|null
	 */
	private static function match_post_by_path(string $path, string $post_type): ?array
	{
		$path     = (string) (wp_parse_url($path, PHP_URL_PATH) ?? $path);
		$path     = trim($path, '/');
		$segments = $path === '' ? [] : explode('/', $path);

		if (($segments[0] ?? '') === 'en') {
			array_shift($segments);
		}

		if (count($segments) !== 2 || $segments[0] !== $post_type) {
			return null;
		}

		$post = get_page_by_path($segments[1], OBJECT, $post_type);

		if (! $post instanceof WP_Post) {
			return null;
		}

		return [
			'id'        => $post->ID,
			'title'     => get_the_title($post),
			'edit_link' => get_edit_post_link($post->ID, ''),
		];
	}

	/**
	 * Regla de idioma para todo el dashboard: cualquier ruta que empiece con
	 * /en/ es inglés (Polylang oculta el prefijo del idioma por defecto,
	 * español) — se prioriza la ruta real visitada, nunca el idioma del
	 * navegador (GA4 no lo expone de forma fiable de todas formas).
	 */
	private static function is_english_path(string $path): bool
	{
		$path = trim((string) (wp_parse_url($path, PHP_URL_PATH) ?? $path), '/');

		return $path === 'en' || str_starts_with($path, 'en/');
	}

	// -----------------------------------------------------------------
	// Fuentes de tráfico
	// -----------------------------------------------------------------

	public static function get_traffic_sources(string $period, string $custom_start = '', string $custom_end = ''): array
	{
		$range = self::resolve_range($period, $custom_start, $custom_end);

		if ($range === null) {
			return self::error_result('bad_request');
		}

		$cache_params = ['start' => self::fmt($range['start']), 'end' => self::fmt($range['end'])];

		$result = self::cached_call('traffic_sources', $cache_params, static function () use ($range) {
			return [
				'dateRanges' => [['startDate' => self::fmt($range['start']), 'endDate' => self::fmt($range['end'])]],
				'dimensions' => [['name' => 'sessionDefaultChannelGroup']],
				'metrics'    => [['name' => 'activeUsers'], ['name' => 'sessions']],
				'orderBys'   => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
				'limit'      => 20,
			];
		});

		if ($result['success']) {
			$result['data'] = self::normalize_single_dimension($result['data'], 'channel');
		}

		return $result;
	}

	public static function get_source_medium(string $period, string $custom_start = '', string $custom_end = ''): array
	{
		$range = self::resolve_range($period, $custom_start, $custom_end);

		if ($range === null) {
			return self::error_result('bad_request');
		}

		$cache_params = ['start' => self::fmt($range['start']), 'end' => self::fmt($range['end'])];

		$result = self::cached_call('source_medium', $cache_params, static function () use ($range) {
			return [
				'dateRanges' => [['startDate' => self::fmt($range['start']), 'endDate' => self::fmt($range['end'])]],
				'dimensions' => [['name' => 'sessionSource'], ['name' => 'sessionMedium']],
				'metrics'    => [['name' => 'totalUsers'], ['name' => 'sessions'], ['name' => 'keyEvents']],
				'orderBys'   => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
				'limit'      => 30,
			];
		});

		if ($result['success']) {
			$result['data'] = self::normalize_source_medium($result['data']);
		}

		return $result;
	}

	private static function normalize_source_medium(array $raw): array
	{
		$metric_headers = array_column($raw['metricHeaders'] ?? [], 'name');
		$rows           = [];

		foreach ($raw['rows'] ?? [] as $row) {
			$values = [];
			foreach ($row['metricValues'] ?? [] as $i => $mv) {
				$name           = $metric_headers[$i] ?? ('metric_' . $i);
				$values[$name] = is_numeric($mv['value'] ?? null) ? (float) $mv['value'] : 0.0;
			}

			$rows[] = [
				'source'      => $row['dimensionValues'][0]['value'] ?? '(not set)',
				'medium'      => $row['dimensionValues'][1]['value'] ?? '(not set)',
				'users'       => $values['totalUsers'] ?? 0.0,
				'sessions'    => $values['sessions'] ?? 0.0,
				'key_events'  => $values['keyEvents'] ?? 0.0,
			];
		}

		return $rows;
	}

	// -----------------------------------------------------------------
	// Ubicación
	// -----------------------------------------------------------------

	public static function get_geo(string $period, string $custom_start = '', string $custom_end = ''): array
	{
		$range = self::resolve_range($period, $custom_start, $custom_end);

		if ($range === null) {
			return self::error_result('bad_request');
		}

		$cache_params = ['start' => self::fmt($range['start']), 'end' => self::fmt($range['end'])];

		$result = self::cached_call('geo', $cache_params, static function () use ($range) {
			return [
				'dateRanges' => [['startDate' => self::fmt($range['start']), 'endDate' => self::fmt($range['end'])]],
				'dimensions' => [['name' => 'country'], ['name' => 'city']],
				'metrics'    => [['name' => 'activeUsers'], ['name' => 'sessions']],
				'orderBys'   => [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]],
				'limit'      => 25,
			];
		});

		if ($result['success']) {
			$result['data'] = self::normalize_geo($result['data']);
		}

		return $result;
	}

	private static function normalize_geo(array $raw): array
	{
		$metric_headers = array_column($raw['metricHeaders'] ?? [], 'name');
		$rows           = [];

		foreach ($raw['rows'] ?? [] as $row) {
			$values = [];
			foreach ($row['metricValues'] ?? [] as $i => $mv) {
				$name           = $metric_headers[$i] ?? ('metric_' . $i);
				$values[$name] = is_numeric($mv['value'] ?? null) ? (float) $mv['value'] : 0.0;
			}

			$rows[] = [
				'country'  => $row['dimensionValues'][0]['value'] ?? '(not set)',
				'city'     => $row['dimensionValues'][1]['value'] ?? '(not set)',
				'users'    => $values['activeUsers'] ?? 0.0,
				'sessions' => $values['sessions'] ?? 0.0,
			];
		}

		return $rows;
	}

	// -----------------------------------------------------------------
	// Dispositivos y navegadores
	// -----------------------------------------------------------------

	public static function get_devices(string $period, string $custom_start = '', string $custom_end = ''): array
	{
		$range = self::resolve_range($period, $custom_start, $custom_end);

		if ($range === null) {
			return self::error_result('bad_request');
		}

		$cache_params = ['start' => self::fmt($range['start']), 'end' => self::fmt($range['end'])];

		$result = self::cached_call('devices', $cache_params, static function () use ($range) {
			return [
				'dateRanges' => [['startDate' => self::fmt($range['start']), 'endDate' => self::fmt($range['end'])]],
				'dimensions' => [['name' => 'deviceCategory']],
				'metrics'    => [['name' => 'activeUsers'], ['name' => 'sessions'], ['name' => 'screenPageViews']],
				'orderBys'   => [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]],
			];
		});

		if ($result['success']) {
			$rows          = self::normalize_single_dimension($result['data'], 'device');
			$total_users   = array_sum(array_column($rows, 'users'));
			$result['data'] = array_map(static function (array $row) use ($total_users): array {
				$row['percent'] = $total_users > 0 ? round(($row['users'] / $total_users) * 100, 1) : 0.0;

				return $row;
			}, $rows);
		}

		return $result;
	}

	public static function get_browsers(string $period, string $custom_start = '', string $custom_end = ''): array
	{
		$range = self::resolve_range($period, $custom_start, $custom_end);

		if ($range === null) {
			return self::error_result('bad_request');
		}

		$cache_params = ['start' => self::fmt($range['start']), 'end' => self::fmt($range['end'])];

		$result = self::cached_call('browsers', $cache_params, static function () use ($range) {
			return [
				'dateRanges' => [['startDate' => self::fmt($range['start']), 'endDate' => self::fmt($range['end'])]],
				'dimensions' => [['name' => 'browser']],
				'metrics'    => [['name' => 'activeUsers'], ['name' => 'sessions']],
				'orderBys'   => [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]],
				'limit'      => 15,
			];
		});

		if ($result['success']) {
			$result['data'] = self::normalize_single_dimension($result['data'], 'browser');
		}

		return $result;
	}

	/**
	 * Normalizador genérico para reportes de 1 dimensión + activeUsers/sessions
	 * (+screenPageViews si viene) — reutilizado por canal, dispositivo, navegador.
	 */
	private static function normalize_single_dimension(array $raw, string $dimension_key): array
	{
		$metric_headers = array_column($raw['metricHeaders'] ?? [], 'name');
		$rows           = [];

		foreach ($raw['rows'] ?? [] as $row) {
			$values = [];
			foreach ($row['metricValues'] ?? [] as $i => $mv) {
				$name           = $metric_headers[$i] ?? ('metric_' . $i);
				$values[$name] = is_numeric($mv['value'] ?? null) ? (float) $mv['value'] : 0.0;
			}

			$entry                    = [$dimension_key => $row['dimensionValues'][0]['value'] ?? '(not set)'];
			$entry['users']           = $values['activeUsers'] ?? 0.0;
			$entry['sessions']        = $values['sessions'] ?? 0.0;

			if (isset($values['screenPageViews'])) {
				$entry['views'] = $values['screenPageViews'];
			}

			$rows[] = $entry;
		}

		return $rows;
	}

	// -----------------------------------------------------------------
	// Idiomas (ES/EN por ruta real, no por idioma del navegador)
	// -----------------------------------------------------------------

	public static function get_languages(string $period, string $custom_start = '', string $custom_end = ''): array
	{
		$range = self::resolve_range($period, $custom_start, $custom_end);

		if ($range === null) {
			return self::error_result('bad_request');
		}

		$cache_params = ['start' => self::fmt($range['start']), 'end' => self::fmt($range['end'])];

		$result = self::cached_call('languages', $cache_params, static function () use ($range) {
			return [
				'dateRanges' => [['startDate' => self::fmt($range['start']), 'endDate' => self::fmt($range['end'])]],
				'dimensions' => [['name' => 'pageTitle'], ['name' => 'pagePath']],
				'metrics'    => [['name' => 'activeUsers'], ['name' => 'sessions'], ['name' => 'screenPageViews']],
				'orderBys'   => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]],
				'limit'      => 200,
			];
		});

		if ($result['success']) {
			$result['data'] = self::normalize_languages($result['data']);
		}

		return $result;
	}

	private static function normalize_languages(array $raw): array
	{
		$metric_headers = array_column($raw['metricHeaders'] ?? [], 'name');

		$totals = [
			'es' => ['users' => 0.0, 'sessions' => 0.0, 'views' => 0.0],
			'en' => ['users' => 0.0, 'sessions' => 0.0, 'views' => 0.0],
		];
		$top_pages = ['es' => [], 'en' => []];

		foreach ($raw['rows'] ?? [] as $row) {
			$title = $row['dimensionValues'][0]['value'] ?? '';
			$path  = $row['dimensionValues'][1]['value'] ?? '';
			$lang  = self::is_english_path($path) ? 'en' : 'es';

			$values = [];
			foreach ($row['metricValues'] ?? [] as $i => $mv) {
				$name           = $metric_headers[$i] ?? ('metric_' . $i);
				$values[$name] = is_numeric($mv['value'] ?? null) ? (float) $mv['value'] : 0.0;
			}

			$totals[$lang]['users']    += $values['activeUsers'] ?? 0.0;
			$totals[$lang]['sessions'] += $values['sessions'] ?? 0.0;
			$totals[$lang]['views']    += $values['screenPageViews'] ?? 0.0;

			if (count($top_pages[$lang]) < 10) {
				$top_pages[$lang][] = [
					'title' => $title,
					'path'  => $path,
					'views' => $values['screenPageViews'] ?? 0.0,
				];
			}
		}

		return ['totals' => $totals, 'top_pages' => $top_pages];
	}

	// -----------------------------------------------------------------
	// Eventos y key events
	// -----------------------------------------------------------------

	/**
	 * Un solo reporte (eventName + eventCount/totalUsers/eventCountPerUser/
	 * keyEvents) reutilizado para tres cosas: la lista completa de eventos,
	 * el subconjunto marcado como "key event" en GA4, y la comprobación de
	 * qué eventos recomendados (WhatsApp, formulario, teléfono, correo,
	 * consulta de propiedad/terreno, cambio de idioma) existen realmente.
	 * Nunca se inventa un evento que no aparezca en la respuesta de Google.
	 */
	public static function get_events(string $period, string $custom_start = '', string $custom_end = ''): array
	{
		$range = self::resolve_range($period, $custom_start, $custom_end);

		if ($range === null) {
			return self::error_result('bad_request');
		}

		$cache_params = ['start' => self::fmt($range['start']), 'end' => self::fmt($range['end'])];

		$result = self::cached_call('events', $cache_params, static function () use ($range) {
			return [
				'dateRanges' => [['startDate' => self::fmt($range['start']), 'endDate' => self::fmt($range['end'])]],
				'dimensions' => [['name' => 'eventName']],
				'metrics'    => [['name' => 'eventCount'], ['name' => 'totalUsers'], ['name' => 'eventCountPerUser'], ['name' => 'keyEvents']],
				'orderBys'   => [['metric' => ['metricName' => 'eventCount'], 'desc' => true]],
				'limit'      => 100,
			];
		});

		if ($result['success']) {
			$result['data'] = self::normalize_events($result['data']);
		}

		return $result;
	}

	private static function normalize_events(array $raw): array
	{
		$metric_headers = array_column($raw['metricHeaders'] ?? [], 'name');
		$all            = [];

		foreach ($raw['rows'] ?? [] as $row) {
			$name = $row['dimensionValues'][0]['value'] ?? '(not set)';

			$values = [];
			foreach ($row['metricValues'] ?? [] as $i => $mv) {
				$mname           = $metric_headers[$i] ?? ('metric_' . $i);
				$values[$mname] = is_numeric($mv['value'] ?? null) ? (float) $mv['value'] : 0.0;
			}

			$all[] = [
				'name'                => $name,
				'event_count'         => $values['eventCount'] ?? 0.0,
				'users'               => $values['totalUsers'] ?? 0.0,
				'events_per_user'     => round($values['eventCountPerUser'] ?? 0.0, 2),
				'key_events'          => $values['keyEvents'] ?? 0.0,
			];
		}

		$key_events = array_values(array_filter($all, static fn (array $e): bool => $e['key_events'] > 0));

		$event_names_lower = array_map(static fn (array $e): string => mb_strtolower($e['name']), $all);

		$recommended = [];
		foreach (self::RECOMMENDED_EVENTS as $key => $def) {
			$found = null;

			foreach ($all as $i => $event) {
				foreach ($def['patterns'] as $pattern) {
					if (str_contains($event_names_lower[$i], $pattern)) {
						$found = $event['name'];
						break 2;
					}
				}
			}

			$recommended[$key] = [
				'label'         => $def['label'],
				'configured'    => $found !== null,
				'matched_event' => $found,
			];
		}

		return ['all' => $all, 'key_events' => $key_events, 'recommended' => $recommended];
	}
}
