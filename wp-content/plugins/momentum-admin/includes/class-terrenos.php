<?php
/**
 * Módulo Terrenos: reutiliza el CPT 'terrenos' y su esquema de meta
 * definidos en el tema (wp-content/themes/momentum/inc/cpt/terrenos.php).
 * No se registra el CPT de nuevo ni se duplica su esquema.
 *
 * Mismo patrón que Momentum_Admin_Properties (class-properties.php): el
 * guardado de la meta compartida (precio, zona, estado, codigo_propiedad,
 * etc.) NO se reimplementa aquí. El formulario usa los mismos nombres de
 * campo que el esquema del tema (momentum_precio, momentum_zona, ...) y el
 * nonce propio de Terrenos ('momentum_terreno_meta_save'), de modo que
 * momentum_save_terreno_meta() -ya enganchada a save_post_terrenos en el
 * tema- guarda esa meta automáticamente al llamar wp_insert_post()/
 * wp_update_post(). Igual que en Propiedades, 'codigo_propiedad' es texto
 * libre que el administrador escribe a mano (placeholder "MT-001" en
 * templates/terrenos/form.php); no se genera ni se bloquea su edición.
 */

if (! defined('ABSPATH')) {
	exit;
}

class Momentum_Admin_Terrenos
{
	const POST_TYPE = 'terrenos';

	const SAVE_NONCE_ACTION  = 'momentum_admin_save_terreno';
	const SAVE_NONCE_FIELD   = 'momentum_admin_terreno_nonce';
	const TRASH_NONCE_ACTION = 'momentum_admin_trash_terreno';
	const TRASH_NONCE_FIELD  = 'momentum_admin_terreno_trash_nonce';

	private static function base_url(): string
	{
		return home_url('/admin/terrenos/');
	}

	private static function cpt_available(): bool
	{
		return function_exists('momentum_terreno_meta_schema') && post_type_exists(self::POST_TYPE);
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

		if (isset($_POST['momentum_admin_terreno_submit'])) {
			self::handle_save();
		} elseif (isset($_POST['momentum_admin_terreno_trash'])) {
			self::handle_trash();
		}
	}

