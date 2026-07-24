<?php
/**
 * Site header bar: logo + primary nav + phone + mobile toggle.
 *
 * Included from header.php in place of Blocksy's blocksy_output_header().
 */

if (! defined('ABSPATH')) {
	exit;
}

$phone_1 = momentum_get_phone_1();
$phone_2 = momentum_get_phone_2();
?>
<header class="momentum-site-header">
	<div class="momentum-container momentum-site-header__inner">
		<div class="momentum-site-header__logo">
			<?php if (has_custom_logo()) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a href="<?php echo esc_url(home_url('/')); ?>" class="momentum-site-header__logo-text">
					<?php bloginfo('name'); ?>
				</a>
			<?php endif; ?>
		</div>

		<nav class="momentum-site-header__nav" aria-label="Menú principal">
			<?php
			wp_nav_menu([
				'theme_location' => 'menu_1',
				'container'      => false,
				'menu_class'     => 'momentum-nav',
				'fallback_cb'    => false,
			]);
			?>
		</nav>

		<div class="momentum-site-header__actions">
			<?php if ($phone_1 !== '' || $phone_2 !== '') : ?>
				<div class="momentum-phone-group">
					<?php if ($phone_1 !== '') : ?>
						<a class="momentum-site-header__phone" href="<?php echo esc_attr(momentum_phone_to_tel_link($phone_1)); ?>">
							<?php echo momentum_icon('phone'); ?>
							<span><?php echo esc_html($phone_1); ?></span>
						</a>
					<?php endif; ?>
					<?php if ($phone_2 !== '') : ?>
						<a class="momentum-site-header__phone" href="<?php echo esc_attr(momentum_phone_to_tel_link($phone_2)); ?>">
							<?php echo momentum_icon('phone'); ?>
							<span><?php echo esc_html($phone_2); ?></span>
						</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<button type="button" class="momentum-site-header__toggle" aria-expanded="false" aria-controls="momentum-mobile-nav">
				<span></span>
				<span></span>
				<span></span>
				<span class="screen-reader-text">Abrir menú</span>
			</button>
		</div>
	</div>

	<div id="momentum-mobile-nav" class="momentum-mobile-nav">
		<?php
		wp_nav_menu([
			'theme_location' => 'menu_1',
			'container'      => false,
			'menu_class'     => 'momentum-mobile-nav__menu',
			'fallback_cb'    => false,
		]);
		?>
		<?php if ($phone_1 !== '') : ?>
			<a class="momentum-mobile-nav__phone" href="<?php echo esc_attr(momentum_phone_to_tel_link($phone_1)); ?>">
				<?php echo momentum_icon('phone'); ?>
				<span><?php echo esc_html($phone_1); ?></span>
			</a>
		<?php endif; ?>
		<?php if ($phone_2 !== '') : ?>
			<a class="momentum-mobile-nav__phone" href="<?php echo esc_attr(momentum_phone_to_tel_link($phone_2)); ?>">
				<?php echo momentum_icon('phone'); ?>
				<span><?php echo esc_html($phone_2); ?></span>
			</a>
		<?php endif; ?>
	</div>
</header>
