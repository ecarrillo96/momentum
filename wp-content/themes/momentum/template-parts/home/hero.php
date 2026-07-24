<?php
/**
 * Home — Hero section.
 */

if (! defined('ABSPATH')) {
	exit;
}
?>
<section class="momentum-hero">
	<video
		class="momentum-hero__video"
		autoplay
		muted
		loop
		playsinline
		poster="<?php echo esc_url(momentum_get_hero_poster_url()); ?>"
	>
		<source src="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/video/hero-dummy.mp4'); ?>" type="video/mp4">
	</video>

	<div class="momentum-container">
		<div class="momentum-hero__inner">
			<span class="momentum-hero__eyebrow">Bienes raíces en Lomas de Cocoyoc</span>

			<h1>Encuentra tu próximo hogar en Lomas de Cocoyoc</h1>

			<p>
				Compra, renta o invierte en las propiedades mejor ubicadas de la región,
				con el respaldo de un equipo especializado que te acompaña de principio a fin.
			</p>

			<div class="momentum-hero__actions">
				<a class="momentum-btn momentum-btn--accent" href="<?php echo esc_url(home_url('/venta/')); ?>">Ver propiedades en venta</a>
				<a class="momentum-btn momentum-btn--outline" href="<?php echo esc_url(home_url('/renta/')); ?>">Ver propiedades en renta</a>
			</div>

			<div class="momentum-hero__stats">
				<div class="momentum-hero__stat">
					<strong>+12</strong>
					<span>Propiedades disponibles</span>
				</div>
				<div class="momentum-hero__stat">
					<strong>+10 años</strong>
					<span>De experiencia local</span>
				</div>
				<div class="momentum-hero__stat">
					<strong>100%</strong>
					<span>Asesoría acompañada</span>
				</div>
			</div>
		</div>
	</div>
</section>
