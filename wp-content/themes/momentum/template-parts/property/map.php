<?php
/**
 * Property detail — amenities placeholder.
 *
 * Solo se muestra para propiedades en la zona "Lomas de Cocoyoc"; el resto
 * de las zonas no renderiza nada aquí.
 */

if (! defined('ABSPATH')) {
	exit;
}

$data       = momentum_get_propiedad(get_the_ID());
$zona_label = momentum_get_zona_label($data['zona']);

if ($zona_label !== 'Lomas de Cocoyoc') {
	return;
}
?>
<div class="momentum-property-block">
	<div class="momentum-amenities-placeholder">
		<?php
		// TODO(contenido pendiente): placeholder de desarrollo, no es copy final.
		esc_html_e('Aquí va imagen de amenidades del fraccionamiento', 'momentum');
		?>
	</div>
</div>
