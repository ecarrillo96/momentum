<?php
/**
 * Servicio de traducción automática ES→(otros idiomas) para el Blog (post
 * type nativo 'post'), en el mismo espíritu que
 * Momentum_Property_Translation_Service para Propiedades/Terrenos: crear la
 * traducción, enlazarla con Polylang, sincronizar los campos que no
 * requieren traducción, traducir los que sí (protegiendo cualquier edición
 * manual), decidir si se publica, y mantener el estado visible en wp-admin.
 *
 * Deliberadamente NO es una generalización de Momentum_Property_Translation_Service
 * -esa clase está fuertemente acoplada al esquema de meta de Propiedades/
 * Terrenos (momentum_propiedad_meta_schema()/momentum_terreno_meta_schema()),
 * que el Blog no tiene-. En cambio, reutiliza toda la infraestructura ya
 * genérica: inc/translation-provider.php (proveedor DeepL), inc/translation-usage.php
 * (cuota), inc/translation-log.php (historial, misma tabla, post_type='post'),
 * y los métodos estáticos sin estado de Momentum_Property_Translation_Service
 * (status_label(), is_translation_allowed()) en vez de duplicarlos.
 *
 * Campos traducidos (sin equivalente de 'características', que es exclusivo
 * de Propiedades/Terrenos): title (post_title), description (post_content),
 * excerpt (post_excerpt), meta_title/meta_description (rank_math_title/
 * rank_math_description -Rank Math ya es quien genera el SEO del blog, ver
 * inc/blog-seo.php, desactivado-). Los campos propios momentum_seo_* (inc/cpt/blog.php)
 * NO se tocan aquí: hoy no tienen efecto en el <head> (mismo motivo que en
 * Propiedades, que tampoco los usa).
 */

if (! defined('ABSPATH')) {
	exit;
}

class Momentum_Blog_Translation_Service
{
	private const STATUS_META      = '_momentum_blog_translation_status';
	private const SOURCE_HASH_META = '_momentum_blog_source_hash';
	private const SNAPSHOT_META    = '_momentum_blog_sync_snapshot';

	private const TEXTUAL_KEYS = ['title', 'description', 'excerpt', 'meta_title', 'meta_description'];

	private const BRAND_NAME = 'Momentum Cocoyoc';

	private static bool $syncing = false;

	/**
	 * Bandera para el problema de temporización del guardado desde
	 * /admin/blog/: Momentum_Admin_Blog::handle_save() guarda imagen
	 * destacada, etiquetas y meta propia DESPUÉS de wp_insert_post()/
	 * wp_update_post() (de forma procedural, no vía hook, a diferencia de
	 * Propiedades/Terrenos). Si el hook automático de este servicio
	 * (enganchado a 'save_post_post') corriera durante ese wp_insert_post()/
	 * wp_update_post(), leería datos todavía incompletos. Momentum_Admin_Blog
	 * activa esta bandera justo antes de guardar el post, y llama a
	 * handle_save() explícitamente él mismo una vez que ya guardó todo lo
	 * demás -exactamente el mismo patrón que ya usa
	 * Momentum_Admin_CLI_Migrate_Translations con el servicio de Propiedades-.
	 * Para guardados nativos de wp-admin (que no pasan por el plugin), esta
	 * bandera nunca se activa y el hook funciona sin cambios.
	 */
	private static bool $external_trigger_pending = false;

	/**
	 * IDs de traducción creadas en esta misma ejecución de run() -mismo
	 * criterio que Momentum_Property_Translation_Service, para el registro
	 * 'is_new' del historial-.
	 */
	private array $newly_created_translation_ids = [];

	private int $source_id;

	public static function defer_auto_trigger(): void
	{
		self::$external_trigger_pending = true;
	}

	public static function resume_auto_trigger(): void
	{
		self::$external_trigger_pending = false;
	}

	/**
	 * Enganchada a 'save_post_post' (ver functions.php). No llama a
	 * handle_save() si el guardado ya va a disparar la traducción de forma
	 * explícita (ver defer_auto_trigger() arriba).
	 */
	public static function handle_save_hook(int $post_id, WP_Post $post, bool $update): void
	{
		if (self::$external_trigger_pending) {
			return;
		}

		self::handle_save($post_id, $post, $update);
	}

