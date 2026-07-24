<?php
/**
 * Módulo Blog: administra el post type nativo 'post' (el mismo que ya
 * consumen page-blog.php / template-parts/blog-grid.php / single.php en el
 * tema). No se registra ningún CPT nuevo. El esquema de meta (SEO, flags)
 * vive en wp-content/themes/momentum/inc/cpt/blog.php y se reutiliza
 * aquí igual que Momentum_Admin_Properties reutiliza el esquema de
 * propiedades del tema.
 */

if (! defined('ABSPATH')) {
	exit;
}

class Momentum_Admin_Blog
{
	const POST_TYPE = 'post';

	const SAVE_NONCE_ACTION   = 'momentum_admin_save_article';
	const SAVE_NONCE_FIELD    = 'momentum_admin_article_nonce';
	const TRASH_NONCE_ACTION  = 'momentum_admin_trash_article';
	const TRASH_NONCE_FIELD   = 'momentum_admin_trash_article_nonce';
	const STATUS_NONCE_ACTION = 'momentum_admin_toggle_article_status';
	const STATUS_NONCE_FIELD  = 'momentum_admin_status_article_nonce';

	private static function base_url(): string
	{
		return home_url('/admin/blog/');
	}

	private static function stash_key(): string
	{
		return 'momentum_admin_article_draft_' . get_current_user_id();
	}

	public static function maybe_handle_actions(): void
	{
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			return;
		}

