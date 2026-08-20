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
		// Cuenta de TikTok en inglés. Cuando esté vacía, el footer cae al
		// link en español (mismo criterio que momentum_intro_video_url_en).
		'momentum_tiktok_url_en' => 'https://www.tiktok.com/@momentumcocoyocmx',
		'momentum_phone_1'       => '735 379 7781',
		'momentum_phone_2'       => '735 166 4461',
		'momentum_address'       => "Estación vieja #10, Int. 3, Centro, Oaxtepec\nC.P. 62738, Yautepec, Morelos",
		'momentum_email'         => 'momentumcocoyoc.ventas@gmail.com',
		// GA4/Search Console: sin default -a propósito, nunca se rellena un
		// ID/código de ejemplo aquí. Vacío = integración no conectada todavía;
		// ver inc/seo-integrations.php, que no imprime nada mientras estén así.
		'momentum_ga_measurement_id' => '',
		'momentum_gsc_verification'  => '',
		'momentum_auto_publish_translations' => '0',
		// Vacío = todavía no hay video en inglés subido; el sitio sigue
		// mostrando el mismo video en español a los visitantes en inglés
		// (comportamiento actual, sin cambio) hasta que se guarde una URL aquí.
		'momentum_intro_video_url_en' => '',
	];
}

/**
 * La API key de DeepL vive en su propia opción, con autoload desactivado
 * -no hace falta en cada visita pública, solo cuando
 * Momentum_Property_Translation_Service traduce-. register_setting() no
 * deja fijar el autoload de una opción nueva directamente, así que se
 * precrea aquí (una sola vez, si todavía no existe) para que
 * update_option() -lo que realmente usa options.php- conserve ese autoload
 * en todos los guardados posteriores desde el formulario.
 */
add_action('after_setup_theme', function () {
	if (get_option('momentum_translation_api_key_deepl', false) === false) {
		add_option('momentum_translation_api_key_deepl', '', '', 'no');
	}
});

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

function momentum_sanitize_tiktok_url_en($value)
{
	return momentum_sanitize_social_url_field('momentum_tiktok_url_en', __('TikTok (inglés)', 'momentum'), $value);
}

/**
 * URL opcional del video de introducción en inglés (mismo criterio de
 * validación que las redes sociales: http/https, o vacío para "no
 * configurado todavía" -momentum_get_intro_video_url() cae al video en
 * español mientras esté vacío-).
 */
