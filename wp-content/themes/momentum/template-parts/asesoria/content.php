<?php
/**
 * Asesoría — intro text, bank logos, closing text.
 */

if (! defined('ABSPATH')) {
	exit;
}

$whatsapp_message = __('Hola, me interesa tramitar mi crédito, vengo desde su sitio web.', 'momentum');
$whatsapp_phone   = momentum_get_phone_1() ?: momentum_get_phone_2();
$whatsapp_url     = momentum_phone_to_whatsapp_url($whatsapp_phone, $whatsapp_message);
?>
<section class="momentum-section">
	<div class="momentum-container">
		<div class="momentum-prose momentum-prose--wide">
			<p>
				<?php esc_html_e('Somos asesores profesionales con más de 20 años de experiencia en el ramo inmobiliario, te ayudamos a tramitar tu crédito hipotecario para la adquisición de un bien inmueble. Trabajamos con todos los bancos.', 'momentum'); ?>
			</p>
		</div>
	</div>
</section>

<section class="momentum-section">
	<div class="momentum-container">
		<?php
		// page-hero above this is text-only and the intro paragraph is short,
		// so this photo is this page's largest above-the-fold element (same
		// pattern confirmed via Lighthouse on /nosotros/ and the blog single).
		echo wp_get_attachment_image(
			89,
			'1536x1536',
			false,
			[
				'class'         => 'momentum-featured-photo',
				'alt'           => momentum_attachment_alt(89, get_the_title()),
				'sizes'         => '(max-width: 1280px) 100vw, 1232px',
				'loading'       => 'eager',
				'fetchpriority' => 'high',
				'decoding'      => 'async',
			]
		);
		?>
	</div>
</section>

<section class="momentum-section">
	<div class="momentum-container">
		<div class="momentum-prose momentum-prose--wide">
			<p>
				<?php esc_html_e('Si tienes dudas, con gusto podemos ayudarte y asesorarte, ya que somos especialistas en productos, procesos, requisitos y todo lo necesario para que puedas adquirir algún patrimonio; analizando las opciones y alternativas que más se acomoden a tus necesidades.', 'momentum'); ?>
			</p>

			<?php if ($whatsapp_url !== '') : ?>
				<div class="momentum-asesoria-cta">
					<a class="momentum-btn momentum-btn--accent" href="<?php echo esc_url($whatsapp_url); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e('Tramitar mi crédito', 'momentum'); ?>
					</a>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
