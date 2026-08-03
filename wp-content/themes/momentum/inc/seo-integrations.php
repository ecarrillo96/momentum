<?php
/**
 * Google Analytics 4 y verificación de Google Search Console — ambos
 * quedan preparados para conectarse desde Admin > General (campos
 * 'momentum_ga_measurement_id' y 'momentum_gsc_verification', registrados
 * en inc/site-settings.php) sin tocar código: mientras el campo esté
 * vacío, esta pieza no imprime absolutamente nada en el <head> -no hay
 * ningún ID de ejemplo hardcodeado en ningún lado-.
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Snippet estándar de gtag.js (Google Analytics 4). Prioridad 5: debe
 * cargar temprano en <head>, antes de cualquier otro script, siguiendo la
 * recomendación de Google.
 */
add_action('wp_head', function (): void {
	$measurement_id = momentum_get_ga_measurement_id();

	if ($measurement_id === '') {
		return;
	}
	?>
	<!-- Google tag (gtag.js) -->
	<script async src="<?php echo esc_url('https://www.googletagmanager.com/gtag/js?id=' . $measurement_id); ?>"></script>
	<script>
		window.dataLayer = window.dataLayer || [];
		function gtag(){dataLayer.push(arguments);}
		gtag('js', new Date());
		gtag('config', '<?php echo esc_js($measurement_id); ?>');
	</script>
	<?php
}, 5);

/**
 * Meta tag de verificación de Google Search Console (método "Etiqueta
 * HTML"). El valor ya llega saneado a solo [A-Za-z0-9_-] desde
 * momentum_sanitize_gsc_verification(), pero se escapa igual por
 * consistencia con el resto del tema -nunca se confía en un valor
 * guardado como si ya fuera seguro para salida-.
 */
add_action('wp_head', function (): void {
	$verification = momentum_get_gsc_verification();

	if ($verification === '') {
		return;
	}

	printf('<meta name="google-site-verification" content="%s">' . "\n", esc_attr($verification));
}, 5);
