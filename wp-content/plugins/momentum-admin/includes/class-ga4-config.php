<?php
/**
 * Configuración del panel de Estadísticas (Google Analytics Data API).
 *
 * Única fuente de verdad de dónde vienen el Property ID y las credenciales
 * de la cuenta de servicio -nunca se guardan en la base de datos ni en el
 * repositorio-. Se leen, en este orden de prioridad, de:
 *
 *  1. Constante en wp-config.php (MOMENTUM_GA4_PROPERTY_ID / MOMENTUM_GA4_CREDENTIALS_PATH).
 *  2. Variable de entorno del mismo nombre (getenv()).
 *
 * Para el JSON de la cuenta de servicio hay una tercera opción, pensada para
 * hosts donde no se puede dejar un archivo fuera del directorio público:
 * MOMENTUM_GA4_CREDENTIALS_JSON como variable de entorno con el contenido
 * completo del JSON (nunca como constante de wp-config.php, para no tentar a
 * pegar el JSON dentro de un archivo versionado).
 */

if (! defined('ABSPATH')) {
	exit;
}

class Momentum_GA4_Config
{
	public static function get_property_id(): string
	{
		if (defined('MOMENTUM_GA4_PROPERTY_ID') && trim((string) MOMENTUM_GA4_PROPERTY_ID) !== '') {
			return trim((string) MOMENTUM_GA4_PROPERTY_ID);
		}

		$env = getenv('MOMENTUM_GA4_PROPERTY_ID');

		return $env !== false ? trim($env) : '';
	}

	public static function get_credentials_path(): string
	{
		if (defined('MOMENTUM_GA4_CREDENTIALS_PATH') && trim((string) MOMENTUM_GA4_CREDENTIALS_PATH) !== '') {
			return trim((string) MOMENTUM_GA4_CREDENTIALS_PATH);
		}

		$env = getenv('MOMENTUM_GA4_CREDENTIALS_PATH');

		return $env !== false ? trim($env) : '';
	}

	/**
	 * Contenido JSON de la cuenta de servicio pasado directo por variable de
	 * entorno (alternativa a un archivo en disco). Nunca se expone tal cual
	 * a ninguna plantilla -solo se usa dentro de get_credentials()-.
	 */
	private static function get_credentials_json_env(): string
	{
		$env = getenv('MOMENTUM_GA4_CREDENTIALS_JSON');

		return $env !== false ? trim($env) : '';
	}

	/**
	 * 'env_json'|'file'|'none' — de dónde saldrían las credenciales si se
	 * pidieran ahora mismo. Usado solo por el panel de diagnóstico (nunca
	 * imprime el contenido, solo esta etiqueta).
	 */
	public static function credentials_source(): string
	{
		if (self::get_credentials_json_env() !== '') {
			return 'env_json';
		}

		if (self::get_credentials_path() !== '') {
			return 'file';
		}

		return 'none';
	}

	public static function credentials_configured(): bool
	{
		return self::credentials_source() !== 'none';
	}

	public static function credentials_file_accessible(): bool
	{
		$path = self::get_credentials_path();

		if ($path === '') {
			return false;
		}

		return is_readable($path);
	}

	public static function is_configured(): bool
	{
		return self::get_property_id() !== '' && self::credentials_configured();
	}

	/**
	 * @return array{client_email: string, private_key: string}|null null si
	 *         no hay credenciales configuradas, el archivo no es legible, o
	 *         el JSON no tiene el formato esperado de una cuenta de servicio.
	 */
	public static function get_credentials(): ?array
	{
		$raw = self::get_credentials_json_env();

		if ($raw === '') {
			$path = self::get_credentials_path();

			if ($path === '' || ! is_readable($path)) {
				return null;
			}

			$raw = (string) file_get_contents($path);
		}

		$data = json_decode($raw, true);

		if (! is_array($data) || empty($data['client_email']) || empty($data['private_key'])) {
			return null;
		}

		return [
			'client_email' => (string) $data['client_email'],
			'private_key'  => (string) $data['private_key'],
		];
	}
}
