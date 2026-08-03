<?php
/**
 * Pantalla "Momentum → Traducciones": estado de conexión con DeepL, consumo
 * mensual, actividad de traducción de Propiedades/Terrenos, estimación de
 * cuánto rinde la cuota restante, e historial de las últimas operaciones
 * con opción de reintentar.
 *
 * No duplica ninguna lógica de negocio: solo LEE de
 * Momentum_Property_Translation_Service / inc/translation-usage.php /
 * inc/translation-log.php (todos en el tema), y para "Reintentar" llama a
 * Momentum_Property_Translation_Service::retry_translation() -el mismo
 * método que ya usa cualquier guardado normal-.
 */

if (! defined('ABSPATH')) {
	exit;
}

class Momentum_Admin_Translations
{
	const REFRESH_NONCE_ACTION = 'momentum_admin_refresh_translation_usage';
	const REFRESH_NONCE_FIELD  = 'momentum_translation_refresh_nonce';
	const RETRY_NONCE_ACTION   = 'momentum_admin_retry_translation';
	const RETRY_NONCE_FIELD    = 'momentum_translation_retry_nonce';

	private static function base_url(): string
	{
		return home_url('/admin/traducciones/');
	}

	private static function available(): bool
	{
		return class_exists('Momentum_Property_Translation_Service')
			&& function_exists('momentum_get_translation_usage')
			&& function_exists('momentum_get_translation_log_recent');
	}

	/**
	 * Procesa las acciones POST (actualizar consumo / reintentar) ANTES de
	 * cualquier salida, para poder redirigir (Post/Redirect/Get).
	 */
	public static function maybe_handle_actions(): void
	{
		if (! self::available() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
			return;
		}

		if (isset($_POST['momentum_translation_refresh'])) {
			self::handle_refresh();
		} elseif (isset($_POST['momentum_translation_retry'])) {
			self::handle_retry();
		}
	}

	private static function handle_refresh(): void
	{
		$redirect_base = self::base_url();

		if (
			! isset($_POST[self::REFRESH_NONCE_FIELD])
			|| ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::REFRESH_NONCE_FIELD])), self::REFRESH_NONCE_ACTION)
		) {
			wp_safe_redirect(add_query_arg('error', 'nonce', $redirect_base));
			exit;
		}

		if (! current_user_can(Momentum_Admin_Permissions::CAPABILITY)) {
			wp_safe_redirect(add_query_arg('error', 'permisos', $redirect_base));
			exit;
		}

		momentum_get_translation_usage(true);

		wp_safe_redirect(add_query_arg('mensaje', 'actualizado', $redirect_base));
		exit;
	}

	private static function handle_retry(): void
	{
		$redirect_base = self::base_url();
		$es_post_id    = isset($_POST['momentum_es_post_id']) ? absint($_POST['momentum_es_post_id']) : 0;

		$nonce_ok = $es_post_id > 0
			&& isset($_POST[self::RETRY_NONCE_FIELD])
			&& wp_verify_nonce(
				sanitize_text_field(wp_unslash($_POST[self::RETRY_NONCE_FIELD])),
				self::RETRY_NONCE_ACTION . '_' . $es_post_id
			);

		if (! $nonce_ok) {
			wp_safe_redirect(add_query_arg('error', 'nonce', $redirect_base));
			exit;
		}

		if (! current_user_can(Momentum_Admin_Permissions::CAPABILITY)) {
			wp_safe_redirect(add_query_arg('error', 'permisos', $redirect_base));
			exit;
		}

		if (! get_post($es_post_id)) {
			wp_safe_redirect(add_query_arg('error', 'no_encontrado', $redirect_base));
			exit;
		}

		if (! Momentum_Property_Translation_Service::is_translation_allowed()) {
			wp_safe_redirect(add_query_arg('error', 'sin_cuota', $redirect_base));
			exit;
		}

		$ok = Momentum_Property_Translation_Service::retry_translation($es_post_id);

		wp_safe_redirect(add_query_arg('mensaje', $ok ? 'reintentado' : 'error_reintento', $redirect_base));
		exit;
	}

	public static function render(): void
	{
		if (! self::available()) {
			echo '<div class="momentum-admin-notice momentum-admin-notice--error"><p>';
			esc_html_e('El sistema de traducción no está disponible (¿el tema Momentum está activo?).', 'momentum-admin');
			echo '</p></div>';

			return;
		}

		$usage           = momentum_get_translation_usage();
		$usage_percent   = momentum_translation_usage_percent($usage);
		$threshold       = momentum_translation_usage_threshold($usage);
		$connection      = momentum_get_deepl_connection_status();
		$status_counts   = Momentum_Property_Translation_Service::get_status_counts();
		$log_stats       = momentum_get_translation_log_stats();
		$recent_log      = momentum_get_translation_log_recent(50);
		$translation_ok  = Momentum_Property_Translation_Service::is_translation_allowed();

		// Estimación de fichas adicionales que caben en la cuota restante:
		// solo con una muestra mínima de traducciones exitosas reales -antes
		// de eso, un promedio de 1 o 2 fichas no es representativo-.
		$min_sample     = 3;
		$avg_chars      = $log_stats['avg_chars_per_success'];
		$has_estimate   = $avg_chars !== null && $avg_chars > 0 && $log_stats['success_count'] >= $min_sample && $usage['character_count'] !== null && $usage['character_limit'] !== null;
		$remaining_chars = $has_estimate ? max(0, $usage['character_limit'] - $usage['character_count']) : null;
		$estimated_listings = $has_estimate ? (int) floor($remaining_chars / $avg_chars) : null;

		$mensaje_code = isset($_GET['mensaje']) ? sanitize_key(wp_unslash($_GET['mensaje'])) : '';
		$error_code   = isset($_GET['error']) ? sanitize_key(wp_unslash($_GET['error'])) : '';

		$mensajes = [
			'actualizado'  => __('Consumo de DeepL actualizado.', 'momentum-admin'),
			'reintentado'  => __('Se volvió a intentar la traducción. Revisa el estado en la tabla de abajo.', 'momentum-admin'),
		];
		$errores = [
			'nonce'          => __('La acción expiró o no es válida. Inténtalo de nuevo.', 'momentum-admin'),
			'permisos'       => __('No tienes permisos para realizar esta acción.', 'momentum-admin'),
			'no_encontrado'  => __('No se encontró la ficha en español indicada.', 'momentum-admin'),
			'sin_cuota'      => __('No se puede reintentar: no hay proveedor configurado o la cuota de DeepL está agotada.', 'momentum-admin'),
			'error_reintento' => __('No se pudo reintentar la traducción.', 'momentum-admin'),
		];

		include MOMENTUM_ADMIN_PATH . 'templates/translations.php';
	}
}
