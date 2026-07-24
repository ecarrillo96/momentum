<?php
/**
 * Property detail — embedded map + "Ver en Google Maps" link.
 */

if (! defined('ABSPATH')) {
	exit;
}

$data       = momentum_get_propiedad(get_the_ID());
$zona_label = momentum_get_zona_label($data['zona']);

if (! $zona_label) {
	return;
}

$query      = $zona_label;
$embed_src  = 'https://www.google.com/maps?q=' . rawurlencode($query) . '&output=embed';
$maps_link  = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($query);
?>
<div class="momentum-property-block">
	<h2>Ubicación</h2>

	<div class="momentum-map momentum-map--property">
		<iframe
			src="<?php echo esc_url($embed_src); ?>"
			width="100%"
			height="100%"
			style="border:0"
			allowfullscreen
			loading="lazy"
			referrerpolicy="no-referrer-when-downgrade"
			title="Ubicación de <?php the_title_attribute(); ?>"
		></iframe>
	</div>

	<div class="momentum-property-map__footer">
		<p><?php echo esc_html($zona_label); ?></p>
		<a class="momentum-btn momentum-btn--outline" href="<?php echo esc_url($maps_link); ?>" target="_blank" rel="noopener noreferrer">
			Ver en Google Maps
		</a>
	</div>
</div>
