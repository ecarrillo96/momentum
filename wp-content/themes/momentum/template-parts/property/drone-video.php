<?php
/**
 * Property detail — drone video placeholder.
 *
 * Solo se muestra para terrenos (post type 'terrenos'); propiedades no
 * renderiza nada aquí.
 */

if (! defined('ABSPATH')) {
	exit;
}

if (get_post_type() !== 'terrenos') {
	return;
}
?>
<div class="momentum-property-block">
	<div class="momentum-amenities-placeholder">
		<?php
		// TODO(contenido pendiente): placeholder de desarrollo, no es copy final.
		esc_html_e('Aquí va video de dron', 'momentum');
		?>
	</div>
</div>
