<?php
/**
 * Autenticación OAuth2 de cuenta de servicio de Google (flujo JWT Bearer),
 * implementada con funciones nativas de PHP (openssl_sign) y wp_remote_post()
 * -sin ninguna librería externa ni Composer-. Mismo patrón que ya usa este
 * proyecto para DeepL (inc/providers/class-translation-provider-deepl.php,
 * en el tema): wp_remote_post() + manejo explícito de errores por código.
 *
 * El access token se cachea en un transient (no en cada carga) para no
 * firmar un JWT nuevo ni pedir un token nuevo en cada consulta.
 */

if (! defined('ABSPATH')) {
	exit;
}

class Momentum_GA4_Auth
{
	private const TOKEN_TRANSIENT = 'momentum_ga4_access_token';
	private const SCOPE           = 'https://www.googleapis.com/auth/analytics.readonly';
	private const TOKEN_URL       = 'https://oauth2.googleapis.com/token';

	/**
	 * 'not_configured'|'invalid_key'|'network'|'auth'|'' (vacío = sin error).
	 */
	private static string $last_error = '';

	public static function get_last_error(): string
	{
		return self::$last_error;
	}

	public static function get_access_token(): ?string
	{
		self::$last_error = '';

		$cached = get_transient(self::TOKEN_TRANSIENT);

		if (is_string($cached) && $cached !== '') {
			return $cached;
		}

		$credentials = Momentum_GA4_Config::get_credentials();

		if ($credentials === null) {
			self::$last_error = 'not_configured';

			return null;
		}

		$jwt = self::build_signed_jwt($credentials['client_email'], $credentials['private_key']);

		if ($jwt === null) {
			self::$last_error = 'invalid_key';

			return null;
		}

		$response = wp_remote_post(self::TOKEN_URL, [
			'timeout' => 15,
			'body'    => [
				'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
				'assertion'  => $jwt,
			],
		]);

		if (is_wp_error($response)) {
			self::$last_error = 'network';
			self::log('[Momentum GA4] fallo de red al pedir token: ' . $response->get_error_message());

			return null;
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		$body = json_decode((string) wp_remote_retrieve_body($response), true);

		if ($code !== 200 || ! is_array($body) || empty($body['access_token'])) {
			self::$last_error = 'auth';
			self::log(sprintf('[Momentum GA4] token HTTP %d: %s', $code, wp_remote_retrieve_body($response)));

			return null;
		}

		$token      = (string) $body['access_token'];
		$expires_in = isset($body['expires_in']) ? (int) $body['expires_in'] : 3600;

		// Se resta un margen de 2 minutos al TTL real del token para nunca
		// usar uno a punto de expirar en medio de una consulta.
		set_transient(self::TOKEN_TRANSIENT, $token, max(60, $expires_in - 120));

		return $token;
	}

	public static function invalidate_token(): void
	{
		delete_transient(self::TOKEN_TRANSIENT);
	}

	/**
	 * JWT firmado (RS256) según el flujo "OAuth 2.0 for Server to Server
	 * Applications" de Google, sin dependencias externas.
	 */
	private static function build_signed_jwt(string $client_email, string $private_key): ?string
	{
		$now = time();

		$header = ['alg' => 'RS256', 'typ' => 'JWT'];
		$claims = [
			'iss'   => $client_email,
			'scope' => self::SCOPE,
			'aud'   => self::TOKEN_URL,
			'iat'   => $now,
			'exp'   => $now + 3600,
		];

		$segments = [
			self::base64url_encode((string) wp_json_encode($header)),
			self::base64url_encode((string) wp_json_encode($claims)),
		];

		$signing_input = implode('.', $segments);

		$key_resource = openssl_pkey_get_private($private_key);

		if ($key_resource === false) {
			self::log('[Momentum GA4] la private key del JSON de credenciales no es válida.');

			return null;
		}

		$signature = '';
		$signed    = openssl_sign($signing_input, $signature, $key_resource, OPENSSL_ALGO_SHA256);

		if (! $signed) {
			self::log('[Momentum GA4] openssl_sign() falló al firmar el JWT.');

			return null;
		}

		$segments[] = self::base64url_encode($signature);

		return implode('.', $segments);
	}

	private static function base64url_encode(string $data): string
	{
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}

	private static function log(string $message): void
	{
		if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
			error_log($message);
		}
	}
}
