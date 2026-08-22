<?php
/**
 * Módulo Propiedades: reutiliza el CPT 'propiedades' y su esquema de meta
 * definidos en el tema (wp-content/themes/momentum/inc/cpt/propiedades.php).
 * No se registra el CPT de nuevo ni se duplica su esquema.
 *
 * Importante: el guardado de los campos meta (precio, tipo_operacion, etc.)
 * NO se reimplementa aquí. El formulario usa los mismos nombres de campo que
 * el metabox nativo del tema (momentum_precio, momentum_tipo_operacion, ...)
 * y el mismo nonce ('momentum_propiedad_meta_save'), de modo que la función
 * momentum_save_propiedad_meta() -ya enganchada a save_post_propiedades en
 * el tema- guarda los meta automáticamente cuando se llama a
 * wp_insert_post()/wp_update_post(). Así se reutiliza el esquema existente
 * sin duplicarlo.
 */

if (! defined('ABSPATH')) {
	exit;
}

class Momentum_Admin_Properties
{
	const POST_TYPE = 'propiedades';

	const SAVE_NONCE_ACTION  = 'momentum_admin_save_property';
	const SAVE_NONCE_FIELD   = 'momentum_admin_property_nonce';
	const TRASH_NONCE_ACTION = 'momentum_admin_trash_property';
	const TRASH_NONCE_FIELD  = 'momentum_admin_trash_nonce';

	private static function base_url(): string
	{
		return home_url('/admin/propiedades/');
	}

	private static function cpt_available(): bool
	{
		return function_exists('momentum_propiedad_meta_schema') && post_type_exists(self::POST_TYPE);
	}

	/**
	 * Procesa acciones POST (guardar / papelera) ANTES de que se imprima
	 * cualquier salida, para poder redirigir (Post/Redirect/Get) sin
	 * conflictos de cabeceras.
	 */
	public static function maybe_handle_actions(): void
	{
		if (! self::cpt_available() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
			return;
		}

		if (isset($_POST['momentum_admin_property_submit'])) {
			self::handle_save();
		} elseif (isset($_POST['momentum_admin_property_trash'])) {
			self::handle_trash();
		}
	}

	public static function render(): void
	{
		if (! self::cpt_available()) {
			echo '<div class="momentum-admin-notice momentum-admin-notice--error"><p>';
			esc_html_e('El tipo de contenido "propiedades" no está disponible. Verifica que el tema Momentum esté activo.', 'momentum-admin');
			echo '</p></div>';

			return;
		}

		$accion = isset($_GET['accion']) ? sanitize_key(wp_unslash($_GET['accion'])) : 'listar';

		if ($accion === 'nueva') {
			self::start_new_draft();

			return;
		}

		if ($accion === 'editar') {
			self::render_form(isset($_GET['id']) ? absint($_GET['id']) : 0);

			return;
		}

		self::render_list();
	}

	/**
	 * Antes, "Nueva propiedad" mostraba el formulario sin crear el post
	 * hasta el primer guardado. Eso dejaba sin post_id real cualquier
	 * imagen subida ANTES de guardar por primera vez: el filtro upload_dir
	 * del plugin momentum-property-media-organizer no tenía nada que
	 * resolver todavía, así que esas imágenes caían en año/mes en vez de
	 * propiedades/{codigo}/ -detectado en pruebas QA: crear una propiedad y
	 * subir su imagen destacada + galería en la misma sesión, antes de
	 * guardar, las dejaba en wp-content/uploads/2026/08/ en vez de
	 * propiedades/MC-TEST-001/-.
	 *
	 * Se replica aquí el mismo patrón que WordPress ya usa de fábrica en
	 * post-new.php: crear un auto-draft y redirigir a la pantalla de
	 * edición real, para que exista un ID de verdad desde el primer
	 * momento en que se abre el selector de medios. Los auto-draft
	 * abandonados (el usuario nunca guarda) los limpia WordPress solo
	 * -cron nativo wp_scheduled_auto_draft_delete, purga a los 7 días-,
	 * sin necesidad de nada adicional aquí.
	 */
	private static function start_new_draft(): void
	{
		$redirect_base = self::base_url();

		if (! current_user_can(Momentum_Admin_Permissions::CAPABILITY)) {
			wp_safe_redirect(add_query_arg('error', 'permisos', $redirect_base));
			exit;
		}

		$new_id = wp_insert_post([
			'post_type'   => self::POST_TYPE,
			'post_status' => 'auto-draft',
			'post_title'  => __('Auto Draft'),
		], true);

		if (is_wp_error($new_id)) {
			wp_safe_redirect(add_query_arg('error', 'guardado', $redirect_base));
			exit;
		}

		wp_safe_redirect(add_query_arg(['accion' => 'editar', 'id' => (int) $new_id], $redirect_base));
		exit;
	}

