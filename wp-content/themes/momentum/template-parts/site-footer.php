<?php
/**
 * Site footer: brand column + link columns + contact + bottom bar.
 *
 * Included from footer.php in place of Blocksy's blocksy_output_footer().
 */

if (! defined('ABSPATH')) {
	exit;
}

$property_links = [
	'Casas en venta'    => '/venta/',
	'Casas en renta'    => '/renta/',
	'Terrenos en venta' => '/terrenos/',
];

$service_links = [
	'Construcción de casas'  => '/construccion/',
	'Asesoría inmobiliaria'  => '/asesoria/',
	'Avalúos'                => '/asesoria/',
	'Trámites y gestores'    => '/asesoria/',
];

$social_links = array_filter([
	'facebook'  => momentum_get_facebook_url(),
	'instagram' => momentum_get_instagram_url(),
	'tiktok'    => momentum_get_tiktok_url(),
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
					Inmobiliaria especializada en Lomas de Cocoyoc. Compra, venta, renta de propiedades,
					terrenos y construcción de viviendas.
				</p>

				<div class="momentum-footer-social">
					<?php foreach ($social_links as $network => $url) : ?>
						<a href="<?php echo esc_url($url); ?>" class="momentum-footer-social__icon" aria-label="<?php echo esc_attr(ucfirst($network)); ?>" target="_blank" rel="noopener noreferrer">
							<?php echo momentum_icon($network); ?>
						</a>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="momentum-footer-col">
				<h3>Propiedades</h3>
				<ul>
					<?php foreach ($property_links as $label => $path) : ?>
						<li><a href="<?php echo esc_url(home_url($path)); ?>"><?php echo esc_html($label); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="momentum-footer-col">
				<h3>Servicios</h3>
				<ul>
					<?php foreach ($service_links as $label => $path) : ?>
						<li><a href="<?php echo esc_url(home_url($path)); ?>"><?php echo esc_html($label); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="momentum-footer-col">
				<h3>Contacto</h3>
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
						<span>Lun - Vie: 9:00 - 18:00<br>Sáb: 9:00 - 14:00</span>
					</li>
				</ul>
			</div>
		</div>

		<div class="momentum-site-footer__bottom">
			<p>&copy; <?php echo esc_html(date('Y')); ?> Momentum Cocoyoc. Todos los derechos reservados.</p>
			<p class="momentum-site-footer__legal">
				<span>Aviso de privacidad</span>
				<span aria-hidden="true">·</span>
				<span>Términos y condiciones</span>
			</p>
		</div>
	</div>
</footer>
