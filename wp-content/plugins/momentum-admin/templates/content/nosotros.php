<?php
/**
 * @var array{es: WP_Post, en: ?WP_Post} $pages
 * @var array $values_es  clave del schema => valor actual en la página ES
 * @var array $values_en  clave del schema => valor actual en la página EN (vacío si no hay traducción)
 * @var array $schema     momentum_nosotros_meta_schema()
 */
if (! defined('ABSPATH')) {
	exit;
}

$error_code   = isset($_GET['error']) ? sanitize_key(wp_unslash($_GET['error'])) : '';
$mensaje_code = isset($_GET['mensaje']) ? sanitize_key(wp_unslash($_GET['mensaje'])) : '';

$errores = [
	'nonce'    => __('La sesión del formulario expiró. Intenta guardar de nuevo.', 'momentum-admin'),
	'permisos' => __('No tienes permisos para guardar estos cambios.', 'momentum-admin'),
	'pagina'   => __('No se encontró la página "Nosotros".', 'momentum-admin'),
];

$mensajes = [
	'actualizado' => __('Cambios guardados correctamente.', 'momentum-admin'),
];

$action_url = home_url('/admin/general-about/');

$editor_settings_base = [
	'textarea_rows' => 10,
	'media_buttons' => false,
	'tinymce'       => [
		'block_formats' => 'Párrafo=p',
		'toolbar1'      => 'bold,italic,bullist,numlist,link,unlink,undo,redo',
	],
	'quicktags' => true,
];
?>
<div class="momentum-admin-page-header">
	<h1><?php esc_html_e('Editar sección Nosotros', 'momentum-admin'); ?></h1>
	<a class="momentum-admin-link" href="<?php echo esc_url(get_permalink($pages['es']->ID)); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Ver página pública', 'momentum-admin'); ?> &rarr;</a>
</div>

<?php if ($mensaje_code && isset($mensajes[$mensaje_code])) : ?>
	<p class="momentum-admin-alert momentum-admin-alert--success"><?php echo esc_html($mensajes[$mensaje_code]); ?></p>
<?php endif; ?>

<?php if ($error_code && isset($errores[$error_code])) : ?>
	<p class="momentum-admin-alert momentum-admin-alert--error"><?php echo esc_html($errores[$error_code]); ?></p>
<?php endif; ?>

<?php if (! $pages['en']) : ?>
	<p class="momentum-admin-alert"><?php esc_html_e('No se encontró la traducción en inglés de esta página. Los campos en inglés no se podrán guardar hasta que exista esa traducción en Polylang.', 'momentum-admin'); ?></p>
<?php endif; ?>

