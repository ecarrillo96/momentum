<?php
/**
 * Home — Intro video overlay (autoplay muted, con botón "Omitir intro").
 *
 * Se renderiza con el atributo `hidden` puesto por PHP; assets/js/intro-video.js
 * lo destapa en cuanto corre, salvo que sessionStorage indique que el
 * visitante ya lo vio u omitió en esta sesión (así no se repite en cada
 * página vista del mismo visitante).
 */

if (! defined('ABSPATH')) {
	exit;
}

$video_url = momentum_get_intro_video_url();
?>
<div class="momentum-intro-video" data-momentum-intro-video hidden>
	<div class="momentum-intro-video__frame">
		<button type="button" class="momentum-intro-video__skip" data-momentum-intro-skip>
			<?php esc_html_e('Omitir intro', 'momentum'); ?>
			<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M5 5v14l11-7zM18 5h2v14h-2z"/></svg>
		</button>

		<video
			class="momentum-intro-video__player"
			src="<?php echo esc_url($video_url); ?>"
			data-momentum-intro-player
			autoplay
			muted
			playsinline
			controls
			preload="auto"
		></video>
	</div>
</div>
