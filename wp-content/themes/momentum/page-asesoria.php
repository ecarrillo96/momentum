<?php
/**
 * Asesoría — mortgage/financial advisory page.
 *
 * WordPress selects this file automatically for the page with slug
 * "asesoria" (page-{slug}.php takes priority over page.php).
 *
 * @package Momentum
 */

get_header();

get_template_part('template-parts/page-hero', null, [
	'title'    => 'Asesoría',
	'subtitle' => 'Acompañamiento experto para tu crédito hipotecario, con todos los bancos.',
]);

get_template_part('template-parts/asesoria/content');

get_footer();
