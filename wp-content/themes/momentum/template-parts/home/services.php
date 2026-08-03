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
		'title' => __('Venta', 'momentum'),
		'text'  => __('Encuentra la casa, departamento o terreno ideal para comprar en Lomas de Cocoyoc.', 'momentum'),
		'slug'  => 'venta',
	],
	[
		'icon'  => 'home',
		'title' => __('Renta', 'momentum'),
		'text'  => __('Propiedades amuebladas y sin amueblar, listas para tu siguiente etapa.', 'momentum'),
		'slug'  => 'renta',
	],
	[
		'icon'  => 'land',
		'title' => __('Terrenos', 'momentum'),
		'text'  => __('Lotes residenciales para construir el hogar que tienes en mente.', 'momentum'),
		'slug'  => 'terrenos',
	],
	[
		'icon'  => 'tool',
		'title' => __('Construcción', 'momentum'),
		'text'  => __('Diseño y construcción de vivienda a la medida, de principio a fin.', 'momentum'),
		'slug'  => 'construccion',
	],
	[
		'icon'  => 'compass',
		'title' => __('Asesoría', 'momentum'),
		'text'  => __('Acompañamiento legal, financiero e inmobiliario en cada operación.', 'momentum'),
		'slug'  => 'asesoria',
	],
];
?>
<section class="momentum-section momentum-section--dark">
	<div class="momentum-container">
		<div class="momentum-section-heading">
			<div>
				<span class="momentum-eyebrow"><?php esc_html_e('Lo que hacemos', 'momentum'); ?></span>
				<h2><?php esc_html_e('Servicios pensados para cada etapa', 'momentum'); ?></h2>
				<p><?php esc_html_e('Ya sea que quieras comprar, rentar, invertir o construir, tenemos un servicio dedicado para ti.', 'momentum'); ?></p>
			</div>
		</div>

		<div class="momentum-services-grid">
			<?php foreach ($services as $service) : ?>
				<a class="momentum-service-card" href="<?php echo esc_url(momentum_url_for_page($service['slug'])); ?>">
					<div class="momentum-service-card__icon"><?php echo momentum_icon($service['icon']); ?></div>
					<h3><?php echo esc_html($service['title']); ?></h3>
					<p><?php echo esc_html($service['text']); ?></p>
					<span class="momentum-service-card__link"><?php esc_html_e('Conocer más →', 'momentum'); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
