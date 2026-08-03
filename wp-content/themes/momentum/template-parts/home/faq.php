<?php
/**
 * Home — Preguntas frecuentes (acordeón con <details>/<summary>, sin JS).
 */

if (! defined('ABSPATH')) {
	exit;
}

$faqs = [
	[
		'q' => __('¿Dónde se encuentra Lomas de Cocoyoc?', 'momentum'),
		'a' => __('Lomas de Cocoyoc es un fraccionamiento residencial ubicado en el municipio de Yautepec, Morelos, muy cerca de Oaxtepec y a poco más de una hora de la Ciudad de México.', 'momentum'),
	],
	[
		'q' => __('¿Qué tipos de propiedades manejan?', 'momentum'),
		'a' => __('Trabajamos con casas y departamentos en venta y renta, así como terrenos residenciales dentro y alrededor de Lomas de Cocoyoc.', 'momentum'),
	],
	[
		'q' => __('¿Cómo agendo una visita?', 'momentum'),
		'a' => __('Puedes contactarnos por WhatsApp, teléfono o el formulario de contacto del sitio y coordinamos una visita en el horario que mejor te convenga.', 'momentum'),
	],
	[
		'q' => __('¿Los terrenos cuentan con servicios?', 'momentum'),
		'a' => __('La mayoría de los terrenos que manejamos cuentan con servicios básicos disponibles; te confirmamos el detalle específico de cada lote antes de tu visita.', 'momentum'),
	],
	[
		'q' => __('¿Ofrecen financiamiento?', 'momentum'),
		'a' => __('Sí, trabajamos con todos los bancos y te asesoramos en todo el proceso de crédito hipotecario.', 'momentum'),
	],
	[
		'q' => __('¿Pueden ayudarme a vender mi propiedad?', 'momentum'),
		'a' => __('Sí, ofrecemos asesoría completa para vender tu propiedad, desde la valuación hasta el cierre de la operación.', 'momentum'),
	],
	[
		'q' => __('¿También construyen casas?', 'momentum'),
		'a' => __('Sí, contamos con un equipo de construcción para diseñar y edificar tu casa a la medida, de principio a fin.', 'momentum'),
	],
	[
		'q' => __('¿Cuál es el costo de sus servicios?', 'momentum'),
		'a' => __('El costo depende del tipo de operación y servicio; contáctanos para darte una cotización personalizada sin compromiso.', 'momentum'),
	],
];
?>
<section class="momentum-section">
	<div class="momentum-container">
		<div class="momentum-section-heading momentum-section-heading--center">
			<div>
				<span class="momentum-eyebrow"><?php esc_html_e('Preguntas frecuentes', 'momentum'); ?></span>
				<h2><?php esc_html_e('Resolvemos tus dudas', 'momentum'); ?></h2>
			</div>
		</div>

		<div class="momentum-faq">
			<?php foreach ($faqs as $faq) : ?>
				<details class="momentum-faq__item">
					<summary>
						<span><?php echo esc_html($faq['q']); ?></span>
						<span class="momentum-faq__toggle" aria-hidden="true"></span>
					</summary>
					<p><?php echo esc_html($faq['a']); ?></p>
				</details>
			<?php endforeach; ?>
		</div>
	</div>
</section>
