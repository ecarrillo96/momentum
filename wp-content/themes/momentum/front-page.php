<?php
/**
 * Home page template.
 *
 * WordPress selects this file automatically for the static front page
 * (page_on_front), ahead of page.php, so Blocksy's default page hero/title
 * is never rendered here.
 *
 * @package Momentum
 */

get_header();
?>

<?php
get_template_part('template-parts/home/hero');
get_template_part('template-parts/home/trust-bar');
get_template_part('template-parts/home/featured-properties');
get_template_part('template-parts/home/services');
get_template_part('template-parts/blog-grid');
get_template_part('template-parts/home/cta-banner');
?>

<?php
get_footer();