	/**
	 * Punto de entrada real -llamado por el hook (handle_save_hook()),
	 * explícitamente por Momentum_Admin_Blog::handle_save() (plugin
	 * momentum-admin), y por Momentum_Admin_CLI_Migrate_Blog_Translations-.
	 * Aplica las mismas guardas que Momentum_Property_Translation_Service::handle_save().
	 */
	public static function handle_save(int $post_id, WP_Post $post, bool $update): void
	{
		if (self::$syncing) {
			return;
		}

		if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
			return;
		}

		if (! momentum_is_multilingual()) {
			return;
		}

		$lang = momentum_get_post_language($post_id);

		if ($lang === '') {
			$lang = momentum_default_language();
			momentum_set_post_language($post_id, $lang);
		}

		if ($lang !== momentum_default_language()) {
			return;
		}

		self::$syncing = true;

		try {
			(new self($post_id))->run();
		} finally {
			self::$syncing = false;
		}
	}

	/**
	 * Vocabulario de estados compartido con Propiedades/Terrenos -no se
	 * inventa un segundo set de etiquetas para el mismo concepto-.
	 */
	public static function status_label(string $status): string
	{
		return Momentum_Property_Translation_Service::status_label($status);
	}

	public static function status_meta_key(): string
	{
		return self::STATUS_META;
	}

	/**
	 * Multi-idioma + proveedor configurado + cuota disponible: exactamente
	 * la misma pregunta que para Propiedades/Terrenos, sin nada específico
	 * de blog -se delega en vez de duplicar-.
	 */
	public static function is_translation_allowed(): bool
	{
		return Momentum_Property_Translation_Service::is_translation_allowed();
	}

	/**
	 * Reintenta la traducción de un artículo en español ya guardado. No es
	 * una vía distinta: llama al mismo handle_save() de cualquier guardado
	 * normal.
	 */
	public static function retry_translation(int $es_post_id): bool
	{
		$post = get_post($es_post_id);

		if (! $post instanceof WP_Post || $post->post_type !== 'post') {
			return false;
		}

		self::handle_save($es_post_id, $post, true);

		return true;
	}

	private function __construct(int $source_id)
	{
		$this->source_id = $source_id;
	}

	private function run(): void
	{
		foreach (momentum_get_secondary_languages() as $lang) {
			$target_id = $this->ensure_translation($lang);

			if ($target_id <= 0) {
				continue;
			}

			$this->sync_shared_fields($target_id);
			$this->sync_image($target_id);
			$this->sync_textual_fields($target_id, $lang);
			$this->sync_publish_status($target_id);
		}
	}

	/**
	 * Devuelve el ID de la traducción en $lang, creándola (borrador vacío,
	 * enlazada vía Polylang) si todavía no existe. Nunca duplica.
	 */
	private function ensure_translation(string $lang): int
	{
		$existing = momentum_find_translation($this->source_id, $lang);

		if ($existing) {
			return $existing;
		}

		$title         = get_the_title($this->source_id);
		$fallback_slug = sanitize_title($title !== '' ? $title : 'articulo-' . $this->source_id);

		// Mismo motivo que en Momentum_Property_Translation_Service::ensure_translation():
		// se necesita poder crear un borrador vacío a propósito.
		$allow_empty = static fn (): bool => false;
		add_filter('wp_insert_post_empty_content', $allow_empty);

		$new_id = wp_insert_post([
			'post_type'   => 'post',
			'post_status' => 'draft',
			'post_title'  => '',
			'post_name'   => $fallback_slug,
		], true);

		remove_filter('wp_insert_post_empty_content', $allow_empty);

		if (is_wp_error($new_id)) {
			error_log('[Momentum Blog Translation] no se pudo crear la traducción (post #' . $this->source_id . '): ' . $new_id->get_error_code());

			return 0;
		}

		$new_id = (int) $new_id;

		momentum_set_post_language($new_id, $lang);
		momentum_link_translations([
			momentum_default_language() => $this->source_id,
			$lang                       => $new_id,
		]);

		update_post_meta($new_id, self::STATUS_META, Momentum_Property_Translation_Service::STATUS_PENDING);
		update_post_meta($new_id, self::SNAPSHOT_META, ['_slug' => md5($fallback_slug)]);

		$this->newly_created_translation_ids[] = $new_id;

		return $new_id;
	}

	/**
	 * Campos que NO requieren traducción: se copian siempre, sin condición.
	 * A diferencia de Propiedades/Terrenos, el Blog no tiene un esquema de
	 * meta grande que recorrer -solo el flag "Destacado" y las etiquetas-.
	 */
	private function sync_shared_fields(int $target_id): void
	{
		$destacado = get_post_meta($this->source_id, 'momentum_destacado', true);

		if ($destacado !== '') {
			update_post_meta($target_id, 'momentum_destacado', $destacado);
		} else {
			delete_post_meta($target_id, 'momentum_destacado');
		}

		$this->sync_tags($target_id);
	}

	/**
	 * Etiquetas copiadas tal cual (sin traducir los términos) -decisión
	 * explícita: traducir taxonomía requeriría términos equivalentes en
	 * inglés ya enlazados vía Polylang, que este proyecto no gestiona hoy.
	 */
	private function sync_tags(int $target_id): void
	{
		$tags = wp_get_post_terms($this->source_id, 'post_tag', ['fields' => 'names']);

		if (is_wp_error($tags)) {
			return;
		}

		wp_set_post_terms($target_id, $tags, 'post_tag', false);
	}

	/**
	 * Misma imagen destacada (mismo ID de adjunto) -no se duplican medios-.
	 */
	private function sync_image(int $target_id): void
	{
		$thumbnail_id = get_post_thumbnail_id($this->source_id);

		if ($thumbnail_id) {
			set_post_thumbnail($target_id, $thumbnail_id);
		} else {
			delete_post_thumbnail($target_id);
		}
	}

	private function get_textual_fields(int $post_id): array
	{
		$post = get_post($post_id);

		return [
			'title'            => $post->post_title,
			'description'      => $post->post_content,
			'excerpt'          => $post->post_excerpt,
			'meta_title'       => (string) get_post_meta($post_id, 'rank_math_title', true),
			'meta_description' => (string) get_post_meta($post_id, 'rank_math_description', true),
		];
	}

	private function compute_source_hash(array $source_fields): string
	{
		return md5(implode("\x1f", $source_fields));
	}

	private function get_protected_terms(): array
	{
		return [self::BRAND_NAME];
	}

	/**
	 * Traduce (o mantiene protegidos) título, descripción, extracto y SEO
	 * (Rank Math), y deriva el slug del título traducido. Mismo mecanismo de
	 * protección de ediciones manuales que Momentum_Property_Translation_Service::sync_textual_fields()
	 * -comparación contra la huella de lo último que este servicio escribió-.
	 */
	private function sync_textual_fields(int $target_id, string $lang): void
	{
		$source_fields = $this->get_textual_fields($this->source_id);
		$source_hash   = $this->compute_source_hash($source_fields);
		$stored_hash   = (string) get_post_meta($this->source_id, self::SOURCE_HASH_META, true);

		$target_fields = $this->get_textual_fields($target_id);
		$snapshot      = (array) get_post_meta($target_id, self::SNAPSHOT_META, true);

		$rewritable = [];

		foreach (self::TEXTUAL_KEYS as $key) {
			$current       = $target_fields[$key];
			$expected_hash = $snapshot[$key] ?? null;
			$rewritable[$key] = ($current === '') || ($expected_hash !== null && md5($current) === $expected_hash);
		}

		if (! in_array(true, $rewritable, true)) {
			update_post_meta($target_id, self::STATUS_META, Momentum_Property_Translation_Service::STATUS_MANUAL);

			return;
		}

		$status = (string) get_post_meta($target_id, self::STATUS_META, true);

		if ($status === Momentum_Property_Translation_Service::STATUS_COMPLETE && $source_hash === $stored_hash) {
			return;
		}

		$provider = momentum_get_translation_provider();

		if ($provider === null) {
			update_post_meta($target_id, self::STATUS_META, Momentum_Property_Translation_Service::STATUS_NOT_CONFIGURED);

			return;
		}

		$fields_to_translate = array_intersect_key($source_fields, array_filter($rewritable));
		$char_count           = array_sum(array_map('mb_strlen', $fields_to_translate));
		$is_new               = in_array($target_id, $this->newly_created_translation_ids, true);

		if (function_exists('momentum_translation_quota_available') && ! momentum_translation_quota_available()) {
			update_post_meta($target_id, self::STATUS_META, Momentum_Property_Translation_Service::STATUS_QUOTA_EXCEEDED);
			$this->log_translation_event($target_id, 0, 0, 'blocked', Momentum_Property_Translation_Service::STATUS_QUOTA_EXCEEDED, $is_new, 'Cuota de DeepL agotada; no se intentó la solicitud.');

			return;
		}

		update_post_meta($target_id, self::STATUS_META, Momentum_Property_Translation_Service::STATUS_TRANSLATING);

		$started_at = microtime(true);

		$translated = $provider->translate_fields(
			$fields_to_translate,
			momentum_default_language(),
			$lang,
			$this->get_protected_terms()
		);

		$duration_ms = (int) round((microtime(true) - $started_at) * 1000);
		$result      = $translated === null ? 'error' : 'ok';

		if (function_exists('momentum_invalidate_translation_usage_cache')) {
			momentum_invalidate_translation_usage_cache();
		}

		error_log(sprintf(
			'[Momentum Blog Translation] post #%d es->%s: %s, %d caracteres, %dms',
			$this->source_id,
			$lang,
			$result,
			$char_count,
			$duration_ms
		));

		do_action('momentum_translation_attempted', $this->source_id, 'post', $lang, $char_count, $duration_ms, $result);

		if ($translated === null) {
			$error_type   = $provider->get_last_error_type();
			$final_status = $error_type === 'quota' ? Momentum_Property_Translation_Service::STATUS_QUOTA_EXCEEDED : Momentum_Property_Translation_Service::STATUS_ERROR;

			update_post_meta($target_id, self::STATUS_META, $final_status);
			$this->log_translation_event($target_id, $char_count, $duration_ms, 'error', $final_status, $is_new, $this->error_message_for_type($error_type));

			return;
		}

		$final = [];
		foreach (self::TEXTUAL_KEYS as $key) {
			$final[$key] = $rewritable[$key] ? (string) ($translated[$key] ?? '') : $target_fields[$key];
		}

		if (trim($final['title']) === '' || trim($final['description']) === '') {
			update_post_meta($target_id, self::STATUS_META, Momentum_Property_Translation_Service::STATUS_ERROR);
			$this->log_translation_event($target_id, $char_count, $duration_ms, 'error', Momentum_Property_Translation_Service::STATUS_ERROR, $is_new, 'El proveedor devolvió título o descripción vacíos.');

			return;
		}

		$update_args = [
			'ID'           => $target_id,
			'post_title'   => $final['title'],
			'post_content' => $final['description'],
			'post_excerpt' => $final['excerpt'],
		];

		if ($rewritable['title']) {
			$current_slug    = get_post($target_id)->post_name;
			$slug_hash       = $snapshot['_slug'] ?? null;
			$slug_rewritable = $current_slug === '' || ($slug_hash !== null && md5($current_slug) === $slug_hash);

			if ($slug_rewritable) {
				$new_slug = sanitize_title($final['title']);

				if ($new_slug !== '') {
					$update_args['post_name'] = $new_slug;
				}
			}
		}

		wp_update_post($update_args);

		update_post_meta($target_id, 'rank_math_title', $final['meta_title']);
		update_post_meta($target_id, 'rank_math_description', $final['meta_description']);

		$new_snapshot = $snapshot;
		foreach (self::TEXTUAL_KEYS as $key) {
			if ($rewritable[$key]) {
				$new_snapshot[$key] = md5($final[$key]);
			}
		}
		if (isset($update_args['post_name'])) {
			$new_snapshot['_slug'] = md5(get_post($target_id)->post_name);
		}

		update_post_meta($target_id, self::SNAPSHOT_META, $new_snapshot);
		update_post_meta($target_id, self::STATUS_META, Momentum_Property_Translation_Service::STATUS_COMPLETE);
		update_post_meta($this->source_id, self::SOURCE_HASH_META, $source_hash);
		$this->log_translation_event($target_id, $char_count, $duration_ms, 'ok', Momentum_Property_Translation_Service::STATUS_COMPLETE, $is_new, null);
	}

	private function log_translation_event(int $target_id, int $char_count, int $duration_ms, string $result, string $status, bool $is_new, ?string $error_message): void
	{
		if (! function_exists('momentum_log_translation_event')) {
			return;
		}

		momentum_log_translation_event([
			'es_post_id'    => $this->source_id,
			'en_post_id'    => $target_id,
			'post_type'     => 'post',
			'char_count'    => $char_count,
			'duration_ms'   => $duration_ms,
			'result'        => $result,
			'status'        => $status,
			'provider'      => 'deepl',
			'is_new'        => $is_new,
			'error_message' => $error_message,
		]);
	}

	private function error_message_for_type(string $error_type): string
	{
		switch ($error_type) {
			case 'quota':
				return 'DeepL respondió que la cuota de caracteres está agotada.';
			case 'auth':
				return 'DeepL rechazó la API key configurada (no válida o vacía).';
			case 'network':
				return 'Fallo de red o del servidor de DeepL tras los reintentos.';
			case 'invalid_response':
				return 'DeepL devolvió una respuesta inesperada o incompleta.';
			default:
				return 'Error desconocido al traducir.';
		}
	}

	/**
	 * Espeja el estado de publicación (publicado ⇄ borrador) del artículo en
	 * español hacia su traducción, siempre -ya NO depende del interruptor
	 * "Publicar traducciones automáticamente"-. Mismo criterio y mismo
	 * cambio que Momentum_Property_Translation_Service::sync_publish_status(),
	 * ver el comentario ahí para el porqué.
	 */
	private function sync_publish_status(int $target_id): void
	{
		$source = get_post($this->source_id);
		$target = get_post($target_id);

		if (! $source instanceof WP_Post || ! $target instanceof WP_Post) {
			return;
		}

		if ($source->post_status === 'publish') {
			$status = (string) get_post_meta($target_id, self::STATUS_META, true);

			if (! in_array($status, [Momentum_Property_Translation_Service::STATUS_COMPLETE, Momentum_Property_Translation_Service::STATUS_MANUAL], true)) {
				return;
			}

			if (trim($target->post_title) === '' || trim($target->post_content) === '') {
				return;
			}

			if ($target->post_status !== 'publish') {
				wp_update_post(['ID' => $target_id, 'post_status' => 'publish']);
			}

			return;
		}

		if ($target->post_status === 'publish') {
			wp_update_post(['ID' => $target_id, 'post_status' => 'draft']);
		}
	}
}

