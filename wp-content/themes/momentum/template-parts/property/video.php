<?php
/**
 * Property detail — main video (video_url): título + botón de ancho
 * completo que abre el video en una pestaña nueva (sin embed inline).
 *
 * Solo la versión en español usa esta plantilla; 'video_url_en' se guarda
 * pero no se consume aquí (queda listo para la futura versión en inglés).
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
	<h2>Video</h2>

	<a class="momentum-btn momentum-btn--outline momentum-btn--block" href="<?php echo esc_url($video_url); ?>" target="_blank" rel="noopener noreferrer">
		Ver video
	</a>
</div>
