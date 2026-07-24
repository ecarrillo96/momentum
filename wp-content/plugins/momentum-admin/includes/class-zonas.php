<?php
/**
 * Módulo Zonas: reutiliza el CPT 'zonas' definido en el tema
 * (wp-content/themes/momentum/inc/cpt/zonas.php). No se registra el CPT de
 * nuevo ni se duplica su lógica: momentum_get_zonas(), momentum_find_zona_by_name()
 * y momentum_count_propiedades_con_zona() viven allí y este módulo solo las consume.
 *
 * A diferencia de Propiedades/Blog (formularios de página completa, POST +
 * redirect), esta pantalla es listar/agregar/eliminar en un modal sin
 * recargar, así que Agregar y Eliminar se resuelven vía la API de AJAX
 * nativa de WordPress (wp_ajax_*), no vía un submit a la propia URL.
 */

if (! defined('ABSPATH')) {
	exit;
}

class Momentum_Admin_Zonas
{
	const POST_TYPE = 'zonas';

	const NONCE_ACTION = 'momentum_admin_zonas';
	const NAME_MAX_LEN = 60;

	public static function init(): void
	{
		add_action('wp_ajax_momentum_admin_add_zona', [__CLASS__, 'ajax_add']);
		add_action('wp_ajax_momentum_admin_delete_zona', [__CLASS__, 'ajax_delete']);
	}

	private static function cpt_available(): bool
	{
		return function_exists('momentum_get_zonas') && post_type_exists(self::POST_TYPE);
	}

	// -----------------------------------------------------------------
	// Listado
	// -----------------------------------------------------------------

	public static function render(): void
	{
		if (! self::cpt_available()) {
			echo '<div class="momentum-admin-notice momentum-admin-notice--error"><p>';
			esc_html_e('El tipo de contenido "zonas" no está disponible. Verifica que el tema Momentum esté activo.', 'momentum-admin');
			echo '</p></div>';

			return;
		}

		$zonas = get_posts([
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		]);

		$nonce = wp_create_nonce(self::NONCE_ACTION);

		include MOMENTUM_ADMIN_PATH . 'templates/zonas/list.php';
	}

	// -----------------------------------------------------------------
	// AJAX: Agregar
	// -----------------------------------------------------------------

	public static function ajax_add(): void
	{
		check_ajax_referer(self::NONCE_ACTION, 'nonce');

		if (! current_user_can(Momentum_Admin_Permissions::CAPABILITY)) {
			wp_send_json_error(['message' => __('No tienes permiso para realizar esta acción.', 'momentum-admin')], 403);
		}

		$raw_name = isset($_POST['nombre']) ? wp_unslash($_POST['nombre']) : '';
		$name     = self::sanitize_zona_name((string) $raw_name);

		if ($name === '') {
			wp_send_json_error(['message' => __('El nombre de la zona es obligatorio.', 'momentum-admin')], 422);
		}

		if (mb_strlen($name) > self::NAME_MAX_LEN) {
			wp_send_json_error([
				'message' => sprintf(
					/* translators: %d: longitud máxima permitida */
					__('El nombre de la zona no puede superar los %d caracteres.', 'momentum-admin'),
					self::NAME_MAX_LEN
				),
			], 422);
		}

		if (momentum_find_zona_by_name($name)) {
			wp_send_json_error(['message' => __('La zona ya existe.', 'momentum-admin')], 409);
		}

		$post_id = wp_insert_post([
			'post_type'   => self::POST_TYPE,
			'post_title'  => $name,
			'post_status' => 'publish',
		], true);

		if (is_wp_error($post_id)) {
			wp_send_json_error(['message' => __('Ocurrió un error al guardar la zona.', 'momentum-admin')], 500);
		}

		$post = get_post($post_id);

		wp_send_json_success([
			'message' => __('Zona agregada correctamente.', 'momentum-admin'),
			'zona'    => [
				'id'    => $post_id,
				'name'  => $post->post_title,
				'slug'  => $post->post_name,
				'date'  => date_i18n('d/m/Y', strtotime($post->post_date)),
			],
		]);
	}

	/**
	 * Requerido = no vacío tras trim(); espacios dobles/múltiples colapsados
	 * a uno solo; sin etiquetas HTML (sanitize_text_field ya las retira).
	 */
	private static function sanitize_zona_name(string $raw): string
	{
		$clean = sanitize_text_field($raw);
		$clean = preg_replace('/\s+/u', ' ', $clean);
		$clean = trim((string) $clean);

		return $clean;
	}

	// -----------------------------------------------------------------
	// AJAX: Eliminar
	// -----------------------------------------------------------------

	public static function ajax_delete(): void
	{
		check_ajax_referer(self::NONCE_ACTION, 'nonce');

		if (! current_user_can(Momentum_Admin_Permissions::CAPABILITY)) {
			wp_send_json_error(['message' => __('No tienes permiso para realizar esta acción.', 'momentum-admin')], 403);
		}

		$zona_id = isset($_POST['zona_id']) ? absint($_POST['zona_id']) : 0;

		if ($zona_id <= 0 || get_post_type($zona_id) !== self::POST_TYPE) {
			wp_send_json_error(['message' => __('Zona no encontrada.', 'momentum-admin')], 404);
		}

		$slug  = get_post_field('post_name', $zona_id);
		$usada = momentum_count_propiedades_con_zona($slug);

		if ($usada > 0) {
			wp_send_json_error([
				'message' => __('No es posible eliminar esta zona porque está siendo utilizada por una o más propiedades.', 'momentum-admin'),
			], 409);
		}

		$deleted = wp_delete_post($zona_id, true);

		if (! $deleted) {
			wp_send_json_error(['message' => __('Ocurrió un error al eliminar la zona.', 'momentum-admin')], 500);
		}

		wp_send_json_success(['message' => __('Zona eliminada correctamente.', 'momentum-admin')]);
	}
}
