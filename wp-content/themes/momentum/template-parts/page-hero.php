<?php
/**
 * Reusable banner-style hero for internal pages (Venta, Renta, Terrenos, etc).
 *
 * Usage:
 *   get_template_part('template-parts/page-hero', null, [
 *       'title'    => 'Propiedades en venta',
 *       'subtitle' => 'Encuentra la casa o terreno ideal en Lomas de Cocoyoc',
 *   ]);
 */

if (! defined('ABSPATH')) {
	exit;
}

$title    = $args['title'] ?? get_the_title();
$subtitle = $args['subtitle'] ?? '';
?>
<section class="momentum-page-hero">
	<div class="momentum-container">
		<h1><?php echo esc_html($title); ?></h1>

		<?php if ($subtitle) : ?>
			<p><?php echo esc_html($subtitle); ?></p>
		<?php endif; ?>
	</div>
</section>
