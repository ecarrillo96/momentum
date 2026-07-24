<?php
/**
 * Footer — Momentum child theme.
 *
 * Mirrors Blocksy's footer.php structure (closes #main-container, hooks,
 * wp_footer). Only the footer content is replaced, via
 * template-parts/site-footer.php.
 *
 * @package Momentum
 */

blocksy_after_current_template();
do_action('blocksy:content:bottom');
?>
	</main>

	<?php
	do_action('blocksy:content:after');
	do_action('blocksy:footer:before');

	get_template_part('template-parts/site-footer');

	do_action('blocksy:footer:after');
	?>
</div>

<?php get_template_part('template-parts/whatsapp-float'); ?>

<?php wp_footer(); ?>

</body>
</html>
