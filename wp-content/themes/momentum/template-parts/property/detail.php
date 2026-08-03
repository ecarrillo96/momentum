<?php
/**
 * Detail layout shared by single-propiedades.php y single-terrenos.php.
 * Extraído de single-propiedades.php para que ambos post types reutilicen la
 * misma estructura sin duplicar el archivo. Debe incluirse dentro del loop
 * (the_post() ya llamado).
 */

if (! defined('ABSPATH')) {
	exit;
}

get_template_part('template-parts/property/availability-notice');
get_template_part('template-parts/property/header');
get_template_part('template-parts/property/gallery');
?>

<section class="momentum-section">
	<div class="momentum-container momentum-property-layout">
		<div class="momentum-property-layout__main">
			<?php
			get_template_part('template-parts/property/description');
			get_template_part('template-parts/property/features');
			get_template_part('template-parts/property/drone-video');
			get_template_part('template-parts/property/video');
			get_template_part('template-parts/property/map');
			?>
		</div>

		<aside class="momentum-property-layout__sidebar">
			<?php
			get_template_part('template-parts/property/contact-form');
			get_template_part('template-parts/property/info-general');
			?>
		</aside>
	</div>
</section>

<?php
get_template_part('template-parts/property/similar');
