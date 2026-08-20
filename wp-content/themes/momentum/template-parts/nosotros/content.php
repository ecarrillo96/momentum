<?php
/**
 * Nosotros — image, company story, image.
 *
 * Imagen principal/secundaria, eyebrow y contenido son editables desde
 * Admin > General > Nosotros (Momentum_Admin_Content_Pages, plugin
 * momentum-admin); ver inc/page-content-fields.php. Las imágenes son
 * compartidas entre idiomas (momentum_get_shared_page_meta()); el eyebrow y
 * el contenido son independientes por idioma (post meta de la página que se
 * esté viendo). Los IDs 87/88 y los <p> de abajo son el respaldo si algún
 * campo se vaciara, para que esta página nunca se quede sin imagen ni texto.
 */

if (! defined('ABSPATH')) {
	exit;
}

$nosotros_page_id = get_queried_object_id();

$main_image_id      = (int) momentum_get_shared_page_meta($nosotros_page_id, 'nosotros_main_image') ?: 87;
$secondary_image_id = (int) momentum_get_shared_page_meta($nosotros_page_id, 'nosotros_secondary_image') ?: 88;
$section_label       = get_post_meta($nosotros_page_id, 'nosotros_section_label', true);
$section_content     = get_post_meta($nosotros_page_id, 'nosotros_section_content', true);
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
		if (wp_attachment_is_image($main_image_id)) {
			echo wp_get_attachment_image(
				$main_image_id,
				'1536x1536',
				false,
				[
					'class'         => 'momentum-featured-photo',
					'alt'           => momentum_attachment_alt($main_image_id, get_the_title()),
					'sizes'         => $featured_photo_sizes,
					'loading'       => 'eager',
					'fetchpriority' => 'high',
					'decoding'      => 'async',
				]
			);
		}
		?>
	</div>
</section>

<section class="momentum-section">
	<div class="momentum-container">
		<div class="momentum-prose momentum-prose--wide">
			<span class="momentum-eyebrow"><?php echo esc_html($section_label ?: __('Quiénes somos', 'momentum')); ?></span>

			<?php if ($section_content !== '') : ?>
				<?php
			// wpautop(): el contenido guardado desde el editor WYSIWYG del panel
			// admin no siempre conserva <p> explícitos (TinyMCE puede devolver
			// párrafos separados solo por saltos de línea) -mismo tratamiento
			// que WordPress aplica de forma nativa a post_content en 'the_content',
			// así que un párrafo sin <p> nunca queda pegado al eyebrow ni a los
			// demás párrafos.
			echo wp_kses_post(wpautop($section_content));
			?>
			<?php else : ?>
				<p>
					<?php esc_html_e('En Momentum Cocoyoc S.A. de C.V. somos una inmobiliaria en Lomas de Cocoyoc con más de 15 años de experiencia en el mercado de bienes raíces en Morelos. Desde nuestra fundación en 2008, nos especializamos en la venta y renta de casas, terrenos y residencias de lujo en Lomas de Cocoyoc, ofreciendo a cada cliente un servicio profesional, cercano y confiable.', 'momentum'); ?>
				</p>

				<p>
					<?php esc_html_e('Nuestro compromiso es ayudarte a encontrar la propiedad ideal, ya sea una casa en venta en Lomas de Cocoyoc, un terreno para invertir o una residencia con plusvalía y ubicación privilegiada cerca de la CDMX.', 'momentum'); ?>
				</p>

				<p>
					<?php esc_html_e('Gracias a nuestro crecimiento constante y la confianza de nuestros clientes, hoy nos consolidamos como una opción segura para quienes buscan comprar casa en Lomas de Cocoyoc con la tranquilidad de estar respaldados por asesores expertos.', 'momentum'); ?>
				</p>

				<p>
					<?php esc_html_e('En Momentum Cocoyoc, nuestro mayor objetivo es la satisfacción de cada cliente, acompañándote en todo el proceso para que tu experiencia de compra sea clara, segura y exitosa.', 'momentum'); ?>
				</p>
			<?php endif; ?>
		</div>
	</div>
</section>

<section class="momentum-section">
	<div class="momentum-container">
		<?php
		if (wp_attachment_is_image($secondary_image_id)) {
			echo wp_get_attachment_image(
				$secondary_image_id,
				'1536x1536',
				false,
				[
					'class'    => 'momentum-featured-photo',
					'alt'      => momentum_attachment_alt($secondary_image_id, get_the_title()),
					'sizes'    => $featured_photo_sizes,
					'loading'  => 'lazy',
					'decoding' => 'async',
				]
			);
		}
		?>
	</div>
</section>
