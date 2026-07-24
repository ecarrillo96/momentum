<?php
/**
 * Single Propiedad — property detail page.
 *
 * WordPress selects this file automatically for single posts of the
 * "propiedades" CPT (single-{post_type}.php takes priority over single.php).
 *
 * Kept thin on purpose: the layout lives in template-parts/property/detail.php,
 * shared with single-terrenos.php.
 *
 * @package Momentum
 */

get_header();

while (have_posts()) :
	the_post();

	get_template_part('template-parts/property/detail');

endwhile;

get_footer();
