<?php
/**
 * @var int    $post_id
 * @var array  $schema
 * @var array  $values
 * @var string $title
 * @var string $content
 * @var string $status
 * @var int    $featured_id
 * @var array  $gallery_ids
 */
if (! defined('ABSPATH')) {
	exit;
}

$is_edit      = $post_id > 0;
$error_code   = isset($_GET['error']) ? sanitize_key(wp_unslash($_GET['error'])) : '';
$mensaje_code = isset($_GET['mensaje']) ? sanitize_key(wp_unslash($_GET['mensaje'])) : '';

$errores = [
	'titulo'     => __('El título es obligatorio.', 'momentum-admin'),
	'taxes_usd'  => __('Taxes USD debe ser un número mayor o igual que cero.', 'momentum-admin'),
	'video'      => __('El campo Video debe ser una URL válida (o dejarse vacío).', 'momentum-admin'),
	'video_en'   => __('El campo Video en inglés debe ser una URL válida (o dejarse vacío).', 'momentum-admin'),
];

$mensajes = [
	'creada'      => __('Propiedad creada correctamente.', 'momentum-admin'),
	'actualizada' => __('Propiedad actualizada correctamente.', 'momentum-admin'),
];

$page_title = $is_edit ? __('Editar propiedad', 'momentum-admin') : __('Nueva propiedad', 'momentum-admin');
$action_url = $is_edit
	? add_query_arg(['accion' => 'editar', 'id' => $post_id], home_url('/admin/propiedades/'))
	: add_query_arg('accion', 'nueva', home_url('/admin/propiedades/'));
?>
<div class="momentum-admin-page-header">
	<h1><?php echo esc_html($page_title); ?></h1>
	<a class="momentum-admin-link" href="<?php echo esc_url(home_url('/admin/propiedades/')); ?>">&larr; <?php esc_html_e('Volver al listado', 'momentum-admin'); ?></a>
</div>

<?php if ($mensaje_code && isset($mensajes[$mensaje_code])) : ?>
	<p class="momentum-admin-alert momentum-admin-alert--success"><?php echo esc_html($mensajes[$mensaje_code]); ?></p>
<?php endif; ?>

<?php if ($error_code && isset($errores[$error_code])) : ?>
	<p class="momentum-admin-alert momentum-admin-alert--error"><?php echo esc_html($errores[$error_code]); ?></p>
<?php endif; ?>

