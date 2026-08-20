<?php
/**
 * Módulo "Contenido — Inicio / Nosotros / Construcción": edita los campos
 * registrados en wp-content/themes/momentum/inc/page-content-fields.php
 * (post meta nativo sobre las Páginas reales "inicio"/"nosotros"/"construccion"
 * y su traducción en inglés). No se registra ningún CPT ni schema nuevo
 * aquí -se reutiliza momentum_home_meta_schema()/momentum_nosotros_meta_schema()/
 * momentum_construccion_meta_schema() del tema, mismo criterio que
 * Momentum_Admin_Properties reutilizando el schema de 'propiedades'-.
 *
 * Los campos de texto/WYSIWYG/CTA son independientes por idioma (cada
 * idioma es una Página distinta en Polylang): el formulario los repite dos
 * veces, una por idioma, con el mismo criterio ya usado en Admin > General
 * para pares como 'TikTok' / 'TikTok (inglés)'. Los campos de imagen y
 * video son compartidos (misma pieza en ambos idiomas) y solo se capturan
 * una vez, guardados sobre la página en español.
 */

if (! defined('ABSPATH')) {
	exit;
}

class Momentum_Admin_Content_Pages
{
	const NONCE_ACTION_HOME         = 'momentum_admin_save_home_content';
	const NONCE_FIELD_HOME          = 'momentum_admin_home_content_nonce';
	const NONCE_ACTION_NOSOTROS     = 'momentum_admin_save_nosotros_content';
	const NONCE_FIELD_NOSOTROS      = 'momentum_admin_nosotros_content_nonce';
	const NONCE_ACTION_CONSTRUCCION = 'momentum_admin_save_construccion_content';
	const NONCE_FIELD_CONSTRUCCION  = 'momentum_admin_construccion_content_nonce';

	/**
	 * Slug ES de cada página -> slug de la ruta del panel (/admin/{slug}/).
	 * Única fuente de verdad para el mapeo, usada tanto al redirigir después
	 * de guardar como al incluir la plantilla correcta.
	 */
	private static function router_page_for(string $es_slug): string
	{
		$map = [
			'inicio'       => 'general-home-images',
			'nosotros'     => 'general-about',
			'construccion' => 'general-construction',
		];

		return $map[$es_slug] ?? $es_slug;
	}

	public static function maybe_handle_actions(): void
	{
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			return;
		}

