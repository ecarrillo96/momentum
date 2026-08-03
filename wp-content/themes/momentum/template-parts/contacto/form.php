<?php
/**
 * Contacto — contact form. Handled by inc/contact-form.php via wp_mail().
 */

if (! defined('ABSPATH')) {
	exit;
}

$status = isset($_GET['contacto']) ? sanitize_text_field(wp_unslash($_GET['contacto'])) : '';
?>
<div class="momentum-contact-form">
	<h3><?php esc_html_e('Envíanos un mensaje', 'momentum'); ?></h3>

	<?php if ($status === 'ok') : ?>
		<div class="momentum-form-message momentum-form-message--success">
			<?php esc_html_e('Gracias, tu mensaje fue enviado correctamente. Te contactaremos pronto.', 'momentum'); ?>
		</div>
	<?php elseif ($status === 'error') : ?>
		<div class="momentum-form-message momentum-form-message--error">
			<?php esc_html_e('No pudimos enviar tu mensaje. Verifica los datos e intenta de nuevo.', 'momentum'); ?>
		</div>
	<?php endif; ?>

	<form method="post" action="">
		<?php wp_nonce_field('momentum_contact_form', 'momentum_contact_nonce'); ?>
		<input type="hidden" name="momentum_contact_submit" value="1">

		<div class="momentum-form-row">
			<label for="contacto-nombre"><?php esc_html_e('Nombre completo', 'momentum'); ?></label>
			<input type="text" id="contacto-nombre" name="nombre" required>
		</div>

		<div class="momentum-form-row">
			<label for="contacto-telefono"><?php esc_html_e('Teléfono', 'momentum'); ?></label>
			<input type="tel" id="contacto-telefono" name="telefono">
		</div>

		<div class="momentum-form-row">
			<label for="contacto-email"><?php esc_html_e('Correo electrónico', 'momentum'); ?></label>
			<input type="email" id="contacto-email" name="email" required>
		</div>

		<div class="momentum-form-row">
			<label for="contacto-mensaje"><?php esc_html_e('Mensaje', 'momentum'); ?></label>
			<textarea id="contacto-mensaje" name="mensaje" rows="5" required></textarea>
		</div>

		<button type="submit" class="momentum-btn momentum-btn--primary"><?php esc_html_e('Enviar mensaje', 'momentum'); ?></button>
	</form>
</div>
