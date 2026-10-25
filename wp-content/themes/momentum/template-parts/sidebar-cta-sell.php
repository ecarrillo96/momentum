<?php
/**
 * Reusable dark CTA box for listing sidebars (Venta, Renta, Terrenos) —
 * invita al visitante a vender su propiedad con nosotros. Se muestra debajo
 * de sidebar-cta.php (misma estructura/clases, solo cambia el copy).
 */

if (! defined('ABSPATH')) {
	exit;
}

$phone_1 = momentum_get_phone_1();
$phone_2 = momentum_get_phone_2();
?>
<div class="momentum-sidebar-cta">
	<h3><?php esc_html_e('¿Quieres vender tu propiedad?', 'momentum'); ?></h3>
	<p><?php esc_html_e('Nosotros somos tu mejor opción, tenemos el equipo necesario para vender tu propiedad.', 'momentum'); ?></p>
	<a class="momentum-btn momentum-btn--accent" href="<?php echo esc_url(momentum_url_for_page('contacto')); ?>"><?php esc_html_e('Contactar ahora', 'momentum'); ?></a>
	<?php if ($phone_1 !== '' || $phone_2 !== '') : ?>
		<div class="momentum-phone-group">
			<?php if ($phone_1 !== '') : ?>
				<a class="momentum-sidebar-cta__phone" href="<?php echo esc_attr(momentum_phone_to_tel_link($phone_1)); ?>">
					<?php echo momentum_icon('phone'); ?>
					<span><?php echo esc_html($phone_1); ?></span>
				</a>
			<?php endif; ?>
			<?php if ($phone_2 !== '') : ?>
				<a class="momentum-sidebar-cta__phone" href="<?php echo esc_attr(momentum_phone_to_tel_link($phone_2)); ?>">
					<?php echo momentum_icon('phone'); ?>
					<span><?php echo esc_html($phone_2); ?></span>
				</a>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
