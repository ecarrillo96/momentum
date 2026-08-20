<?php
/**
 * Único servicio responsable de la traducción automática ES→(otros idiomas)
 * de Propiedades/Terrenos: crear la traducción, enlazarla con Polylang,
 * sincronizar los campos que no requieren traducción, traducir los que sí
 * (protegiendo cualquier edición manual), decidir si se publica, y
 * mantener el estado (pending/complete/manual/error) visible en el admin.
 *
 * Los hooks en functions.php solo delegan aquí -toda la lógica de negocio
 * vive en esta clase, nada repartido en otros archivos-. Depende únicamente
 * de los helpers de inc/language.php (nunca llama pll_*() directo) y de la
 * interfaz Momentum_Translation_Provider (inc/translation-provider.php);
 * ninguno de los dos depende de vuelta de esta clase.
 */

if (! defined('ABSPATH')) {
	exit;
}

class Momentum_Property_Translation_Service
{
	private const STATUS_META      = '_momentum_translation_status';
	private const SOURCE_HASH_META = '_momentum_source_hash';
	private const SNAPSHOT_META    = '_momentum_sync_snapshot';

	/**
	 * Los 7 estados estandarizados de una traducción -ninguna otra parte del
	 * proyecto debe inventar un estado distinto para el mismo concepto-.
	 */
	public const STATUS_PENDING        = 'pending';
	public const STATUS_TRANSLATING    = 'translating';
	public const STATUS_COMPLETE       = 'complete';
	public const STATUS_MANUAL         = 'manual';
	public const STATUS_ERROR          = 'error';
	public const STATUS_QUOTA_EXCEEDED = 'quota_exceeded';
	public const STATUS_NOT_CONFIGURED = 'not_configured';

	private const TEXTUAL_KEYS = ['title', 'description', 'excerpt', 'caracteristicas', 'meta_title', 'meta_description'];

	private const BRAND_NAME = 'Momentum Cocoyoc';

	private static bool $syncing = false;

	/**
	 * Bandera para el problema de temporización del guardado desde
	 * /admin/propiedades y /admin/terrenos: Momentum_Admin_Properties::handle_save()
	 * y Momentum_Admin_Terrenos::handle_save() guardan la imagen destacada
	 * (self::save_featured_image()) DESPUÉS de wp_insert_post()/wp_update_post()
	 * -de forma procedural, no vía hook-. Si el hook automático de este
	 * servicio (enganchado a 'save_post_propiedades'/'save_post_terrenos')
	 * corriera durante ese wp_insert_post()/wp_update_post(), sync_image()
	 * copiaría la imagen destacada ANTERIOR (o ninguna, en una ficha nueva)
	 * en vez de la que se acaba de seleccionar en el mismo formulario -bug
	 * real detectado en la ficha MC-124: una propiedad nueva creada con
	 * imagen quedaba sin imagen en su traducción-.
	 *
	 * Momentum_Admin_Properties/Terrenos activan esta bandera justo antes de
	 * guardar el post, y llaman a handle_save() explícitamente ellos mismos
	 * una vez que la imagen destacada ya quedó guardada -mismo patrón que
	 * Momentum_Blog_Translation_Service::defer_auto_trigger() ya usa para el
	 * mismo problema en el Blog-. Para guardados nativos de wp-admin (donde
	 * la imagen destacada se guarda vía AJAX del metabox core ANTES del
	 * guardado principal, sin este problema de orden), esta bandera nunca se
	 * activa y el hook automático funciona sin cambios.
	 */
	private static bool $external_trigger_pending = false;

	private int $source_id;
	private string $post_type;
	private array $schema;

	/**
	 * IDs de traducción creadas en esta misma ejecución de run() -para que
	 * sync_textual_fields() sepa si el intento de traducción corresponde a
	 * una ficha nueva o a una actualización, y lo registre así en el
	 * historial (momentum_log_translation_event(), campo 'is_new')-.
	 */
	private array $newly_created_translation_ids = [];

	public static function defer_auto_trigger(): void
	{
		self::$external_trigger_pending = true;
	}

	public static function resume_auto_trigger(): void
	{
		self::$external_trigger_pending = false;
	}

