<?php
/**
 * Contacto — info, contact form and map.
 *
 * WordPress selects this file automatically for the page with slug
 * "contacto" (page-{slug}.php takes priority over page.php).
 *
 * @package Momentum
 */

get_header();

get_template_part('template-parts/page-hero', null, [
	'title'    => __('Contacto', 'momentum'),
	'subtitle' => __('Estamos para ayudarte. Escríbenos, llámanos o visítanos.', 'momentum'),
]);
?>

<section class="momentum-section">
	<div class="momentum-container momentum-contact-layout">
		<div class="momentum-contact-layout__main">
			<?php get_template_part('template-parts/contacto/banner'); ?>
		</div>

		<aside class="momentum-contact-layout__sidebar">
			<?php get_template_part('template-parts/contacto/info'); ?>
		</aside>
	</div>
</section>

<?php
get_template_part('template-parts/contacto/map');

get_footer();
