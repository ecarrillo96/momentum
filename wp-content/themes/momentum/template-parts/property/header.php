<?php
/**
 * Property detail — breadcrumb, title, price, location, feature icons.
 */

if (! defined('ABSPATH')) {
	exit;
}

$property_id = get_the_ID();
$data        = momentum_get_propiedad($property_id);
// Sin 'tipo_propiedad' (deprecado), una propiedad se trata como terreno
// cuando no tiene construcción registrada — así se comportan hoy todos los
// registros reales con tipo 'Terreno' (construccion_m2 = 0).
$is_terreno  = $data['construccion_m2'] <= 0;
$zona_label  = momentum_get_zona_label($data['zona']);
?>
<section class="momentum-property-header">
	<div class="momentum-container">
		<div class="momentum-property-header__top">
			<h1><?php the_title(); ?></h1>
			<div class="momentum-property-header__price">
				<?php echo esc_html(momentum_get_property_price_display($data)); ?>
				<?php if (momentum_current_language() !== momentum_default_language()) : ?>
					<span class="momentum-property-header__price-note"><?php echo esc_html(momentum_usd_estimate_note()); ?></span>
				<?php endif; ?>
			</div>
		</div>

		<?php if ($zona_label) : ?>
			<div class="momentum-property-header__location">
				<?php echo momentum_icon('pin'); ?>
				<span><?php echo esc_html($zona_label); ?></span>
			</div>
		<?php endif; ?>

		<div class="momentum-property-header__features">
			<span><?php echo momentum_icon('land'); ?> <?php echo esc_html(sprintf(
				/* translators: %s: metros cuadrados de terreno */
				__('%s m² Terreno', 'momentum'),
				(string) intval($data['terreno_m2'])
			)); ?></span>
			<?php if (! $is_terreno) : ?>
				<span><?php echo momentum_icon('ruler'); ?> <?php echo esc_html(sprintf(
					/* translators: %s: metros cuadrados de construcción */
					__('%s m² Construcción', 'momentum'),
					(string) intval($data['construccion_m2'])
				)); ?></span>
				<span><?php echo momentum_icon('bed'); ?> <?php echo esc_html(sprintf(
					/* translators: %s: número de recámaras */
					__('%s Recámaras', 'momentum'),
					(string) intval($data['recamaras'])
				)); ?></span>
				<span><?php echo momentum_icon('bath'); ?> <?php echo esc_html(sprintf(
					/* translators: %s: número de baños */
					__('%s Baños', 'momentum'),
					$data['banos']
				)); ?></span>
			<?php endif; ?>
		</div>
	</div>
</section>
