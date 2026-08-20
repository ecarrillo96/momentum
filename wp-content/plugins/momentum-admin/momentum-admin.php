<?php
/**
 * Plugin Name: Momentum Admin
 * Description: Panel administrativo personalizado para Momentum Cocoyoc, accesible en /admin. Usa autenticación nativa de WordPress.
 * Version: 1.0.0
 * Author: Momentum Cocoyoc
 * Text Domain: momentum-admin
 * Requires PHP: 7.4
 */

if (! defined('ABSPATH')) {
	exit;
}

define('MOMENTUM_ADMIN_VERSION', '1.1.8');
define('MOMENTUM_ADMIN_PATH', plugin_dir_path(__FILE__));
define('MOMENTUM_ADMIN_URL', plugin_dir_url(__FILE__));

require_once MOMENTUM_ADMIN_PATH . 'includes/class-permissions.php';
require_once MOMENTUM_ADMIN_PATH . 'includes/class-auth.php';
require_once MOMENTUM_ADMIN_PATH . 'includes/class-assets.php';
require_once MOMENTUM_ADMIN_PATH . 'includes/class-properties.php';
require_once MOMENTUM_ADMIN_PATH . 'includes/class-terrenos.php';
require_once MOMENTUM_ADMIN_PATH . 'includes/class-blog.php';
require_once MOMENTUM_ADMIN_PATH . 'includes/class-zonas.php';
require_once MOMENTUM_ADMIN_PATH . 'includes/class-general.php';
require_once MOMENTUM_ADMIN_PATH . 'includes/class-content-pages.php';
require_once MOMENTUM_ADMIN_PATH . 'includes/class-translations.php';

// Panel de Estadísticas (Google Analytics Data API). Sin Composer ni SDK
// oficial de Google -cliente REST propio, ver includes/class-ga4-client.php-.
require_once MOMENTUM_ADMIN_PATH . 'includes/class-ga4-config.php';
require_once MOMENTUM_ADMIN_PATH . 'includes/class-ga4-auth.php';
require_once MOMENTUM_ADMIN_PATH . 'includes/class-ga4-client.php';
require_once MOMENTUM_ADMIN_PATH . 'includes/class-ga4-cache.php';
require_once MOMENTUM_ADMIN_PATH . 'includes/class-ga4-diagnostics.php';
require_once MOMENTUM_ADMIN_PATH . 'includes/class-ga4-reports.php';
require_once MOMENTUM_ADMIN_PATH . 'includes/class-ga4-admin.php';
require_once MOMENTUM_ADMIN_PATH . 'includes/class-ga4-ajax.php';

require_once MOMENTUM_ADMIN_PATH . 'includes/class-router.php';

// Comando WP-CLI de migración ciudad/ubicación -> zona (no-op fuera de WP-CLI).
require_once MOMENTUM_ADMIN_PATH . 'includes/class-cli-migrate-zona.php';

// Comandos WP-CLI de datos de prueba para paginación (no-op fuera de WP-CLI).
require_once MOMENTUM_ADMIN_PATH . 'includes/class-cli-seed-test-properties.php';

// Comando WP-CLI de migración al sistema de traducción automática ES→EN
// (no-op fuera de WP-CLI).
require_once MOMENTUM_ADMIN_PATH . 'includes/class-cli-migrate-translations.php';

// Comando WP-CLI de migración del Blog al sistema de traducción automática
// ES→EN (no-op fuera de WP-CLI).
require_once MOMENTUM_ADMIN_PATH . 'includes/class-cli-migrate-blog-translations.php';

Momentum_Admin_Router::init();
Momentum_Admin_Zonas::init();
Momentum_GA4_Ajax::init();

register_activation_hook(__FILE__, ['Momentum_Admin_Router', 'activate']);
register_deactivation_hook(__FILE__, ['Momentum_Admin_Router', 'deactivate']);
