<?php
/**
 * Contacto — banner image. El formulario quedó obsoleto: todo el contacto
 * se maneja por WhatsApp (ver template-parts/whatsapp-float.php).
 */

if (! defined('ABSPATH')) {
	exit;
}
?>
<div class="momentum-contact-banner">
	<img
		src="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/images/contacto.jpg'); ?>"
		alt="<?php esc_attr_e('Contáctanos', 'momentum'); ?>"
		width="1600"
		height="900"
	>
</div>
