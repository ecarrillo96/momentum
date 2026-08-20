<?php
/**
 * @var array $values          option_name => valor actual (crudo, ya listo para mostrar en el input)
 * @var array $errors_by_field option_name => string[] mensajes de error
 * @var bool  $saved
 */
if (! defined('ABSPATH')) {
	exit;
}

$field_error = static function (string $name) use ($errors_by_field): string {
	return $errors_by_field[$name][0] ?? '';
};

$field_class = static function (string $name) use ($field_error): string {
	return $field_error($name) !== '' ? 'momentum-admin-field momentum-admin-field--has-error' : 'momentum-admin-field';
};
?>
<div class="momentum-admin-page-header">
	<h1><?php esc_html_e('General', 'momentum-admin'); ?></h1>
</div>

<?php if ($saved) : ?>
	<p class="momentum-admin-alert momentum-admin-alert--success"><?php esc_html_e('Cambios guardados correctamente.', 'momentum-admin'); ?></p>
<?php elseif (! empty($errors_by_field)) : ?>
	<p class="momentum-admin-alert momentum-admin-alert--error"><?php esc_html_e('No se guardaron algunos campos. Revisa los errores marcados abajo.', 'momentum-admin'); ?></p>
<?php endif; ?>

<form method="post" action="<?php echo esc_url(admin_url('options.php')); ?>" class="momentum-admin-form" id="momentum-general-form">
	<?php settings_fields(MOMENTUM_SETTINGS_GROUP); ?>

	<section class="momentum-admin-settings-section">
		<h2><?php esc_html_e('Tipo de cambio', 'momentum-admin'); ?></h2>

		<div class="momentum-admin-form-grid">
			<div class="<?php echo esc_attr($field_class('momentum_exchange_rate')); ?>">
				<label for="momentum_exchange_rate"><?php esc_html_e('Tipo de cambio MXN a USD', 'momentum-admin'); ?></label>
				<input
					type="number"
					id="momentum_exchange_rate"
					name="momentum_exchange_rate"
					value="<?php echo esc_attr($values['momentum_exchange_rate']); ?>"
					placeholder="18.50"
					step="0.0001"
					min="0.0001"
					required
				>
				<p class="momentum-admin-field-hint"><?php esc_html_e('Cantidad de pesos mexicanos equivalentes a 1 dólar estadounidense. Ejemplo: 18.50', 'momentum-admin'); ?></p>
				<?php if ($field_error('momentum_exchange_rate') !== '') : ?>
					<p class="momentum-admin-field-error"><?php echo esc_html($field_error('momentum_exchange_rate')); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<section class="momentum-admin-settings-section">
		<h2><?php esc_html_e('Información de contacto y redes sociales', 'momentum-admin'); ?></h2>

		<div class="momentum-admin-form-grid">
			<div class="<?php echo esc_attr($field_class('momentum_facebook_url')); ?>">
				<label for="momentum_facebook_url"><?php esc_html_e('Facebook', 'momentum-admin'); ?></label>
				<input type="url" id="momentum_facebook_url" name="momentum_facebook_url" value="<?php echo esc_attr($values['momentum_facebook_url']); ?>" placeholder="https://www.facebook.com/MomentumCocoyocMx">
				<?php if ($field_error('momentum_facebook_url') !== '') : ?>
					<p class="momentum-admin-field-error"><?php echo esc_html($field_error('momentum_facebook_url')); ?></p>
				<?php endif; ?>
			</div>

			<div class="<?php echo esc_attr($field_class('momentum_instagram_url')); ?>">
				<label for="momentum_instagram_url"><?php esc_html_e('Instagram', 'momentum-admin'); ?></label>
				<input type="url" id="momentum_instagram_url" name="momentum_instagram_url" value="<?php echo esc_attr($values['momentum_instagram_url']); ?>" placeholder="https://www.instagram.com/momentumcocoyoc/">
				<?php if ($field_error('momentum_instagram_url') !== '') : ?>
					<p class="momentum-admin-field-error"><?php echo esc_html($field_error('momentum_instagram_url')); ?></p>
				<?php endif; ?>
			</div>

			<div class="<?php echo esc_attr($field_class('momentum_tiktok_url')); ?>">
				<label for="momentum_tiktok_url"><?php esc_html_e('TikTok', 'momentum-admin'); ?></label>
				<input type="url" id="momentum_tiktok_url" name="momentum_tiktok_url" value="<?php echo esc_attr($values['momentum_tiktok_url']); ?>" placeholder="https://www.tiktok.com/@momentumcocoyocmr">
				<?php if ($field_error('momentum_tiktok_url') !== '') : ?>
					<p class="momentum-admin-field-error"><?php echo esc_html($field_error('momentum_tiktok_url')); ?></p>
				<?php endif; ?>
			</div>

			<div class="<?php echo esc_attr($field_class('momentum_tiktok_url_en')); ?>">
				<label for="momentum_tiktok_url_en"><?php esc_html_e('TikTok (inglés)', 'momentum-admin'); ?></label>
				<input type="url" id="momentum_tiktok_url_en" name="momentum_tiktok_url_en" value="<?php echo esc_attr($values['momentum_tiktok_url_en']); ?>" placeholder="https://www.tiktok.com/@momentumcocoyocmx">
				<?php if ($field_error('momentum_tiktok_url_en') !== '') : ?>
					<p class="momentum-admin-field-error"><?php echo esc_html($field_error('momentum_tiktok_url_en')); ?></p>
				<?php endif; ?>
			</div>

			<div class="<?php echo esc_attr($field_class('momentum_phone_1')); ?>">
				<label for="momentum_phone_1"><?php esc_html_e('Número de teléfono 1', 'momentum-admin'); ?></label>
				<input type="text" id="momentum_phone_1" name="momentum_phone_1" value="<?php echo esc_attr($values['momentum_phone_1']); ?>" placeholder="735 379 7781" maxlength="25">
				<?php if ($field_error('momentum_phone_1') !== '') : ?>
					<p class="momentum-admin-field-error"><?php echo esc_html($field_error('momentum_phone_1')); ?></p>
				<?php endif; ?>
			</div>

			<div class="<?php echo esc_attr($field_class('momentum_phone_2')); ?>">
				<label for="momentum_phone_2"><?php esc_html_e('Número de teléfono 2', 'momentum-admin'); ?></label>
				<input type="text" id="momentum_phone_2" name="momentum_phone_2" value="<?php echo esc_attr($values['momentum_phone_2']); ?>" placeholder="735 166 4461" maxlength="25">
				<?php if ($field_error('momentum_phone_2') !== '') : ?>
					<p class="momentum-admin-field-error"><?php echo esc_html($field_error('momentum_phone_2')); ?></p>
				<?php endif; ?>
			</div>

			<div class="<?php echo esc_attr($field_class('momentum_email')); ?>">
				<label for="momentum_email"><?php esc_html_e('Correo electrónico', 'momentum-admin'); ?></label>
				<input type="email" id="momentum_email" name="momentum_email" value="<?php echo esc_attr($values['momentum_email']); ?>" placeholder="contacto@momentumcocoyoc.com">
				<?php if ($field_error('momentum_email') !== '') : ?>
					<p class="momentum-admin-field-error"><?php echo esc_html($field_error('momentum_email')); ?></p>
				<?php endif; ?>
			</div>

			<div class="<?php echo esc_attr($field_class('momentum_address')); ?> momentum-admin-field--full">
				<label for="momentum_address"><?php esc_html_e('Dirección', 'momentum-admin'); ?></label>
				<textarea id="momentum_address" name="momentum_address" rows="3"><?php echo esc_textarea($values['momentum_address']); ?></textarea>
				<?php if ($field_error('momentum_address') !== '') : ?>
					<p class="momentum-admin-field-error"><?php echo esc_html($field_error('momentum_address')); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<div class="momentum-admin-form-actions">
		<button type="submit" class="momentum-admin-button" id="momentum-general-submit"><?php esc_html_e('Guardar cambios', 'momentum-admin'); ?></button>
	</div>
</form>
