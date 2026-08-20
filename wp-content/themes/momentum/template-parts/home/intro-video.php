<?php
/**
 * Home — Intro video overlay (autoplay muted, con botón "Omitir intro").
 *
 * Se renderiza con el atributo `hidden` puesto por PHP; assets/js/intro-video.js
 * lo destapa en cuanto corre, salvo que sessionStorage indique que el
 * visitante ya lo vio u omitió en esta sesión (así no se repite en cada
 * página vista del mismo visitante).
 *
 * El video NO se carga con el HTML inicial: preload="none" y sin `src` en
 * las <source> (van en `data-src`) para que el navegador no descargue ni un
 * byte de video hasta que assets/js/intro-video.js decida reproducirlo. El
 * `poster` sí se pinta de inmediato -es una imagen aparte, ~135 KB- y ocupa
 * el mismo espacio que ocupará el video (`width`/`height` fijos, además del
 * aspect-ratio ya definido en intro-video.css), así que no hay salto de
 * layout entre el poster y el primer fotograma real.
 */

if (! defined('ABSPATH')) {
	exit;
}

$video_url      = momentum_get_intro_video_url();
$video_webm_url = momentum_get_intro_video_webm_url();
$poster_url     = momentum_get_intro_video_poster_url();
?>
<div class="momentum-intro-video" data-momentum-intro-video hidden>
	<div class="momentum-intro-video__frame">
		<button type="button" class="momentum-intro-video__skip" data-momentum-intro-skip>
			<?php esc_html_e('Omitir intro', 'momentum'); ?>
			<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M5 5v14l11-7zM18 5h2v14h-2z"/></svg>
		</button>

		<video
			class="momentum-intro-video__player"
			data-momentum-intro-player
			muted
			playsinline
			controls
			preload="none"
			width="960"
			height="540"
			<?php if ($poster_url !== '') : ?>poster="<?php echo esc_url($poster_url); ?>"<?php endif; ?>
		>
			<?php if ($video_webm_url !== '') : ?>
				<source data-src="<?php echo esc_url($video_webm_url); ?>" type="video/webm">
			<?php endif; ?>
			<source data-src="<?php echo esc_url($video_url); ?>" type="video/mp4">
		</video>
	</div>
</div>
