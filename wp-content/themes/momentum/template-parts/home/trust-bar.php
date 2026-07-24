<?php
/**
 * Home — Trust bar (4 pillars).
 */

if (! defined('ABSPATH')) {
	exit;
}

$items = [
	[
		'icon'  => 'compass',
		'title' => 'Asesoría personalizada',
		'text'  => 'Te acompañamos en cada etapa de tu compra, venta o inversión inmobiliaria.',
	],
	[
		'icon'  => 'shield',
		'title' => 'Procesos seguros',
		'text'  => 'Gestionamos la documentación y los trámites legales con total transparencia.',
	],
	[
		'icon'  => 'map',
		'title' => 'Experiencia local',
		'text'  => 'Conocemos a fondo el mercado de Lomas de Cocoyoc y sus alrededores.',
	],
	[
		'icon'  => 'headset',
		'title' => 'Atención dedicada',
		'text'  => 'Un asesor te acompaña y resuelve tus dudas en cada paso del proceso.',
	],
];
?>
<section class="momentum-section momentum-section--tight">
	<div class="momentum-container">
		<div class="momentum-trust-bar">
			<?php foreach ($items as $item) : ?>
				<div class="momentum-trust-item">
					<div class="momentum-trust-item__icon"><?php echo momentum_icon($item['icon']); ?></div>
					<h3><?php echo esc_html($item['title']); ?></h3>
					<p><?php echo esc_html($item['text']); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
