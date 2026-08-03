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

// El badge sobre la imagen solo muestra el código; el estado (si aplica)
// se muestra aparte, junto al título.
$badge = $data['codigo_propiedad'];

$status_label = '';
if (in_array($data['estado'], ['Preventa', 'Apartada'], true)) {
	$status_label = momentum_get_estado_label($data['estado']);
}
?>
<article class="momentum-property-card">
	<a class="momentum-property-card__media" href="<?php echo esc_url(get_permalink($post_id)); ?>">
		<?php if ($badge) : ?>
			<span class="momentum-property-card__badge"><?php echo esc_html($badge); ?></span>
		<?php endif; ?>

		<?php if (has_post_thumbnail($post_id)) : ?>
			<?php
			$thumbnail_id  = get_post_thumbnail_id($post_id);
			$thumbnail_alt = momentum_property_image_alt($thumbnail_id, $post_id);

			echo wp_get_attachment_image(
				$thumbnail_id,
				'large',
				false,
				$is_priority
					? [
						'alt'           => $thumbnail_alt,
						'sizes'         => $image_sizes,
						'loading'       => 'eager',
						'fetchpriority' => 'high',
						'decoding'      => 'async',
					]
					: [
						'alt'      => $thumbnail_alt,
						'sizes'    => $image_sizes,
						'loading'  => 'lazy',
						'decoding' => 'async',
					]
			);
			?>
		<?php endif; ?>
	</a>

	<div class="momentum-property-card__body">
		<?php if ($status_label) : ?>
			<span class="momentum-property-card__status"><?php echo esc_html($status_label); ?></span>
		<?php endif; ?>

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
			<span><?php echo momentum_icon('land'); ?> <?php echo esc_html(sprintf(
				/* translators: %s: metros cuadrados de terreno */
				__('%s m²', 'momentum'),
				(string) intval($data['terreno_m2'])
			)); ?></span>
			<?php if (! $is_terreno) : ?>
				<span><?php echo momentum_icon('ruler'); ?> <?php echo esc_html(sprintf(
					/* translators: %s: metros cuadrados de construcción */
					__('%s m²', 'momentum'),
					(string) intval($data['construccion_m2'])
				)); ?></span>
				<span><?php echo momentum_icon('bed'); ?> <?php echo esc_html((string) intval($data['recamaras'])); ?></span>
				<span><?php echo momentum_icon('bath'); ?> <?php echo esc_html($data['banos']); ?></span>
			<?php endif; ?>
		</div>

		<div class="momentum-property-card__footer">
			<span class="momentum-property-card__price">
				<?php echo esc_html(momentum_get_property_price_display($data)); ?>
			</span>
		</div>

		<?php if (momentum_current_language() !== momentum_default_language()) : ?>
			<p class="momentum-property-card__price-note"><?php echo esc_html(momentum_usd_estimate_note()); ?></p>
		<?php endif; ?>
	</div>
</article>
