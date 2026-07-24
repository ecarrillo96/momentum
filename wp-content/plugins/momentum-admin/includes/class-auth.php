<?php
/**
 * Autenticación 100% nativa de WordPress (wp_signon / wp_logout_url).
 * No se crean tablas ni se guardan contraseñas propias.
 */

if (! defined('ABSPATH')) {
	exit;
}

class Momentum_Admin_Auth
{
	const NONCE_ACTION = 'momentum_admin_login';
	const NONCE_FIELD  = 'momentum_admin_login_nonce';

	/**
	 * Procesa el POST de login (si lo hay) y renderiza el formulario.
	 * Sale del script tras un login exitoso (redirect) o al terminar de imprimir la vista.
	 */
	public static function render_login(): void
	{
		$error = '';

		if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['momentum_admin_login_submit'])) {
			$result = self::process_login();

			if (is_wp_error($result)) {
				$error = $result->get_error_message();
			} else {
				wp_safe_redirect(home_url('/admin/'));
				exit;
			}
		}

		include MOMENTUM_ADMIN_PATH . 'templates/login.php';
	}

	/**
	 * @return \WP_User|\WP_Error
	 */
	private static function process_login()
	{
		if (
			! isset($_POST[self::NONCE_FIELD])
			|| ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])), self::NONCE_ACTION)
		) {
			return new WP_Error('momentum_invalid_nonce', __('La sesión del formulario expiró. Intenta de nuevo.', 'momentum-admin'));
		}

		$username = isset($_POST['momentum_username']) ? sanitize_text_field(wp_unslash($_POST['momentum_username'])) : '';
		// La contraseña no se sanitiza con filtros de texto (alteraría caracteres válidos);
		// solo se revierte el escape de comillas que aplica WordPress a $_POST.
		$password = isset($_POST['momentum_password']) ? (string) wp_unslash($_POST['momentum_password']) : '';
		$remember = ! empty($_POST['momentum_remember']);

		if ($username === '' || $password === '') {
			return new WP_Error('momentum_empty_fields', __('Ingresa tu usuario y contraseña.', 'momentum-admin'));
		}

		$user = wp_signon([
			'user_login'    => $username,
			'user_password' => $password,
			'remember'      => $remember,
		], is_ssl());

		if (is_wp_error($user)) {
			// Mensaje genérico: no revelar si el usuario existe o no.
			return new WP_Error('momentum_login_failed', __('Usuario o contraseña incorrectos.', 'momentum-admin'));
		}

		return $user;
	}

	public static function logout_url(): string
	{
		return wp_logout_url(home_url('/admin/'));
	}
}