		if (isset($_POST['momentum_admin_home_content_submit'])) {
			self::handle_save('inicio', momentum_home_meta_schema(), self::NONCE_ACTION_HOME, self::NONCE_FIELD_HOME);
		} elseif (isset($_POST['momentum_admin_nosotros_content_submit'])) {
			self::handle_save('nosotros', momentum_nosotros_meta_schema(), self::NONCE_ACTION_NOSOTROS, self::NONCE_FIELD_NOSOTROS);
		} elseif (isset($_POST['momentum_admin_construccion_content_submit'])) {
			self::handle_save('construccion', momentum_construccion_meta_schema(), self::NONCE_ACTION_CONSTRUCCION, self::NONCE_FIELD_CONSTRUCCION);
		}
	}

	public static function render_home(): void
	{
		self::render('inicio', momentum_home_meta_schema());
	}

	public static function render_nosotros(): void
	{
		self::render('nosotros', momentum_nosotros_meta_schema());
	}

	public static function render_construccion(): void
	{
		self::render('construccion', momentum_construccion_meta_schema());
	}

	// -----------------------------------------------------------------
	// Resolución de páginas ES/EN
	// -----------------------------------------------------------------

	/**
	 * @return array{es: ?WP_Post, en: ?WP_Post}
	 */
	private static function resolve_pages(string $es_slug): array
	{
		if (! function_exists('momentum_nosotros_meta_schema')) {
			return ['es' => null, 'en' => null];
		}

		$es_page = get_page_by_path($es_slug);
		$en_page = null;

		if ($es_page && function_exists('momentum_find_translation')) {
			$en_id   = momentum_find_translation($es_page->ID, 'en');
			$en_page = $en_id ? get_post($en_id) : null;
		}

		return ['es' => $es_page, 'en' => $en_page];
	}

	// -----------------------------------------------------------------
	// Formulario
	// -----------------------------------------------------------------

	private static function render(string $es_slug, array $schema): void
	{
		if (! function_exists('momentum_sanitize_page_field')) {
			echo '<div class="momentum-admin-notice momentum-admin-notice--error"><p>';
			esc_html_e('Los campos de contenido no están disponibles. Verifica que el tema Momentum esté activo.', 'momentum-admin');
			echo '</p></div>';

			return;
		}

		$pages = self::resolve_pages($es_slug);

		if (! $pages['es']) {
			echo '<div class="momentum-admin-notice momentum-admin-notice--error"><p>';
			printf(
				/* translators: %s: slug de la página (ej. "nosotros") */
				esc_html__('No se encontró la página "%s". Verifica que exista y esté publicada.', 'momentum-admin'),
				esc_html($es_slug)
			);
			echo '</p></div>';

			return;
		}

		$values_es = [];
		$values_en = [];

		foreach ($schema as $key => $field) {
			$values_es[$key] = get_post_meta($pages['es']->ID, $key, true);
			$values_en[$key] = $pages['en'] ? get_post_meta($pages['en']->ID, $key, true) : '';
		}

		include MOMENTUM_ADMIN_PATH . 'templates/content/' . $es_slug . '.php';
	}

	// -----------------------------------------------------------------
	// Guardado
	// -----------------------------------------------------------------

	private static function handle_save(string $es_slug, array $schema, string $nonce_action, string $nonce_field): void
	{
		$redirect_base = home_url('/admin/' . self::router_page_for($es_slug) . '/');

		if (
			! isset($_POST[$nonce_field])
			|| ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonce_field])), $nonce_action)
		) {
			wp_safe_redirect(add_query_arg('error', 'nonce', $redirect_base));
			exit;
		}

		if (! current_user_can(Momentum_Admin_Permissions::CAPABILITY)) {
			wp_safe_redirect(add_query_arg('error', 'permisos', $redirect_base));
			exit;
		}

		$pages = self::resolve_pages($es_slug);

		if (! $pages['es']) {
			wp_safe_redirect(add_query_arg('error', 'pagina', $redirect_base));
			exit;
		}

		foreach ($schema as $key => $field) {
			$shared = ! empty($field['shared']);

			// Campo compartido: un solo input en el formulario (name = $key
			// tal cual), se guarda solo en la página en español.
			if ($shared) {
				if (! array_key_exists($key, $_POST) && $field['type'] !== 'bool') {
					continue;
				}

				$raw   = $_POST[$key] ?? '';
				$clean = momentum_sanitize_page_field(wp_unslash($raw), $field);
				update_post_meta($pages['es']->ID, $key, $clean);

				continue;
			}

			// Campo por idioma: dos inputs, "{key}_es" y "{key}_en".
			if (array_key_exists($key . '_es', $_POST) || $field['type'] === 'bool') {
				$raw_es   = $_POST[$key . '_es'] ?? '';
				$clean_es = momentum_sanitize_page_field(wp_unslash($raw_es), $field);
				update_post_meta($pages['es']->ID, $key, $clean_es);
			}

			if ($pages['en'] && (array_key_exists($key . '_en', $_POST) || $field['type'] === 'bool')) {
				$raw_en   = $_POST[$key . '_en'] ?? '';
				$clean_en = momentum_sanitize_page_field(wp_unslash($raw_en), $field);
				update_post_meta($pages['en']->ID, $key, $clean_en);
			}
		}

		wp_safe_redirect(add_query_arg('mensaje', 'actualizado', $redirect_base));
		exit;
	}
}
