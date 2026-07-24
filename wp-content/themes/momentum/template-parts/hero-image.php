<?php
/**
 * Large full-bleed image hero, for pages that lead with a single strong
 * visual (Construcción, and reusable later for Nosotros, etc).
 *
 * Usage:
 *   get_template_part('template-parts/hero-image', null, [
 *       'image_id' => 48,
 *       'title'    => 'Construcción a la medida',
 *       'subtitle' => '...',
 *   ]);
 */

if (! defined('ABSPATH')) {
	exit;
}

$image_id = $args['image_id'] ?? 0;
$title    = $args['title'] ?? get_the_title();
$subtitle = $args['subtitle'] ?? '';

// CSS background-image (background-size:cover, full-bleed section), so it
// can't use srcset — '1536x1536' is a safe fixed upper bound that avoids
// shipping the raw original while staying sharp on wide screens. Falls
// back down the chain if a smaller original lacks that intermediate size.
$image_url = $image_id
	? (
		wp_get_attachment_image_url($image_id, '1536x1536')
		?: wp_get_attachment_image_url($image_id, 'large')
		?: wp_get_attachment_image_url($image_id, 'full')
	)
	: '';
?>
<section class="momentum-hero-image" <?php if ($image_url) : ?>style="background-image:url('<?php echo esc_url($image_url); ?>')"<?php endif; ?>>
	<div class="momentum-hero-image__overlay"></div>

	<div class="momentum-container momentum-hero-image__content">
		<h1><?php echo esc_html($title); ?></h1>

		<?php if ($subtitle) : ?>
			<p><?php echo esc_html($subtitle); ?></p>
		<?php endif; ?>
	</div>
</section>
