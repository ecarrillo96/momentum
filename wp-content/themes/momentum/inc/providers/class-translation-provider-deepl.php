<?php
/**
 * Proveedor de traducción DeepL API Free (https://api-free.deepl.com/v2/translate,
 * https://api-free.deepl.com/v2/usage).
 *
 * Cargado únicamente por momentum_get_translation_provider() en
 * inc/translation-provider.php, que ya deja cargada la interfaz
 * Momentum_Translation_Provider antes de este require -no hace falta
 * volver a requerirla aquí-.
 */

if (! defined('ABSPATH')) {
	exit;
}

class Momentum_DeepL_Translation_Provider implements Momentum_Translation_Provider
{
	private const TRANSLATE_ENDPOINT = 'https://api-free.deepl.com/v2/translate';
	private const USAGE_ENDPOINT     = 'https://api-free.deepl.com/v2/usage';
	private const TIMEOUT      = 30;
	private const USAGE_TIMEOUT = 15;
	private const MAX_RETRIES  = 2;
	private const RETRY_DELAYS = [1, 2];

	/**
	 * Mapeo de nuestro slug de idioma (Polylang) al código que espera DeepL.
	 * DeepL exige variantes regionales para el destino en inglés
	 * (EN-US/EN-GB) pero acepta el código genérico como origen.
	 */
	private const SOURCE_LANG_MAP = [
		'es' => 'ES',
		'en' => 'EN',
	];

	private const TARGET_LANG_MAP = [
		'es' => 'ES',
		'en' => 'EN-US',
	];

	private string $api_key;

	/**
	 * 'quota'|'auth'|'network'|'invalid_response'|'other'|'' (vacío = sin
	 * error, la última llamada -translate_fields() o get_usage()- salió bien).
	 * Consultado por Momentum_Property_Translation_Service justo después de
	 * una respuesta null, y por la pantalla de estadísticas para el estado
	 * de conexión ("Estado de DeepL").
	 */
	private string $last_error_type = '';

	public function __construct(string $api_key)
	{
		$this->api_key = trim($api_key);
	}

	public function is_configured(): bool
	{
		return $this->api_key !== '';
	}

	public function get_last_error_type(): string
	{
		return $this->last_error_type;
	}

	public function translate_fields(array $fields, string $from_locale, string $to_locale, array $protect_terms = []): ?array
	{
		$this->last_error_type = '';

		if (! $this->is_configured() || ! $fields) {
			$this->last_error_type = $this->is_configured() ? 'other' : 'auth';

			return null;
		}

		$keys   = array_keys($fields);
		$protect_terms = array_values(array_unique(array_filter($protect_terms, static fn ($t) => trim((string) $t) !== '')));
		$texts  = array_map(
			fn (string $value): string => $this->protect_terms($value, $protect_terms),
			array_values($fields)
		);

		$body = [
			'text'        => $texts,
			'source_lang' => self::SOURCE_LANG_MAP[$from_locale] ?? strtoupper($from_locale),
			'target_lang' => self::TARGET_LANG_MAP[$to_locale] ?? strtoupper($to_locale),
		];

		if ($protect_terms) {
			$body['tag_handling'] = 'xml';
			$body['ignore_tags']  = ['x'];
		}

		$data = $this->request_with_retries(self::TRANSLATE_ENDPOINT, $body, self::TIMEOUT);

		if (! is_array($data) || empty($data['translations']) || ! is_array($data['translations'])) {
			if ($this->last_error_type === '') {
				$this->last_error_type = 'invalid_response';
			}

			return null;
		}

		if (count($data['translations']) !== count($keys)) {
			error_log('[Momentum DeepL] respuesta con un número de traducciones distinto al enviado, se descarta.');
			$this->last_error_type = 'invalid_response';

			return null;
		}

		$result = [];

		foreach ($keys as $index => $key) {
			$translated       = (string) ($data['translations'][$index]['text'] ?? '');
			$result[$key]     = $this->unprotect_terms($translated);
		}

		return $result;
	}

