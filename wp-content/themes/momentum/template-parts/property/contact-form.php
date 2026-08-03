<?php
/**
 * Property detail — sidebar contact card: botón "Me interesa" (WhatsApp) +
 * teléfonos. Ya no incluye el formulario nombre/teléfono/correo/mensaje
 * (era solo visual, sin submit handler) — se quitó a pedido explícito,
 * dejando el WhatsApp como único canal de contacto de esta tarjeta.
 *
 * Cuando la propiedad no está disponible (momentum_is_property_available()),
 * este CTA específico se reemplaza por uno genérico hacia el listado
 * correspondiente — "Me interesa esta propiedad" sería engañoso si ya se
 * vendió/rentó. El botón flotante de WhatsApp del sitio (template-parts/
 * whatsapp-float.php) es un componente aparte y no se toca: sigue siendo
 * el canal general de contacto en toda página, disponible o no.
 */

if (! defined('ABSPATH')) {
	exit;
}

$phone_1 = momentum_get_phone_1();
$phone_2 = momentum_get_phone_2();

$property_id   = get_the_ID();
$property_data = momentum_get_propiedad($property_id);
$es_terreno    = get_post_type($property_id) === 'terrenos';

if (! momentum_is_property_available($property_id)) :
	// Listado al que mandar al visitante: mismo tipo de operación cuando
	// aplica (propiedades en Venta/Renta), o Terrenos para ese CPT (no
	// tiene 'tipo_operacion', ver momentum_terreno_meta_schema()).
	// momentum_url_for_page() ya resuelve la URL en el idioma actual.
	$listado_slug = $es_terreno
		? 'terrenos'
		: ($property_data['tipo_operacion'] === 'Renta' ? 'renta' : 'venta');
	?>
	<div class="momentum-property-contact-card momentum-property-contact-card--unavailable">
		<h3><?php echo esc_html__('Esta propiedad ya no se encuentra disponible.', 'momentum'); ?></h3>
		<p><?php echo esc_html__('Pero seguramente tenemos otras opciones que te pueden interesar.', 'momentum'); ?></p>

		<a
			class="momentum-btn momentum-btn--accent momentum-btn--block"
			href="<?php echo esc_url(momentum_url_for_page($listado_slug)); ?>"
		>
			<?php echo esc_html__('Ver propiedades disponibles', 'momentum'); ?>
		</a>

		<?php if ($phone_1 !== '' || $phone_2 !== '') : ?>
			<div class="momentum-property-contact-card__phone">
				<span><?php echo esc_html__('o llámanos', 'momentum'); ?></span>
				<div class="momentum-phone-group">
					<?php if ($phone_1 !== '') : ?>
						<a href="<?php echo esc_attr(momentum_phone_to_tel_link($phone_1)); ?>">
							<?php echo momentum_icon('phone'); ?>
							<?php echo esc_html($phone_1); ?>
						</a>
					<?php endif; ?>
					<?php if ($phone_2 !== '') : ?>
						<a href="<?php echo esc_attr(momentum_phone_to_tel_link($phone_2)); ?>">
							<?php echo momentum_icon('phone'); ?>
							<?php echo esc_html($phone_2); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return;
endif;

// Botón "Me interesa": mismo número que el resto del sitio (fallback al
// teléfono 2 si el 1 está vacío, igual que template-parts/whatsapp-float.php),
// con un mensaje prellenado que identifica el inmueble por su código. Si no
// tiene código capturado, se usa el título como identificador de respaldo
// para que el botón nunca quede roto por falta de ese dato.
$identificador   = $property_data['codigo_propiedad'] !== '' ? $property_data['codigo_propiedad'] : get_the_title($property_id);
$interes_mensaje = $es_terreno
	? sprintf(
		/* translators: %s: código o título del terreno, para el mensaje prellenado de WhatsApp */
		__('Hola, me interesa el terreno %s visto en tu sitio web.', 'momentum'),
		$identificador
	)
	: sprintf(
		/* translators: %s: código o título de la propiedad, para el mensaje prellenado de WhatsApp */
		__('Hola, me interesa la propiedad %s vista en tu sitio web.', 'momentum'),
		$identificador
	);
$interes_whatsapp_url = momentum_phone_to_whatsapp_url($phone_1 ?: $phone_2, $interes_mensaje);
?>
<div class="momentum-property-contact-card">
	<h3><?php esc_html_e('¿Interesado en esta propiedad?', 'momentum'); ?></h3>
	<p><?php esc_html_e('Contáctanos para más información o agendar una visita.', 'momentum'); ?></p>

	<?php if ($interes_whatsapp_url !== '') : ?>
		<a
			class="momentum-btn momentum-btn--accent momentum-btn--block"
			href="<?php echo esc_url($interes_whatsapp_url); ?>"
			target="_blank"
			rel="noopener noreferrer"
		>
			<?php echo momentum_icon('whatsapp'); ?>
			<?php esc_html_e('Me interesa', 'momentum'); ?>
		</a>
	<?php endif; ?>

	<?php if ($phone_1 !== '' || $phone_2 !== '') : ?>
		<div class="momentum-property-contact-card__phone">
			<span><?php esc_html_e('o llámanos', 'momentum'); ?></span>
			<div class="momentum-phone-group">
				<?php if ($phone_1 !== '') : ?>
					<a href="<?php echo esc_attr(momentum_phone_to_tel_link($phone_1)); ?>">
						<?php echo momentum_icon('phone'); ?>
						<?php echo esc_html($phone_1); ?>
					</a>
				<?php endif; ?>
				<?php if ($phone_2 !== '') : ?>
					<a href="<?php echo esc_attr(momentum_phone_to_tel_link($phone_2)); ?>">
						<?php echo momentum_icon('phone'); ?>
						<?php echo esc_html($phone_2); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
</div>
