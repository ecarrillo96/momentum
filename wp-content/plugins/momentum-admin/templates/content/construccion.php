<?php
/**
 * @var array{es: WP_Post, en: ?WP_Post} $pages
 * @var array $values_es  clave del schema => valor actual en la página ES
 * @var array $values_en  clave del schema => valor actual en la página EN (vacío si no hay traducción)
 * @var array $schema     momentum_construccion_meta_schema()
 */
if (! defined('ABSPATH')) {
	exit;
}

$error_code   = isset($_GET['error']) ? sanitize_key(wp_unslash($_GET['error'])) : '';
$mensaje_code = isset($_GET['mensaje']) ? sanitize_key(wp_unslash($_GET['mensaje'])) : '';

$errores = [
	'nonce'    => __('La sesión del formulario expiró. Intenta guardar de nuevo.', 'momentum-admin'),
	'permisos' => __('No tienes permisos para guardar estos cambios.', 'momentum-admin'),
	'pagina'   => __('No se encontró la página "Construcción".', 'momentum-admin'),
];

$mensajes = [
	'actualizado' => __('Cambios guardados correctamente.', 'momentum-admin'),
];

$action_url = home_url('/admin/general-construction/');

$editor_settings_base = [
	'textarea_rows' => 10,
	'media_buttons' => false,
	'tinymce'       => [
		'block_formats' => 'Párrafo=p',
		'toolbar1'      => 'bold,italic,bullist,numlist,link,unlink,undo,redo',
	],
	'quicktags' => true,
];

$video_source   = $values_es['construccion_video_source'] ?: 'file';
$video_file_id  = (int) $values_es['construccion_video_file'];
$video_poster_id = (int) $values_es['construccion_video_poster'];
?>
<div class="momentum-admin-page-header">
	<h1><?php esc_html_e('Editar sección Construcción', 'momentum-admin'); ?></h1>
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