	/**
	 * Consumo actual de la cuenta DeepL: caracteres usados y límite del plan.
	 * Nunca lanza ni expone la API key -solo la usa en el header de la
	 * solicitud, igual que translate_fields()-. Devuelve null en cualquier
	 * fallo; el llamador consulta get_last_error_type() para el motivo.
	 *
	 * @return array{character_count: int, character_limit: int}|null
	 */
	public function get_usage(): ?array
	{
		$this->last_error_type = '';

		if (! $this->is_configured()) {
			$this->last_error_type = 'auth';

			return null;
		}

		$response = wp_remote_get(self::USAGE_ENDPOINT, [
			'timeout' => self::USAGE_TIMEOUT,
			'headers' => [
				'Authorization' => 'DeepL-Auth-Key ' . $this->api_key,
			],
		]);

		if (is_wp_error($response)) {
			error_log('[Momentum DeepL] /usage fallo de red: ' . $response->get_error_code());
			$this->last_error_type = 'network';

			return null;
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		$this->last_error_type = $this->error_type_for_status($code);

		if ($code !== 200) {
			error_log(sprintf('[Momentum DeepL] /usage HTTP %d', $code));

			return null;
		}

		$data = json_decode((string) wp_remote_retrieve_body($response), true);

		if (! is_array($data) || ! isset($data['character_count'], $data['character_limit'])) {
			$this->last_error_type = 'invalid_response';

			return null;
		}

		return [
			'character_count' => (int) $data['character_count'],
			'character_limit' => (int) $data['character_limit'],
		];
	}

	/**
	 * Envuelve cada ocurrencia literal de un término protegido en <x>...</x>
	 * para que DeepL (con tag_handling=xml + ignore_tags=['x']) lo devuelva
	 * sin traducir -así el nombre de zona, el código de propiedad o la marca
	 * nunca cambian de idioma por accidente-.
	 */
	private function protect_terms(string $text, array $protect_terms): string
	{
		if ($text === '' || ! $protect_terms) {
			return $text;
		}

		foreach ($protect_terms as $term) {
			$text = str_replace($term, '<x>' . $term . '</x>', $text);
		}

		return $text;
	}

	/**
	 * Quita las etiquetas <x>/</x> del resultado, se hayan conservado o no
	 * en la respuesta de DeepL -operación segura en ambos casos-.
	 */
	private function unprotect_terms(string $text): string
	{
		return str_replace(['<x>', '</x>'], '', $text);
	}

	/**
	 * Traduce un código HTTP de DeepL al tipo de error que consumen
	 * Momentum_Property_Translation_Service (¿reintentar o bloquear?) y la
	 * pantalla de estadísticas (¿qué aviso mostrar?). 456 es el código
	 * documentado de DeepL para "cuota de caracteres agotada".
	 */
	private function error_type_for_status(int $code): string
	{
		if ($code === 200) {
			return '';
		}

		if ($code === 456) {
			return 'quota';
		}

		if (in_array($code, [401, 403], true)) {
			return 'auth';
		}

		if (in_array($code, [429, 500, 502, 503, 504], true)) {
			return 'network';
		}

		return 'other';
	}

	/**
	 * @return array{translations: array<int, array{text: string}>}|null
	 */
	private function request_with_retries(string $endpoint, array $body, int $timeout): ?array
	{
		for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
			$response = wp_remote_post($endpoint, [
				'timeout' => $timeout,
				'headers' => [
					'Authorization' => 'DeepL-Auth-Key ' . $this->api_key,
					'Content-Type'  => 'application/json',
				],
				'body' => wp_json_encode($body),
			]);

			if (is_wp_error($response)) {
				error_log('[Momentum DeepL] fallo de red: ' . $response->get_error_code());
				$this->last_error_type = 'network';
				$retryable = true;
			} else {
				$code = (int) wp_remote_retrieve_response_code($response);

				if ($code === 200) {
					$decoded = json_decode((string) wp_remote_retrieve_body($response), true);

					return is_array($decoded) ? $decoded : null;
				}

				$this->last_error_type = $this->error_type_for_status($code);
				$retryable = in_array($code, [429, 500, 502, 503, 504], true);
				error_log(sprintf(
					'[Momentum DeepL] HTTP %d%s',
					$code,
					$retryable ? ' (reintentando)' : ' (sin reintentar, probable error de configuración o cuota)'
				));

				if (! $retryable) {
					return null;
				}
			}

			if ($attempt < self::MAX_RETRIES) {
				sleep(self::RETRY_DELAYS[$attempt] ?? 2);
			}
		}

		return null;
	}
}
