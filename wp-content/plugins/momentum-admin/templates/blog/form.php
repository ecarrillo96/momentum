<?php
/**
 * @var int    $post_id
 * @var array  $schema  momentum_articulo_meta_schema()
 * @var array  $meta    valores actuales de cada clave del schema
 * @var string $title
 * @var string $slug
 * @var string $excerpt
 * @var string $content
 * @var string $status
 * @var int    $featured_id
 * @var string $featured_alt
 * @var string $tags_value
 * @var string $date_value
 */
if (! defined('ABSPATH')) {
	exit;
}

$is_edit      = $post_id > 0;
$error_code   = isset($_GET['err']) ? sanitize_key(wp_unslash($_GET['err'])) : '';
$mensaje_code = isset($_GET['mensaje']) ? sanitize_key(wp_unslash($_GET['mensaje'])) : '';

$errores = [
	'titulo'              => __('El título es obligatorio.', 'momentum-admin'),
	'guardado'             => __('Ocurrió un error al guardar el artículo.', 'momentum-admin'),
	'publicar_incompleto'  => __('Debes completar título, contenido e imagen destacada antes de publicar. El artículo se guardó como borrador.', 'momentum-admin'),
];

$mensajes = [
	'creado'            => __('Artículo creado correctamente.', 'momentum-admin'),
	'actualizado'       => __('Artículo actualizado correctamente.', 'momentum-admin'),
	'publicado'         => __('Artículo publicado correctamente.', 'momentum-admin'),
	'guardado_borrador' => __('Artículo guardado como borrador.', 'momentum-admin'),
];

$page_title = $is_edit ? __('Editar artículo', 'momentum-admin') : __('Nuevo artículo', 'momentum-admin');
$action_url = $is_edit
	? add_query_arg(['accion' => 'editar', 'id' => $post_id], home_url('/admin/blog/'))
	: add_query_arg('accion', 'nueva', home_url('/admin/blog/'));

// TinyMCE clásico: sin H1 (el título del artículo ya es el H1 público, ver
// momentum_downgrade_h1()) y con "Añadir objeto" para insertar imágenes
// desde la Biblioteca de Medios en vez del diálogo nativo de TinyMCE.
$editor_settings_base = [
	'textarea_rows' => 12,
	'media_buttons' => true,
	'tinymce'       => [
		'block_formats' => 'Párrafo=p;Encabezado 2=h2;Encabezado 3=h3;Encabezado 4=h4;Cita=blockquote',
		'toolbar1'      => 'formatselect,bold,italic,bullist,numlist,blockquote,hr,link,unlink,image,undo,redo',
	],
	'quicktags' => true,
];
?>
<div class="momentum-admin-page-header">
	<h1><?php echo esc_html($page_title); ?></h1>
	<a class="momentum-admin-link" href="<?php echo esc_url(home_url('/admin/blog/')); ?>">&larr; <?php esc_html_e('Volver al listado', 'momentum-admin'); ?></a>
</div>

<?php if ($mensaje_code && isset($mensajes[$mensaje_code])) : ?>
	<p class="momentum-admin-alert momentum-admin-alert--success"><?php echo esc_html($mensajes[$mensaje_code]); ?></p>
<?php endif; ?>

<?php if ($error_code && isset($errores[$error_code])) : ?>
	<p class="momentum-admin-alert momentum-admin-alert--error"><?php echo esc_html($errores[$error_code]); ?></p>
<?php endif; ?>

