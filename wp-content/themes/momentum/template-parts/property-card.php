<?php
/**
 * Reusable propiedad card.
 *
 * Usage: set $post_id before including, e.g.
 *   set_query_var('momentum_property_id', get_the_ID());
 *   get_template_part('template-parts/property-card');
 *
 * Used by: Home (destacadas), Venta, Renta, Terrenos, Propiedades similares.
 */

if (! defined('ABSPATH')) {
	exit;
}

$post_id = get_query_var('momentum_property_id', get_the_ID());
$data    = momentum_get_propiedad($post_id);

// Set by template-parts/property-listing.php for the very first card on
// Venta/Renta/Terrenos (confirmed via Lighthouse to be the LCP element on
// those pages — their page-hero has no image). Everywhere else this card
// is never the largest above-the-fold element (Home has the hero video,
// "similar properties" is always below the fold), so it defaults to lazy.
$is_priority = (bool) get_query_var('momentum_property_priority', false);
set_query_var('momentum_property_priority', false); // consume, don't leak to the next card

// Grid is 3 columns desktop / 2 tablet / 1 mobile inside a 1280px container
// with 24px side padding and a 28px gap (momentum.css) — this mirrors the
// actual rendered width so the browser picks the right srcset candidate
// instead of always the largest one.
$image_sizes = '(max-width: 680px) 100vw, (max-width: 1024px) 50vw, 392px';

// Sin 'tipo_propiedad' (deprecado): sin construcción registrada se trata
// como terreno, igual que en template-parts/property/header.php.
$is_terreno = $data['construccion_m2'] <= 0;
$zona_label = momentum_get_zona_label($data['zona']);

$badge = '';
if ($data['estado'] === 'Preventa') {
	$badge = 'En preventa';
} elseif ($data['estado'] === 'Apartada') {
	$badge = 'Apartada';
} elseif ($data['codigo_propiedad']) {
	$badge = $data['codigo_propiedad'];
}
?>
<article class="momentum-property-card">
	<a class="momentum-property-card__media" href="<?php echo esc_url(get_permalink($post_id)); ?>">
		<?php if ($badge) : ?>
			<span class="momentum-property-card__badge"><?php echo esc_html($badge); ?></span>
		<?php endif; ?>

		<?php if (has_post_thumbnail($post_id)) : ?>
			<?php
			echo wp_get_attachment_image(
				get_post_thumbnail_id($post_id),
				'large',
				false,
				$is_priority
					? [
						'sizes'         => $image_sizes,
						'loading'       => 'eager',
						'fetchpriority' => 'high',
						'decoding'      => 'async',
					]
					: [
						'sizes'    => $image_sizes,
						'loading'  => 'lazy',
						'decoding' => 'async',
					]
			);
			?>
		<?php endif; ?>
	</a>

	<div class="momentum-property-card__body">
		<h3 class="momentum-property-card__title">
			<a href="<?php echo esc_url(get_permalink($post_id)); ?>"><?php echo esc_html(get_the_title($post_id)); ?></a>
		</h3>

		<?php if ($zona_label) : ?>
			<div class="momentum-property-card__location">
				<?php echo momentum_icon('pin'); ?>
				<span><?php echo esc_html($zona_label); ?></span>
			</div>
		<?php endif; ?>

		<div class="momentum-property-card__features">
			<span><?php echo momentum_icon('land'); ?> <?php echo esc_html((string) intval($data['terreno_m2'])); ?> m²</span>
			<?php if (! $is_terreno) : ?>
				<span><?php echo momentum_icon('ruler'); ?> <?php echo esc_html((string) intval($data['construccion_m2'])); ?> m²</span>
				<span><?php echo momentum_icon('bed'); ?> <?php echo esc_html((string) intval($data['recamaras'])); ?></span>
				<span><?php echo momentum_icon('bath'); ?> <?php echo esc_html(rtrim(rtrim(number_format($data['banos'], 1), '0'), '.')); ?></span>
			<?php endif; ?>
		</div>

		<div class="momentum-property-card__footer">
			<span class="momentum-property-card__price">
				<?php echo esc_html(momentum_format_price($data['precio'], $data['tipo_operacion'])); ?>
			</span>
		</div>
	</div>
</article>
