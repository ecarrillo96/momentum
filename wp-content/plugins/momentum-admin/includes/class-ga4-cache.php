<?php
/**
 * Caché del panel de Estadísticas, sobre la Transients API nativa de
 * WordPress. Cada combinación de reporte + rango de fechas + comparación
 * tiene su propia clave; nunca se consulta Google Analytics en cada carga
 * de página sin pasar primero por aquí.
 */

if (! defined('ABSPATH')) {
	exit;
}

class Momentum_GA4_Cache
{
	/**
	 * 20 minutos: dentro del rango de 15-30 pedido.
	 */
	public const TTL = 20 * MINUTE_IN_SECONDS;

	private const PREFIX             = 'momentum_ga4_';
	private const LOCK_PREFIX         = 'momentum_ga4_lock_';
	private const LAST_REFRESH_OPTION = 'momentum_ga4_last_cache_refresh';

	public static function key(string $report, array $params): string
	{
		ksort($params);

		return self::PREFIX . md5($report . '|' . wp_json_encode($params));
	}

	/**
	 * @return array|null null = sin caché vigente (hay que consultar la API).
	 */
	public static function get(string $key): ?array
	{
		$value = get_transient($key);

		return is_array($value) ? $value : null;
	}

	public static function set(string $key, array $value): void
	{
		$value['meta'] = [
			'cached_at'  => time(),
			'from_cache' => false,
		];

		set_transient($key, $value, self::TTL);
		update_option(self::LAST_REFRESH_OPTION, time(), false);
	}

	public static function last_refresh(): ?int
	{
		$value = get_option(self::LAST_REFRESH_OPTION, 0);

		return $value ? (int) $value : null;
	}

	/**
	 * Borra ÚNICAMENTE los transients de este módulo (busca por prefijo
	 * propio) — nunca toca caché de otros plugins. Usado tanto por
	 * "Actualizar datos" (borra y vuelve a consultar de inmediato) como por
	 * "Borrar caché de Analytics" (borra sin volver a consultar).
	 *
	 * El prefijo 'momentum_ga4_' ya cubre, sin necesidad de nada aparte,
	 * los resultados de reporte (Momentum_GA4_Cache::key()), los locks
	 * (LOCK_PREFIX) y el token OAuth (Momentum_GA4_Auth::TOKEN_TRANSIENT)
	 * -los tres arrancan con ese mismo prefijo-. invalidate_token() de
	 * abajo es redundante con eso, pero se deja explícito por claridad.
	 *
	 * También resetea LAST_REFRESH_OPTION -no es un transient, así que el
	 * LIKE de arriba no lo alcanza-: sin este reset, "Última actualización
	 * de caché" seguía mostrando la hora de la última consulta real aunque
	 * ya no quedara ningún dato cacheado.
	 */
	public static function flush_all(): void
	{
		global $wpdb;

		$like_value   = $wpdb->esc_like('_transient_' . self::PREFIX) . '%';
		$like_timeout = $wpdb->esc_like('_transient_timeout_' . self::PREFIX) . '%';

		$wpdb->query($wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$like_value,
			$like_timeout
		));

		delete_option(self::LAST_REFRESH_OPTION);

		Momentum_GA4_Auth::invalidate_token();
	}

	/**
	 * Mutex corto (30s) para que dos peticiones simultáneas al MISMO reporte
	 * (p. ej. dos pestañas del panel abiertas a la vez) no disparen dos
	 * llamadas reales a la API al mismo tiempo.
	 */
	public static function acquire_lock(string $key): bool
	{
		$lock_key = self::LOCK_PREFIX . $key;

		if (get_transient($lock_key)) {
			return false;
		}

		set_transient($lock_key, 1, 30);

		return true;
	}

	public static function release_lock(string $key): void
	{
		delete_transient(self::LOCK_PREFIX . $key);
	}
}
