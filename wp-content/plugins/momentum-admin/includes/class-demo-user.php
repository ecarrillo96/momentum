<?php
/**
 * ============================================================================
 *  CÓDIGO TEMPORAL DE DEMOSTRACIÓN — AISLADO A PROPÓSITO
 * ============================================================================
 * Crea un usuario "admin" / "admin" con rol administrador, ÚNICAMENTE durante
 * la activación del plugin y ÚNICAMENTE si ese usuario no existe todavía.
 * Nunca sobrescribe la contraseña de un usuario "admin" ya existente.
 *
 * Usa exclusivamente wp_insert_user() (función nativa de WordPress). No se
 * crean tablas propias ni se guarda la contraseña en ningún otro lugar.
 * La contraseña no se registra en logs, no se imprime en HTML ni se expone
 * en ningún mensaje.
 *
 * -----------------------------------------------------------------------
 * PARA ELIMINAR ESTA CREACIÓN AUTOMÁTICA:
 * 1) Borra este archivo (includes/class-demo-user.php).
 * 2) En momentum-admin.php elimina:
 *      - El require_once de este archivo.
 *      - La línea: register_activation_hook(__FILE__, ['Momentum_Admin_Demo_User', 'maybe_create']);
 * 3) (Opcional) Elimina manualmente el usuario "admin" desde
 *    /admin (no aplica, ese panel no gestiona usuarios) o desde
 *    wp-admin > Usuarios, o vía WP-CLI:  wp user delete admin --reassign=<ID>
 * -----------------------------------------------------------------------
 */

if (! defined('ABSPATH')) {
	exit;
}

class Momentum_Admin_Demo_User
{
	const DEMO_USERNAME = 'admin';
	const DEMO_PASSWORD = 'admin';

	/**
	 * Hook: register_activation_hook. No se ejecuta en ningún otro momento.
	 */
	public static function maybe_create(): void
	{
		if (username_exists(self::DEMO_USERNAME)) {
			return;
		}

		$host  = (string) wp_parse_url(home_url(), PHP_URL_HOST);
		$host  = $host !== '' ? $host : 'momentumcocoyoc.local';
		$email = 'admin@' . $host;

		if (email_exists($email)) {
			$email = 'admin+demo@' . $host;
		}

		$user_id = wp_insert_user([
			'user_login'   => self::DEMO_USERNAME,
			'user_pass'    => self::DEMO_PASSWORD,
			'user_email'   => $email,
			'role'         => 'administrator',
			'display_name' => 'Administrador Demo',
		]);

		// Sin logging ni salida: si falla, no hay credencial que exponer.
		unset($user_id);
	}
}
