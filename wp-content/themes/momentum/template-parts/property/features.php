<?php
/**
 * Property detail — características checklist.
 */

if (! defined('ABSPATH')) {
	exit;
}

$caracteristicas = momentum_get_caracteristicas(get_the_ID());

if (! $caracteristicas) {
	return;
}
?>
<div class="momentum-property-block">
	<h2>Características</h2>
	<ul class="momentum-features-grid">
		<?php foreach ($caracteristicas as $item) : ?>
			<li>
				<?php echo momentum_icon('check'); ?>
				<span><?php echo esc_html($item); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
