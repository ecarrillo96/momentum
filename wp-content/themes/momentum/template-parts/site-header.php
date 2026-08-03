<?php
/**
 * Site header bar: logo + primary nav + mobile toggle.
 *
 * Included from header.php in place of Blocksy's blocksy_output_header().
 */

if (! defined('ABSPATH')) {
	exit;
}

?>
<header class="momentum-site-header">
	<div class="momentum-container momentum-site-header__inner">
		<div class="momentum-site-header__logo">
			<?php if (has_custom_logo()) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a href="<?php echo esc_url(momentum_home_url()); ?>" class="momentum-site-header__logo-text">
					<?php bloginfo('name'); ?>
				</a>
			<?php endif; ?>
		</div>

		<nav class="momentum-site-header__nav" aria-label="<?php esc_attr_e('Menú principal', 'momentum'); ?>">
			<?php momentum_render_primary_menu(['menu_class' => 'momentum-nav']); ?>
		</nav>

		<div class="momentum-site-header__actions">
			<?php $language_switcher = momentum_language_switcher(); ?>
			<?php if ($language_switcher !== '') : ?>
				<nav class="momentum-lang-switcher" aria-label="<?php esc_attr_e('Selector de idioma', 'momentum'); ?>">
					<?php echo $language_switcher; ?>
				</nav>
			<?php endif; ?>

			<button type="button" class="momentum-site-header__toggle" aria-expanded="false" aria-controls="momentum-mobile-nav">
				<span></span>
				<span></span>
				<span></span>
				<span class="screen-reader-text"><?php esc_html_e('Abrir menú', 'momentum'); ?></span>
			</button>
		</div>
	</div>

	<div id="momentum-mobile-nav" class="momentum-mobile-nav">
		<?php momentum_render_primary_menu(['menu_class' => 'momentum-mobile-nav__menu']); ?>

		<?php if ($language_switcher !== '') : ?>
			<nav class="momentum-lang-switcher momentum-lang-switcher--mobile" aria-label="<?php esc_attr_e('Selector de idioma', 'momentum'); ?>">
				<?php echo $language_switcher; ?>
			</nav>
		<?php endif; ?>
	</div>
</header>
