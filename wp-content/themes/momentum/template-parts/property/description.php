<?php
/**
 * Property detail — description (post_content).
 */

if (! defined('ABSPATH')) {
	exit;
}

if (! get_the_content()) {
	return;
}
?>
<div class="momentum-property-block">
	<h2><?php esc_html_e('Descripción', 'momentum'); ?></h2>
	<div class="momentum-prose">
		<?php the_content(); ?>
	</div>
</div>
