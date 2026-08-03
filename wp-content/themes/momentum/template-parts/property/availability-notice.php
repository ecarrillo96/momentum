<?php
/**
 * Property detail — aviso de "ya no disponible".
 *
 * Se renderiza solo cuando momentum_is_property_available() es false (estado
 * fuera de momentum_public_estado_values(): Apartada, Vendida, Rentada). No
 * se elimina la ficha ni se redirige -sigue existiendo y respondiendo 200-,
 * pero esta franja avisa de inmediato, antes de la galería y el resto de la
 * info, para que nadie llegue a "Me interesa" pensando que sigue disponible
 * (ver template-parts/property/contact-form.php, que reacciona a la misma
 * función).
 *
 * Envuelto en __()/esc_html__() con el text domain del tema ('momentum', ya
 * declarado en style.css) aunque hoy el sitio es solo en español -no hay
 * WPML/Polylang ni ningún selector de idioma real en el proyecto (confirmado:
 * cero usos de __()/_e() en el tema antes de este archivo)-, así el texto
 * queda listo para traducirse el día que exista esa infraestructura, sin
 * inventar una todavía.
 */

if (! defined('ABSPATH')) {
	exit;
}

$property_id = get_the_ID();

if (momentum_is_property_available($property_id)) {
	return;
}
?>
<div class="momentum-availability-notice" role="status">
	<div class="momentum-container momentum-availability-notice__inner">
		<?php echo momentum_icon('info'); ?>
		<p><?php echo esc_html__('Esta propiedad ya no se encuentra disponible.', 'momentum'); ?></p>
	</div>
</div>
