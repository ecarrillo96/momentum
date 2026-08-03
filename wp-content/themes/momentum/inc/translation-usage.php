<?php
/**
 * Consulta y cachea el consumo de la cuenta DeepL (endpoint /usage), y
 * decide si hay cuota disponible para traducir. Único lugar del proyecto
 * que sabe "cuándo" volver a preguntarle a DeepL cuánto se ha consumido -el
 * "cómo" preguntarlo vive en Momentum_DeepL_Translation_Provider::get_usage(),
 * no se duplica aquí-.
 *
 * Lo consumen dos cosas, ambas sin llamar a DeepL directamente:
 * - Momentum_Property_Translation_Service, para decidir si intenta traducir
 *   o bloquea con estado 'quota_exceeded' (momentum_translation_quota_available()).
 * - La pantalla Momentum → Traducciones, para mostrar el estado y la barra
 *   de uso (momentum_get_translation_usage()).
 */

if (! defined('ABSPATH')) {
	exit;
}

const MOMENTUM_TRANSLATION_USAGE_TRANSIENT = 'momentum_deepl_usage';
const MOMENTUM_TRANSLATION_USAGE_CACHE_TTL = 15 * MINUTE_IN_SECONDS;
const MOMENTUM_TRANSLATION_USAGE_LAST_GOOD_OPTION = 'momentum_translation_usage_last_good';

/**
 * Consumo actual, con caché de 15 minutos (transient). Nunca llama a DeepL
 * más de una vez cada 15 minutos salvo $force_refresh=true (botón
 * "Actualizar ahora" en la pantalla, o justo después de una traducción real
 * -ver momentum_invalidate_translation_usage_cache()-).
 *
 * @return array{
 *     status: 'ok'|'not_configured'|'error',
 *     character_count: int|null,
 *     character_limit: int|null,
 *     fetched_at: int|null,
 *     stale: bool,
 *     error_type: string,
 * }
 */
function momentum_get_translation_usage(bool $force_refresh = false): array
{
	if (! $force_refresh) {
		$cached = get_transient(MOMENTUM_TRANSLATION_USAGE_TRANSIENT);

		if (is_array($cached)) {
			return $cached;
		}
	}

	$provider = function_exists('momentum_get_translation_provider') ? momentum_get_translation_provider() : null;

	if ($provider === null) {
		return [
			'status'          => 'not_configured',
			'character_count' => null,
			'character_limit' => null,
			'fetched_at'      => null,
			'stale'           => false,
			'error_type'      => 'auth',
		];
	}

	$usage = $provider->get_usage();

	if ($usage === null) {
		// Fallo puntual: se conserva el último valor válido conocido (si lo
		// hay) marcado como desactualizado, en vez de mostrar un hueco vacío
		// o bloquear traducciones por un problema transitorio del endpoint
		// de consulta -distinto de si /translate mismo falla, eso lo maneja
		// el servicio por separado-.
		$last_good = get_option(MOMENTUM_TRANSLATION_USAGE_LAST_GOOD_OPTION, null);

		if (is_array($last_good)) {
			$last_good['stale']      = true;
			$last_good['status']     = 'ok';
			$last_good['error_type'] = $provider->get_last_error_type();

			return $last_good;
		}

		return [
			'status'          => 'error',
			'character_count' => null,
			'character_limit' => null,
			'fetched_at'      => null,
			'stale'           => true,
			'error_type'      => $provider->get_last_error_type(),
		];
	}

	$result = [
		'status'          => 'ok',
		'character_count' => $usage['character_count'],
		'character_limit' => $usage['character_limit'],
		'fetched_at'      => time(),
		'stale'           => false,
		'error_type'      => '',
	];

	set_transient(MOMENTUM_TRANSLATION_USAGE_TRANSIENT, $result, MOMENTUM_TRANSLATION_USAGE_CACHE_TTL);
	// autoload=false: esta opción no se necesita en cada visita pública, solo
	// cuando se pregunta el consumo (guardado de fichas o la pantalla de estadísticas).
	update_option(MOMENTUM_TRANSLATION_USAGE_LAST_GOOD_OPTION, $result, false);

	return $result;
}

/**
 * Se llama después de cada intento REAL de traducción (Momentum_Property_Translation_Service),
 * para que la siguiente lectura de consumo sea fresca en vez de esperar a
 * que expire el caché de 15 minutos.
 */
function momentum_invalidate_translation_usage_cache(): void
{
	delete_transient(MOMENTUM_TRANSLATION_USAGE_TRANSIENT);
}

/**
 * Porcentaje usado (0–100), o null si no hay datos de consumo disponibles
 * (proveedor no configurado, o fallo sin ningún valor previo conocido).
 */
function momentum_translation_usage_percent(array $usage): ?float
{
	if ($usage['character_count'] === null || $usage['character_limit'] === null || $usage['character_limit'] <= 0) {
		return null;
	}

	return min(100, ($usage['character_count'] / $usage['character_limit']) * 100);
}

/**
 * 'normal' (<80%), 'warning' (80–89%), 'critical' (90–99%), 'blocked'
 * (100% o sin cuota disponible). Sin datos de consumo, se trata como
 * 'normal' -fallar cerrado bloquearía traducciones por un problema de
 * lectura, no de cuota real-.
 */
function momentum_translation_usage_threshold(array $usage): string
{
	$percent = momentum_translation_usage_percent($usage);

	if ($percent === null) {
		return 'normal';
	}

	if ($percent >= 100) {
		return 'blocked';
	}

	if ($percent >= 90) {
		return 'critical';
	}

	if ($percent >= 80) {
		return 'warning';
	}

	return 'normal';
}

/**
 * true si hay cuota para intentar una traducción nueva. Solo bloquea
 * (false) cuando se SABE con certeza -mediante un valor de consumo válido,
 * aunque esté desactualizado- que el límite ya se alcanzó. Sin ningún dato
 * de consumo disponible, no bloquea -el propio intento de traducción, si
 * falla por cuota, ya lo reporta translate_fields() vía get_last_error_type()-.
 */
function momentum_translation_quota_available(): bool
{
	$usage = momentum_get_translation_usage();

	return momentum_translation_usage_threshold($usage) !== 'blocked';
}

/**
 * Estado de conexión con DeepL para la tarjeta "Estado de DeepL":
 * 'connected'|'not_configured'|'auth_error'|'quota_exceeded'|'temporary_error'.
 */
function momentum_get_deepl_connection_status(): string
{
	$usage = momentum_get_translation_usage();

	if ($usage['status'] === 'not_configured') {
		return 'not_configured';
	}

	if (momentum_translation_usage_threshold($usage) === 'blocked') {
		return 'quota_exceeded';
	}

	if ($usage['status'] === 'ok' && ! $usage['stale']) {
		return 'connected';
	}

	if ($usage['error_type'] === 'auth') {
		return 'auth_error';
	}

	return 'temporary_error';
}