		if (isset($_POST['momentum_admin_article_submit'])) {
			self::handle_save();
		} elseif (isset($_POST['momentum_admin_article_trash'])) {
			self::handle_trash();
		} elseif (isset($_POST['momentum_admin_article_toggle_status'])) {
			self::handle_toggle_status();
		}
	}

	public static function render(): void
	{
		$accion = isset($_GET['accion']) ? sanitize_key(wp_unslash($_GET['accion'])) : 'listar';

		if ($accion === 'nueva') {
			self::render_form(0);

			return;
		}

		if ($accion === 'editar') {
			self::render_form(isset($_GET['id']) ? absint($_GET['id']) : 0);

			return;
		}

		self::render_list();
	}

	// -----------------------------------------------------------------
	// Guardar
	// -----------------------------------------------------------------

	private static function handle_save(): void
	{
		$redirect_base = self::base_url();

		if (
			! isset($_POST[self::SAVE_NONCE_FIELD])
			|| ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::SAVE_NONCE_FIELD])), self::SAVE_NONCE_ACTION)
		) {
			wp_safe_redirect(add_query_arg('err', 'nonce', $redirect_base));
			exit;
		}

		if (! current_user_can(Momentum_Admin_Permissions::CAPABILITY)) {
			wp_safe_redirect(add_query_arg('err', 'permisos', $redirect_base));
			exit;
		}

		$post_id = isset($_POST['momentum_article_id']) ? absint($_POST['momentum_article_id']) : 0;
		$is_new  = $post_id === 0;

		if (! $is_new && (get_post_type($post_id) !== self::POST_TYPE || ! current_user_can('edit_post', $post_id))) {
			wp_safe_redirect(add_query_arg('err', 'permisos', $redirect_base));
			exit;
		}

		$title = isset($_POST['momentum_article_title']) ? sanitize_text_field(wp_unslash($_POST['momentum_article_title'])) : '';

		if ($title === '') {
			// Nada se guarda todavía (ni siquiera un borrador): se conserva lo
			// capturado en un transiente de corta vida para repoblar el
			// formulario tras el redirect, en vez de perder lo escrito.
			set_transient(self::stash_key(), $_POST, 60);

			$target = $is_new
				? add_query_arg('accion', 'nueva', $redirect_base)
				: add_query_arg(['accion' => 'editar', 'id' => $post_id], $redirect_base);

			wp_safe_redirect(add_query_arg('err', 'titulo', $target));
			exit;
		}

		$requested_status = (isset($_POST['momentum_article_status']) && $_POST['momentum_article_status'] === 'publish')
			? 'publish'
			: 'draft';

		$slug_input = isset($_POST['momentum_article_slug']) ? sanitize_text_field(wp_unslash($_POST['momentum_article_slug'])) : '';
		$slug       = $slug_input !== '' ? sanitize_title($slug_input) : sanitize_title($title);

		$excerpt = isset($_POST['momentum_article_excerpt']) ? sanitize_textarea_field(wp_unslash($_POST['momentum_article_excerpt'])) : '';
		$content = isset($_POST['momentum_article_content']) ? wp_kses_post(wp_unslash($_POST['momentum_article_content'])) : '';
		$content = momentum_downgrade_h1($content);

		$featured_id  = isset($_POST['momentum_article_featured_image']) ? absint($_POST['momentum_article_featured_image']) : 0;

		// Requisitos para publicar (no aplican a borradores). Si faltan, el
		// artículo se guarda de todas formas como borrador -no se pierde nada
		// capturado- y se redirige con un aviso para completarlo.
		$missing_for_publish = [];

		if ($requested_status === 'publish') {
			if (wp_strip_all_tags($content) === '') {
				$missing_for_publish[] = 'contenido';
			}
			if (! $featured_id || ! wp_attachment_is_image($featured_id)) {
				$missing_for_publish[] = 'imagen';
			}
		}

		$final_status = ($requested_status === 'publish' && empty($missing_for_publish)) ? 'publish' : 'draft';

		$old_status = $is_new ? '' : get_post_status($post_id);

		$postarr = [
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_excerpt' => $excerpt,
			'post_content' => $content,
			'post_status'  => $final_status,
			'post_type'    => self::POST_TYPE,
		];

		$date_input = isset($_POST['momentum_article_date']) ? sanitize_text_field(wp_unslash($_POST['momentum_article_date'])) : '';
		if ($date_input !== '') {
			$timestamp = strtotime($date_input);
			if ($timestamp !== false) {
				$postarr['post_date']     = gmdate('Y-m-d H:i:s', $timestamp + (int) (get_option('gmt_offset') * HOUR_IN_SECONDS));
				$postarr['post_date_gmt'] = gmdate('Y-m-d H:i:s', $timestamp);
			}
		}

		if ($is_new) {
			$result = wp_insert_post($postarr, true);
		} else {
			$postarr['ID'] = $post_id;
			$result        = wp_update_post($postarr, true);
		}

		if (is_wp_error($result) || ! $result) {
			set_transient(self::stash_key(), $_POST, 60);

			$target = $is_new
				? add_query_arg('accion', 'nueva', $redirect_base)
				: add_query_arg(['accion' => 'editar', 'id' => $post_id], $redirect_base);

			wp_safe_redirect(add_query_arg('err', 'guardado', $target));
			exit;
		}

		$post_id = (int) $result;

		if ($final_status === 'draft' && $old_status === 'publish') {
			update_post_meta($post_id, 'momentum_unpublished', '1');
		} elseif ($final_status === 'publish') {
			update_post_meta($post_id, 'momentum_unpublished', '');
		}

		self::save_tags($post_id);
		self::save_featured_image($post_id, $featured_id);
		self::save_meta_schema($post_id);

		$mensaje = $is_new ? 'creado' : 'actualizado';
		$error   = '';

		if (! empty($missing_for_publish)) {
			$mensaje = 'guardado_borrador';
			$error   = 'publicar_incompleto';
		} elseif ($final_status === 'publish') {
			$mensaje = 'publicado';
		}

		$target = add_query_arg(
			array_filter(['accion' => 'editar', 'id' => $post_id, 'mensaje' => $mensaje, 'err' => $error]),
			$redirect_base
		);

		wp_safe_redirect($target);
		exit;
	}

	private static function save_tags(int $post_id): void
	{
		$tags_raw = isset($_POST['momentum_article_tags']) ? sanitize_text_field(wp_unslash($_POST['momentum_article_tags'])) : '';
		$tags     = array_values(array_filter(array_map('trim', explode(',', $tags_raw))));

		wp_set_post_terms($post_id, $tags, 'post_tag');
	}

	/**
	 * Igual que Momentum_Admin_Properties::save_featured_image(), más el
	 * texto alternativo, que se guarda en el campo nativo del adjunto
	 * (_wp_attachment_image_alt) — no se crea un campo propio para esto.
	 */
	private static function save_featured_image(int $post_id, int $featured_id): void
	{
		if ($featured_id > 0 && wp_attachment_is_image($featured_id)) {
			set_post_thumbnail($post_id, $featured_id);

			$alt = isset($_POST['momentum_article_featured_alt']) ? sanitize_text_field(wp_unslash($_POST['momentum_article_featured_alt'])) : '';
			update_post_meta($featured_id, '_wp_attachment_image_alt', $alt);
		} else {
			delete_post_thumbnail($post_id);
		}
	}

	private static function save_meta_schema(int $post_id): void
	{
		foreach (momentum_articulo_meta_schema() as $key => $field) {
			if (! isset($_POST[$key])) {
				update_post_meta($post_id, $key, '');
				continue;
			}

			$raw = wp_unslash($_POST[$key]);

			if (! empty($field['kses'])) {
				update_post_meta($post_id, $key, momentum_downgrade_h1(wp_kses_post($raw)));
			} elseif (! empty($field['options'])) {
				update_post_meta($post_id, $key, in_array($raw, $field['options'], true) ? $raw : '');
			} elseif (! empty($field['textarea'])) {
				update_post_meta($post_id, $key, sanitize_textarea_field($raw));
			} elseif ($field['type'] === 'number') {
				update_post_meta($post_id, $key, absint($raw));
			} else {
				update_post_meta($post_id, $key, sanitize_text_field($raw));
			}
		}
	}

	// -----------------------------------------------------------------
	// Papelera / publicar / despublicar
	// -----------------------------------------------------------------

	private static function handle_trash(): void
	{
		$redirect_base = self::base_url();
		$post_id       = isset($_POST['momentum_article_id']) ? absint($_POST['momentum_article_id']) : 0;

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
			wp_safe_redirect(add_query_arg('err', 'papelera', $redirect_base));
			exit;
		}

		wp_trash_post($post_id);

		wp_safe_redirect(add_query_arg('mensaje', 'papelera', $redirect_base));
		exit;
	}

	private static function handle_toggle_status(): void
	{
		$redirect_base = self::base_url();
		$post_id       = isset($_POST['momentum_article_id']) ? absint($_POST['momentum_article_id']) : 0;

		$nonce_ok = $post_id > 0
			&& isset($_POST[self::STATUS_NONCE_FIELD])
			&& wp_verify_nonce(
				sanitize_text_field(wp_unslash($_POST[self::STATUS_NONCE_FIELD])),
				self::STATUS_NONCE_ACTION . '_' . $post_id
			);

		if (
			! $nonce_ok
			|| get_post_type($post_id) !== self::POST_TYPE
			|| ! current_user_can('edit_post', $post_id)
		) {
			wp_safe_redirect(add_query_arg('err', 'permisos', $redirect_base));
			exit;
		}

		$current = get_post_status($post_id);

		if ($current === 'publish') {
			wp_update_post(['ID' => $post_id, 'post_status' => 'draft']);
			update_post_meta($post_id, 'momentum_unpublished', '1');
			$mensaje = 'despublicado';
		} else {
			$has_content  = wp_strip_all_tags(get_post_field('post_content', $post_id)) !== '';
			$has_image    = has_post_thumbnail($post_id);

			if (! $has_content || ! $has_image) {
				wp_safe_redirect(add_query_arg(
					['accion' => 'editar', 'id' => $post_id, 'err' => 'publicar_incompleto'],
					$redirect_base
				));
				exit;
			}

			wp_update_post(['ID' => $post_id, 'post_status' => 'publish']);
			update_post_meta($post_id, 'momentum_unpublished', '');
			$mensaje = 'publicado';
		}

		wp_safe_redirect(add_query_arg('mensaje', $mensaje, $redirect_base));
		exit;
	}

	// -----------------------------------------------------------------
	// Listado
	// -----------------------------------------------------------------

	private static function query_articulos(): array
	{
		$search   = isset($_GET['buscar']) ? sanitize_text_field(wp_unslash($_GET['buscar'])) : '';
		$estado   = isset($_GET['estado']) ? sanitize_key(wp_unslash($_GET['estado'])) : '';
		$orden    = isset($_GET['orden']) && $_GET['orden'] === 'asc' ? 'asc' : 'desc';
		$paged    = isset($_GET['pg']) ? max(1, absint($_GET['pg'])) : 1;

		if (! in_array($estado, ['borrador', 'publicado', 'despublicado'], true)) {
			$estado = '';
		}

		$query_args = [
			'post_type'      => self::POST_TYPE,
			'post_status'    => ['publish', 'draft'],
			'posts_per_page' => 20,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => strtoupper($orden),
		];

		if ($search !== '') {
			$query_args['title_filter'] = $search; // usado solo por el where_cb de abajo
		}

		if ($estado === 'publicado') {
			$query_args['post_status'] = ['publish'];
		} elseif ($estado === 'borrador') {
			$query_args['post_status'] = ['draft'];
			$query_args['meta_query']  = [
				'relation' => 'OR',
				['key' => 'momentum_unpublished', 'compare' => 'NOT EXISTS'],
				['key' => 'momentum_unpublished', 'value' => '1', 'compare' => '!='],
			];
		} elseif ($estado === 'despublicado') {
			$query_args['post_status'] = ['draft'];
			$query_args['meta_query']  = [
				['key' => 'momentum_unpublished', 'value' => '1', 'compare' => '='],
			];
		}

		$where_cb = null;

		if ($search !== '') {
			$where_cb = static function (string $where) use ($search): string {
				global $wpdb;

				$like = '%' . $wpdb->esc_like($search) . '%';

				$where .= $wpdb->prepare(" AND {$wpdb->posts}.post_title LIKE %s", $like);

				return $where;
			};

			add_filter('posts_where', $where_cb);
		}

		unset($query_args['title_filter']);

		$query = new WP_Query($query_args);

		if ($where_cb) {
			remove_filter('posts_where', $where_cb);
		}

		return [
			'query'  => $query,
			'search' => $search,
			'estado' => $estado,
			'orden'  => $orden,
			'paged'  => $paged,
		];
	}

	private static function render_list(): void
	{
		$result = self::query_articulos();

		include MOMENTUM_ADMIN_PATH . 'templates/blog/list.php';
	}

	// -----------------------------------------------------------------
	// Formulario (crear / editar)
	// -----------------------------------------------------------------

	private static function render_form(int $post_id): void
	{
		$post = $post_id ? get_post($post_id) : null;

		if ($post_id && (! $post || $post->post_type !== self::POST_TYPE)) {
			echo '<div class="momentum-admin-notice momentum-admin-notice--error"><p>';
			esc_html_e('Artículo no encontrado.', 'momentum-admin');
			echo '</p></div>';

			return;
		}

		if ($post_id && ! current_user_can('edit_post', $post_id)) {
			wp_die(
				esc_html__('No tienes permiso para editar este artículo.', 'momentum-admin'),
				esc_html__('Acceso denegado', 'momentum-admin'),
				['response' => 403]
			);
		}

		// Repuebla el formulario desde el transiente si venimos de un error
		// de "título obligatorio" (ver handle_save()), en vez de mostrarlo
		// vacío y perder lo que el administrador ya había escrito.
		$stashed = null;
		if (($_GET['err'] ?? '') === 'titulo') {
			$stashed = get_transient(self::stash_key());
			delete_transient(self::stash_key());
		}

		$field = static function (string $key, $default = '') use ($stashed) {
			if (is_array($stashed) && isset($stashed[$key])) {
				return wp_unslash($stashed[$key]);
			}

			return $default;
		};

		$schema = momentum_articulo_meta_schema();
		$meta   = [];
		foreach ($schema as $key => $f) {
			$meta[$key] = $field($key, $post_id ? get_post_meta($post_id, $key, true) : '');
		}

		$title       = $field('momentum_article_title', $post ? $post->post_title : '');
		$slug        = $field('momentum_article_slug', $post ? $post->post_name : '');
		$excerpt     = $field('momentum_article_excerpt', $post ? $post->post_excerpt : '');
		$content     = $field('momentum_article_content', $post ? $post->post_content : '');
		$status      = $post ? $post->post_status : 'draft';
		$featured_id = $field('momentum_article_featured_image', $post_id ? (int) get_post_thumbnail_id($post_id) : 0);
		$featured_alt = $featured_id ? get_post_meta((int) $featured_id, '_wp_attachment_image_alt', true) : '';
		$tags_value  = $field('momentum_article_tags', $post_id ? implode(', ', wp_list_pluck(get_the_tags($post_id) ?: [], 'name')) : '');
		// post_date ya está en hora local del sitio (no se convierte desde
		// post_date_gmt, que en algunos artículos migrados quedó en cero
		// aunque post_date sí tiene fecha real -eso producía un valor
		// inválido para el input datetime-local-).
		$date_default = $post_id && $post->post_date !== '0000-00-00 00:00:00'
			? str_replace(' ', 'T', substr($post->post_date, 0, 16))
			: current_time('Y-m-d\TH:i');
		$date_value   = $field('momentum_article_date', $date_default);

		include MOMENTUM_ADMIN_PATH . 'templates/blog/form.php';
	}
}
