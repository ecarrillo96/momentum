<?php
/**
 * Configuración global del sitio: tipo de cambio, redes sociales, teléfonos,
 * dirección y correo. Fuente única de verdad para todo el tema — nada de
 * esto debe volver a escribirse hardcodeado en un template-part.
 *
 * Se administra desde Admin > General (Momentum_Admin_General, en el plugin
 * momentum-admin), que solo consume register_setting()/get_option() desde
 * aquí, igual que Momentum_Admin_Zonas consume el CPT 'zonas' del tema sin
 * reimplementar su lógica.
 *
 * El guardado real lo procesa options.php (núcleo de WordPress, vía
 * settings_fields()): no hay ningún manejador de POST propio para esta
 * pantalla. Cada sanitize_callback es responsable de rechazar valores
 * inválidos devolviendo el valor anterior (get_option()) en vez del nuevo,
 * para nunca sobrescribir una configuración válida con basura.
 */

if (! defined('ABSPATH')) {
	exit;
}

const MOMENTUM_SETTINGS_GROUP = 'momentum_general';

/**
 * Valores por defecto usados SOLO como fallback de lectura (get_option) si
 * la opción nunca se ha guardado -no se escriben en la base de datos-. Para
 * el tipo de cambio es el valor pedido explícitamente (18.50). Para el
 * resto son los valores que ya estaban hardcodeados en el sitio, para que
 * al conectar los templates a estas opciones el sitio no se quede en blanco
 * hasta que alguien abra el formulario y guarde.
 */
function momentum_settings_defaults(): array
{
	return [
		'momentum_exchange_rate' => '18.50',
		'momentum_facebook_url'  => 'https://www.facebook.com/MomentumCocoyocMx',
		'momentum_instagram_url' => 'https://www.instagram.com/momentumcocoyoc/',
		'momentum_tiktok_url'    => 'https://www.tiktok.com/@momentumcocoyocmr',
		'momentum_phone_1'       => '735 379 7781',
		'momentum_phone_2'       => '735 166 4461',
		'momentum_address'       => "Estación vieja #10, Centro, Oaxtepec\nC.P. 62738, Yautepec, Morelos",
		'momentum_email'         => 'info@momentumcocoyoc.com',
	];
}

// -----------------------------------------------------------------
// Sanitización
// -----------------------------------------------------------------

/**
 * Numérico, mayor que cero, hasta 4 decimales, sin símbolos. Cualquier
 * cosa que no cumpla se rechaza y se conserva el valor guardado.
 */
function momentum_sanitize_exchange_rate($value)
{
	$raw = trim((string) $value);

	if ($raw === '' || ! preg_match('/^\d+(\.\d{1,4})?$/', $raw) || (float) $raw <= 0) {
		add_settings_error(
			'momentum_exchange_rate',
			'momentum_exchange_rate_invalid',
			__('El tipo de cambio debe ser un número mayor que cero, con hasta 4 decimales (por ejemplo 18.50).', 'momentum-admin')
		);

		return get_option('momentum_exchange_rate', momentum_settings_defaults()['momentum_exchange_rate']);
	}

	return $raw;
}

/**
 * URL opcional, solo http/https. Reutilizado por Facebook/Instagram/TikTok
 * -misma validación, distinta etiqueta para el mensaje de error-.
 */
function momentum_sanitize_social_url_field(string $option_name, string $label, $value): string
{
	$raw = trim((string) $value);

	if ($raw === '') {
		return '';
	}

	$clean = esc_url_raw($raw, ['http', 'https']);

	if ($clean === '' || ! preg_match('#^https?://#i', $clean)) {
		add_settings_error(
			$option_name,
			$option_name . '_invalid',
			sprintf(
				/* translators: %s: nombre de la red social */
				__('%s debe ser una URL válida que empiece con http:// o https://.', 'momentum-admin'),
				$label
			)
		);

		return get_option($option_name, momentum_settings_defaults()[$option_name] ?? '');
	}

	return $clean;
}