<form method="post" action="<?php echo esc_url($action_url); ?>" class="momentum-admin-form" id="momentum-nosotros-content-form">
	<?php wp_nonce_field(Momentum_Admin_Content_Pages::NONCE_ACTION_NOSOTROS, Momentum_Admin_Content_Pages::NONCE_FIELD_NOSOTROS); ?>

	<details class="momentum-admin-collapse" open>
		<summary><?php esc_html_e('1. Hero', 'momentum-admin'); ?></summary>

		<div class="momentum-admin-form-grid">
			<div class="momentum-admin-field">
				<label for="nosotros_hero_title_es"><?php echo esc_html($schema['nosotros_hero_title']['label']); ?> — <?php esc_html_e('Español', 'momentum-admin'); ?></label>
				<input type="text" id="nosotros_hero_title_es" name="nosotros_hero_title_es" value="<?php echo esc_attr($values_es['nosotros_hero_title']); ?>">
			</div>

			<div class="momentum-admin-field">
				<label for="nosotros_hero_title_en"><?php echo esc_html($schema['nosotros_hero_title']['label']); ?> — <?php esc_html_e('Inglés', 'momentum-admin'); ?></label>
				<input type="text" id="nosotros_hero_title_en" name="nosotros_hero_title_en" value="<?php echo esc_attr($values_en['nosotros_hero_title']); ?>" <?php disabled(! $pages['en']); ?>>
			</div>

			<div class="momentum-admin-field">
				<label for="nosotros_hero_subtitle_es"><?php echo esc_html($schema['nosotros_hero_subtitle']['label']); ?> — <?php esc_html_e('Español', 'momentum-admin'); ?></label>
				<input type="text" id="nosotros_hero_subtitle_es" name="nosotros_hero_subtitle_es" value="<?php echo esc_attr($values_es['nosotros_hero_subtitle']); ?>">
			</div>

			<div class="momentum-admin-field">
				<label for="nosotros_hero_subtitle_en"><?php echo esc_html($schema['nosotros_hero_subtitle']['label']); ?> — <?php esc_html_e('Inglés', 'momentum-admin'); ?></label>
				<input type="text" id="nosotros_hero_subtitle_en" name="nosotros_hero_subtitle_en" value="<?php echo esc_attr($values_en['nosotros_hero_subtitle']); ?>" <?php disabled(! $pages['en']); ?>>
			</div>
		</div>
	</details>

	<details class="momentum-admin-collapse" open>
		<summary><?php esc_html_e('2. Contenido principal ("Quiénes somos")', 'momentum-admin'); ?></summary>

		<div class="momentum-admin-form-grid">
			<div class="momentum-admin-field">
				<label for="nosotros_section_label_es"><?php echo esc_html($schema['nosotros_section_label']['label']); ?> — <?php esc_html_e('Español', 'momentum-admin'); ?></label>
				<input type="text" id="nosotros_section_label_es" name="nosotros_section_label_es" value="<?php echo esc_attr($values_es['nosotros_section_label']); ?>">
			</div>

			<div class="momentum-admin-field">
				<label for="nosotros_section_label_en"><?php echo esc_html($schema['nosotros_section_label']['label']); ?> — <?php esc_html_e('Inglés', 'momentum-admin'); ?></label>
				<input type="text" id="nosotros_section_label_en" name="nosotros_section_label_en" value="<?php echo esc_attr($values_en['nosotros_section_label']); ?>" <?php disabled(! $pages['en']); ?>>
			</div>

			<div class="momentum-admin-field momentum-admin-field--full">
				<label for="nosotros_section_content_es"><?php echo esc_html($schema['nosotros_section_content']['label']); ?> — <?php esc_html_e('Español', 'momentum-admin'); ?></label>
				<?php wp_editor($values_es['nosotros_section_content'], 'nosotros_section_content_es', array_merge($editor_settings_base, ['textarea_name' => 'nosotros_section_content_es'])); ?>
			</div>

			<div class="momentum-admin-field momentum-admin-field--full">
				<label for="nosotros_section_content_en"><?php echo esc_html($schema['nosotros_section_content']['label']); ?> — <?php esc_html_e('Inglés', 'momentum-admin'); ?></label>
				<?php if ($pages['en']) : ?>
					<?php wp_editor($values_en['nosotros_section_content'], 'nosotros_section_content_en', array_merge($editor_settings_base, ['textarea_name' => 'nosotros_section_content_en'])); ?>
				<?php else : ?>
					<textarea rows="10" disabled placeholder="<?php esc_attr_e('No hay traducción en inglés de esta página todavía.', 'momentum-admin'); ?>"></textarea>
				<?php endif; ?>
			</div>
		</div>
	</details>

	<details class="momentum-admin-collapse" open>
		<summary><?php esc_html_e('3. Imágenes', 'momentum-admin'); ?></summary>

		<p class="momentum-admin-field-hint"><?php esc_html_e('Las imágenes son la misma pieza visual en español e inglés: se capturan una sola vez aquí y se usan en ambos idiomas.', 'momentum-admin'); ?></p>

		<div class="momentum-admin-form-grid">
			<div class="momentum-admin-field momentum-admin-field--full">
				<label><?php echo esc_html($schema['nosotros_main_image']['label']); ?></label>
				<p class="momentum-admin-field-hint"><?php esc_html_e('Foto de la recepción, debajo del hero. Recomendado: horizontal, mínimo 1600px de ancho.', 'momentum-admin'); ?></p>
				<input type="hidden" id="nosotros_main_image" name="nosotros_main_image" value="<?php echo esc_attr($values_es['nosotros_main_image']); ?>">
				<div id="nosotros-main-image-preview" class="momentum-admin-media-preview">
					<?php if ($values_es['nosotros_main_image']) : ?>
						<div class="momentum-gallery-item">
							<?php echo wp_get_attachment_image((int) $values_es['nosotros_main_image'], 'thumbnail'); ?>
							<button type="button" class="momentum-gallery-remove" aria-label="<?php esc_attr_e('Quitar imagen', 'momentum-admin'); ?>">&times;</button>
						</div>
					<?php endif; ?>
				</div>
				<button type="button" class="momentum-admin-button momentum-admin-button--secondary" id="nosotros-main-image-select"><?php esc_html_e('Seleccionar imagen', 'momentum-admin'); ?></button>
			</div>

			<div class="momentum-admin-field momentum-admin-field--full">
				<label><?php echo esc_html($schema['nosotros_secondary_image']['label']); ?></label>
				<p class="momentum-admin-field-hint"><?php esc_html_e('Foto de la sala de juntas, al final de la página. Recomendado: horizontal, mínimo 1600px de ancho.', 'momentum-admin'); ?></p>
				<input type="hidden" id="nosotros_secondary_image" name="nosotros_secondary_image" value="<?php echo esc_attr($values_es['nosotros_secondary_image']); ?>">
				<div id="nosotros-secondary-image-preview" class="momentum-admin-media-preview">
					<?php if ($values_es['nosotros_secondary_image']) : ?>
						<div class="momentum-gallery-item">
							<?php echo wp_get_attachment_image((int) $values_es['nosotros_secondary_image'], 'thumbnail'); ?>
							<button type="button" class="momentum-gallery-remove" aria-label="<?php esc_attr_e('Quitar imagen', 'momentum-admin'); ?>">&times;</button>
						</div>
					<?php endif; ?>
				</div>
				<button type="button" class="momentum-admin-button momentum-admin-button--secondary" id="nosotros-secondary-image-select"><?php esc_html_e('Seleccionar imagen', 'momentum-admin'); ?></button>
			</div>
		</div>
	</details>

	<div class="momentum-admin-form-actions">
		<button type="submit" name="momentum_admin_nosotros_content_submit" value="1" class="momentum-admin-button" id="momentum-nosotros-content-submit"><?php esc_html_e('Guardar cambios', 'momentum-admin'); ?></button>
	</div>
</form>
