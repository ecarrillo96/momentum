<?php
/**
 * Contacto — address / phone / email info card.
 */

if (! defined('ABSPATH')) {
	exit;
}

$address = momentum_get_address();
$phone_1 = momentum_get_phone_1();
$phone_2 = momentum_get_phone_2();
$email   = momentum_get_email();
?>
<div class="momentum-contact-info">
	<?php if (has_custom_logo()) : ?>
		<div class="momentum-contact-info__logo"><?php the_custom_logo(); ?></div>
	<?php endif; ?>

	<h3>Información de contacto</h3>

	<ul>
		<?php if ($address !== '') : ?>
			<li>
				<?php echo momentum_icon('pin'); ?>
				<div>
					<strong>Dirección</strong>
					<span><?php echo nl2br(esc_html($address)); ?></span>
				</div>
			</li>
		<?php endif; ?>

		<?php if ($phone_1 !== '' || $phone_2 !== '') : ?>
			<li>
				<?php echo momentum_icon('phone'); ?>
				<div>
					<strong>Teléfono</strong>
					<span>
						<?php if ($phone_1 !== '') : ?>
							<a href="<?php echo esc_attr(momentum_phone_to_tel_link($phone_1)); ?>"><?php echo esc_html($phone_1); ?></a>
						<?php endif; ?>
						<?php if ($phone_1 !== '' && $phone_2 !== '') : ?><br><?php endif; ?>
						<?php if ($phone_2 !== '') : ?>
							<a href="<?php echo esc_attr(momentum_phone_to_tel_link($phone_2)); ?>"><?php echo esc_html($phone_2); ?></a>
						<?php endif; ?>
					</span>
				</div>
			</li>
		<?php endif; ?>

		<li>
			<?php echo momentum_icon('mail'); ?>
			<div>
				<strong>Email</strong>
				<span>
					<?php if ($email !== '') : ?>
						<a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a><br>
					<?php endif; ?>
					<a href="mailto:momentumcocoyoc.ventas@gmail.com">momentumcocoyoc.ventas@gmail.com</a>
				</span>
			</div>
		</li>
	</ul>
</div>
