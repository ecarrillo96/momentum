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
		/*
		 * settings_fields(), add_settings_error()/get_settings_errors() viven
		 * en wp-admin/includes/{plugin,template}.php, que WordPress solo
		 * carga dentro de /wp-admin/. Esta pantalla se renderiza desde una
		 * ruta propia del front-end (el router de este plugin), así que hay
		 * que traerlos a mano -el guard con function_exists() la hace segura
		 * de llamar aunque ya estuvieran cargados por otra razón-.
		 */
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

			return;
		}

		$values = [
			// Crudo (string), no get_momentum_exchange_rate(): ese getter
			// castea a float para cálculos del tema y perdería ceros a la
			// derecha (ej. "18.50" -> 18.5) al re-mostrarlo en el formulario.
			'momentum_exchange_rate' => get_option('momentum_exchange_rate', momentum_settings_defaults()['momentum_exchange_rate']),
			'momentum_facebook_url'  => momentum_get_facebook_url(),
			'momentum_instagram_url' => momentum_get_instagram_url(),
			'momentum_tiktok_url'    => momentum_get_tiktok_url(),
			'momentum_phone_1'       => momentum_get_phone_1(),
			'momentum_phone_2'       => momentum_get_phone_2(),
			'momentum_address'       => momentum_get_address(),
			'momentum_email'         => momentum_get_email(),
		];

		// get_settings_errors() consume el transiente que options.php dejó
		// tras procesar el POST anterior; se agrupa por campo para pintar
		// cada mensaje junto a su input, no como un aviso genérico único.
		$errors_by_field = [];
		foreach (get_settings_errors() as $error) {
			$errors_by_field[$error['setting']][] = $error['message'];
		}

		$saved = isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true' && empty($errors_by_field);

		include MOMENTUM_ADMIN_PATH . 'templates/general.php';
	}
}
