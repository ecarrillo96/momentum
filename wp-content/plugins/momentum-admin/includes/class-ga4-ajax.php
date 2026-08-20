<?php
/**
 * Puente AJAX entre el JS del dashboard y Momentum_GA4_Reports. El
 * navegador SOLO puede pedir un nombre de reporte de ALLOWED_REPORTS y un
 * periodo de ALLOWED_PERIODS -las dimensiones/métricas reales de cada
 * reporte viven fijas en Momentum_GA4_Reports, nunca se reciben aquí-.
 *
 * Verificación de permisos y nonce en CADA acción, independientemente de
 * que Momentum_GA4_Admin::render() ya la haya verificado al cargar la
 * página -una petición AJAX es una entrada distinta, con su propia guarda-.
 */

if (! defined('ABSPATH')) {
	exit;
}

class Momentum_GA4_Ajax
{
	private const ALLOWED_REPORTS = [
		'summary', 'timeseries', 'top_pages', 'top_properties', 'top_terrenos',
		'traffic_sources', 'source_medium', 'geo', 'devices', 'browsers',
		'languages', 'events',
	];

	private const ALLOWED_PERIODS = ['today', 'yesterday', '7d', '28d', '30d', '90d', 'custom'];

	public static function init(): void
	{
		add_action('wp_ajax_momentum_ga4_get_report', [__CLASS__, 'handle_get_report']);
		add_action('wp_ajax_momentum_ga4_refresh_cache', [__CLASS__, 'handle_refresh_cache']);
		add_action('wp_ajax_momentum_ga4_clear_cache', [__CLASS__, 'handle_clear_cache']);
		add_action('wp_ajax_momentum_ga4_test_connection', [__CLASS__, 'handle_test_connection']);
	}

	public static function handle_get_report(): void
	{
		self::guard();

		$report = isset($_POST['report']) ? sanitize_key(wp_unslash($_POST['report'])) : '';

		if (! in_array($report, self::ALLOWED_REPORTS, true)) {
			wp_send_json_error(['error_message' => __('Reporte no reconocido.', 'momentum-admin')]);
		}

		$period = isset($_POST['period']) ? sanitize_key(wp_unslash($_POST['period'])) : '7d';

		if (! in_array($period, self::ALLOWED_PERIODS, true)) {
			wp_send_json_error(['error_message' => __('Periodo no válido.', 'momentum-admin')]);
		}

		$compare = isset($_POST['compare']) && sanitize_text_field(wp_unslash($_POST['compare'])) === '1';

		$custom_start = '';
		$custom_end   = '';

		if ($period === 'custom') {
			$custom_start = self::sanitize_date($_POST['start'] ?? '');
			$custom_end   = self::sanitize_date($_POST['end'] ?? '');

			if ($custom_start === '' || $custom_end === '') {
				wp_send_json_error(['error_message' => __('Rango de fechas inválido. Usa el formato AAAA-MM-DD.', 'momentum-admin')]);
			}
		}

		$result = self::dispatch($report, $period, $compare, $custom_start, $custom_end);

		if ($result['success']) {
			wp_send_json_success($result);
		}

		wp_send_json_error($result);
	}

	public static function handle_refresh_cache(): void
	{
		self::guard();

		Momentum_GA4_Cache::flush_all();

		wp_send_json_success(['message' => __('Caché de Estadísticas actualizado.', 'momentum-admin')]);
	}

	/**
	 * "Borrar caché de Analytics": a diferencia de handle_refresh_cache()
	 * (usado por "Actualizar datos", que borra Y vuelve a consultar de
	 * inmediato), este endpoint solo borra -el usuario decide después,
	 * presionando "Actualizar datos" por separado, cuándo volver a pedirle
	 * datos a GA4-. Mismo método de borrado (Momentum_GA4_Cache::flush_all()),
	 * endpoint propio para que la intención de cada botón quede clara.
	 */
	public static function handle_clear_cache(): void
	{
		self::guard();

		Momentum_GA4_Cache::flush_all();

		wp_send_json_success(['message' => __('Caché de Analytics borrado.', 'momentum-admin')]);
	}

	public static function handle_test_connection(): void
	{
		self::guard();

		wp_send_json_success(Momentum_GA4_Diagnostics::test_connection());
	}

	/**
	 * @param mixed $value
	 */
	private static function sanitize_date($value): string
	{
		$value = sanitize_text_field(wp_unslash((string) $value));

		return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
	}

	private static function dispatch(string $report, string $period, bool $compare, string $start, string $end): array
	{
		switch ($report) {
			case 'summary':
				return Momentum_GA4_Reports::get_summary($period, $compare, $start, $end);
			case 'timeseries':
				return Momentum_GA4_Reports::get_timeseries($period, $start, $end);
			case 'top_pages':
				return Momentum_GA4_Reports::get_top_pages($period, $start, $end);
			case 'top_properties':
				return Momentum_GA4_Reports::get_top_properties($period, $start, $end);
			case 'top_terrenos':
				return Momentum_GA4_Reports::get_top_terrenos($period, $start, $end);
			case 'traffic_sources':
				return Momentum_GA4_Reports::get_traffic_sources($period, $start, $end);
			case 'source_medium':
				return Momentum_GA4_Reports::get_source_medium($period, $start, $end);
			case 'geo':
				return Momentum_GA4_Reports::get_geo($period, $start, $end);
			case 'devices':
				return Momentum_GA4_Reports::get_devices($period, $start, $end);
			case 'browsers':
				return Momentum_GA4_Reports::get_browsers($period, $start, $end);
			case 'languages':
				return Momentum_GA4_Reports::get_languages($period, $start, $end);
			case 'events':
				return Momentum_GA4_Reports::get_events($period, $start, $end);
			default:
				return [
					'success'       => false,
					'error_code'    => 'invalid_response',
					'error_message' => __('Reporte no reconocido.', 'momentum-admin'),
					'data'          => null,
				];
		}
	}

	/**
	 * manage_options + nonce, en cada acción -nunca se asume que la sesión
	 * ya se validó al cargar la página-.
	 */
	private static function guard(): void
	{
		if (! is_user_logged_in() || ! current_user_can(Momentum_Admin_Permissions::CAPABILITY)) {
			wp_send_json_error(['error_message' => __('No tienes permiso para realizar esta acción.', 'momentum-admin')], 403);
		}

		check_ajax_referer(Momentum_GA4_Admin::NONCE_ACTION, 'nonce');
	}
}
