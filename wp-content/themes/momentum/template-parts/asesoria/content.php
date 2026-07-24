<?php
/**
 * Asesoría — intro text, bank logos, closing text.
 */

if (! defined('ABSPATH')) {
	exit;
}
?>
<section class="momentum-section">
	<div class="momentum-container">
		<div class="momentum-prose momentum-prose--wide">
			<p>
				Somos asesores profesionales con más de 20 años de experiencia en el ramo
				inmobiliario, te ayudamos a tramitar tu crédito hipotecario para la adquisición de
				un bien inmueble. Trabajamos con todos los bancos.
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
				Si tienes dudas, con gusto podemos ayudarte y asesorarte, ya que somos
				especialistas en productos, procesos, requisitos y todo lo necesario para que
				puedas adquirir algún patrimonio; analizando las opciones y alternativas que más
				se acomoden a tus necesidades.
			</p>
		</div>
	</div>
</section>