	/**
	 * true mientras run() está en ejecución -incluye el momento en que
	 * sync_textual_fields() llama wp_update_post() sobre la TRADUCCIÓN, lo
	 * que dispara 'save_post_propiedades'/'save_post_terrenos' otra vez sobre
	 * ese post-. Consultado por momentum_save_propiedad_meta()/
	 * momentum_save_terreno_meta() (inc/cpt/*.php) para no reprocesar
	 * $_POST -que todavía contiene los campos del formulario de la ficha en
	 * ESPAÑOL que originó la sincronización- como si fuera un guardado
	 * legítimo de la traducción. Sin esta guarda, ese reprocesamiento
	 * sobreescribía 'video_url' de la traducción con el valor en español
	 * justo después de que sync_shared_fields() ya lo hubiera puesto en el
	 * valor correcto en inglés -bug real detectado en la ficha MC-124-.
	 */
	public static function is_syncing(): bool
	{
		return self::$syncing;
	}

	/**
	 * Enganchada a 'save_post_propiedades'/'save_post_terrenos' (ver
	 * functions.php). No llama a handle_save() si el guardado ya va a
	 * disparar la sincronización de forma explícita (ver defer_auto_trigger()
	 * arriba) -evita sincronizar con la imagen destacada todavía sin guardar-.
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
	 * explícitamente por Momentum_Admin_Properties/Terrenos::handle_save()
	 * (plugin momentum-admin) después de guardar la imagen destacada, por
	 * retry_translation(), y por Momentum_Admin_CLI_Migrate_Translations-.
	 * Aplica todas las guardas y, si corresponde, delega el trabajo real a
	 * una instancia de esta clase.
	 */
	public static function handle_save(int $post_id, WP_Post $post, bool $update): void
	{
		// Sin esta guarda, el wp_insert_post() de ensure_translation() dispara
		// save_post_{tipo} otra vez y este método se re-entraría sobre sí mismo.
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
			// Pasa con fichas creadas desde el panel /admin/propiedades o
			// /admin/terrenos, que no pasan por el flujo estándar de Polylang
			// en post-new.php.
			$lang = momentum_default_language();
			momentum_set_post_language($post_id, $lang);
		}

		// Si esto NO es el idioma por defecto, es la propia traducción
		// guardándose (o un tercer idioma futuro) -nunca origina una
		// sincronización "hacia adelante" desde aquí-.
		if ($lang !== momentum_default_language()) {
			return;
		}

		self::$syncing = true;