<form method="post" action="<?php echo esc_url($action_url); ?>" class="momentum-admin-form" id="momentum-admin-property-form">
	<?php wp_nonce_field(Momentum_Admin_Properties::SAVE_NONCE_ACTION, Momentum_Admin_Properties::SAVE_NONCE_FIELD); ?>
	<?php // Nonce del metabox nativo del tema: permite que momentum_save_propiedad_meta() guarde la meta. ?>
	<?php wp_nonce_field('momentum_propiedad_meta_save', 'momentum_propiedad_meta_nonce'); ?>
	<input type="hidden" name="momentum_property_id" value="<?php echo esc_attr((string) $post_id); ?>">

	<div class="momentum-admin-form-grid">
		<div class="momentum-admin-field momentum-admin-field--full">
			<label for="momentum_property_title"><?php esc_html_e('Título', 'momentum-admin'); ?> *</label>
			<input type="text" id="momentum_property_title" name="momentum_property_title" required value="<?php echo esc_attr($title); ?>">
		</div>

		<div class="momentum-admin-field momentum-admin-field--full">
			<label for="momentum_property_content"><?php esc_html_e('Descripción', 'momentum-admin'); ?></label>
			<textarea id="momentum_property_content" name="momentum_property_content" rows="6"><?php echo esc_textarea($content); ?></textarea>
		</div>

		<div class="momentum-admin-field">
			<label for="momentum_codigo_propiedad"><?php esc_html_e('Código de propiedad', 'momentum-admin'); ?></label>
			<input type="text" id="momentum_codigo_propiedad" name="momentum_codigo_propiedad" placeholder="MC-001" value="<?php echo esc_attr($values['codigo_propiedad']); ?>">
		</div>

		<div class="momentum-admin-field">
			<label for="momentum_precio"><?php esc_html_e('Precio (MXN)', 'momentum-admin'); ?></label>
			<input type="number" step="0.01" min="0" id="momentum_precio" name="momentum_precio" value="<?php echo esc_attr($values['precio']); ?>">
		</div>

		<div class="momentum-admin-field">
			<label for="momentum_tipo_operacion"><?php esc_html_e('Tipo de operación', 'momentum-admin'); ?></label>
			<select id="momentum_tipo_operacion" name="momentum_tipo_operacion">
				<option value=""><?php esc_html_e('Selecciona', 'momentum-admin'); ?></option>
				<?php foreach ($schema['tipo_operacion']['options'] as $option) : ?>
					<option value="<?php echo esc_attr($option); ?>" <?php selected($values['tipo_operacion'], $option); ?>><?php echo esc_html($option); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="momentum-admin-field">
			<label for="momentum_estado"><?php esc_html_e('Estado de la propiedad', 'momentum-admin'); ?></label>
			<select id="momentum_estado" name="momentum_estado">
				<option value=""><?php esc_html_e('Selecciona', 'momentum-admin'); ?></option>
				<?php foreach ($schema['estado']['options'] as $option) : ?>
					<option value="<?php echo esc_attr($option); ?>" <?php selected($values['estado'], $option); ?>><?php echo esc_html($option); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="momentum-admin-field">
			<label for="momentum_property_status"><?php esc_html_e('Visibilidad', 'momentum-admin'); ?></label>
			<select id="momentum_property_status" name="momentum_property_status">
				<option value="publish" <?php selected($status, 'publish'); ?>><?php esc_html_e('Publicada', 'momentum-admin'); ?></option>
				<option value="draft" <?php selected($status, 'draft'); ?>><?php esc_html_e('Borrador', 'momentum-admin'); ?></option>
			</select>
		</div>

		<div class="momentum-admin-field">
			<label for="momentum_zona"><?php esc_html_e('Zona', 'momentum-admin'); ?></label>
			<select id="momentum_zona" name="momentum_zona">
				<option value=""><?php esc_html_e('Selecciona una zona', 'momentum-admin'); ?></option>
				<?php foreach ($schema['zona']['options_map'] as $value => $label) : ?>
					<option value="<?php echo esc_attr($value); ?>" <?php selected($values['zona'], $value); ?>><?php echo esc_html($label); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="momentum-admin-field">
			<label for="momentum_recamaras"><?php esc_html_e('Recámaras', 'momentum-admin'); ?></label>
			<input type="number" step="1" min="0" id="momentum_recamaras" name="momentum_recamaras" value="<?php echo esc_attr($values['recamaras']); ?>">
		</div>

		<div class="momentum-admin-field">
			<label for="momentum_banos"><?php esc_html_e('Baños', 'momentum-admin'); ?></label>
			<input type="number" step="0.5" min="0" id="momentum_banos" name="momentum_banos" value="<?php echo esc_attr($values['banos']); ?>">
		</div>

		<div class="momentum-admin-field">
			<label for="momentum_terreno_m2"><?php esc_html_e('Terreno (m²)', 'momentum-admin'); ?></label>
			<input type="number" step="1" min="0" id="momentum_terreno_m2" name="momentum_terreno_m2" value="<?php echo esc_attr($values['terreno_m2']); ?>">
		</div>

		<div class="momentum-admin-field">
			<label for="momentum_construccion_m2"><?php esc_html_e('Construcción (m²)', 'momentum-admin'); ?></label>
			<input type="number" step="1" min="0" id="momentum_construccion_m2" name="momentum_construccion_m2" value="<?php echo esc_attr($values['construccion_m2']); ?>">
		</div>

		<div class="momentum-admin-field">
			<label for="momentum_taxes_usd"><?php esc_html_e('Taxes USD', 'momentum-admin'); ?></label>
			<input type="number" step="0.01" min="0" id="momentum_taxes_usd" name="momentum_taxes_usd" value="<?php echo esc_attr($values['taxes_usd']); ?>">
			<p class="momentum-admin-field-hint"><?php esc_html_e('Impuestos expresados en USD', 'momentum-admin'); ?></p>
		</div>

		<div class="momentum-admin-field momentum-admin-field--full">
			<label for="momentum_caracteristicas"><?php esc_html_e('Características (una por línea)', 'momentum-admin'); ?></label>
			<textarea id="momentum_caracteristicas" name="momentum_caracteristicas" rows="5" placeholder="Alberca con calefacción&#10;Jardín amplio&#10;Terraza techada"><?php echo esc_textarea($values['caracteristicas']); ?></textarea>
		</div>

		<div class="momentum-admin-field">
			<label for="momentum_video_url"><?php esc_html_e('Video', 'momentum-admin'); ?></label>
			<input type="url" id="momentum_video_url" name="momentum_video_url" placeholder="https://www.youtube.com/watch?v=..." value="<?php echo esc_attr($values['video_url']); ?>">
		</div>

		<div class="momentum-admin-field">
			<label for="momentum_video_url_en"><?php esc_html_e('Video en inglés', 'momentum-admin'); ?></label>
			<input type="url" id="momentum_video_url_en" name="momentum_video_url_en" placeholder="https://www.youtube.com/watch?v=..." value="<?php echo esc_attr($values['video_url_en']); ?>">
		</div>

		<div class="momentum-admin-field momentum-admin-field--full">
			<label><?php esc_html_e('Imagen destacada', 'momentum-admin'); ?></label>
			<input type="hidden" id="momentum_property_featured_image" name="momentum_property_featured_image" value="<?php echo esc_attr((string) $featured_id); ?>">
			<div id="momentum-featured-preview" class="momentum-admin-media-preview">
				<?php if ($featured_id) : ?>
					<div class="momentum-gallery-item">
						<?php echo wp_get_attachment_image($featured_id, 'thumbnail'); ?>
						<button type="button" class="momentum-gallery-remove" aria-label="<?php esc_attr_e('Quitar imagen', 'momentum-admin'); ?>">&times;</button>
					</div>
				<?php endif; ?>
			</div>
			<button type="button" class="momentum-admin-button momentum-admin-button--secondary" id="momentum-featured-select"><?php esc_html_e('Seleccionar imagen', 'momentum-admin'); ?></button>
		</div>

		<div class="momentum-admin-field momentum-admin-field--full">
			<label><?php esc_html_e('Galería de imágenes', 'momentum-admin'); ?></label>
			<input type="hidden" id="momentum_galeria" name="momentum_galeria" value="<?php echo esc_attr($values['galeria']); ?>">
			<div id="momentum-galeria-preview" class="momentum-admin-media-preview">
				<?php foreach ($gallery_ids as $attachment_id) : ?>
					<div class="momentum-gallery-item" data-id="<?php echo esc_attr((string) $attachment_id); ?>">
						<?php echo wp_get_attachment_image($attachment_id, 'thumbnail'); ?>
						<button type="button" class="momentum-gallery-remove" aria-label="<?php esc_attr_e('Eliminar imagen', 'momentum-admin'); ?>">&times;</button>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="momentum-admin-button momentum-admin-button--secondary" id="momentum-galeria-select"><?php esc_html_e('Seleccionar imágenes', 'momentum-admin'); ?></button>
		</div>
	</div>

	<div class="momentum-admin-form-actions">
		<button type="submit" name="momentum_admin_property_submit" value="1" class="momentum-admin-button"><?php esc_html_e('Guardar propiedad', 'momentum-admin'); ?></button>
		<?php if ($is_edit) : ?>
			<a href="<?php echo esc_url(get_permalink($post_id)); ?>" target="_blank" rel="noopener noreferrer" class="momentum-admin-link"><?php esc_html_e('Ver propiedad pública', 'momentum-admin'); ?></a>
		<?php endif; ?>
	</div>
</form>
