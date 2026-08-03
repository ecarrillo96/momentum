<?php
/**
 * Property detail — main video (video_url): título + botón de ancho
 * completo que abre el video en una pestaña nueva (sin embed inline).
 *
 * Esta misma plantilla se usa para ambos idiomas, sin ninguna rama de
 * idioma aquí: cada ficha (ES o EN) es un post distinto con su propio
 * 'video_url'. 'video_url_en' solo existe en la ficha en español -es el
 * campo donde se captura el video en inglés-; al guardar esa ficha,
 * Momentum_Property_Translation_Service::sync_shared_fields() copia su
 * valor al 'video_url' de la ficha en inglés (o el mismo video en español
 * si 'video_url_en' aún no se ha capturado). Por eso basta con leer
 * 'video_url' del post actual para que salga el video correcto en cada
 * idioma.
 */

if (! defined('ABSPATH')) {
	exit;
}

$video_url = (string) get_post_meta(get_the_ID(), 'video_url', true);

if ($video_url === '') {
	return;
}
?>
<div class="momentum-property-block">
	<h2><?php esc_html_e('Video', 'momentum'); ?></h2>

	<a class="momentum-btn momentum-btn--outline momentum-btn--block" href="<?php echo esc_url($video_url); ?>" target="_blank" rel="noopener noreferrer">
		<?php esc_html_e('Ver video', 'momentum'); ?>
	</a>
</div>