function momentum_sanitize_facebook_url($value)
{
	return momentum_sanitize_social_url_field('momentum_facebook_url', 'Facebook', $value);
}

function momentum_sanitize_instagram_url($value)
{
	return momentum_sanitize_social_url_field('momentum_instagram_url', 'Instagram', $value);
}

function momentum_sanitize_tiktok_url($value)
{
	return momentum_sanitize_social_url_field('momentum_tiktok_url', 'TikTok', $value);
}

/**
 * Teléfono opcional: dígitos, espacios, paréntesis, guiones y "+" -sin
 * reformatear lo que el administrador escribió, solo se recorta y se
 * valida el juego de caracteres y un largo razonable-.
 */
function momentum_sanitize_phone_field(string $option_name, string $label, $value): string
{
	$raw = trim((string) $value);

	if ($raw === '') {
		return '';
	}

	if (! preg_match('/^[0-9+\-\s()]+$/', $raw) || mb_strlen($raw) > 25) {
		add_settings_error(
			$option_name,
			$option_name . '_invalid',
			sprintf(
				/* translators: %s: etiqueta del campo de teléfono */
				__('%s solo puede contener números, espacios, paréntesis, guiones y el signo +.', 'momentum-admin'),
				$label
			)
		);

		return get_option($option_name, momentum_settings_defaults()[$option_name] ?? '');
	}

	return $raw;
}

function momentum_sanitize_phone_1($value)
{
	return momentum_sanitize_phone_field('momentum_phone_1', __('Número de teléfono 1', 'momentum-admin'), $value);
}

function momentum_sanitize_phone_2($value)
{
	return momentum_sanitize_phone_field('momentum_phone_2', __('Número de teléfono 2', 'momentum-admin'), $value);
}

/**
 * Dirección: textarea libre, sin HTML, conserva saltos de línea.
 */
function momentum_sanitize_address($value)
{
	return trim(sanitize_textarea_field((string) $value));
}

/**
 * Correo opcional, validado con is_email() tras normalizar a minúsculas.
 */
function momentum_sanitize_email_setting($value)
{
	$raw = trim((string) $value);

	if ($raw === '') {
		return '';
	}

	$clean = sanitize_email(strtolower($raw));

	if (! is_email($clean)) {
		add_settings_error(
			'momentum_email',
			'momentum_email_invalid',
			__('Ingresa un correo electrónico válido.', 'momentum-admin')
		);

		return get_option('momentum_email', momentum_settings_defaults()['momentum_email']);
	}

	return $clean;
}

// -----------------------------------------------------------------
// Registro (Settings API)
// -----------------------------------------------------------------

function momentum_register_general_settings(): void
{
	$fields = [
		'momentum_exchange_rate' => 'momentum_sanitize_exchange_rate',
		'momentum_facebook_url'  => 'momentum_sanitize_facebook_url',
		'momentum_instagram_url' => 'momentum_sanitize_instagram_url',
		'momentum_tiktok_url'    => 'momentum_sanitize_tiktok_url',
		'momentum_phone_1'       => 'momentum_sanitize_phone_1',
		'momentum_phone_2'       => 'momentum_sanitize_phone_2',
		'momentum_address'       => 'momentum_sanitize_address',
		'momentum_email'         => 'momentum_sanitize_email_setting',
	];

	foreach ($fields as $option_name => $callback) {
		register_setting(MOMENTUM_SETTINGS_GROUP, $option_name, [
			'type'              => 'string',
			'sanitize_callback' => $callback,
			/*
			 * Sin 'default' aquí a propósito: si se registra un default,
			 * get_option() lo devuelve como "valor actual" incluso sin fila
			 * en la BD, y si el admin guarda el formulario sin cambiar nada
			 * (mismo valor que el placeholder), update_option() compara
			 * nuevo === "actual" (el default) y decide que no hay cambio
			 * -no escribe nada-. El fallback de lectura ya lo da cada
			 * función get_momentum_*()/momentum_get_*() de abajo, pasando su
			 * propio default a get_option() explícitamente.
			 */
			'show_in_rest'      => false,
			/*
			 * Literal (no la constante del plugin momentum-admin): este
			 * archivo vive en el tema y no debe depender de una clase de
			 * otro plugin para no fallar si algún día se carga en otro
			 * orden. 'manage_options' es el mismo valor que usa
			 * Momentum_Admin_Permissions::CAPABILITY en todo el panel.
			 */
			'capability'        => 'manage_options',
		]);
	}
}
add_action('admin_init', 'momentum_register_general_settings');
/*
 * options.php exige que la opción esté registrada en el MISMO proceso que
 * la recibe -corre bajo /wp-admin, así que 'admin_init' es lo único que
 * garantiza que esto se ejecute a tiempo-. Este archivo vive en el tema y
 * se carga siempre (no solo dentro de /admin), así que 'admin_init' aquí es
 * seguro y no afecta el front-end.
 */

