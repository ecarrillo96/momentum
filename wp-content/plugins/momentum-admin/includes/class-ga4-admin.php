<?php
/**
 * Controlador de la página /admin/estadisticas/: verifica permisos, encola
 * los assets propios de esta única pantalla (nunca en el resto del panel),
 * y renderiza el shell del dashboard -los datos se piden por AJAX después,
 * ver class-ga4-ajax.php-.
 */

if (! defined('ABSPATH')) {
	exit;
}

class Momentum_GA4_Admin
{
	public const NONCE_ACTION = 'momentum_ga4_dashboard';

	public static function render(): void
	{
		if (! current_user_can(Momentum_Admin_Permissions::CAPABILITY)) {
			wp_die(
				esc_html__('No tienes permiso para ver esta página.', 'momentum-admin'),
				esc_html__('Acceso denegado', 'momentum-admin'),
				['response' => 403]
			);
		}

		self::enqueue_assets();

		$status = Momentum_GA4_Diagnostics::get_status();

		include MOMENTUM_ADMIN_PATH . 'templates/ga4/dashboard.php';
	}

	/**
	 * Se llama desde dentro de render(), ANTES de que templates/layout/header.php
	 * imprima wp_print_styles() y templates/layout/footer.php imprima
	 * wp_print_scripts() -mismo orden que ya usa Momentum_Admin_Router::render_page()
	 * para el resto del panel-, así que basta con encolar aquí sin tocar el router.
	 */
	private static function enqueue_assets(): void
	{
		wp_enqueue_style(
			'momentum-ga4-dashboard',
			MOMENTUM_ADMIN_URL . 'assets/css/ga4-dashboard.css',
			[],
			MOMENTUM_ADMIN_VERSION
		);

		wp_enqueue_script(
			'momentum-ga4-chartjs',
			MOMENTUM_ADMIN_URL . 'assets/js/vendor/chart.umd.min.js',
			[],
			'4.4.4',
			true
		);

		wp_enqueue_script(
			'momentum-ga4-dashboard',
			MOMENTUM_ADMIN_URL . 'assets/js/ga4-dashboard.js',
			['momentum-ga4-chartjs'],
			MOMENTUM_ADMIN_VERSION,
			true
		);

		wp_localize_script('momentum-ga4-dashboard', 'momentumGA4', [
			'ajaxUrl' => admin_url('admin-ajax.php'),
			'nonce'   => wp_create_nonce(self::NONCE_ACTION),
			'i18n'    => [
				'loading'      => __('Cargando…', 'momentum-admin'),
				'noData'       => __('Sin datos para el periodo seleccionado.', 'momentum-admin'),
				'error'        => __('No se pudieron cargar los datos.', 'momentum-admin'),
				'lastUpdated'  => __('Última actualización', 'momentum-admin'),
				'never'        => __('Nunca', 'momentum-admin'),
				'notAvailable' => __('No disponible', 'momentum-admin'),
				'cacheCleared' => __('Caché borrado.', 'momentum-admin'),
			],
		]);
	}
}