	public static function render(): void
	{
		if (! self::cpt_available()) {
			echo '<div class="momentum-admin-notice momentum-admin-notice--error"><p>';
			esc_html_e('El tipo de contenido "terrenos" no está disponible. Verifica que el tema Momentum esté activo.', 'momentum-admin');
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
	 * Ver Momentum_Admin_Properties::start_new_draft() -mismo problema y
	 * misma solución, replicada aquí porque Terrenos tiene su propio
	 * manejador de acciones: crear el auto-draft antes de mostrar el
	 * formulario "nueva", para que exista un post_id real desde el primer
	 * momento en que se abre el selector de medios y el filtro upload_dir
	 * de momentum-property-media-organizer tenga algo que resolver.
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

		$post_id = isset($_POST['momentum_terreno_id']) ? absint($_POST['momentum_terreno_id']) : 0;
		$is_new  = $post_id === 0;
		// Ver Momentum_Admin_Properties::handle_save() -mismo tratamiento-:
		// con start_new_draft(), $post_id ya viene de un auto-draft real en el
		// caso normal de "primer guardado" de una ficha nueva.
		$was_auto_draft = false;

		if (! $is_new) {
			if (get_post_type($post_id) !== self::POST_TYPE || ! current_user_can('edit_post', $post_id)) {
				wp_safe_redirect(add_query_arg('error', 'permisos', $redirect_base));
				exit;
			}

			$existing_post  = get_post($post_id);
			$was_auto_draft = $existing_post instanceof WP_Post && $existing_post->post_status === 'auto-draft';
		}

		$title   = isset($_POST['momentum_terreno_title']) ? sanitize_text_field(wp_unslash($_POST['momentum_terreno_title'])) : '';
		$status  = (isset($_POST['momentum_terreno_status']) && in_array($_POST['momentum_terreno_status'], ['publish', 'draft'], true))
			? sanitize_key($_POST['momentum_terreno_status'])
			: 'draft';
		// Ver Momentum_Admin_Properties::handle_save() -mismo motivo-:
		// normaliza '\r\n' de un <textarea> real a '\n' antes de guardar, para
		// no romper la huella de sincronización de traducción.
		$content = isset($_POST['momentum_terreno_content'])
			? wp_kses_post(momentum_normalize_line_endings(wp_unslash($_POST['momentum_terreno_content'])))
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

		// Ver Momentum_Admin_Properties::handle_save() -mismo motivo-:
		// WordPress no regenera el slug a partir del título en un
		// wp_update_post() sobre un post que ya tiene post_name, así que se
		// regenera aquí explícitamente cuando el título realmente cambió.
		if (! $is_new && $existing_post instanceof WP_Post && $existing_post->post_title !== $title) {
			$postarr['post_name'] = wp_unique_post_slug(sanitize_title($title), $post_id, $status, self::POST_TYPE, 0);
		}

		// La imagen destacada se guarda de forma procedural MÁS ABAJO, después
		// de wp_insert_post()/wp_update_post() -que ya dispara la sincronización
		// de traducción vía el hook 'save_post_terrenos'-. Sin diferir ese
		// disparo automático, la traducción copiaría la imagen ANTERIOR (o
		// ninguna, en una ficha nueva) en vez de la que se está guardando en
		// este mismo formulario. Ver Momentum_Property_Translation_Service::$external_trigger_pending
		// (mismo bug ya corregido para Propiedades, ver class-properties.php).
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

		// La meta compartida (precio, zona, estado, codigo_propiedad, taxes,
		// video, galería...) la guarda automáticamente
		// momentum_save_terreno_meta() del tema, enganchada a
		// save_post_terrenos, porque el formulario usa sus mismos campos.

		self::save_featured_image($post_id);

		// Ver Momentum_Admin_Properties::handle_save() -mismo motivo-: el
		// hook de 'save_post_terrenos' que limpia la destacada de la
		// galería ya corrió con la destacada ANTERIOR, así que se repite
		// aquí ahora que la nueva ya quedó guardada.
		momentum_strip_featured_image_from_gallery($post_id);

		// Ver Momentum_Admin_Properties::handle_save() -mismo mecanismo-:
		// promueve imágenes que hayan caído en terrenos/pendiente-{ID}/ hacia
		// terrenos/{CODIGO}/ ahora que el código ya quedó guardado.
		if (class_exists('Momentum_PMO_Upload_Directory')) {
			Momentum_PMO_Upload_Directory::promote_pending_uploads($post_id);
		}

		$treated_as_new = $is_new || $was_auto_draft;

		// Ahora sí, con la imagen destacada ya guardada: dispara la
		// sincronización de traducción explícitamente y reactiva el hook
		// normal para la siguiente petición.
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
	 * guarda aquí con la función nativa set_post_thumbnail(), igual que en
	 * Momentum_Admin_Properties::save_featured_image().
	 */
	private static function save_featured_image(int $post_id): void
	{
		$featured_id = isset($_POST['momentum_terreno_featured_image'])
			? absint($_POST['momentum_terreno_featured_image'])
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
		$post_id       = isset($_POST['momentum_terreno_id']) ? absint($_POST['momentum_terreno_id']) : 0;

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

		// Papelera, no borrado permanente: conserva la meta (incluyendo
		// codigo_propiedad) para que momentum_generate_next_terreno_code()
		// nunca reasigne un código ya emitido.
		wp_trash_post($post_id);

		wp_safe_redirect(add_query_arg('mensaje', 'papelera', $redirect_base));
		exit;
	}

	// -----------------------------------------------------------------
	// Listado
	// -----------------------------------------------------------------

	private static function query_terrenos(): array
	{
		$schema = momentum_terreno_meta_schema();

		$search = isset($_GET['buscar']) ? sanitize_text_field(wp_unslash($_GET['buscar'])) : '';
		$estado = isset($_GET['estado']) ? sanitize_text_field(wp_unslash($_GET['estado'])) : '';
		$zona   = isset($_GET['zona']) ? sanitize_text_field(wp_unslash($_GET['zona'])) : '';
		$paged  = isset($_GET['pg']) ? max(1, absint($_GET['pg'])) : 1;

		// Whitelist contra las opciones reales del esquema (evita valores arbitrarios).
		if ($estado !== '' && ! in_array($estado, $schema['estado']['options'], true)) {
			$estado = '';
		}
		if ($zona !== '' && ! isset($schema['zona']['options_map'][$zona])) {
			$zona = '';
		}

		$meta_query = ['relation' => 'AND'];

		if ($estado !== '') {
			$meta_query[] = ['key' => 'estado', 'value' => $estado, 'compare' => '='];
		}
		if ($zona !== '') {
			$meta_query[] = ['key' => 'zona', 'value' => $zona, 'compare' => '='];
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
			'query'  => $query,
			'search' => $search,
			'estado' => $estado,
			'zona'   => $zona,
			'paged'  => $paged,
		];
	}

	private static function render_list(): void
	{
		$result = self::query_terrenos();
		$schema = momentum_terreno_meta_schema();
		$total_registrados = self::count_registered();

		include MOMENTUM_ADMIN_PATH . 'templates/terrenos/list.php';
	}

	/**
	 * Total de terrenos registrados (publicados + borrador + pendientes,
	 * mismo alcance que query_terrenos()), sin importar filtros de búsqueda
	 * activos -para el contador del encabezado del listado-. Mismo criterio
	 * que Momentum_Admin_Properties::count_registered(): filtrado al idioma
	 * por defecto para no duplicar por la traducción en inglés.
	 */
	private static function count_registered(): int
	{
		if (! momentum_is_multilingual()) {
			$counts = wp_count_posts(self::POST_TYPE);

			return (int) ($counts->publish ?? 0) + (int) ($counts->draft ?? 0) + (int) ($counts->pending ?? 0);
		}

		$query = new WP_Query([
			'post_type'      => self::POST_TYPE,
			'post_status'    => ['publish', 'draft', 'pending'],
			'lang'           => momentum_default_language(),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
		]);

		return (int) $query->found_posts;
	}

	// -----------------------------------------------------------------
	// Formulario (crear / editar)
	// -----------------------------------------------------------------

	private static function render_form(int $post_id): void
	{
		$post = $post_id ? get_post($post_id) : null;

		if ($post_id && (! $post || $post->post_type !== self::POST_TYPE)) {
			echo '<div class="momentum-admin-notice momentum-admin-notice--error"><p>';
			esc_html_e('Terreno no encontrado.', 'momentum-admin');
			echo '</p></div>';

			return;
		}

		if ($post_id && ! current_user_can('edit_post', $post_id)) {
			wp_die(
				esc_html__('No tienes permiso para editar este terreno.', 'momentum-admin'),
				esc_html__('Acceso denegado', 'momentum-admin'),
				['response' => 403]
			);
		}

		$schema = momentum_terreno_meta_schema();
		$values = [];

		foreach ($schema as $key => $field) {
			$values[$key] = $post_id ? get_post_meta($post_id, $key, true) : '';
		}

		$title       = $post ? $post->post_title : '';
		$content     = $post ? $post->post_content : '';
		$status      = $post ? $post->post_status : 'draft';
		$featured_id = $post_id ? (int) get_post_thumbnail_id($post_id) : 0;
		$gallery_ids = $post_id && function_exists('momentum_get_galeria_ids') ? momentum_get_galeria_ids($post_id) : [];

		include MOMENTUM_ADMIN_PATH . 'templates/terrenos/form.php';
	}
}