<form method="post" action="<?php echo esc_url($action_url); ?>" class="momentum-admin-form" id="momentum-construccion-content-form">
	<?php wp_nonce_field(Momentum_Admin_Content_Pages::NONCE_ACTION_CONSTRUCCION, Momentum_Admin_Content_Pages::NONCE_FIELD_CONSTRUCCION); ?>

	<details class="momentum-admin-collapse" open>
		<summary><?php esc_html_e('1. Hero', 'momentum-admin'); ?></summary>

		<div class="momentum-admin-form-grid">
			<div class="momentum-admin-field">
				<label for="construccion_hero_title_es"><?php echo esc_html($schema['construccion_hero_title']['label']); ?> — <?php esc_html_e('Español', 'momentum-admin'); ?></label>
				<input type="text" id="construccion_hero_title_es" name="construccion_hero_title_es" value="<?php echo esc_attr($values_es['construccion_hero_title']); ?>">
			</div>

			<div class="momentum-admin-field">
				<label for="construccion_hero_title_en"><?php echo esc_html($schema['construccion_hero_title']['label']); ?> — <?php esc_html_e('Inglés', 'momentum-admin'); ?></label>
				<input type="text" id="construccion_hero_title_en" name="construccion_hero_title_en" value="<?php echo esc_attr($values_en['construccion_hero_title']); ?>" <?php disabled(! $pages['en']); ?>>
			</div>

			<div class="momentum-admin-field momentum-admin-field--full">
				<label for="construccion_hero_subtitle_es"><?php echo esc_html($schema['construccion_hero_subtitle']['label']); ?> — <?php esc_html_e('Español', 'momentum-admin'); ?></label>
				<input type="text" id="construccion_hero_subtitle_es" name="construccion_hero_subtitle_es" value="<?php echo esc_attr($values_es['construccion_hero_subtitle']); ?>">
				<p class="momentum-admin-field-hint"><?php esc_html_e('Se muestra en una sola línea con ancho limitado; si es muy larga, el navegador la parte sola en dos líneas.', 'momentum-admin'); ?></p>
			</div>

			<div class="momentum-admin-field momentum-admin-field--full">
				<label for="construccion_hero_subtitle_en"><?php echo esc_html($schema['construccion_hero_subtitle']['label']); ?> — <?php esc_html_e('Inglés', 'momentum-admin'); ?></label>
				<input type="text" id="construccion_hero_subtitle_en" name="construccion_hero_subtitle_en" value="<?php echo esc_attr($values_en['construccion_hero_subtitle']); ?>" <?php disabled(! $pages['en']); ?>>
			</div>
		</div>
	</details>

	<details class="momentum-admin-collapse" open>
		<summary><?php esc_html_e('2. Video', 'momentum-admin'); ?></summary>

		<p class="momentum-admin-field-hint"><?php esc_html_e('El video y su portada son la misma pieza en español e inglés: se capturan una sola vez aquí.', 'momentum-admin'); ?></p>

		<div class="momentum-admin-form-grid">
			<div class="momentum-admin-field">
				<label for="construccion_video_source"><?php echo esc_html($schema['construccion_video_source']['label']); ?></label>
				<select id="construccion_video_source" name="construccion_video_source">
					<?php foreach ($schema['construccion_video_source']['options'] as $value => $label) : ?>
						<option value="<?php echo esc_attr($value); ?>" <?php selected($video_source, $value); ?>><?php echo esc_html($label); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="momentum-admin-field momentum-admin-field--full" data-momentum-video-source-panel="file">
				<label><?php echo esc_html($schema['construccion_video_file']['label']); ?></label>
				<p class="momentum-admin-field-hint"><?php esc_html_e('Formato recomendado: MP4 (H.264), lo más liviano posible sin perder calidad visible.', 'momentum-admin'); ?></p>
				<input type="hidden" id="construccion_video_file" name="construccion_video_file" value="<?php echo esc_attr((string) $video_file_id); ?>">
				<div id="construccion-video-file-preview" class="momentum-admin-media-preview">
					<?php if ($video_file_id) : ?>
						<div class="momentum-gallery-item momentum-gallery-item--file">
							<span class="dashicons dashicons-format-video"></span>
							<span class="momentum-admin-file-name"><?php echo esc_html(basename((string) get_attached_file($video_file_id))); ?></span>
							<button type="button" class="momentum-gallery-remove" aria-label="<?php esc_attr_e('Quitar video', 'momentum-admin'); ?>">&times;</button>
						</div>
					<?php endif; ?>
				</div>
				<button type="button" class="momentum-admin-button momentum-admin-button--secondary" id="construccion-video-file-select"><?php esc_html_e('Seleccionar video', 'momentum-admin'); ?></button>
			</div>

			<div class="momentum-admin-field momentum-admin-field--full" data-momentum-video-source-panel="url">
				<label for="construccion_video_url"><?php echo esc_html($schema['construccion_video_url']['label']); ?></label>
				<input type="url" id="construccion_video_url" name="construccion_video_url" placeholder="https://..." value="<?php echo esc_attr($values_es['construccion_video_url']); ?>">
			</div>

			<div class="momentum-admin-field momentum-admin-field--full">
				<label><?php echo esc_html($schema['construccion_video_poster']['label']); ?></label>
				<p class="momentum-admin-field-hint"><?php esc_html_e('Imagen que se ve antes de reproducir el video. Opcional: si se deja vacía, se usa el primer fotograma del video.', 'momentum-admin'); ?></p>
				<input type="hidden" id="construccion_video_poster" name="construccion_video_poster" value="<?php echo esc_attr((string) $video_poster_id); ?>">
				<div id="construccion-video-poster-preview" class="momentum-admin-media-preview">
					<?php if ($video_poster_id) : ?>
						<div class="momentum-gallery-item">
							<?php echo wp_get_attachment_image($video_poster_id, 'thumbnail'); ?>
							<button type="button" class="momentum-gallery-remove" aria-label="<?php esc_attr_e('Quitar imagen', 'momentum-admin'); ?>">&times;</button>
						</div>
					<?php endif; ?>
				</div>
				<button type="button" class="momentum-admin-button momentum-admin-button--secondary" id="construccion-video-poster-select"><?php esc_html_e('Seleccionar imagen', 'momentum-admin'); ?></button>
			</div>

			<div class="momentum-admin-field momentum-admin-field--full">
				<label for="construccion_video_alt"><?php echo esc_html($schema['construccion_video_alt']['label']); ?></label>
				<input type="text" id="construccion_video_alt" name="construccion_video_alt" value="<?php echo esc_attr($values_es['construccion_video_alt']); ?>">
			</div>
		</div>
	</details>

	<details class="momentum-admin-collapse" open>
		<summary><?php esc_html_e('3. Nuestra experiencia', 'momentum-admin'); ?></summary>

		<div class="momentum-admin-form-grid">
			<div class="momentum-admin-field">
				<label for="construccion_section_label_es"><?php echo esc_html($schema['construccion_section_label']['label']); ?> — <?php esc_html_e('Español', 'momentum-admin'); ?></label>
				<input type="text" id="construccion_section_label_es" name="construccion_section_label_es" value="<?php echo esc_attr($values_es['construccion_section_label']); ?>">
			</div>

			<div class="momentum-admin-field">
				<label for="construccion_section_label_en"><?php echo esc_html($schema['construccion_section_label']['label']); ?> — <?php esc_html_e('Inglés', 'momentum-admin'); ?></label>
				<input type="text" id="construccion_section_label_en" name="construccion_section_label_en" value="<?php echo esc_attr($values_en['construccion_section_label']); ?>" <?php disabled(! $pages['en']); ?>>
			</div>

			<div class="momentum-admin-field momentum-admin-field--full">
				<label for="construccion_section_content_es"><?php echo esc_html($schema['construccion_section_content']['label']); ?> — <?php esc_html_e('Español', 'momentum-admin'); ?></label>
				<?php wp_editor($values_es['construccion_section_content'], 'construccion_section_content_es', array_merge($editor_settings_base, ['textarea_name' => 'construccion_section_content_es'])); ?>
			</div>

			<div class="momentum-admin-field momentum-admin-field--full">
				<label for="construccion_section_content_en"><?php echo esc_html($schema['construccion_section_content']['label']); ?> — <?php esc_html_e('Inglés', 'momentum-admin'); ?></label>
				<?php if ($pages['en']) : ?>
					<?php wp_editor($values_en['construccion_section_content'], 'construccion_section_content_en', array_merge($editor_settings_base, ['textarea_name' => 'construccion_section_content_en'])); ?>
				<?php else : ?>
					<textarea rows="10" disabled placeholder="<?php esc_attr_e('No hay traducción en inglés de esta página todavía.', 'momentum-admin'); ?>"></textarea>
				<?php endif; ?>
			</div>
		</div>
	</details>

	<details class="momentum-admin-collapse" open>
		<summary><?php esc_html_e('4. Botón de contacto', 'momentum-admin'); ?></summary>

		<div class="momentum-admin-form-grid">
			<div class="momentum-admin-field">
				<label for="construccion_cta_text_es"><?php echo esc_html($schema['construccion_cta_text']['label']); ?> — <?php esc_html_e('Español', 'momentum-admin'); ?></label>
				<input type="text" id="construccion_cta_text_es" name="construccion_cta_text_es" value="<?php echo esc_attr($values_es['construccion_cta_text']); ?>">
			</div>

			<div class="momentum-admin-field">
				<label for="construccion_cta_text_en"><?php echo esc_html($schema['construccion_cta_text']['label']); ?> — <?php esc_html_e('Inglés', 'momentum-admin'); ?></label>
				<input type="text" id="construccion_cta_text_en" name="construccion_cta_text_en" value="<?php echo esc_attr($values_en['construccion_cta_text']); ?>" <?php disabled(! $pages['en']); ?>>
			</div>

			<div class="momentum-admin-field">
				<label for="construccion_cta_url_es"><?php echo esc_html($schema['construccion_cta_url']['label']); ?> — <?php esc_html_e('Español', 'momentum-admin'); ?></label>
				<input type="url" id="construccion_cta_url_es" name="construccion_cta_url_es" placeholder="https://..." value="<?php echo esc_attr($values_es['construccion_cta_url']); ?>">
				<p class="momentum-admin-field-hint"><?php esc_html_e('Por defecto es un enlace de WhatsApp; puede reemplazarse por cualquier URL (otra página, un formulario, etc.).', 'momentum-admin'); ?></p>
			</div>

			<div class="momentum-admin-field">
				<label for="construccion_cta_url_en"><?php echo esc_html($schema['construccion_cta_url']['label']); ?> — <?php esc_html_e('Inglés', 'momentum-admin'); ?></label>
				<input type="url" id="construccion_cta_url_en" name="construccion_cta_url_en" placeholder="https://..." value="<?php echo esc_attr($values_en['construccion_cta_url']); ?>" <?php disabled(! $pages['en']); ?>>
			</div>

			<div class="momentum-admin-field">
				<label class="momentum-admin-checkbox">
					<input type="hidden" name="construccion_cta_new_tab_es" value="0">
					<input type="checkbox" id="construccion_cta_new_tab_es" name="construccion_cta_new_tab_es" value="1" <?php checked($values_es['construccion_cta_new_tab'] !== '0'); ?>>
					<?php echo esc_html($schema['construccion_cta_new_tab']['label']); ?> — <?php esc_html_e('Español', 'momentum-admin'); ?>
				</label>
			</div>

			<div class="momentum-admin-field">
				<label class="momentum-admin-checkbox">
					<input type="hidden" name="construccion_cta_new_tab_en" value="0">
					<input type="checkbox" id="construccion_cta_new_tab_en" name="construccion_cta_new_tab_en" value="1" <?php checked($values_en['construccion_cta_new_tab'] !== '0'); ?> <?php disabled(! $pages['en']); ?>>
					<?php echo esc_html($schema['construccion_cta_new_tab']['label']); ?> — <?php esc_html_e('Inglés', 'momentum-admin'); ?>
				</label>
			</div>
		</div>
	</details>

	<div class="momentum-admin-form-actions">
		<button type="submit" name="momentum_admin_construccion_content_submit" value="1" class="momentum-admin-button" id="momentum-construccion-content-submit"><?php esc_html_e('Guardar cambios', 'momentum-admin'); ?></button>
	</div>
</form>