function momentum_sanitize_intro_video_url_en($value)
{
	return momentum_sanitize_social_url_field('momentum_intro_video_url_en', __('Video de introducción (inglés)', 'momentum'), $value);
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

/**
 * Google Analytics 4 Measurement ID: formato 'G-' + 6 a 12 caracteres
 * alfanuméricos (el largo real de Google varía; se deja margen sin ser
 * laxo). Opcional -vacío significa "todavía no conectado"-, nunca se
 * autocompleta con un ID de ejemplo.
 */
function momentum_sanitize_ga_measurement_id($value)
{
	$raw = strtoupper(trim((string) $value));

	if ($raw === '') {
		return '';
	}

	if (! preg_match('/^G-[A-Z0-9]{6,12}$/', $raw)) {
		add_settings_error(
			'momentum_ga_measurement_id',
			'momentum_ga_measurement_id_invalid',
			__('El Measurement ID debe tener el formato G-XXXXXXXXXX (lo proporciona Google Analytics 4 al crear el flujo de datos).', 'momentum-admin')
		);

		return get_option('momentum_ga_measurement_id', '');
	}

	return $raw;
}

/**
 * Código de verificación de Google Search Console (método "etiqueta HTML").
 * Acepta tanto el valor solo (lo que Google llama "content") como la
 * etiqueta <meta> completa que Search Console ofrece copiar/pegar -en ese
 * caso se extrae solo el 'content', nunca se guarda HTML crudo-, para no
 * depender de que el administrador sepa cuál de las dos partes pegar.
 */
function momentum_sanitize_gsc_verification($value)
{
	$raw = trim((string) $value);

	if ($raw === '') {
		return '';
	}

	if (preg_match('/content=["\']([^"\']+)["\']/i', $raw, $matches)) {
		$raw = $matches[1];
	}

	$clean = preg_replace('/[^A-Za-z0-9_\-]/', '', $raw);

	if ($clean === '' || $clean !== $raw || mb_strlen($clean) < 10 || mb_strlen($clean) > 255) {
		add_settings_error(
			'momentum_gsc_verification',
			'momentum_gsc_verification_invalid',
			__('El código de verificación no es válido. Pega el valor "content" de la etiqueta HTML que te da Google Search Console (o la etiqueta completa).', 'momentum-admin')
		);

		return get_option('momentum_gsc_verification', '');
	}

	return $clean;
}

/**
 * Casilla "publicar traducciones automáticamente". Se apoya en un input
 * type="hidden" con el mismo name puesto ANTES de la casilla en el template
 * -patrón estándar de WordPress para checkboxes en Settings API-: si la
 * casilla queda sin marcar, el navegador no la envía y solo sobrevive el
 * hidden ('0'); si se marca, el valor de la casilla ('1') llega después en
 * $_POST y es el que PHP conserva para nombres duplicados.
 */
function momentum_sanitize_auto_publish_translations($value)
{
	return $value === '1' ? '1' : '0';
}

/**
 * API key de DeepL: dejar el campo vacío al guardar significa "no cambiar"
 * -nunca se borra una key ya guardada por accidente al reenviar el
 * formulario con el campo en blanco (nunca se vuelve a mostrar completa,
 * ver momentum_get_deepl_api_key_masked_hint())-. Se guarda recortada, sin
 * más validación de formato: cada proveedor decide si la key es válida al
 * intentar usarla (Momentum_DeepL_Translation_Provider::is_configured()
 * solo confirma que no esté vacía).
 */
function momentum_sanitize_translation_api_key_deepl($value)
{
	$raw = trim((string) $value);

	return $raw === '' ? get_option('momentum_translation_api_key_deepl', '') : $raw;
}

/**
 * '' si no hay key guardada. Si hay una, un texto seguro para mostrar en el
 * placeholder del campo -nunca la key completa- con los últimos 4
 * caracteres, para que el administrador confirme cuál tiene guardada sin
 * exponerla en el HTML de la página.
 */
function momentum_get_deepl_api_key_masked_hint(): string
{
	$key = (string) get_option('momentum_translation_api_key_deepl', '');

	if ($key === '') {
		return '';
	}

	return sprintf(
		/* translators: %s: últimos 4 caracteres de la API key ya guardada */
		__('Configurada (termina en ****%s)', 'momentum-admin'),
		substr($key, -4)
	);
}

function momentum_auto_publish_translations_enabled(): bool
{
	return get_option('momentum_auto_publish_translations', momentum_settings_defaults()['momentum_auto_publish_translations']) === '1';
}

// -----------------------------------------------------------------
// Registro (Settings API)
// -----------------------------------------------------------------

/**
 * Única fuente de verdad option_name => sanitize_callback para la pantalla
 * Admin > General, usada tanto para registrar los campos
 * (momentum_register_general_settings()) como para filtrar los errores de
 * guardado a solo estos campos (momentum_general_settings_field_names(),
 * consumida por Momentum_Admin_General::render() en el plugin) -sin esto,
 * get_settings_errors() sin filtrar recoge CUALQUIER error registrado
 * durante el mismo request por otro plugin (Polylang, Rank Math, el propio
 * núcleo de WordPress), mostrando un aviso genérico de "no se guardó" sin
 * que ningún campo propio esté realmente marcado en rojo-.
 */
function momentum_general_settings_fields(): array
{
	return [
		'momentum_exchange_rate' => 'momentum_sanitize_exchange_rate',
		'momentum_facebook_url'  => 'momentum_sanitize_facebook_url',
		'momentum_instagram_url' => 'momentum_sanitize_instagram_url',
		'momentum_tiktok_url'    => 'momentum_sanitize_tiktok_url',
		'momentum_tiktok_url_en' => 'momentum_sanitize_tiktok_url_en',
		'momentum_phone_1'       => 'momentum_sanitize_phone_1',
		'momentum_phone_2'       => 'momentum_sanitize_phone_2',
		'momentum_address'       => 'momentum_sanitize_address',
		'momentum_email'         => 'momentum_sanitize_email_setting',
		'momentum_ga_measurement_id' => 'momentum_sanitize_ga_measurement_id',
		'momentum_gsc_verification'  => 'momentum_sanitize_gsc_verification',
		'momentum_auto_publish_translations'  => 'momentum_sanitize_auto_publish_translations',
		'momentum_translation_api_key_deepl'  => 'momentum_sanitize_translation_api_key_deepl',
		'momentum_intro_video_url_en'         => 'momentum_sanitize_intro_video_url_en',
	];
}

function momentum_general_settings_field_names(): array
{
	return array_keys(momentum_general_settings_fields());
}

function momentum_register_general_settings(): void
{
	$fields = momentum_general_settings_fields();

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

function momentum_get_tiktok_url_en(): string
{
	return (string) get_option('momentum_tiktok_url_en', momentum_settings_defaults()['momentum_tiktok_url_en']);
}

/**
 * '' mientras no se haya subido/guardado un video de introducción en
 * inglés -ver momentum_get_intro_video_url() en inc/helpers.php, que cae al
 * video en español mientras esto esté vacío.
 */
function momentum_get_intro_video_url_en(): string
{
	return (string) get_option('momentum_intro_video_url_en', momentum_settings_defaults()['momentum_intro_video_url_en']);
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

/**
 * '' mientras nadie haya conectado Google Analytics todavía -ver
 * inc/seo-integrations.php, que usa este getter para decidir si imprime
 * el snippet de gtag.js o no imprime nada.
 */
function momentum_get_ga_measurement_id(): string
{
	return (string) get_option('momentum_ga_measurement_id', '');
}

/**
 * '' mientras nadie haya conectado Google Search Console todavía -mismo
 * criterio que momentum_get_ga_measurement_id().
 */
function momentum_get_gsc_verification(): string
{
	return (string) get_option('momentum_gsc_verification', '');
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
