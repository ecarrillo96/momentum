<?php
/**
 * Home — Hero section: fondo oscuro a todo el ancho (sin foto), con una
 * fila de 3 diferenciadores al pie.
 */

if (! defined('ABSPATH')) {
	exit;
}

$features = [
	['icon' => 'shield',     'text' => __('Asesoría personalizada en todo el proceso', 'momentum')],
	['icon' => 'map',        'text' => __('Conocimiento profundo del mercado local', 'momentum')],
	['icon' => 'handshake',  'text' => __('Acompañamiento legal y financiero', 'momentum')],
];
?>
<section class="momentum-hero">
	<div class="momentum-container">
		<div class="momentum-hero__content">
			<span class="momentum-hero__eyebrow"><?php esc_html_e('Inmobiliaria especializada en Lomas de Cocoyoc', 'momentum'); ?></span>

			<h1><?php esc_html_e('Casas, Terrenos y Propiedades en Venta en Lomas de Cocoyoc', 'momentum'); ?></h1>

			<p>
				<?php esc_html_e('Te acompañamos en cada paso para que encuentres la propiedad ideal para ti y tu familia.', 'momentum'); ?>
			</p>

			<a class="momentum-btn momentum-btn--accent" href="#sobre-momentum"><?php esc_html_e('Conoce más', 'momentum'); ?></a>
		</div>

		<div class="momentum-hero__features">
			<?php foreach ($features as $feature) : ?>
				<div class="momentum-hero__feature">
					<?php echo momentum_icon($feature['icon']); ?>
					<span><?php echo esc_html($feature['text']); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
