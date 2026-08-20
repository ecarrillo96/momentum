<?php
/**
 * Site footer: brand column + link columns + contact + bottom bar.
 *
 * Included from footer.php in place of Blocksy's blocksy_output_footer().
 */

if (! defined('ABSPATH')) {
	exit;
}

$social_links = array_filter([
	'facebook'  => momentum_get_facebook_url(),
	'instagram' => momentum_get_instagram_url(),
	'tiktok'    => momentum_get_tiktok_url_for_current_language(),
]);

$phone_1 = momentum_get_phone_1();
$phone_2 = momentum_get_phone_2();
$address = momentum_get_address();
$email   = momentum_get_email();
?>
<footer class="momentum-site-footer">
	<div class="momentum-container">
		<div class="momentum-site-footer__grid">
			<div class="momentum-footer-col momentum-footer-col--brand">
				<?php if (has_custom_logo()) : ?>
					<div class="momentum-footer-logo"><?php the_custom_logo(); ?></div>
				<?php else : ?>
					<p class="momentum-footer-brand-name"><?php bloginfo('name'); ?></p>
				<?php endif; ?>

				<p class="momentum-footer-tagline">
					<?php esc_html_e('Inmobiliaria especializada en Lomas de Cocoyoc. Compra, venta, renta de propiedades, terrenos y construcción de casas residenciales.', 'momentum'); ?>
				</p>
			</div>

			<div class="momentum-footer-col">
				<h3><?php esc_html_e('Contacto', 'momentum'); ?></h3>
				<ul class="momentum-footer-contact">
					<?php if ($address !== '') : ?>
						<li>
							<?php echo momentum_icon('pin'); ?>
							<span><?php echo nl2br(esc_html($address)); ?></span>
						</li>
					<?php endif; ?>
					<?php if ($phone_1 !== '' || $phone_2 !== '') : ?>
						<li>
							<?php echo momentum_icon('phone'); ?>
							<span>
								<?php if ($phone_1 !== '') : ?>
									<a href="<?php echo esc_attr(momentum_phone_to_tel_link($phone_1)); ?>"><?php echo esc_html($phone_1); ?></a>
								<?php endif; ?>
								<?php if ($phone_1 !== '' && $phone_2 !== '') : ?><br><?php endif; ?>
								<?php if ($phone_2 !== '') : ?>
									<a href="<?php echo esc_attr(momentum_phone_to_tel_link($phone_2)); ?>"><?php echo esc_html($phone_2); ?></a>
								<?php endif; ?>
							</span>
						</li>
					<?php endif; ?>
					<?php if ($email !== '') : ?>
						<li>
							<?php echo momentum_icon('mail'); ?>
							<a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
						</li>
					<?php endif; ?>
					<li>
						<?php echo momentum_icon('clock'); ?>
						<span>
							<?php esc_html_e('Lun - Vie: 9:00 - 18:00', 'momentum'); ?><br>
							<?php esc_html_e('Sáb: 10:00 - 15:00', 'momentum'); ?>
						</span>
					</li>
				</ul>
			</div>

			<?php if ($social_links) : ?>
				<div class="momentum-footer-col momentum-footer-col--social">
					<div class="momentum-footer-social">
						<?php foreach ($social_links as $network => $url) : ?>
							<a href="<?php echo esc_url($url); ?>" class="momentum-footer-social__icon" aria-label="<?php echo esc_attr(ucfirst($network)); ?>" target="_blank" rel="noopener noreferrer">
								<?php echo momentum_icon($network); ?>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<div class="momentum-site-footer__bottom">
			<p>
				<?php
				printf(
					/* translators: %s: año actual, para el aviso de derechos reservados del footer */
					esc_html__('© %s Momentum Cocoyoc. Todos los derechos reservados.', 'momentum'),
					esc_html(date('Y'))
				);
				?>
			</p>
			<p class="momentum-site-footer__legal">
				<span><?php esc_html_e('Aviso de privacidad', 'momentum'); ?></span>
				<span aria-hidden="true">·</span>
				<span><?php esc_html_e('Términos y condiciones', 'momentum'); ?></span>
			</p>
		</div>
	</div>
</footer>
