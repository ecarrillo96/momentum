<?php
/** @var string $page */
if (! defined('ABSPATH')) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php esc_html_e('Panel administrativo', 'momentum-admin'); ?> · Momentum Cocoyoc</title>
<?php
/*
 * Esta pantalla no pasa por wp_head() (no es una plantilla del tema ni una
 * página nativa de wp-admin), así que el site icon nativo de WordPress no se
 * imprime solo aquí. Se reutilizan las mismas funciones nativas
 * (get_site_icon_url()/has_site_icon()) para no duplicar ni hardcodear la
 * URL del ícono configurado en Ajustes > General > Identidad del sitio.
 */
if (has_site_icon()) :
	?>
	<link rel="icon" href="<?php echo esc_url(get_site_icon_url(32)); ?>" sizes="32x32">
	<link rel="icon" href="<?php echo esc_url(get_site_icon_url(192)); ?>" sizes="192x192">
	<link rel="apple-touch-icon" href="<?php echo esc_url(get_site_icon_url(180)); ?>">
	<?php
endif;

/*
 * Imprime exactamente lo que Momentum_Admin_Assets encoló (jquery/dashicons
 * siempre; el bundle completo de wp_enqueue_media() en Propiedades), sin
 * listar handles a mano: así nunca falta un handle hermano como
 * media-audiovideo (ver footer.php para el detalle del bug que esto evita).
 */
wp_print_styles();
?>
<link rel="stylesheet" href="<?php echo esc_url(MOMENTUM_ADMIN_URL . 'assets/css/admin-panel.css?v=' . MOMENTUM_ADMIN_VERSION); ?>">
</head>
<body class="momentum-admin-body">
<div class="momentum-admin momentum-admin-shell">
