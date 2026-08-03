<?php
/**
 * Interfaz de proveedor de traducción automática — desacoplada a propósito.
 *
 * Momentum_Property_Translation_Service (class-property-translation-service.php)
 * solo conoce esta interfaz, nunca una clase concreta de proveedor. Hoy solo
 * existe un proveedor real (DeepL, providers/class-translation-provider-deepl.php);
 * agregar otro en el futuro es una clase nueva que implemente esta interfaz,
 * sin tocar el servicio.
 */

if (! defined('ABSPATH')) {
	exit;
}

interface Momentum_Translation_Provider
{
	/**
	 * true si el proveedor tiene todo lo necesario para traducir (ej. una
	 * API key válida configurada). El servicio nunca intenta traducir con
	 * un proveedor que devuelva false aquí.
	 */
	public function is_configured(): bool;

	/**
	 * Traduce todos los campos de una ficha en una sola solicitud.
	 *
	 * @param array<string, string> $fields        Ej. ['title' => .., 'description' => .., 'excerpt' => .., 'caracteristicas' => .., 'meta_title' => .., 'meta_description' => ..]. Los valores vacíos se envían igual (para mantener el orden), el proveedor debe devolver también cadena vacía para esos.
	 * @param string                $from_locale   Ej. 'es'.
	 * @param string                $to_locale     Ej. 'en'.
	 * @param string[]              $protect_terms Cadenas literales (nombre de zona, código de propiedad, marca) que deben aparecer sin cambios en el resultado -cada proveedor decide cómo honrarlo (DeepL: ignore_tags; un proveedor basado en LLM: instrucción en el prompt)-.
	 * @return array<string, string>|null Mismas llaves que $fields, valores traducidos, o null si toda la solicitud falló (red, autenticación, cuota, respuesta inválida).
	 */
	public function translate_fields(array $fields, string $from_locale, string $to_locale, array $protect_terms = []): ?array;

	/**
	 * Consumo actual de la cuenta (caracteres usados/límite del plan), para
	 * la pantalla de estadísticas y para que el servicio decida si hay cuota
	 * antes de intentar traducir. Devuelve null si el proveedor no soporta
	 * esta consulta o si falló -el llamador usa get_last_error_type() para
	 * el motivo-.
	 *
	 * @return array{character_count: int, character_limit: int}|null
	 */
	public function get_usage(): ?array;

	/**
	 * Motivo de la última respuesta null (de translate_fields() o
	 * get_usage()): 'quota'|'auth'|'network'|'invalid_response'|'other'|''
	 * (vacío = la última llamada no falló). Nunca incluye la key ni datos
	 * sensibles, solo una categoría.
	 */
	public function get_last_error_type(): string;
}

/**
 * API key de DeepL: constante en wp-config.php tiene prioridad sobre la
 * opción guardada en wp-admin (Admin > General > Traducción automática).
 */
function momentum_get_deepl_api_key(): string
{
	if (defined('MOMENTUM_DEEPL_API_KEY') && MOMENTUM_DEEPL_API_KEY !== '') {
		return (string) MOMENTUM_DEEPL_API_KEY;
	}

	return (string) get_option('momentum_translation_api_key_deepl', '');
}

/**
 * Instancia del proveedor de traducción configurado, o null si no hay
 * ninguno (sin API key en ningún lado). Momentum_Property_Translation_Service
 * trata "sin proveedor" igual que "el proveedor falló": la ficha se queda
 * pendiente/en borrador, nunca se publica contenido sin traducir.
 */
function momentum_get_translation_provider(): ?Momentum_Translation_Provider
{
	static $provider  = null;
	static $attempted = false;

	if ($attempted) {
		return $provider;
	}

	$attempted = true;

	$deepl_key = momentum_get_deepl_api_key();

	if ($deepl_key === '') {
		return null;
	}

	require_once __DIR__ . '/providers/class-translation-provider-deepl.php';

	$candidate = new Momentum_DeepL_Translation_Provider($deepl_key);

	if ($candidate->is_configured()) {
		$provider = $candidate;
	}

	return $provider;
}
