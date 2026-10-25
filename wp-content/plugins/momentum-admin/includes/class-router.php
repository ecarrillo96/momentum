<?php
/**
 * Rutas limpias bajo /admin. Las rewrite rules se registran en cada carga
 * de 'init' (operación en memoria, sin costo), pero flush_rewrite_rules()
 * SOLO se ejecuta en activación/desactivación del plugin.
 *
 * Todas las rutas pasan por el mismo gate de autenticación/permisos aquí,
 * no solo el menú visual.
 */

if (! defined('ABSPATH')) {
	exit;
}

class Momentum_Admin_Router
{
	const QUERY_VAR      = 'momentum_admin';
	const QUERY_VAR_PAGE = 'momentum_admin_page';

	const ALLOWED_PAGES = [
		'propiedades',
		'terrenos',
		'zonas',
		'general',
		'general-home-images',
		'general-about',
		'general-construction',
		'general-seo',
		'blog',
		'traducciones',
		'estadisticas',
		'guias',
	];

	public static function init(): void
	{
		add_action('init', [__CLASS__, 'register_rewrites']);
		add_filter('query_vars', [__CLASS__, 'register_query_vars']);
		add_action('template_redirect', [__CLASS__, 'dispatch']);
	}

	public static function register_rewrites(): void
	{
		add_rewrite_rule(
			'^admin/?$',
			'index.php?' . self::QUERY_VAR . '=1&' . self::QUERY_VAR_PAGE . '=propiedades',
			'top'
		);

		add_rewrite_rule(
			'^admin/([^/]+)/?$',
			'index.php?' . self::QUERY_VAR . '=1&' . self::QUERY_VAR_PAGE . '=$matches[1]',
			'top'
		);
	}

	public static function register_query_vars(array $vars): array
	{
		$vars[] = self::QUERY_VAR;
		$vars[] = self::QUERY_VAR_PAGE;

		return $vars;
	}

	/**
	 * Ejecuta solo en la activación del plugin.
	 */
	public static function activate(): void
	{
		self::register_rewrites();
		flush_rewrite_rules();
	}

	/**
	 * Ejecuta solo en la desactivación del plugin (limpia las reglas propias).
	 */
	public static function deactivate(): void
	{
		flush_rewrite_rules();
	}

	public static function dispatch(): void
	{
		if (! get_query_var(self::QUERY_VAR)) {
			return;
		}

		nocache_headers();
		header('X-Robots-Tag: noindex, nofollow', true);

		$page = get_query_var(self::QUERY_VAR_PAGE);
		$page = ($page === '' || $page === null) ? 'propiedades' : sanitize_key($page);

		/*
		 * Alias de compatibilidad: la regla de '^admin/?$' solo se regenera con
		 * flush_rewrite_rules() (activación/desactivación del plugin, ver
		 * comentario en register_rewrites()). Un sitio ya instalado puede tener
		 * cacheada la versión anterior de la regla, que resolvía a la sección
		 * "dashboard" ya eliminada. Se normaliza aquí para que '/admin/' entre
		 * directo a "propiedades" sin depender de un flush manual.
		 */
		if ($page === 'dashboard') {
			$page = 'propiedades';
		}

		if (! in_array($page, self::ALLOWED_PAGES, true)) {
			self::render_not_found();
			exit;
		}

		// Sobrescribe cualquier status 404 que WP haya asumido para esta ruta virtual.
		status_header(200);

		if (! is_user_logged_in()) {
			Momentum_Admin_Auth::render_login();
			exit;
		}

		if (! Momentum_Admin_Permissions::current_user_can_access()) {
			self::render_forbidden();
			exit;
		}

		if ($page === 'propiedades') {
			Momentum_Admin_Properties::maybe_handle_actions();
		} elseif ($page === 'terrenos') {
			Momentum_Admin_Terrenos::maybe_handle_actions();
		} elseif ($page === 'blog') {
			Momentum_Admin_Blog::maybe_handle_actions();
		} elseif ($page === 'traducciones') {
			Momentum_Admin_Translations::maybe_handle_actions();
		} elseif (in_array($page, ['general-home-images', 'general-about', 'general-construction'], true)) {
			Momentum_Admin_Content_Pages::maybe_handle_actions();
		}

		self::render_page($page);
		exit;
	}

	private static function render_not_found(): void
	{
		status_header(404);
		wp_die(
			esc_html__('Página no encontrada.', 'momentum-admin'),
			esc_html__('404', 'momentum-admin'),
			['response' => 404]
		);
	}

	private static function render_forbidden(): void
	{
		wp_die(
			esc_html__('No tienes permisos suficientes para acceder a este panel.', 'momentum-admin'),
			esc_html__('Acceso denegado', 'momentum-admin'),
			['response' => 403]
		);
	}

	private static function render_page(string $page): void
	{
		Momentum_Admin_Assets::enqueue_common();

		if (in_array($page, ['propiedades', 'terrenos', 'blog', 'general-home-images', 'general-about', 'general-construction'], true)) {
			Momentum_Admin_Assets::enqueue_media();
		}

		ob_start();

		switch ($page) {
			case 'propiedades':
				Momentum_Admin_Properties::render();
				break;

			case 'blog':
				Momentum_Admin_Blog::render();
				break;

			case 'zonas':
				Momentum_Admin_Zonas::render();
				break;

			case 'general':
				Momentum_Admin_General::render();
				break;

			case 'general-home-images':
				Momentum_Admin_Content_Pages::render_home();
				break;

			case 'general-about':
				Momentum_Admin_Content_Pages::render_nosotros();
				break;

			case 'general-construction':
				Momentum_Admin_Content_Pages::render_construccion();
				break;

			case 'general-seo':
				Momentum_Admin_General::render_seo();
				break;

			case 'terrenos':
				Momentum_Admin_Terrenos::render();
				break;

			case 'traducciones':
				Momentum_Admin_Translations::render();
				break;

			case 'estadisticas':
				Momentum_GA4_Admin::render();
				break;

			case 'guias':
				Momentum_Admin_Guias::render();
				break;
		}

		$content = ob_get_clean();

		include MOMENTUM_ADMIN_PATH . 'templates/layout/header.php';
		include MOMENTUM_ADMIN_PATH . 'templates/layout/sidebar.php';
		echo '<main class="momentum-admin-main">' . $content . '</main>';
		include MOMENTUM_ADMIN_PATH . 'templates/layout/footer.php';
	}
}
