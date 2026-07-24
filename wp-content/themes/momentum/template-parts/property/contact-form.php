<?php
/**
 * Property detail — sidebar contact card: botón "Me interesa" (WhatsApp) +
 * teléfonos. Ya no incluye el formulario nombre/teléfono/correo/mensaje
 * (era solo visual, sin submit handler) — se quitó a pedido explícito,
 * dejando el WhatsApp como único canal de contacto de esta tarjeta.
 */

if (! defined('ABSPATH')) {
	exit;
}

$phone_1 = momentum_get_phone_1();
$phone_2 = momentum_get_phone_2();

// Botón "Me interesa": mismo número que el resto del sitio (fallback al
// teléfono 2 si el 1 está vacío, igual que template-parts/whatsapp-float.php),
// con un mensaje prellenado que identifica el inmueble por su código. Si no
// tiene código capturado, se usa el título como identificador de respaldo
// para que el botón nunca quede roto por falta de ese dato.
$property_id     = get_the_ID();
$property_data   = momentum_get_propiedad($property_id);
$es_terreno      = get_post_type($property_id) === 'terrenos';
$identificador   = $property_data['codigo_propiedad'] !== '' ? $property_data['codigo_propiedad'] : get_the_title($property_id);
$interes_mensaje = $es_terreno
	? "Hola, me interesa el terreno {$identificador} visto en tu sitio web."
	: "Hola, me interesa la propiedad {$identificador} vista en tu sitio web.";
$interes_whatsapp_url = momentum_phone_to_whatsapp_url($phone_1 ?: $phone_2, $interes_mensaje);
?>
<div class="momentum-property-contact-card">
	<h3>¿Interesado en esta propiedad?</h3>
	<p>Contáctanos para más información o agendar una visita.</p>

	<?php if ($interes_whatsapp_url !== '') : ?>
		<a
			class="momentum-btn momentum-btn--accent momentum-btn--block"
			href="<?php echo esc_url($interes_whatsapp_url); ?>"
			target="_blank"
			rel="noopener noreferrer"
		>
			<?php echo momentum_icon('whatsapp'); ?>
			Me interesa
		</a>
	<?php endif; ?>

	<?php if ($phone_1 !== '' || $phone_2 !== '') : ?>
		<div class="momentum-property-contact-card__phone">
			<span>o llámanos</span>
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
