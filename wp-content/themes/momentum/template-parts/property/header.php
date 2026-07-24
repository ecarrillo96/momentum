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
				<?php echo esc_html(momentum_format_price($data['precio'], $data['tipo_operacion'])); ?>
			</div>
		</div>

		<?php if ($zona_label) : ?>
			<div class="momentum-property-header__location">
				<?php echo momentum_icon('pin'); ?>
				<span><?php echo esc_html($zona_label); ?></span>
			</div>
		<?php endif; ?>

		<div class="momentum-property-header__features">
			<span><?php echo momentum_icon('land'); ?> <?php echo esc_html((string) intval($data['terreno_m2'])); ?> m² Terreno</span>
			<?php if (! $is_terreno) : ?>
				<span><?php echo momentum_icon('ruler'); ?> <?php echo esc_html((string) intval($data['construccion_m2'])); ?> m² Construcción</span>
				<span><?php echo momentum_icon('bed'); ?> <?php echo esc_html((string) intval($data['recamaras'])); ?> Recámaras</span>
				<span><?php echo momentum_icon('bath'); ?> <?php echo esc_html(rtrim(rtrim(number_format($data['banos'], 1), '0'), '.')); ?> Baños</span>
			<?php endif; ?>
		</div>
	</div>
</section>
