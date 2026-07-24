<?php
/**
 * Home — Final call to action banner.
 */

if (! defined('ABSPATH')) {
	exit;
}

$phone_1 = momentum_get_phone_1();
$phone_2 = momentum_get_phone_2();
?>
<section class="momentum-section">
	<div class="momentum-container">
		<div class="momentum-cta-banner">
			<div class="momentum-cta-banner__content">
				<h2>¿Listo para encontrar tu próxima propiedad?</h2>
				<p>
					Agenda una asesoría gratuita con nuestro equipo y recibe opciones
					personalizadas según lo que buscas.
				</p>
			</div>

			<div class="momentum-cta-banner__actions">
				<a class="momentum-btn momentum-btn--accent" href="<?php echo esc_url(home_url('/contacto/')); ?>">Contactar ahora</a>
				<?php if ($phone_1 !== '' || $phone_2 !== '') : ?>
					<div class="momentum-phone-group">
						<?php if ($phone_1 !== '') : ?>
							<a class="momentum-cta-banner__phone" href="<?php echo esc_attr(momentum_phone_to_tel_link($phone_1)); ?>">
								<?php echo momentum_icon('phone'); ?>
								<?php echo esc_html($phone_1); ?>
							</a>
						<?php endif; ?>
						<?php if ($phone_2 !== '') : ?>
							<a class="momentum-cta-banner__phone" href="<?php echo esc_attr(momentum_phone_to_tel_link($phone_2)); ?>">
								<?php echo momentum_icon('phone'); ?>
								<?php echo esc_html($phone_2); ?>
							</a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
