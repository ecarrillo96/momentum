<?php
/**
 * Tabla propia para el historial de traducción (no un option serializado
 * que crecería indefinidamente): una fila por cada intento REAL de
 * traducción que hace Momentum_Property_Translation_Service. Nunca guarda
 * la API key ni el texto traducido/original -solo metadatos-.
 *
 * Único lugar del proyecto que sabe el nombre/esquema de esta tabla y cómo
 * leer/escribir en ella; el servicio y la pantalla de estadísticas solo
 * llaman a las funciones de aquí, nunca arman SQL propio.
 */

if (! defined('ABSPATH')) {
	exit;
}

const MOMENTUM_TRANSLATION_LOG_DB_VERSION = '1.0';

function momentum_translation_log_table_name(): string
{
	global $wpdb;

	return $wpdb->prefix . 'momentum_translation_log';
}

/**
 * Crea (o actualiza el esquema de) la tabla si hace falta. Guardado por una
 * opción con número de versión -mismo patrón ya usado en el tema para
 * "correr esto una sola vez" (ver momentum_maybe_flush_terrenos_rewrite_rules()
 * en inc/cpt/terrenos.php)-, así que sirve tanto para la primera instalación
 * como para futuras migraciones de esquema (subir MOMENTUM_TRANSLATION_LOG_DB_VERSION
 * y dbDelta() ajusta la tabla existente sin perder filas).
 */
function momentum_maybe_create_translation_log_table(): void
{
	if (get_option('momentum_translation_log_db_version') === MOMENTUM_TRANSLATION_LOG_DB_VERSION) {
		return;
	}

	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table_name      = momentum_translation_log_table_name();
	$charset_collate = $wpdb->get_charset_collate();

	// dbDelta() es estricto con el formato: dos espacios antes de KEY/PRIMARY
	// KEY, cada columna en su propia línea, sin comas colgantes.
	$sql = "CREATE TABLE {$table_name} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		es_post_id BIGINT UNSIGNED NOT NULL,
		en_post_id BIGINT UNSIGNED NOT NULL,
		post_type VARCHAR(20) NOT NULL,
		created_at DATETIME NOT NULL,
		char_count INT UNSIGNED NOT NULL DEFAULT 0,
		duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
		result VARCHAR(10) NOT NULL,
		status VARCHAR(20) NOT NULL,
		provider VARCHAR(20) NOT NULL DEFAULT 'deepl',
		is_new TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
		error_message VARCHAR(500) NULL,
		PRIMARY KEY  (id),
		KEY es_post_id (es_post_id),
		KEY created_at (created_at),
		KEY result (result)
	) {$charset_collate};";

	dbDelta($sql);

	update_option('momentum_translation_log_db_version', MOMENTUM_TRANSLATION_LOG_DB_VERSION);
}
add_action('init', 'momentum_maybe_create_translation_log_table');

/**
 * Registra un intento real de traducción. Se llama únicamente desde
 * Momentum_Property_Translation_Service -es la única fuente de verdad de
 * cuándo ocurrió un intento real-, nunca desde la pantalla de estadísticas
 * (que solo lee).
 *
 * @param array{
 *     es_post_id: int, en_post_id: int, post_type: string, char_count: int,
 *     duration_ms: int, result: string, status: string, provider?: string,
 *     is_new: bool, error_message?: string|null
 * } $data
 */
function momentum_log_translation_event(array $data): void
{
	global $wpdb;

	$error_message = $data['error_message'] ?? null;

	if ($error_message !== null) {
		// Nunca más de 500 caracteres (límite de la columna) y nunca datos
		// sensibles: quien llama ya debe pasar un mensaje corto y saneado
		// (categoría de error, no la excepción cruda), esto es una red de
		// seguridad adicional, no el único filtro.
		$error_message = mb_substr((string) $error_message, 0, 500);
	}

	$wpdb->insert(
		momentum_translation_log_table_name(),
		[
			'es_post_id'    => (int) $data['es_post_id'],
			'en_post_id'    => (int) $data['en_post_id'],
			'post_type'     => sanitize_key($data['post_type']),
			'created_at'    => current_time('mysql'),
			'char_count'    => max(0, (int) $data['char_count']),
			'duration_ms'   => max(0, (int) $data['duration_ms']),
			'result'        => sanitize_key($data['result']),
			'status'        => sanitize_key($data['status']),
			'provider'      => sanitize_key($data['provider'] ?? 'deepl'),
			'is_new'        => ! empty($data['is_new']) ? 1 : 0,
			'error_message' => $error_message,
		],
		['%d', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%s']
	);
}

/**
 * Últimas $limit operaciones, más recientes primero.
 *
 * @return array<int, object>
 */
function momentum_get_translation_log_recent(int $limit = 50): array
{
	global $wpdb;

	$table = momentum_translation_log_table_name();

	$rows = $wpdb->get_results($wpdb->prepare(
		"SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		max(1, $limit)
	));

	return is_array($rows) ? $rows : [];
}

/**
 * Estadísticas agregadas para las tarjetas de la pantalla: promedio de
 * caracteres por traducción exitosa, cuántas traducciones exitosas hay
 * registradas (para saber si el promedio es confiable), última traducción
 * exitosa, y el último error registrado.
 *
 * @return array{
 *     success_count: int, avg_chars_per_success: float|null,
 *     last_success_at: string|null, last_error: object|null
 * }
 */
function momentum_get_translation_log_stats(): array
{
	global $wpdb;

	$table = momentum_translation_log_table_name();

	$success_count = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$table} WHERE result = 'ok'" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	);

	$avg_chars = $success_count > 0
		? (float) $wpdb->get_var("SELECT AVG(char_count) FROM {$table} WHERE result = 'ok'") // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		: null;

	$last_success_at = $wpdb->get_var(
		"SELECT MAX(created_at) FROM {$table} WHERE result = 'ok'" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	);

	$last_error = $wpdb->get_row(
		"SELECT * FROM {$table} WHERE result = 'error' ORDER BY created_at DESC, id DESC LIMIT 1" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	);

	return [
		'success_count'          => $success_count,
		'avg_chars_per_success'  => $avg_chars,
		'last_success_at'        => $last_success_at ?: null,
		'last_error'             => $last_error ?: null,
	];
}

/**
 * Elimina filas más allá de las $keep más recientes. No se programa
 * automáticamente -este historial crece muy lento (una fila por traducción
 * real, no por visita)-, se deja disponible por si alguna vez hace falta
 * engancharla a un cron.
 */
function momentum_prune_translation_log(int $keep = 5000): void
{
	global $wpdb;

	$table = momentum_translation_log_table_name();

	$wpdb->query($wpdb->prepare(
		"DELETE FROM {$table} WHERE id NOT IN (SELECT id FROM (SELECT id FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d) t)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		max(1, $keep)
	));
}