	// -----------------------------------------------------------------
	// Guardar / Papelera
	// -----------------------------------------------------------------

	private static function handle_save(): void
	{
		$redirect_base = self::base_url();

		if (
			! isset($_POST[self::SAVE_NONCE_FIELD])
			|| ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::SAVE_NONCE_FIELD])), self::SAVE_NONCE_ACTION)
		) {
			wp_safe_redirect(add_query_arg('error', 'nonce', $redirect_base));
			exit;
		}

		if (! current_user_can(Momentum_Admin_Permissions::CAPABILITY)) {
			wp_safe_redirect(add_query_arg('error', 'permisos', $redirect_base));
			exit;
		}

		$post_id = isset($_POST['momentum_property_id']) ? absint($_POST['momentum_property_id']) : 0;
		$is_new  = $post_id === 0;
		// Ver start_new_draft(): con el flujo actual, $post_id ya viene de un
		// auto-draft real (nunca 0) en el caso normal de "primer guardado" de
		// una ficha nueva. Se trata igual que $is_new para el mensaje de
		// redirección y para la sincronización de traducción -sigue siendo,
		// en la práctica, la primera vez que esta ficha se guarda con datos-.
		$was_auto_draft = false;

		if (! $is_new) {
			if (get_post_type($post_id) !== self::POST_TYPE || ! current_user_can('edit_post', $post_id)) {
				wp_safe_redirect(add_query_arg('error', 'permisos', $redirect_base));
				exit;
			}

			$existing_post  = get_post($post_id);
			$was_auto_draft = $existing_post instanceof WP_Post && $existing_post->post_status === 'auto-draft';
		}

		$title  = isset($_POST['momentum_property_title']) ? sanitize_text_field(wp_unslash($_POST['momentum_property_title'])) : '';
		$status = (isset($_POST['momentum_property_status']) && in_array($_POST['momentum_property_status'], ['publish', 'draft'], true))
			? sanitize_key($_POST['momentum_property_status'])
			: 'draft';
		// momentum_normalize_line_endings(): un <textarea> real de navegador
		// envía '\r\n', mientras que Momentum_Property_Translation_Service
		// escribe '\n' puro -sin normalizar, un segundo guardado normal desde
		// este formulario dejaba el contenido con bytes distintos a los que
		// esa huella (_momentum_sync_snapshot) esperaba, y el campo quedaba
		// protegido para siempre como si se hubiera editado a mano. Ver
		// wp-content/themes/momentum/inc/cpt/propiedades.php.
		$content = isset($_POST['momentum_property_content'])
			? wp_kses_post(momentum_normalize_line_endings(wp_unslash($_POST['momentum_property_content'])))
			: '';

		$form_target = $is_new
			? add_query_arg('accion', 'nueva', $redirect_base)
			: add_query_arg(['accion' => 'editar', 'id' => $post_id], $redirect_base);

		if ($title === '') {
			wp_safe_redirect(add_query_arg('error', 'titulo', $form_target));
			exit;
		}

		$taxes_usd_raw = isset($_POST['momentum_taxes_usd']) ? trim((string) wp_unslash($_POST['momentum_taxes_usd'])) : '';

		if ($taxes_usd_raw !== '' && (! is_numeric($taxes_usd_raw) || (float) $taxes_usd_raw < 0)) {
			wp_safe_redirect(add_query_arg('error', 'taxes_usd', $form_target));
			exit;
		}

		$video_fields = ['momentum_video_url' => 'video', 'momentum_video_url_en' => 'video_en'];

		foreach ($video_fields as $post_key => $error_code) {
			$raw = isset($_POST[$post_key]) ? trim((string) wp_unslash($_POST[$post_key])) : '';

			if ($raw !== '' && function_exists('momentum_sanitize_video_url') && momentum_sanitize_video_url($raw) === '') {
				wp_safe_redirect(add_query_arg('error', $error_code, $form_target));
				exit;
			}
		}

		$postarr = [
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => $status,
			'post_type'    => self::POST_TYPE,
		];

		// La imagen destacada se guarda de forma procedural MÁS ABAJO, después
		// de wp_insert_post()/wp_update_post() -que ya dispara la sincronización
		// de traducción vía el hook 'save_post_propiedades'-. Sin diferir ese
		// disparo automático, la traducción copiaría la imagen ANTERIOR (o
		// ninguna, en una ficha nueva) en vez de la que se está guardando en
		// este mismo formulario. Ver Momentum_Property_Translation_Service::$external_trigger_pending.
		if (class_exists('Momentum_Property_Translation_Service')) {
			Momentum_Property_Translation_Service::defer_auto_trigger();
		}

		if ($is_new) {
			$result = wp_insert_post($postarr, true);
		} else {
			$postarr['ID'] = $post_id;
			$result        = wp_update_post($postarr, true);
		}

		if (is_wp_error($result) || ! $result) {
			wp_safe_redirect(add_query_arg('error', 'guardado', $redirect_base));
			exit;
		}

		$post_id = (int) $result;

		// La meta (precio, tipo_operacion, ubicación, galería, etc.) la guarda
		// automáticamente momentum_save_propiedad_meta() del tema, enganchada
		// a save_post_propiedades, porque el formulario usa sus mismos campos.

		self::save_featured_image($post_id);

		// El hook de 'save_post_propiedades' que limpia la destacada de la
		// galería (momentum_strip_featured_image_from_gallery(), en
		// inc/cpt/propiedades.php) ya corrió arriba, dentro de
		// wp_insert_post()/wp_update_post(), pero todavía con la destacada
		// ANTERIOR -set_post_thumbnail() de la línea de arriba corre después
		// de ese hook, no antes-, así que se repite aquí ahora que la
		// destacada nueva ya quedó guardada.
		momentum_strip_featured_image_from_gallery($post_id);

		// El código (codigo_propiedad) ya quedó guardado por
		// momentum_save_propiedad_meta() dentro del wp_insert_post()/
		// wp_update_post() de arriba. Si esta ficha subió imágenes ANTES de
		// tener código (ver Momentum_Admin_Properties::start_new_draft()),
		// esas imágenes cayeron en el respaldo propiedades/pendiente-{ID}/;
		// ahora que el código ya se conoce, se promueven a su carpeta final
		// propiedades/{CODIGO}/. No-op si el plugin no está activo o si no
		// hay nada pendiente que promover.
		if (class_exists('Momentum_PMO_Upload_Directory')) {
			Momentum_PMO_Upload_Directory::promote_pending_uploads($post_id);
		}

		$treated_as_new = $is_new || $was_auto_draft;

		// Ahora sí, con la imagen destacada ya guardada: dispara la
		// sincronización de traducción explícitamente (mismo método que ya usa
		// el hook normal para guardados nativos de wp-admin) y reactiva ese
		// hook para la siguiente petición.
		if (class_exists('Momentum_Property_Translation_Service')) {
			Momentum_Property_Translation_Service::handle_save($post_id, get_post($post_id), ! $treated_as_new);
			Momentum_Property_Translation_Service::resume_auto_trigger();
		}

		wp_safe_redirect(add_query_arg(
			['accion' => 'editar', 'id' => $post_id, 'mensaje' => $treated_as_new ? 'creada' : 'actualizada'],
			$redirect_base
		));
		exit;
	}

	/**
	 * La imagen destacada no tiene un equivalente reutilizable en el tema
	 * (el editor nativo la gestiona vía AJAX del metabox core), así que se
	 * guarda aquí con la función nativa set_post_thumbnail().
	 */
	private static function save_featured_image(int $post_id): void
	{
		$featured_id = isset($_POST['momentum_property_featured_image'])
			? absint($_POST['momentum_property_featured_image'])
			: 0;

		if ($featured_id > 0 && wp_attachment_is_image($featured_id)) {
			set_post_thumbnail($post_id, $featured_id);
		} else {
			delete_post_thumbnail($post_id);
		}
	}

	private static function handle_trash(): void
	{
		$redirect_base = self::base_url();
		$post_id       = isset($_POST['momentum_property_id']) ? absint($_POST['momentum_property_id']) : 0;

		$nonce_ok = $post_id > 0
			&& isset($_POST[self::TRASH_NONCE_FIELD])
			&& wp_verify_nonce(
				sanitize_text_field(wp_unslash($_POST[self::TRASH_NONCE_FIELD])),
				self::TRASH_NONCE_ACTION . '_' . $post_id
			);

		if (
			! $nonce_ok
			|| get_post_type($post_id) !== self::POST_TYPE
			|| ! current_user_can('delete_post', $post_id)
		) {
			wp_safe_redirect(add_query_arg('error', 'papelera', $redirect_base));
			exit;
		}

		wp_trash_post($post_id);

		wp_safe_redirect(add_query_arg('mensaje', 'papelera', $redirect_base));
		exit;
	}

	// -----------------------------------------------------------------
	// Listado
	// -----------------------------------------------------------------

	private static function query_properties(): array
	{
		$schema = momentum_propiedad_meta_schema();

		$search         = isset($_GET['buscar']) ? sanitize_text_field(wp_unslash($_GET['buscar'])) : '';
		$estado         = isset($_GET['estado']) ? sanitize_text_field(wp_unslash($_GET['estado'])) : '';
		$tipo_operacion = isset($_GET['tipo_operacion']) ? sanitize_text_field(wp_unslash($_GET['tipo_operacion'])) : '';
		$paged          = isset($_GET['pg']) ? max(1, absint($_GET['pg'])) : 1;

		// Whitelist contra las opciones reales del esquema (evita valores arbitrarios).
		if ($estado !== '' && ! in_array($estado, $schema['estado']['options'], true)) {
			$estado = '';
		}
		if ($tipo_operacion !== '' && ! in_array($tipo_operacion, $schema['tipo_operacion']['options'], true)) {
			$tipo_operacion = '';
		}

		$meta_query = ['relation' => 'AND'];

		if ($estado !== '') {
			$meta_query[] = ['key' => 'estado', 'value' => $estado, 'compare' => '='];
		}
		if ($tipo_operacion !== '') {
			$meta_query[] = ['key' => 'tipo_operacion', 'value' => $tipo_operacion, 'compare' => '='];
		}

		$query_args = [
			'post_type'      => self::POST_TYPE,
			'post_status'    => ['publish', 'draft', 'pending'],
			'posts_per_page' => 20,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		if (count($meta_query) > 1) {
			$query_args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery
		}

		$where_cb = null;

		if ($search !== '') {
			$where_cb = static function (string $where) use ($search): string {
				global $wpdb;

				$like = '%' . $wpdb->esc_like($search) . '%';

				$where .= $wpdb->prepare(
					" AND ( {$wpdb->posts}.post_title LIKE %s OR EXISTS (
						SELECT 1 FROM {$wpdb->postmeta} pm
						WHERE pm.post_id = {$wpdb->posts}.ID
						AND pm.meta_key IN ('codigo_propiedad')
						AND pm.meta_value LIKE %s
					) )",
					$like,
					$like
				);

				return $where;
			};

			add_filter('posts_where', $where_cb);
		}

		$query = new WP_Query($query_args);

		if ($where_cb) {
			remove_filter('posts_where', $where_cb);
		}

		return [
			'query'          => $query,
			'search'         => $search,
			'estado'         => $estado,
			'tipo_operacion' => $tipo_operacion,
			'paged'          => $paged,
		];
	}

	private static function render_list(): void
	{
		$result = self::query_properties();
		$schema = momentum_propiedad_meta_schema();

		include MOMENTUM_ADMIN_PATH . 'templates/properties/list.php';
	}

	// -----------------------------------------------------------------
	// Formulario (crear / editar)
	// -----------------------------------------------------------------

	private static function render_form(int $post_id): void
	{
		$post = $post_id ? get_post($post_id) : null;

		if ($post_id && (! $post || $post->post_type !== self::POST_TYPE)) {
			echo '<div class="momentum-admin-notice momentum-admin-notice--error"><p>';
			esc_html_e('Propiedad no encontrada.', 'momentum-admin');
			echo '</p></div>';

			return;
		}

		if ($post_id && ! current_user_can('edit_post', $post_id)) {
			wp_die(
				esc_html__('No tienes permiso para editar esta propiedad.', 'momentum-admin'),
				esc_html__('Acceso denegado', 'momentum-admin'),
				['response' => 403]
			);
		}

		$schema = momentum_propiedad_meta_schema();
		$values = [];

		foreach ($schema as $key => $field) {
			$values[$key] = $post_id ? get_post_meta($post_id, $key, true) : '';
		}

		$title       = $post ? $post->post_title : '';
		$content     = $post ? $post->post_content : '';
		$status      = $post ? $post->post_status : 'draft';
		$featured_id = $post_id ? (int) get_post_thumbnail_id($post_id) : 0;
		$gallery_ids = $post_id && function_exists('momentum_get_galeria_ids') ? momentum_get_galeria_ids($post_id) : [];

		include MOMENTUM_ADMIN_PATH . 'templates/properties/form.php';
	}
}
