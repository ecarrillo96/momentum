<?php
/**
 * Floating WhatsApp button — fixed to the viewport, shown on every page.
 */

if (! defined('ABSPATH')) {
	exit;
}

// Número de teléfono 1 como principal; si el admin lo deja vacío, usa el 2
// en vez de desaparecer -este botón debe seguir apareciendo siempre-.
$phone       = momentum_get_phone_1() ?: momentum_get_phone_2();
$whatsapp_url = momentum_phone_to_whatsapp_url($phone);

if ($whatsapp_url === '') {
	return;
}
?>
<a
	href="<?php echo esc_url($whatsapp_url); ?>"
	class="momentum-whatsapp-float"
	target="_blank"
	rel="noopener noreferrer"
	aria-label="<?php esc_attr_e('Contactar por WhatsApp', 'momentum'); ?>"
>
	<?php echo momentum_icon('whatsapp'); ?>
</a>
