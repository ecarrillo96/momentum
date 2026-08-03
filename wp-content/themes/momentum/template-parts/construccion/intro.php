<?php
/**
 * Construcción — intro/about copy section.
 */

if (! defined('ABSPATH')) {
	exit;
}

$whatsapp_message = __('Hola, me interesa cotizar mi proyecto de construcción, vengo desde su sitio web.', 'momentum');
$whatsapp_phone   = momentum_get_phone_1() ?: momentum_get_phone_2();
$whatsapp_url     = momentum_phone_to_whatsapp_url($whatsapp_phone, $whatsapp_message);
?>
<div class="momentum-container">
	<div class="momentum-construccion-video">
		<video
			class="momentum-construccion-video__player"
			src="<?php echo esc_url(momentum_get_intro_video_url()); ?>"
			controls
			playsinline
			preload="metadata"
		></video>
	</div>
</div>

<section class="momentum-section">
	<div class="momentum-container">
		<div class="momentum-prose momentum-prose--wide">
			<span class="momentum-eyebrow"><?php esc_html_e('Nuestra experiencia', 'momentum'); ?></span>

			<p>
				<?php esc_html_e('En Momentum Cocoyoc hacemos realidad tu proyecto con el respaldo de un equipo multidisciplinario de arquitectos, ingenieros, urbanistas y especialistas en construcción de casas en Lomas de Cocoyoc. Nuestro compromiso es ofrecer soluciones innovadoras, funcionales y de alta calidad en cada obra.', 'momentum'); ?>
			</p>

			<p>
				<?php esc_html_e('Con más de 15 años de experiencia en bienes raíces en Lomas de Cocoyoc, nos especializamos en el diseño y construcción de residencias modernas y casas de lujo adaptadas a las necesidades de cada cliente.', 'momentum'); ?>
			</p>

			<p>
				<?php esc_html_e('Ya sea que busques una casa nueva en venta en Lomas de Cocoyoc Morelos, construir la casa de tus sueños o invertir en un terreno para edificar tu patrimonio, te acompañamos en cada etapa: desde la planeación y diseño arquitectónico hasta la entrega final.', 'momentum'); ?>
			</p>

			<p>
				<?php esc_html_e('En Momentum Cocoyoc transformamos tus ideas en propiedades exclusivas en Lomas de Cocoyoc, garantizando calidad, estilo y plusvalía.', 'momentum'); ?>
			</p>

			<?php if ($whatsapp_url !== '') : ?>
				<div class="momentum-construccion-cta">
					<a class="momentum-btn momentum-btn--accent" href="<?php echo esc_url($whatsapp_url); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e('Cotizar mi proyecto', 'momentum'); ?>
					</a>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
