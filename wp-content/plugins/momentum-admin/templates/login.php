<?php
/** @var string $error */
if (! defined('ABSPATH')) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php esc_html_e('Iniciar sesión', 'momentum-admin'); ?> · Momentum Cocoyoc</title>
<?php
// Esta pantalla no pasa por login_head(): ver la misma nota en layout/header.php.
if (has_site_icon()) :
	?>
	<link rel="icon" href="<?php echo esc_url(get_site_icon_url(32)); ?>" sizes="32x32">
	<link rel="icon" href="<?php echo esc_url(get_site_icon_url(192)); ?>" sizes="192x192">
	<link rel="apple-touch-icon" href="<?php echo esc_url(get_site_icon_url(180)); ?>">
	<?php
endif;

wp_print_styles(['dashicons']);
?>
<link rel="stylesheet" href="<?php echo esc_url(MOMENTUM_ADMIN_URL . 'assets/css/admin-panel.css?v=' . MOMENTUM_ADMIN_VERSION); ?>">
</head>
<body class="momentum-admin-body momentum-admin-body--login">
<main class="momentum-admin momentum-admin-login">
	<div class="momentum-admin-login-card">
		<div class="momentum-admin-brand momentum-admin-brand--center">
			<?php if (has_custom_logo()) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<span class="momentum-admin-brand-name">Momentum Cocoyoc</span>
			<?php endif; ?>
		</div>

		<?php if (! empty($error)) : ?>
			<p class="momentum-admin-alert momentum-admin-alert--error" role="alert"><?php echo esc_html($error); ?></p>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url(home_url('/admin/')); ?>" class="momentum-admin-login-form">
			<?php wp_nonce_field(Momentum_Admin_Auth::NONCE_ACTION, Momentum_Admin_Auth::NONCE_FIELD); ?>

			<label for="momentum_username"><?php esc_html_e('Usuario o correo', 'momentum-admin'); ?></label>
			<input
				type="text"
				id="momentum_username"
				name="momentum_username"
				autocomplete="username"
				required
				value="<?php echo isset($_POST['momentum_username']) ? esc_attr(sanitize_text_field(wp_unslash($_POST['momentum_username']))) : ''; ?>"
			>

			<label for="momentum_password"><?php esc_html_e('Contraseña', 'momentum-admin'); ?></label>
			<input type="password" id="momentum_password" name="momentum_password" autocomplete="current-password" required>

			<label class="momentum-admin-checkbox">
				<input type="checkbox" name="momentum_remember" value="1">
				<?php esc_html_e('Recordarme', 'momentum-admin'); ?>
			</label>

			<button type="submit" name="momentum_admin_login_submit" value="1" class="momentum-admin-button momentum-admin-button--block">
				<?php esc_html_e('Iniciar sesión', 'momentum-admin'); ?>
			</button>
		</form>
	</div>
</main>
</body>
</html>
