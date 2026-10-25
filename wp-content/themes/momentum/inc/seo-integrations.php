<?php
/**
 * Google Analytics 4, Google Ads, Search Console y Meta Pixel — todos
 * quedan preparados para conectarse desde Admin > General (campos
 * 'momentum_ga_measurement_id', 'momentum_ga_ads_conversion_id',
 * 'momentum_gsc_verification' y 'momentum_meta_pixel_id', registrados en
 * inc/site-settings.php) sin tocar código: mientras un campo esté vacío,
 * esta pieza no imprime absolutamente nada de esa integración en el
 * <head> -no hay ningún ID de ejemplo hardcodeado en ningún lado-.
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Snippet de gtag.js compartido por Google Analytics 4 y Google Ads: la
 * librería (gtag/js) solo debe cargarse UNA vez aunque ambos IDs estén
 * configurados a la vez, así que se reúnen primero y se emite un solo
 * <script> loader con un gtag('config', ...) por cada ID activo -en vez
 * de un loader duplicado por integración-. Prioridad 5: debe cargar
 * temprano en <head>, antes de cualquier otro script, siguiendo la
 * recomendación de Google.
 */
add_action('wp_head', function (): void {
	$ids = array_filter([
		momentum_get_ga_measurement_id(),
		momentum_get_ga_ads_conversion_id(),
	], static fn (string $id): bool => $id !== '');

	if (empty($ids)) {
		return;
	}
	?>
	<!-- Google tag (gtag.js) -->
	<script async src="<?php echo esc_url('https://www.googletagmanager.com/gtag/js?id=' . reset($ids)); ?>"></script>
	<script>
		window.dataLayer = window.dataLayer || [];
		function gtag(){dataLayer.push(arguments);}
		gtag('js', new Date());
		<?php foreach ($ids as $id) : ?>
		gtag('config', '<?php echo esc_js($id); ?>');
		<?php endforeach; ?>
	</script>
	<?php
}, 5);

/**
 * Meta Pixel (Facebook/Instagram Ads). Snippet estándar de Meta, con su
 * <noscript> de respaldo para navegadores sin JavaScript -mismo criterio
 * que el resto de esta pieza: nada se imprime mientras el ID esté vacío.
 */
add_action('wp_head', function (): void {
	$pixel_id = momentum_get_meta_pixel_id();

	if ($pixel_id === '') {
		return;
	}
	?>
	<!-- Meta Pixel Code -->
	<script>
		!function(f,b,e,v,n,t,s)
		{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
		n.callMethod.apply(n,arguments):n.queue.push(arguments)};
		if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
		n.queue=[];t=b.createElement(e);t.async=!0;
		t.src=v;s=b.getElementsByTagName(e)[0];
		s.parentNode.insertBefore(t,s)}(window, document,'script',
		'https://connect.facebook.net/en_US/fbevents.js');
		fbq('init', '<?php echo esc_js($pixel_id); ?>');
		fbq('track', 'PageView');
	</script>
	<noscript><img height="1" width="1" style="display:none"
		src="<?php echo esc_url('https://www.facebook.com/tr?id=' . $pixel_id . '&ev=PageView&noscript=1'); ?>"
	/></noscript>
	<!-- End Meta Pixel Code -->
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
