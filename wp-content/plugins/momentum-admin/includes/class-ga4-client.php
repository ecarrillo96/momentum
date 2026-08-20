<?php
/**
 * Cliente REST mínimo para Google Analytics Data API v1 (runReport), vía
 * wp_remote_post() -sin SDK oficial ni Composer-. Nunca decide QUÉ pedir
 * (dimensiones/métricas/filtros): recibe el cuerpo de la solicitud ya
 * armado por Momentum_GA4_Reports, que es la única clase que define esos
 * valores, siempre fijos en servidor.
 */

if (! defined('ABSPATH')) {
	exit;
}

class Momentum_GA4_Client
{
	private const API_BASE = 'https://analyticsdata.googleapis.com/v1beta';

	/**
	 * 'not_configured'|'auth'|'network'|'permission'|'quota'|'bad_request'|'invalid_response'|''.
	 */
	private static string $last_error        = '';
	private static string $last_error_detail = '';

	public static function get_last_error(): string
	{
		return self::$last_error;
	}

	/**
	 * Detalle técnico (mensaje de Google), NUNCA para mostrar directo al
	 * usuario -solo para el log cuando WP_DEBUG_LOG está activo-.
	 */
	public static function get_last_error_detail(): string
	{
		return self::$last_error_detail;
	}

	/**
	 * @param array $report_request Cuerpo completo de runReport (dimensions,
	 *                                metrics, dateRanges, dimensionFilter,
	 *                                orderBys, limit...).
	 * @return array|null Respuesta decodificada de la API, o null en error.
	 */
	public static function run_report(array $report_request): ?array
	{
		self::$last_error        = '';
		self::$last_error_detail = '';

		$property_id = Momentum_GA4_Config::get_property_id();

		if ($property_id === '') {
			self::$last_error = 'not_configured';

			return null;
		}

		$token = Momentum_GA4_Auth::get_access_token();

		if ($token === null) {
			self::$last_error        = Momentum_GA4_Auth::get_last_error() === 'not_configured' ? 'not_configured' : 'auth';
			self::$last_error_detail = 'Auth: ' . Momentum_GA4_Auth::get_last_error();

			return null;
		}

		$url = self::API_BASE . '/properties/' . rawurlencode($property_id) . ':runReport';

		$response = wp_remote_post($url, [
			'timeout' => 20,
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			],
			'body' => wp_json_encode($report_request),
		]);

		if (is_wp_error($response)) {
			self::$last_error        = 'network';
			self::$last_error_detail = $response->get_error_message();
			self::log('[Momentum GA4] fallo de red en runReport: ' . $response->get_error_message());

			return null;
		}

		$code     = (int) wp_remote_retrieve_response_code($response);
		$raw_body = (string) wp_remote_retrieve_body($response);
		$body     = json_decode($raw_body, true);

		if ($code === 200 && is_array($body)) {
			return $body;
		}

		self::$last_error_detail = self::extract_google_error($body);
		self::log(sprintf('[Momentum GA4] runReport HTTP %d: %s', $code, $raw_body));

		if ($code === 401 || $code === 403) {
			self::$last_error = 'permission';
		} elseif ($code === 429) {
			self::$last_error = 'quota';
		} elseif ($code === 400) {
			self::$last_error = 'bad_request';
		} else {
			self::$last_error = 'invalid_response';
		}

		return null;
	}

	/**
	 * @param mixed $body
	 */
	private static function extract_google_error($body): string
	{
		if (is_array($body) && isset($body['error']['message'])) {
			return (string) $body['error']['message'];
		}

		return '';
	}

	private static function log(string $message): void
	{
		if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
			error_log($message);
		}
	}
}