		try {
			(new self($post_id, $post->post_type))->run();
		} finally {
			self::$syncing = false;
		}
	}

	/**
	 * Label legible para _momentum_translation_status, usado por la columna
	 * "Traducción EN" en el listado nativo de wp-admin (inc/cpt/propiedades.php,
	 * inc/cpt/terrenos.php) -mismo criterio que el resto de esas columnas,
	 * que tampoco están envueltas en __() por ser texto exclusivo de wp-admin-.
	 */
	public static function status_label(string $status): string
	{
		switch ($status) {
			case self::STATUS_COMPLETE:
				return 'Traducida';
			case self::STATUS_MANUAL:
				return 'Editada a mano';
			case self::STATUS_ERROR:
				return 'Error de traducción';
			case self::STATUS_QUOTA_EXCEEDED:
				return 'Cuota de DeepL agotada';
			case self::STATUS_NOT_CONFIGURED:
				return 'Proveedor no configurado';
			case self::STATUS_TRANSLATING:
				return 'Traduciendo…';
			case self::STATUS_PENDING:
			default:
				return 'Pendiente';
		}
	}

	public static function status_meta_key(): string
	{
		return self::STATUS_META;
	}

	/**
	 * Conteo de traducciones EN por estado, agrupado por tipo de contenido
	 * -para las tarjetas de "Actividad" en Momentum → Traducciones-. Recorre
	 * las fichas en el idioma por defecto y su traducción (si existe),
	 * mismo criterio que usa el propio servicio para decidir qué es "origen"
	 * y qué es "traducción".
	 *
	 * @return array<string, array<string, int>> Ej. ['propiedades' => ['complete' => 10, 'pending' => 2, ...], 'terrenos' => [...]]
	 */
	public static function get_status_counts(): array
	{
		$all_statuses = [
			self::STATUS_PENDING,
			self::STATUS_TRANSLATING,
			self::STATUS_COMPLETE,
			self::STATUS_MANUAL,
			self::STATUS_ERROR,
			self::STATUS_QUOTA_EXCEEDED,
			self::STATUS_NOT_CONFIGURED,
		];

		$result = [];

		foreach (['propiedades', 'terrenos'] as $post_type) {
			$counts = array_fill_keys($all_statuses, 0);
			$counts['no_translation'] = 0;
			$counts['total_source']   = 0;

			if (! momentum_is_multilingual()) {
				$result[$post_type] = $counts;

				continue;
			}

			$default_lang = momentum_default_language();
			// Toma el primer idioma secundario -hoy siempre 'en', el único
			// configurado-. Si algún día hay más de uno, esta tarjeta
			// necesitaría una columna por idioma; se mantiene simple mientras
			// solo exista un segundo idioma real.
			$secondary_languages = momentum_get_secondary_languages();
			$target_lang         = $secondary_languages[0] ?? '';

			$source_ids = get_posts([
				'post_type'   => $post_type,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			]);

			foreach ($source_ids as $id) {
				if (momentum_get_post_language($id) !== $default_lang) {
					continue;
				}

				$counts['total_source']++;
				$translation_id = $target_lang !== '' ? momentum_find_translation($id, $target_lang) : 0;

				if (! $translation_id) {
					$counts['no_translation']++;

					continue;
				}

				$status = (string) get_post_meta($translation_id, self::STATUS_META, true);
				$counts[$status] = ($counts[$status] ?? 0) + 1;
			}

			$result[$post_type] = $counts;
		}

		return $result;
	}

	/**
	 * true si en este momento hay todo lo necesario para intentar una
	 * traducción real: Polylang multi-idioma, un proveedor configurado, y
	 * cuota disponible en DeepL. Lo consulta la pantalla de estadísticas
	 * (para decidir si mostrar el botón "Reintentar") y puede consultarlo
	 * cualquier otro código que necesite la misma respuesta sin repetir esta
	 * lógica.
	 */
	public static function is_translation_allowed(): bool
	{
		if (! momentum_is_multilingual()) {
			return false;
		}

		if (! function_exists('momentum_get_translation_provider') || momentum_get_translation_provider() === null) {
			return false;
		}

		return function_exists('momentum_translation_quota_available') && momentum_translation_quota_available();
	}

	/**
	 * Reintenta la traducción de una ficha en español ya guardada -usado por
	 * el botón "Reintentar" del historial en la pantalla de estadísticas-.
	 * No es una vía de traducción distinta: llama exactamente al mismo
	 * handle_save() que ya corre en cada guardado normal, así que hereda
	 * todas sus guardas (no duplica, no publica contenido incompleto,
	 * respeta el bloqueo por cuota, protege ediciones manuales).
	 *
	 * @return bool true si se pudo intentar (no garantiza éxito -consultar
	 *              el estado de la traducción después-), false si el post no
	 *              existe o no es del tipo esperado.
	 */
	public static function retry_translation(int $es_post_id): bool
	{
		$post = get_post($es_post_id);

		if (! $post instanceof WP_Post || ! in_array($post->post_type, ['propiedades', 'terrenos'], true)) {
			return false;
		}

		self::handle_save($es_post_id, $post, true);

		return true;
	}

	private function __construct(int $source_id, string $post_type)
	{
		$this->source_id = $source_id;
		$this->post_type = $post_type;
		$this->schema     = $post_type === 'terrenos' ? momentum_terreno_meta_schema() : momentum_propiedad_meta_schema();
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
	 * enlazada vía Polylang) si todavía no existe. Es la única vía de
	 * creación: si ya existe, nunca se vuelve a insertar -no hay duplicados-.
	 */
	private function ensure_translation(string $lang): int
	{
		$existing = momentum_find_translation($this->source_id, $lang);

		if ($existing) {
			return $existing;
		}

		$codigo        = (string) get_post_meta($this->source_id, 'codigo_propiedad', true);
		$fallback_slug = sanitize_title($codigo !== '' ? $codigo : $this->post_type . '-' . $this->source_id);

		// wp_insert_post() rechaza por defecto un post con título, contenido Y
		// extracto vacíos ('wp_insert_post_empty_content') — exactamente el
		// borrador vacío que se necesita crear aquí a propósito. Se permite
		// solo para esta inserción puntual, no de forma global.
		$allow_empty = static fn (): bool => false;
		add_filter('wp_insert_post_empty_content', $allow_empty);

		$new_id = wp_insert_post([
			'post_type'   => $this->post_type,
			'post_status' => 'draft',
			'post_title'  => '',
			'post_name'   => $fallback_slug,
		], true);

		remove_filter('wp_insert_post_empty_content', $allow_empty);

		if (is_wp_error($new_id)) {
			error_log('[Momentum Translation] no se pudo crear la traducción (' . $this->post_type . ' #' . $this->source_id . '): ' . $new_id->get_error_code());

			return 0;
		}

		$new_id = (int) $new_id;

		momentum_set_post_language($new_id, $lang);
		momentum_link_translations([
			momentum_default_language() => $this->source_id,
			$lang                       => $new_id,
		]);

		update_post_meta($new_id, self::STATUS_META, self::STATUS_PENDING);
		// El slug de respaldo cuenta como "nuestro" desde el inicio, para que
		// se reemplace por el slug basado en el título en cuanto haya traducción.
		update_post_meta($new_id, self::SNAPSHOT_META, ['_slug' => md5($fallback_slug)]);

		$this->newly_created_translation_ids[] = $new_id;

		return $new_id;
	}

	/**
	 * Campos que NO requieren traducción: se copian siempre, en cada
	 * guardado, sin condición -así se mantienen sincronizados-. Recorre el
	 * esquema real del CPT (momentum_propiedad_meta_schema()/
	 * momentum_terreno_meta_schema(), sin reescribirlo) y copia todo excepto
	 * 'caracteristicas' (textual, ver sync_textual_fields()) y los campos
	 * marcados 'deprecated' (no se usan en ningún lado).
	 */
	private function sync_shared_fields(int $target_id): void
	{
		$video_url_en = (string) get_post_meta($this->source_id, 'video_url_en', true);
		$video_url    = (string) get_post_meta($this->source_id, 'video_url', true);

		foreach ($this->schema as $key => $field) {
			if ($key === 'caracteristicas' || ! empty($field['deprecated'])) {
				continue;
			}

			if ($key === 'video_url_en') {
				// Campo de la ficha origen únicamente (guarda el video en
				// inglés que el editor haya capturado); la traducción no
				// necesita su propia copia de este campo.
				continue;
			}

			if ($key === 'video_url') {
				$this->sync_meta_value($target_id, 'video_url', $video_url_en !== '' ? $video_url_en : $video_url);
				continue;
			}

			$this->sync_meta_value($target_id, $key, get_post_meta($this->source_id, $key, true));
		}
	}

	/**
	 * update_post_meta($target_id, $key, '') pasa por el sanitize_callback
	 * registrado (register_post_meta()) y para los campos numéricos eso
	 * convierte '' en "0" -crea una fila de meta con valor 0 en la
	 * traducción aunque la ficha origen nunca haya tenido ese campo
	 * capturado-. No cambia nada visible (0 y "sin valor" se muestran
	 * igual en todos los templates), pero para reflejar con más fidelidad
	 * "este campo no está capturado" en ambos idiomas, se borra la meta en
	 * vez de escribir un valor vacío cuando el origen tampoco lo tiene.
	 */
	private function sync_meta_value(int $target_id, string $key, $value): void
	{
		if ($value === '' || $value === null) {
			delete_post_meta($target_id, $key);

			return;
		}

		update_post_meta($target_id, $key, $value);
	}

	/**
	 * Misma imagen destacada (mismo ID de adjunto) -no se duplican medios,
	 * Polylang con traducción de medios desactivada no lo requiere-.
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

	/**
	 * momentum_normalize_line_endings() sobre cada campo: un guardado real
	 * desde un <textarea> de navegador (panel propio o editor clásico
	 * nativo, usado por Terrenos) puede introducir '\r\n' donde este
	 * servicio siempre escribió '\n' puro. Sin normalizar AQUÍ -en el único
	 * punto que tanto calcula la huella de origen como decide si un campo
	 * de la traducción "sigue igual a lo último que este servicio escribió"-
	 * esa diferencia de bytes, invisible en pantalla, hacía que un campo
	 * nunca tocado a mano se marcara como editado y dejara de traducirse
	 * automáticamente para siempre -bug real detectado en pruebas QA-.
	 * Defensivo a propósito: además de sanear la entrada en los formularios
	 * (ver class-properties.php/class-terrenos.php), normalizar también acá
	 * cubre cualquier otra vía de guardado (editor nativo de bloques o
	 * clásico) sin depender de parchear cada una por separado.
	 */
	private function get_textual_fields(int $post_id): array
	{
		$post = get_post($post_id);

		return [
			'title'            => momentum_normalize_line_endings($post->post_title),
			'description'      => momentum_normalize_line_endings($post->post_content),
			'excerpt'          => momentum_normalize_line_endings($post->post_excerpt),
			'caracteristicas'  => momentum_normalize_line_endings((string) get_post_meta($post_id, 'caracteristicas', true)),
			'meta_title'       => momentum_normalize_line_endings((string) get_post_meta($post_id, 'rank_math_title', true)),
			'meta_description' => momentum_normalize_line_endings((string) get_post_meta($post_id, 'rank_math_description', true)),
		];
	}

	private function compute_source_hash(array $source_fields): string
	{
		return md5(implode("\x1f", $source_fields));
	}

	/**
	 * Nombre de zona, código de propiedad y la marca: se le pide al
	 * proveedor de traducción que los deje intactos (ver
	 * Momentum_Translation_Provider::translate_fields(), parámetro
	 * $protect_terms).
	 */
	private function get_protected_terms(): array
	{
		$zona_slug  = (string) get_post_meta($this->source_id, 'zona', true);
		$zona_label = $zona_slug !== '' && function_exists('momentum_get_zona_label') ? momentum_get_zona_label($zona_slug) : '';
		$codigo     = (string) get_post_meta($this->source_id, 'codigo_propiedad', true);

		return array_values(array_unique(array_filter(
			[$zona_label, $codigo, self::BRAND_NAME],
			static fn (string $term): bool => trim($term) !== ''
		)));
	}

	/**
	 * Traduce (o mantiene protegidos) título, descripción, extracto,
	 * características y SEO (Rank Math), y deriva el slug del título
	 * traducido. Nunca sobrescribe un campo que un editor cambió a mano en
	 * la traducción -se detecta comparando contra la huella de lo último
	 * que este servicio escribió ahí-.
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
			// Un editor ya escribió a mano los 6 campos: no queda nada por
			// traducir, esta traducción está completa "a mano".
			update_post_meta($target_id, self::STATUS_META, self::STATUS_MANUAL);

			return;
		}

		$status = (string) get_post_meta($target_id, self::STATUS_META, true);

		// Ya se tradujo con éxito este mismo contenido español y nada cambió:
		// no se vuelve a llamar la API (evita costos innecesarios). 'pending'
		// y 'error' sí reintentan siempre -son estados "todavía no logrado",
		// no "logrado y sin cambios"-, por si se acaba de configurar la API
		// key o de resolver un fallo transitorio.
		if ($status === self::STATUS_COMPLETE && $source_hash === $stored_hash) {
			return;
		}

		$provider = momentum_get_translation_provider();

		if ($provider === null) {
			update_post_meta($target_id, self::STATUS_META, self::STATUS_NOT_CONFIGURED);

			return;
		}

		$fields_to_translate = array_intersect_key($source_fields, array_filter($rewritable));
		$char_count           = array_sum(array_map('mb_strlen', $fields_to_translate));
		$is_new               = in_array($target_id, $this->newly_created_translation_ids, true);

		// Chequeo de cuota ANTES de llamar a DeepL (dato cacheado, ver
		// inc/translation-usage.php): si ya se sabe que no hay cuota, ni
		// siquiera se intenta la solicitud -se bloquea aquí, no en el
		// proveedor-. Queda registrado en el historial igual que un intento
		// real, para que sea visible en la pantalla de estadísticas.
		if (function_exists('momentum_translation_quota_available') && ! momentum_translation_quota_available()) {
			update_post_meta($target_id, self::STATUS_META, self::STATUS_QUOTA_EXCEEDED);
			$this->log_translation_event($target_id, 0, 0, 'blocked', self::STATUS_QUOTA_EXCEEDED, $is_new, 'Cuota de DeepL agotada; no se intentó la solicitud.');

			return;
		}

		update_post_meta($target_id, self::STATUS_META, self::STATUS_TRANSLATING);

		$started_at = microtime(true);

		$translated = $provider->translate_fields(
			$fields_to_translate,
			momentum_default_language(),
			$lang,
			$this->get_protected_terms()
		);

		$duration_ms = (int) round((microtime(true) - $started_at) * 1000);
		$result      = $translated === null ? 'error' : 'ok';

		// El consumo pudo haber cambiado con esta llamada -se invalida el
		// caché para que la próxima lectura (guardado siguiente o la
		// pantalla de estadísticas) sea fresca en vez de esperar los 15
		// minutos de caché normales.
		if (function_exists('momentum_invalidate_translation_usage_cache')) {
			momentum_invalidate_translation_usage_cache();
		}

		// Log estructurado y saneado: nunca el texto traducido/original, solo
		// metadatos (id, tipo, idioma, caracteres, duración, resultado) — para
		// auditoría y para que Momentum_Admin_CLI_Migrate_Translations pueda
		// acumular estadísticas reales sin reimplementar esta lógica.
		error_log(sprintf(
			'[Momentum Translation] post #%d (%s) es->%s: %s, %d caracteres, %dms',
			$this->source_id,
			$this->post_type,
			$lang,
			$result,
			$char_count,
			$duration_ms
		));

		/**
		 * Se dispara en cada llamada real al proveedor de traducción (nunca
		 * cuando se omite por hash sin cambios, por estar todo protegido a
		 * mano, o por bloqueo de cuota). $result: 'ok'|'error'.
		 */
		do_action('momentum_translation_attempted', $this->source_id, $this->post_type, $lang, $char_count, $duration_ms, $result);

		if ($translated === null) {
			$error_type   = $provider->get_last_error_type();
			$final_status = $error_type === 'quota' ? self::STATUS_QUOTA_EXCEEDED : self::STATUS_ERROR;

			update_post_meta($target_id, self::STATUS_META, $final_status);
			$this->log_translation_event($target_id, $char_count, $duration_ms, 'error', $final_status, $is_new, $this->error_message_for_type($error_type));

			return;
		}

		// Resultado final por campo: el recién traducido si era reescribible,
		// o el que ya tenía la traducción (protegido a mano) si no lo era.
		$final = [];
		foreach (self::TEXTUAL_KEYS as $key) {
			$final[$key] = $rewritable[$key] ? (string) ($translated[$key] ?? '') : $target_fields[$key];
		}

		// Nunca publicar/guardar como completa una ficha con título o
		// descripción vacíos, sin importar la opción de auto-publicar.
		if (trim($final['title']) === '' || trim($final['description']) === '') {
			update_post_meta($target_id, self::STATUS_META, self::STATUS_ERROR);
			$this->log_translation_event($target_id, $char_count, $duration_ms, 'error', self::STATUS_ERROR, $is_new, 'El proveedor devolvió título o descripción vacíos.');

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

		update_post_meta($target_id, 'caracteristicas', $final['caracteristicas']);
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
		update_post_meta($target_id, self::STATUS_META, self::STATUS_COMPLETE);
		update_post_meta($this->source_id, self::SOURCE_HASH_META, $source_hash);
		$this->log_translation_event($target_id, $char_count, $duration_ms, 'ok', self::STATUS_COMPLETE, $is_new, null);
	}

	/**
	 * Único punto de escritura al historial (inc/translation-log.php) desde
	 * el servicio -la pantalla de estadísticas solo lee de ahí, nunca
	 * inserta-.
	 */
	private function log_translation_event(int $target_id, int $char_count, int $duration_ms, string $result, string $status, bool $is_new, ?string $error_message): void
	{
		if (! function_exists('momentum_log_translation_event')) {
			return;
		}

		momentum_log_translation_event([
			'es_post_id'    => $this->source_id,
			'en_post_id'    => $target_id,
			'post_type'     => $this->post_type,
			'char_count'    => $char_count,
			'duration_ms'   => $duration_ms,
			'result'        => $result,
			'status'        => $status,
			'provider'      => 'deepl',
			'is_new'        => $is_new,
			'error_message' => $error_message,
		]);
	}

	/**
	 * Mensaje corto y saneado para el historial -nunca la excepción cruda
	 * del proveedor, nunca la API key-, a partir de la categoría de error
	 * que expone Momentum_Translation_Provider::get_last_error_type().
	 */
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
	 * Espeja el estado de publicación (publicado ⇄ borrador) de la ficha en
	 * español hacia su traducción, siempre -ya NO depende del interruptor
	 * "Publicar traducciones automáticamente" (momentum_auto_publish_translations,
	 * Admin > General). Ese interruptor controlaba esto mismo hasta que se
	 * detectó que publicar/despublicar la ficha en español (p. ej. al
	 * corregir un precio y volver a publicar un terreno) no se reflejaba en
	 * la traducción mientras estuviera apagado -el comportamiento esperado
	 * es que SIEMPRE se refleje, sin depender de configuración-.
	 *
	 * Publicar la traducción sigue exigiendo contenido listo (traducido o
	 * protegido a mano, nunca vacío) -verificación explícita, no basta con
	 * confiar en el status-: nunca se publica una traducción incompleta solo
	 * porque la fuente ya esté publicada. Despublicar, en cambio, no tiene
	 * ese requisito -ocultar contenido siempre es seguro-, así una ficha que
	 * el administrador vendió/despublicó en español nunca deja una versión
	 * en inglés pública y desactualizada.
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

			if (! in_array($status, [self::STATUS_COMPLETE, self::STATUS_MANUAL], true)) {
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