// -----------------------------------------------------------------
// Getters
// -----------------------------------------------------------------

function get_momentum_exchange_rate(): float
{
	$value = (float) get_option('momentum_exchange_rate', momentum_settings_defaults()['momentum_exchange_rate']);

	return $value > 0 ? $value : 18.50;
}

function momentum_get_facebook_url(): string
{
	return (string) get_option('momentum_facebook_url', momentum_settings_defaults()['momentum_facebook_url']);
}

function momentum_get_instagram_url(): string
{
	return (string) get_option('momentum_instagram_url', momentum_settings_defaults()['momentum_instagram_url']);
}

function momentum_get_tiktok_url(): string
{
	return (string) get_option('momentum_tiktok_url', momentum_settings_defaults()['momentum_tiktok_url']);
}

function momentum_get_phone_1(): string
{
	return (string) get_option('momentum_phone_1', momentum_settings_defaults()['momentum_phone_1']);
}

function momentum_get_phone_2(): string
{
	return (string) get_option('momentum_phone_2', momentum_settings_defaults()['momentum_phone_2']);
}

function momentum_get_address(): string
{
	return (string) get_option('momentum_address', momentum_settings_defaults()['momentum_address']);
}

function momentum_get_email(): string
{
	return (string) get_option('momentum_email', momentum_settings_defaults()['momentum_email']);
}

// -----------------------------------------------------------------
// Normalización de teléfonos para enlaces (solo al usarlos, nunca al
// guardarlos -el valor en la base de datos queda tal como se escribió-).
// -----------------------------------------------------------------

/**
 * Deja solo dígitos y un posible "+" inicial. Si el número no trae código
 * de país y tiene 10 dígitos (formato nacional mexicano), antepone +52.
 */
function momentum_phone_digits(string $phone): string
{
	$clean = preg_replace('/[^\d+]/', '', $phone) ?? '';

	if ($clean === '') {
		return '';
	}

	if (strpos($clean, '+') === 0) {
		return $clean;
	}

	if (preg_match('/^\d{10}$/', $clean)) {
		return '+52' . $clean;
	}

	return $clean;
}

function momentum_phone_to_tel_link(string $phone): string
{
	$digits = momentum_phone_digits($phone);

	return $digits === '' ? '' : 'tel:' . $digits;
}

/**
 * @param string $message Texto prellenado del mensaje (opcional). Se codifica
 *                         con rawurlencode() para viajar como query param de
 *                         wa.me -así funciona con espacios/acentos-.
 */
function momentum_phone_to_whatsapp_url(string $phone, string $message = ''): string
{
	$digits = momentum_phone_digits($phone);

	if ($digits === '') {
		return '';
	}

	$url = 'https://wa.me/' . ltrim($digits, '+');

	return $message === '' ? $url : $url . '?text=' . rawurlencode($message);
}