<form method="post" action="<?php echo esc_url($action_url); ?>" class="momentum-admin-form" id="momentum-admin-article-form">
	<?php wp_nonce_field(Momentum_Admin_Blog::SAVE_NONCE_ACTION, Momentum_Admin_Blog::SAVE_NONCE_FIELD); ?>
	<input type="hidden" name="momentum_article_id" value="<?php echo esc_attr((string) $post_id); ?>">
	<input type="hidden" name="momentum_article_status" id="momentum_article_status" value="draft">

	<div class="momentum-admin-form-grid">
		<div class="momentum-admin-field momentum-admin-field--full">
			<label for="momentum_article_title"><?php esc_html_e('Título', 'momentum-admin'); ?> *</label>
			<input type="text" id="momentum_article_title" name="momentum_article_title" required value="<?php echo esc_attr($title); ?>" data-slug-source="1">
		</div>

		<div class="momentum-admin-field momentum-admin-field--full">
			<label for="momentum_article_slug"><?php esc_html_e('Slug / URL amigable', 'momentum-admin'); ?></label>
			<input type="text" id="momentum_article_slug" name="momentum_article_slug" value="<?php echo esc_attr($slug); ?>">
			<p class="momentum-admin-field-hint"><?php esc_html_e('Se genera automáticamente desde el título. Puedes editarlo; no cambia solo al actualizar un artículo existente.', 'momentum-admin'); ?></p>
		</div>

		<div class="momentum-admin-field momentum-admin-field--full">
			<label for="momentum_article_excerpt"><?php esc_html_e('Extracto', 'momentum-admin'); ?></label>
			<textarea id="momentum_article_excerpt" name="momentum_article_excerpt" rows="3"><?php echo esc_textarea($excerpt); ?></textarea>
		</div>

		<div class="momentum-admin-field momentum-admin-field--full">
			<label for="momentum_article_content"><?php esc_html_e('Contenido', 'momentum-admin'); ?></label>
			<?php wp_editor($content, 'momentum_article_content', array_merge($editor_settings_base, ['textarea_name' => 'momentum_article_content'])); ?>
		</div>

		<div class="momentum-admin-field">
			<label for="momentum_article_tags"><?php esc_html_e('Etiquetas', 'momentum-admin'); ?></label>
			<input type="hidden" id="momentum_article_tags" name="momentum_article_tags" value="<?php echo esc_attr($tags_value); ?>">
			<div id="momentum-tags-preview" class="momentum-admin-tag-list"></div>
			<input type="text" id="momentum-tags-input" placeholder="<?php esc_attr_e('Escribe una etiqueta y presiona Enter', 'momentum-admin'); ?>">
		</div>

		<div class="momentum-admin-field momentum-admin-field--full">
			<label for="momentum_article_date"><?php esc_html_e('Fecha de publicación', 'momentum-admin'); ?></label>
			<input type="datetime-local" id="momentum_article_date" name="momentum_article_date" value="<?php echo esc_attr($date_value); ?>">
			<p class="momentum-admin-field-hint"><?php esc_html_e('Toma la fecha y hora actuales por defecto. Cámbiala para programar la publicación.', 'momentum-admin'); ?></p>
		</div>

		<div class="momentum-admin-field momentum-admin-field--full">
			<label class="momentum-admin-checkbox">
				<input type="checkbox" id="momentum_destacado" name="momentum_destacado" value="1" <?php checked($meta['momentum_destacado'], '1'); ?>>
				<?php esc_html_e('Marcar como destacado', 'momentum-admin'); ?>
			</label>
		</div>

		<div class="momentum-admin-field momentum-admin-field--full">
			<label><?php esc_html_e('Imagen destacada', 'momentum-admin'); ?></label>
			<input type="hidden" id="momentum_article_featured_image" name="momentum_article_featured_image" value="<?php echo esc_attr((string) $featured_id); ?>">
			<div id="momentum-article-featured-preview" class="momentum-admin-media-preview">
				<?php if ($featured_id) : ?>
					<div class="momentum-gallery-item">
						<?php echo wp_get_attachment_image($featured_id, 'thumbnail'); ?>
						<button type="button" class="momentum-gallery-remove" aria-label="<?php esc_attr_e('Quitar imagen', 'momentum-admin'); ?>">&times;</button>
					</div>
				<?php endif; ?>
			</div>
			<button type="button" class="momentum-admin-button momentum-admin-button--secondary" id="momentum-article-featured-select"><?php esc_html_e('Seleccionar imagen', 'momentum-admin'); ?></button>

			<label for="momentum_article_featured_alt" class="momentum-admin-field-secondary-label"><?php esc_html_e('Texto alternativo de la imagen', 'momentum-admin'); ?></label>
			<input type="text" id="momentum_article_featured_alt" name="momentum_article_featured_alt" value="<?php echo esc_attr($featured_alt); ?>">
			<p class="momentum-admin-field-hint"><?php esc_html_e('Describe brevemente lo que se ve en la imagen. Ejemplo: "Fachada de casa en Lomas de Cocoyoc".', 'momentum-admin'); ?></p>
		</div>
	</div>

	<details class="momentum-admin-collapse">
		<summary><?php esc_html_e('SEO', 'momentum-admin'); ?></summary>

		<div class="momentum-admin-form-grid">
			<div class="momentum-admin-field">
				<label for="momentum_seo_titulo_es"><?php esc_html_e('Meta title', 'momentum-admin'); ?></label>
				<input type="text" id="momentum_seo_titulo_es" name="momentum_seo_titulo_es" value="<?php echo esc_attr($meta['momentum_seo_titulo_es']); ?>" data-counter="momentum-count-titulo-es" data-recommended="60">
				<p class="momentum-admin-char-count" id="momentum-count-titulo-es"></p>
			</div>

			<div class="momentum-admin-field momentum-admin-field--full">
				<label for="momentum_seo_desc_es"><?php esc_html_e('Meta description', 'momentum-admin'); ?></label>
				<textarea id="momentum_seo_desc_es" name="momentum_seo_desc_es" rows="3" data-counter="momentum-count-desc-es" data-recommended="160"><?php echo esc_textarea($meta['momentum_seo_desc_es']); ?></textarea>
				<p class="momentum-admin-char-count" id="momentum-count-desc-es"></p>
			</div>

			<div class="momentum-admin-field">
				<label for="momentum_seo_keyword"><?php esc_html_e('Palabra clave principal', 'momentum-admin'); ?></label>
				<input type="text" id="momentum_seo_keyword" name="momentum_seo_keyword" value="<?php echo esc_attr($meta['momentum_seo_keyword']); ?>">
			</div>

			<div class="momentum-admin-field">
				<label for="momentum_seo_canonical"><?php esc_html_e('URL canónica (opcional)', 'momentum-admin'); ?></label>
				<input type="url" id="momentum_seo_canonical" name="momentum_seo_canonical" value="<?php echo esc_attr($meta['momentum_seo_canonical']); ?>">
			</div>

			<div class="momentum-admin-field">
				<label class="momentum-admin-checkbox">
					<input type="checkbox" name="momentum_seo_noindex" value="1" <?php checked($meta['momentum_seo_noindex'], '1'); ?>>
					<?php esc_html_e('Impedir que buscadores indexen este artículo', 'momentum-admin'); ?>
				</label>
			</div>

			<div class="momentum-admin-field">
				<label for="momentum_seo_og_titulo"><?php esc_html_e('Título para redes sociales', 'momentum-admin'); ?></label>
				<input type="text" id="momentum_seo_og_titulo" name="momentum_seo_og_titulo" value="<?php echo esc_attr($meta['momentum_seo_og_titulo']); ?>">
			</div>

			<div class="momentum-admin-field momentum-admin-field--full">
				<label for="momentum_seo_og_desc"><?php esc_html_e('Descripción para redes sociales', 'momentum-admin'); ?></label>
				<textarea id="momentum_seo_og_desc" name="momentum_seo_og_desc" rows="2"><?php echo esc_textarea($meta['momentum_seo_og_desc']); ?></textarea>
			</div>

			<div class="momentum-admin-field momentum-admin-field--full">
				<label><?php esc_html_e('Imagen para redes sociales', 'momentum-admin'); ?></label>
				<input type="hidden" id="momentum_seo_og_imagen" name="momentum_seo_og_imagen" value="<?php echo esc_attr((string) $meta['momentum_seo_og_imagen']); ?>">
				<div id="momentum-og-image-preview" class="momentum-admin-media-preview">
					<?php if ($meta['momentum_seo_og_imagen']) : ?>
						<div class="momentum-gallery-item">
							<?php echo wp_get_attachment_image((int) $meta['momentum_seo_og_imagen'], 'thumbnail'); ?>
							<button type="button" class="momentum-gallery-remove" aria-label="<?php esc_attr_e('Quitar imagen', 'momentum-admin'); ?>">&times;</button>
						</div>
					<?php endif; ?>
				</div>
				<button type="button" class="momentum-admin-button momentum-admin-button--secondary" id="momentum-og-image-select"><?php esc_html_e('Seleccionar imagen', 'momentum-admin'); ?></button>
			</div>
		</div>
	</details>

	<div class="momentum-admin-form-actions">
		<button type="submit" name="momentum_admin_article_submit" value="1" class="momentum-admin-button momentum-admin-button--secondary" data-status="draft"><?php esc_html_e('Guardar como borrador', 'momentum-admin'); ?></button>
		<button type="submit" name="momentum_admin_article_submit" value="1" class="momentum-admin-button" data-status="publish"><?php esc_html_e('Publicar artículo', 'momentum-admin'); ?></button>
		<?php if ($is_edit) : ?>
			<a href="<?php echo esc_url($status === 'publish' ? get_permalink($post_id) : get_preview_post_link($post_id)); ?>" target="_blank" rel="noopener noreferrer" class="momentum-admin-link"><?php esc_html_e('Previsualizar', 'momentum-admin'); ?></a>
		<?php endif; ?>
	</div>
</form>
