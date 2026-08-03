<?php
/**
 * Home — Final call to action banner.
 */

if (! defined('ABSPATH')) {
	exit;
}

$whatsapp_message = __('Hola, me interesa encontrar una propiedad en Lomas de Cocoyoc, vengo desde su sitio web.', 'momentum');
$whatsapp_phone   = momentum_get_phone_1() ?: momentum_get_phone_2();
$whatsapp_url     = momentum_phone_to_whatsapp_url($whatsapp_phone, $whatsapp_message);
?>
<section class="momentum-section">
	<div class="momentum-container">
		<div class="momentum-cta-banner">
			<div class="momentum-cta-banner__content">
				<div class="momentum-cta-banner__icon"><?php echo momentum_icon('whatsapp'); ?></div>

				<div>
					<h2><?php esc_html_e('¿Buscas una propiedad en Lomas de Cocoyoc?', 'momentum'); ?></h2>
					<p><?php esc_html_e('Nuestro equipo está listo para ayudarte a encontrarla.', 'momentum'); ?></p>
				</div>
			</div>

			<?php if ($whatsapp_url !== '') : ?>
				<a class="momentum-btn momentum-btn--accent" href="<?php echo esc_url($whatsapp_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Contáctanos', 'momentum'); ?></a>
			<?php endif; ?>
		</div>
	</div>
</section>