/**
 * Columna "Traducción EN" en el listado nativo de wp-admin → Entradas, con
 * el mismo badge de color que ya usa momentum_render_translation_status_column()
 * en inc/cpt/propiedades.php (compartida por Propiedades/Terrenos, que usan
 * la MISMA meta key de estado). Se duplica aquí en vez de generalizar esa
 * función porque el Blog usa una meta key de estado distinta
 * (Momentum_Blog_Translation_Service::status_meta_key()) y no vale la pena
 * tocar una función ya en producción usada por dos CPT críticos para una
 * mejora secundaria de un tercero.
 */
function momentum_blog_admin_columns($columns)
{
	if (momentum_is_multilingual()) {
		$columns['momentum_translation'] = 'Traducción EN';
	}

	return $columns;
}
add_filter('manage_post_posts_columns', 'momentum_blog_admin_columns');

function momentum_blog_admin_column_content($column, $post_id)
{
	if ($column === 'momentum_translation') {
		momentum_render_blog_translation_status_column($post_id);
	}
}
add_action('manage_post_posts_custom_column', 'momentum_blog_admin_column_content', 10, 2);

function momentum_render_blog_translation_status_column(int $post_id): void
{
	if (! momentum_is_multilingual()) {
		echo '—';

		return;
	}

	$translation_id = momentum_find_translation($post_id, 'en');

	if (! $translation_id) {
		echo '—';

		return;
	}

	$status = (string) get_post_meta($translation_id, Momentum_Blog_Translation_Service::status_meta_key(), true);

	$colors = [
		'complete'       => ['#e3f5ee', '#146c48'],
		'manual'         => ['#e8effa', '#1c4f9c'],
		'translating'    => ['#fff6e5', '#8a5a00'],
		'error'          => ['#fdecea', '#a52c1e'],
		'quota_exceeded' => ['#fdecea', '#a52c1e'],
	];
	[$bg, $color] = $colors[$status] ?? ['#f1f1f1', '#555'];

	printf(
		'<span style="display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;background:%s;color:%s;">%s</span>',
		esc_attr($bg),
		esc_attr($color),
		esc_html(Momentum_Blog_Translation_Service::status_label($status))
	);
}
