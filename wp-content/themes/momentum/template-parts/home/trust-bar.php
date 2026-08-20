<?php
/**
 * Home — "¿Por qué elegirnos?" (5 diferenciadores).
 */

if (! defined('ABSPATH')) {
	exit;
}

$items = [
	[
		'icon'  => 'shield',
		'title' => __('Experiencia local', 'momentum'),
		'text'  => __('Más de 20 años en el mercado inmobiliario de Lomas de Cocoyoc.', 'momentum'),
	],
	[
		'icon'  => 'map',
		'title' => __('Conocimiento de la zona', 'momentum'),
		'text'  => __('Sabemos lo que hace especial a Lomas de Cocoyoc y sus alrededores.', 'momentum'),
	],
	[
		'icon'  => 'handshake',
		'title' => __('Acompañamiento total', 'momentum'),
		'text'  => __('Te asesoramos en cada paso del proceso, de principio a fin.', 'momentum'),
	],
	[
		'icon'  => 'document',
		'title' => __('Seguridad jurídica', 'momentum'),
		'text'  => __('Procesos transparentes y respaldo legal en todas nuestras operaciones.', 'momentum'),
	],
	[
		'icon'  => 'star',
		'title' => __('Atención personalizada', 'momentum'),
		'text'  => __('Soluciones a la medida de tus necesidades e inversión.', 'momentum'),
	],
];
?>
<section class="momentum-section momentum-section--light">
	<div class="momentum-container">
		<div class="momentum-section-heading momentum-section-heading--center">
			<div>
				<span class="momentum-eyebrow"><?php esc_html_e('¿Por qué elegirnos?', 'momentum'); ?></span>
				<h2><?php esc_html_e('Tu inversión en las mejores manos', 'momentum'); ?></h2>
			</div>
		</div>

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
