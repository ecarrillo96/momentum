<?php
/**
 * Módulo General: pantalla de configuración global del sitio (tipo de
 * cambio, redes sociales, teléfonos, dirección, correo).
 *
 * Reutiliza por completo la Settings API de WordPress registrada en
 * wp-content/themes/momentum/inc/site-settings.php -mismo criterio que
 * Momentum_Admin_Zonas reutilizando el CPT 'zonas' del tema-: este módulo
 * NO valida, NO sanitiza ni NO guarda nada. El <form> se envía directo a
 * options.php (núcleo de WordPress), que ya se encarga de nonce,
 * capability, sanitización por campo y de no sobrescribir un valor
 * existente con uno inválido (vía los sanitize_callback registrados allá).
 * Por eso esta clase no tiene ningún manejador de POST.
 */

if (! defined('ABSPATH')) {
	exit;
}

class Momentum_Admin_General
{
	public static function render(): void
	{
		if (! self::prepare_settings_api()) {
			return;
		}

		$values           = self::build_values();
		$known_fields     = momentum_general_settings_field_names();
		$errors_by_field  = self::build_errors_by_field($known_fields);
		$saved            = isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true' && empty($errors_by_field);

		include MOMENTUM_ADMIN_PATH . 'templates/general.php';
	}

	/**
	 * SEO/analítica, video de introducción y traducción automática: viven en
	 * su propia pantalla (submenú "SEO y traducción" dentro de General) para
	 * no saturar la configuración general con ajustes técnicos que se tocan
	 * con mucha menos frecuencia.
	 */
	public static function render_seo(): void
	{
		if (! self::prepare_settings_api()) {
			return;
		}

		$values           = self::build_values();
		$known_fields     = momentum_general_settings_field_names();
		$errors_by_field  = self::build_errors_by_field($known_fields);
		$saved            = isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true' && empty($errors_by_field);

		include MOMENTUM_ADMIN_PATH . 'templates/general-seo.php';
	}

	/**
	 * settings_fields(), add_settings_error()/get_settings_errors() viven
	 * en wp-admin/includes/{plugin,template}.php, que WordPress solo
	 * carga dentro de /wp-admin/. Estas pantallas se renderizan desde una
	 * ruta propia del front-end (el router de este plugin), así que hay
	 * que traerlos a mano -el guard con function_exists() la hace segura
	 * de llamar aunque ya estuvieran cargados por otra razón-.
	 *
	 * Devuelve false (y ya imprimió el aviso de error) si el tema Momentum
	 * no está activo; en ese caso el caller debe abortar el render.
	 */
	private static function prepare_settings_api(): bool
	{
		if (! function_exists('settings_fields')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if (! function_exists('get_settings_errors')) {
			require_once ABSPATH . 'wp-admin/includes/template.php';
		}

		if (! function_exists('get_momentum_exchange_rate')) {
			echo '<div class="momentum-admin-notice momentum-admin-notice--error"><p>';
			esc_html_e('La configuración general no está disponible. Verifica que el tema Momentum esté activo.', 'momentum-admin');
			echo '</p></div>';

			return false;
		}

		return true;
	}

	private static function build_values(): array
	{
		return [
			// Crudo (string), no get_momentum_exchange_rate(): ese getter
			// castea a float para cálculos del tema y perdería ceros a la
			// derecha (ej. "18.50" -> 18.5) al re-mostrarlo en el formulario.
			'momentum_exchange_rate' => get_option('momentum_exchange_rate', momentum_settings_defaults()['momentum_exchange_rate']),
			'momentum_facebook_url'  => momentum_get_facebook_url(),
			'momentum_instagram_url' => momentum_get_instagram_url(),
			'momentum_tiktok_url'    => momentum_get_tiktok_url(),
			'momentum_tiktok_url_en' => momentum_get_tiktok_url_en(),
			'momentum_phone_1'       => momentum_get_phone_1(),
			'momentum_phone_2'       => momentum_get_phone_2(),
			'momentum_address'       => momentum_get_address(),
			'momentum_email'         => momentum_get_email(),
			'momentum_ga_measurement_id' => momentum_get_ga_measurement_id(),
			'momentum_gsc_verification'  => momentum_get_gsc_verification(),
			'momentum_ga_ads_conversion_id' => momentum_get_ga_ads_conversion_id(),
			'momentum_meta_pixel_id'        => momentum_get_meta_pixel_id(),
			'momentum_intro_video_url_en' => momentum_get_intro_video_url_en(),
			'momentum_auto_publish_translations' => momentum_auto_publish_translations_enabled() ? '1' : '0',
			// Nunca la key cruda aquí -solo una pista enmascarada para el
			// placeholder del campo (ver momentum_get_deepl_api_key_masked_hint()).
			// El campo siempre se renderiza vacío; dejarlo vacío al guardar
			// significa "no cambiar" (ver momentum_sanitize_translation_api_key_deepl()).
			'momentum_translation_api_key_deepl_hint' => momentum_get_deepl_api_key_masked_hint(),
		];
	}

	/**
	 * get_settings_errors() sin argumento devuelve TODOS los errores
	 * registrados durante este request, de cualquier plugin -no solo los
	 * de esta pantalla-. Se filtra a los campos que este formulario
	 * realmente registra (momentum_general_settings_field_names()) para
	 * que un error ajeno (Polylang, Rank Math, el propio WordPress)
	 * nunca dispare aquí el aviso de "no se guardaron algunos campos"
	 * sin que ningún campo propio esté realmente marcado.
	 */
	private static function build_errors_by_field(array $known_fields): array
	{
		$errors_by_field = [];

		foreach (get_settings_errors() as $error) {
			if (in_array($error['setting'], $known_fields, true)) {
				$errors_by_field[$error['setting']][] = $error['message'];
			}
		}

		return $errors_by_field;
	}
}
