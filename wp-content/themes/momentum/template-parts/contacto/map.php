<?php
/**
 * Contacto — embedded map (no API key required).
 */

if (! defined('ABSPATH')) {
	exit;
}

$address = momentum_get_address();

if ($address === '') {
	return;
}

// Reemplaza saltos de línea por comas para un query de geocoding más limpio
// (la dirección se captura como textarea multilínea en Admin > General).
$address_query = str_replace(["\r\n", "\n"], ', ', $address);
$map_src        = 'https://www.google.com/maps?q=' . rawurlencode($address_query) . '&output=embed';
?>
<section class="momentum-section momentum-section--tight momentum-section--light">
	<div class="momentum-container">
		<div class="momentum-map">
			<iframe
				src="<?php echo esc_url($map_src); ?>"
				width="100%"
				height="100%"
				style="border:0"
				allowfullscreen
				loading="lazy"
				referrerpolicy="no-referrer-when-downgrade"
				title="Ubicación de Momentum Cocoyoc"
			></iframe>
		</div>
	</div>
</section>
