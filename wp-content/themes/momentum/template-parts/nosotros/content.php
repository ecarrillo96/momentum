<?php
/**
 * Nosotros — image, company story, image.
 */

if (! defined('ABSPATH')) {
	exit;
}
?>

<?php
// Full-bleed prose-width photos (.momentum-featured-photo, width:100% up to
// the 1232px content width). '1536x1536' keeps them sharp without shipping
// the raw original; falls back to 'full' automatically if that intermediate
// size is missing for a given attachment.
$featured_photo_sizes = '(max-width: 1280px) 100vw, 1232px';
?>
<section class="momentum-section">
	<div class="momentum-container">
		<?php
		// Confirmed via Lighthouse: this is the LCP element on /nosotros/
		// (page-hero above it is text-only).
		echo wp_get_attachment_image(
			87,
			'1536x1536',
			false,
			[
				'class'         => 'momentum-featured-photo',
				'sizes'         => $featured_photo_sizes,
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
			<span class="momentum-eyebrow">Quiénes somos</span>

			<p>
				En Momentum Cocoyoc S.A. de C.V. somos una inmobiliaria en Lomas de Cocoyoc con
				más de 15 años de experiencia en el mercado de bienes raíces en Morelos. Desde
				nuestra fundación en 2008, nos especializamos en la venta y renta de casas,
				terrenos y residencias de lujo en Lomas de Cocoyoc, ofreciendo a cada cliente un
				servicio profesional, cercano y confiable.
			</p>

			<p>
				Nuestro compromiso es ayudarte a encontrar la propiedad ideal, ya sea una casa en
				venta en Lomas de Cocoyoc, un terreno para invertir o una residencia con plusvalía
				y ubicación privilegiada cerca de la CDMX.
			</p>

			<p>
				Gracias a nuestro crecimiento constante y la confianza de nuestros clientes, hoy
				nos consolidamos como una opción segura para quienes buscan comprar casa en Lomas
				de Cocoyoc con la tranquilidad de estar respaldados por asesores expertos.
			</p>

			<p>
				En Momentum Cocoyoc, nuestro mayor objetivo es la satisfacción de cada cliente,
				acompañándote en todo el proceso para que tu experiencia de compra sea clara,
				segura y exitosa.
			</p>
		</div>
	</div>
</section>

<section class="momentum-section">
	<div class="momentum-container">
		<?php
		echo wp_get_attachment_image(
			88,
			'1536x1536',
			false,
			[
				'class'    => 'momentum-featured-photo',
				'sizes'    => $featured_photo_sizes,
				'loading'  => 'lazy',
				'decoding' => 'async',
			]
		);
		?>
	</div>
</section>
