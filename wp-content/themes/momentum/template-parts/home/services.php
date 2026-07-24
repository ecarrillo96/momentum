<?php
/**
 * Home — Services grid (links to the other main pages).
 */

if (! defined('ABSPATH')) {
	exit;
}

$services = [
	[
		'icon'  => 'key',
		'title' => 'Venta',
		'text'  => 'Encuentra la casa, departamento o terreno ideal para comprar en Lomas de Cocoyoc.',
		'url'   => '/venta/',
	],
	[
		'icon'  => 'home',
		'title' => 'Renta',
		'text'  => 'Propiedades amuebladas y sin amueblar, listas para tu siguiente etapa.',
		'url'   => '/renta/',
	],
	[
		'icon'  => 'land',
		'title' => 'Terrenos',
		'text'  => 'Lotes residenciales para construir el hogar que tienes en mente.',
		'url'   => '/terrenos/',
	],
	[
		'icon'  => 'tool',
		'title' => 'Construcción',
		'text'  => 'Diseño y construcción de vivienda a la medida, de principio a fin.',
		'url'   => '/construccion/',
	],
	[
		'icon'  => 'compass',
		'title' => 'Asesoría',
		'text'  => 'Acompañamiento legal, financiero e inmobiliario en cada operación.',
		'url'   => '/asesoria/',
	],
];
?>
<section class="momentum-section momentum-section--light">
	<div class="momentum-container">
		<div class="momentum-section-heading">
			<div>
				<span class="momentum-eyebrow">Lo que hacemos</span>
				<h2>Servicios pensados para cada etapa</h2>
				<p>Ya sea que quieras comprar, rentar, invertir o construir, tenemos un servicio dedicado para ti.</p>
			</div>
		</div>

		<div class="momentum-services-grid">
			<?php foreach ($services as $service) : ?>
				<a class="momentum-service-card" href="<?php echo esc_url(home_url($service['url'])); ?>">
					<div class="momentum-service-card__icon"><?php echo momentum_icon($service['icon']); ?></div>
					<h3><?php echo esc_html($service['title']); ?></h3>
					<p><?php echo esc_html($service['text']); ?></p>
					<span class="momentum-service-card__link">Conocer más →</span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
