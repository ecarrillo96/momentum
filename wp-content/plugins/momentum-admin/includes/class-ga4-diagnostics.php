<?php
/**
 * Panel de diagnóstico de la integración GA4 — visible solo para
 * administradores dentro de /admin/estadisticas/. Nunca expone private key,
 * client secret, JSON completo ni tokens: solo booleans, fechas y un
 * mensaje de error ya saneado (los mismos strings en español que arma
 * Momentum_GA4_Reports::error_result()).
 */

if (! defined('ABSPATH')) {
	exit;
}

class Momentum_GA4_Diagnostics
{
	private const LAST_CHECK_OPTION = 'momentum_ga4_last_check';
	private const LAST_ERROR_OPTION = 'momentum_ga4_last_error';

	public static function record_check(bool $success, string $sanitized_error = ''): void
	{
		update_option(self::LAST_CHECK_OPTION, [
			'time'    => time(),
			'success' => $success,
		], false);

		if (! $success && $sanitized_error !== '') {
			update_option(self::LAST_ERROR_OPTION, [
				'time'    => time(),
				'message' => $sanitized_error,
			], false);

			return;
		}

		// Una consulta exitosa invalida cualquier error previo -sin esto, el
		// cartel rojo del panel se quedaba mostrando el primer error para
		// siempre (p. ej. "GA4 todavía no está configurado" de antes de
		// poner las credenciales), aunque las consultas posteriores ya
		// funcionaran con normalidad.
		if ($success) {
			delete_option(self::LAST_ERROR_OPTION);
		}
	}

	/**
	 * @return array{
	 *   property_id_configured: bool,
	 *   credentials_configured: bool,
	 *   credentials_source: string,
	 *   credentials_accessible: bool,
	 *   last_check: array{time:int,success:bool}|null,
	 *   last_cache_refresh: int|null,
	 *   last_error: array{time:int,message:string}|null,
	 * }
	 */
	public static function get_status(): array
	{
		$last_check = get_option(self::LAST_CHECK_OPTION, null);
		$last_error = get_option(self::LAST_ERROR_OPTION, null);

		return [
			'property_id_configured' => Momentum_GA4_Config::get_property_id() !== '',
			'credentials_configured' => Momentum_GA4_Config::credentials_configured(),
			'credentials_source'     => Momentum_GA4_Config::credentials_source(),
			'credentials_accessible' => Momentum_GA4_Config::get_credentials() !== null,
			'last_check'             => is_array($last_check) ? $last_check : null,
			'last_cache_refresh'     => Momentum_GA4_Cache::last_refresh(),
			'last_error'             => is_array($last_error) ? $last_error : null,
		];
	}

	/**
	 * Prueba real de conexión (pide el resumen de "hoy", la consulta más
	 * barata posible) — solo se ejecuta cuando el administrador la pide
	 * explícitamente desde el panel, nunca automáticamente en cada carga.
	 *
	 * @return array{success: bool, message: string}
	 */
	public static function test_connection(): array
	{
		$result = Momentum_GA4_Reports::get_summary('today', false);

		return [
			'success' => $result['success'],
			'message' => $result['success'] ? '' : $result['error_message'],
		];
	}
}
