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

define('MOMENTUM_ADMIN_VERSION', '1.1.6');
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
require_once MOMENTUM_ADMIN_PATH . 'includes/class-router.php';

// Aislado a propósito: ver includes/class-demo-user.php para instrucciones de eliminación.
require_once MOMENTUM_ADMIN_PATH . 'includes/class-demo-user.php';

// Comando WP-CLI de migración ciudad/ubicación -> zona (no-op fuera de WP-CLI).
require_once MOMENTUM_ADMIN_PATH . 'includes/class-cli-migrate-zona.php';

// Comandos WP-CLI de datos de prueba para paginación (no-op fuera de WP-CLI).
require_once MOMENTUM_ADMIN_PATH . 'includes/class-cli-seed-test-properties.php';

Momentum_Admin_Router::init();
Momentum_Admin_Zonas::init();

register_activation_hook(__FILE__, ['Momentum_Admin_Router', 'activate']);
register_deactivation_hook(__FILE__, ['Momentum_Admin_Router', 'deactivate']);

// Creación del usuario demo: SOLO se ejecuta en activación del plugin.
// Para eliminar la creación automática, ver instrucciones en class-demo-user.php.
register_activation_hook(__FILE__, ['Momentum_Admin_Demo_User', 'maybe_create']);
