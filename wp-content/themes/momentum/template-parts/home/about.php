<?php
/**
 * Home — "Sobre Momentum Cocoyoc": contexto de marca (SEO + confianza) con
 * stats y foto de la zona.
 */

if (! defined('ABSPATH')) {
	exit;
}

$stats = [
	['icon' => 'check', 'value' => '+10', 'label' => __('años de experiencia', 'momentum')],
	['icon' => 'home',  'value' => '+250', 'label' => __('propiedades comercializadas', 'momentum')],
	['icon' => 'heart', 'value' => '100%', 'label' => __('compromiso con nuestros clientes', 'momentum')],
];

// Foto representativa de la zona (misma galería de imágenes de propiedades).
$image_id = 66;

// Sin foto en el hero (template-parts/home/hero.php es solo texto), esta es
// la primera imagen visible de la home -confirmado como el elemento LCP
// reportado por SEO Site Checkup (10.8s): estaba hardcodeada como <img> con
// loading="lazy" y sin width/height/srcset, así que el navegador ni
// siquiera reservaba el espacio ni empezaba a descargarla hasta que un
// IntersectionObserver la detectara cerca del viewport. wp_get_attachment_image()
// con loading="eager" + fetchpriority="high" replica exactamente el mismo
// patrón ya usado (y confirmado por Lighthouse) en nosotros/content.php,
// asesoria/content.php y single.php para su respectivo elemento LCP.
// 'large' (1024px) es de sobra: en desktop la columna de la imagen mide
// ~588px (ver .momentum-about en home.css), así que cubre incluso pantallas
// de alta densidad sin servir un archivo más pesado del necesario.
?>
<section class="momentum-section" id="sobre-momentum">
	<div class="momentum-container momentum-about">
		<div class="momentum-about__content">
			<span class="momentum-eyebrow"><?php esc_html_e('Sobre Momentum Cocoyoc', 'momentum'); ?></span>
			<h2><?php esc_html_e('Inmobiliaria especializada en Lomas de Cocoyoc, Morelos', 'momentum'); ?></h2>

			<p>
				<?php esc_html_e('En Momentum Cocoyoc tenemos más de 10 años de experiencia ayudando a familias e inversionistas a comprar, vender, rentar y construir en una de las zonas con mayor plusvalía de Morelos.', 'momentum'); ?>
			</p>

			<p>
				<?php esc_html_e('Conocemos el mercado local, sus fraccionamientos, servicios y regulaciones, lo que nos permite ofrecerte asesoría precisa, segura y personalizada para cada etapa del proceso.', 'momentum'); ?>
			</p>

			<div class="momentum-about__stats">
				<?php foreach ($stats as $stat) : ?>
					<div class="momentum-about__stat">
						<?php echo momentum_icon($stat['icon']); ?>
						<span><strong><?php echo esc_html($stat['value']); ?></strong> <?php echo esc_html($stat['label']); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<?php if (wp_attachment_is_image($image_id)) : ?>
			<div class="momentum-about__media">
				<?php
				echo wp_get_attachment_image(
					$image_id,
					'large',
					false,
					[
						'alt'           => momentum_attachment_alt($image_id, __('Lomas de Cocoyoc', 'momentum')),
						'sizes'         => '(max-width: 900px) 100vw, 588px',
						'loading'       => 'eager',
						'fetchpriority' => 'high',
						'decoding'      => 'async',
					]
				);
				?>
			</div>
		<?php endif; ?>
	</div>
</section>
