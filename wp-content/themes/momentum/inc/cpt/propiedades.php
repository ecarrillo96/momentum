<?php
/**
 * Custom Post Type: Propiedades.
 *
 * Meta fields are stored as native post meta (no ACF) using field keys
 * that map 1:1 to ACF field names, so the data can be migrated to
 * ACF Pro later without renaming anything.
 */

if (! defined('ABSPATH')) {
	exit;
}

function momentum_register_cpt_propiedades()
{
	$labels = [
		'name'                  => 'Propiedades',
		'singular_name'         => 'Propiedad',
		'menu_name'             => 'Propiedades',
		'name_admin_bar'        => 'Propiedad',
		'add_new'               => 'Añadir nueva',
		'add_new_item'          => 'Añadir nueva propiedad',
		'new_item'              => 'Nueva propiedad',
		'edit_item'             => 'Editar propiedad',
		'view_item'             => 'Ver propiedad',
		'view_items'            => 'Ver propiedades',
		'all_items'             => 'Todas las propiedades',
		'search_items'          => 'Buscar propiedades',
		'not_found'             => 'No se encontraron propiedades',
		'not_found_in_trash'    => 'No hay propiedades en la papelera',
		'featured_image'        => 'Imagen principal',
		'set_featured_image'    => 'Establecer imagen principal',
		'remove_featured_image' => 'Quitar imagen principal',
		'use_featured_image'    => 'Usar como imagen principal',
		'archives'              => 'Archivo de propiedades',
	];

	register_post_type('propiedades', [
		'labels'          => $labels,
		'public'          => true,
		'has_archive'     => true,
		'show_in_rest'    => true,
		'menu_icon'       => 'dashicons-admin-home',
		'menu_position'   => 5,
		'capability_type' => 'post',
		'supports'        => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'author'],
		'rewrite'         => ['slug' => 'propiedades', 'with_front' => false],
	]);
}
add_action('init', 'momentum_register_cpt_propiedades');

/**
 * Native post meta fields for each propiedad.
 *
 * Keys intentionally match what an ACF field group for this CPT would use,
 * so a future migration only needs to add the field group, not touch data.
 */
function momentum_propiedad_meta_schema()
{
	return [
		'precio'           => ['type' => 'number', 'label' => 'Precio (MXN)'],
		'tipo_operacion'   => ['type' => 'string', 'label' => 'Tipo de operación', 'options' => ['Venta', 'Renta']],
		// Deprecado: ya no se usa en formularios, listados ni la página pública.
		// Se conserva registrado para no perder los valores históricos guardados.
		'tipo_propiedad'   => ['type' => 'string', 'label' => 'Tipo de propiedad', 'options' => ['Casa', 'Departamento', 'Terreno'], 'deprecated' => true],
		'recamaras'        => ['type' => 'number', 'label' => 'Recámaras'],
		'banos'            => ['type' => 'number', 'label' => 'Baños'],
		'terreno_m2'       => ['type' => 'number', 'label' => 'Terreno (m²)'],
		'construccion_m2'  => ['type' => 'number', 'label' => 'Construcción (m²)'],
		// Deprecado: reemplazado por 'zona'. Se conserva registrado por los mismos motivos.
		'ubicacion'        => ['type' => 'string', 'label' => 'Ubicación', 'deprecated' => true],
		'estado'           => ['type' => 'string', 'label' => 'Estado de la propiedad', 'options' => ['Disponible', 'Apartada', 'Preventa', 'Vendida', 'Rentada', 'Remate']],
		// Deprecado: reemplazado por 'zona'. Se conserva registrado por los mismos motivos.
		'ciudad'           => ['type' => 'string', 'label' => 'Ciudad', 'deprecated' => true],
		// options_map ya no es una lista fija: se llena en tiempo real desde
		// el CPT 'zonas' (Admin > Zonas es la única fuente de verdad).
		'zona'             => ['type' => 'string', 'label' => 'Zona', 'options_map' => momentum_get_zonas()],
		'codigo_propiedad' => ['type' => 'string', 'label' => 'Código de propiedad'],
		'taxes_usd'        => ['type' => 'number', 'label' => 'Taxes USD', 'nonnegative' => true],
		'video_url'        => ['type' => 'string', 'label' => 'Video', 'url' => true],
		'video_url_en'     => ['type' => 'string', 'label' => 'Video en inglés', 'url' => true],
		'caracteristicas'  => ['type' => 'string', 'label' => 'Características (una por línea)', 'textarea' => true],
		'galeria'          => ['type' => 'string', 'label' => 'Galería de imágenes', 'gallery' => true],
	];
}

function momentum_sanitize_meta_number($value)
{
	return is_numeric($value) ? floatval($value) : 0;
}

/**
 * Como momentum_sanitize_meta_number(), pero rechaza negativos devolviendo 0
 * (mismo criterio "0 = vacío" que ya usan precio/recamaras/etc. en los listados).
 * Usado por 'taxes_usd' porque ese campo no debe aceptar montos negativos.
 */
function momentum_sanitize_nonnegative_number($value)
{
	if (! is_numeric($value)) {
		return 0;
	}

	$number = (float) $value;

	return $number < 0 ? 0 : $number;
}

/**
 * Sanitiza URLs de video (YouTube, Vimeo, etc.). Rechaza explícitamente
 * cualquier valor con '<' (iframe/HTML pegado) o que no sea una URL http(s)
 * bien formada — nunca se guarda HTML, iframes ni contenido Base64.
 */
function momentum_sanitize_video_url($value)
{
	$value = trim((string) $value);

	if ($value === '' || strpos($value, '<') !== false) {
		return '';
	}

	$clean = esc_url_raw($value, ['http', 'https']);

	if ($clean === '' || ! preg_match('#^https?://#i', $clean) || ! filter_var($clean, FILTER_VALIDATE_URL)) {
		return '';
	}

	return $clean;
}

/**
 * 'galeria' stores a comma-separated list of attachment IDs — the native-meta
 * equivalent of what an ACF Gallery field would hold, so migrating later only
 * means adding the field group, not transforming data.
 */
function momentum_sanitize_gallery_ids($value)
{
	$ids = array_filter(array_map('absint', explode(',', (string) $value)));

	return implode(',', $ids);
}

/**
 * Valores permitidos de un campo con opciones, sea lista indexada
 * (['Venta','Renta']) u opciones slug => label ('options_map', ej. 'zona').
 */
function momentum_propiedad_field_allowed_values(array $field): array
{
	if (! empty($field['options_map'])) {
		return array_keys($field['options_map']);
	}

	return $field['options'] ?? [];
}

function momentum_sanitize_zona($value)
{
	$schema = momentum_propiedad_meta_schema();
	$allowed = momentum_propiedad_field_allowed_values($schema['zona']);

	return in_array($value, $allowed, true) ? $value : '';
}

function momentum_register_propiedad_meta()
{
	foreach (momentum_propiedad_meta_schema() as $key => $schema) {
		$sanitize_callback = 'sanitize_text_field';

		if (! empty($schema['options_map'])) {
			$sanitize_callback = 'momentum_sanitize_zona';
		} elseif (! empty($schema['url'])) {
			$sanitize_callback = 'momentum_sanitize_video_url';
		} elseif ($schema['type'] === 'number') {
			$sanitize_callback = ! empty($schema['nonnegative']) ? 'momentum_sanitize_nonnegative_number' : 'momentum_sanitize_meta_number';
		} elseif (! empty($schema['textarea'])) {
			$sanitize_callback = 'sanitize_textarea_field';
		} elseif (! empty($schema['gallery'])) {
			$sanitize_callback = 'momentum_sanitize_gallery_ids';
		}

		register_post_meta('propiedades', $key, [
			'type'              => $schema['type'],
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => $sanitize_callback,
			'auth_callback'     => function () {
				return current_user_can('edit_posts');
			},
		]);
	}
}
add_action('init', 'momentum_register_propiedad_meta');

function momentum_add_propiedad_metabox()
{
	add_meta_box(
		'momentum_propiedad_datos',
		'Datos de la propiedad',
		'momentum_render_propiedad_metabox',
		'propiedades',
		'normal',
		'high'
	);
}
add_action('add_meta_boxes', 'momentum_add_propiedad_metabox');

function momentum_render_propiedad_metabox($post)
{
	wp_nonce_field('momentum_propiedad_meta_save', 'momentum_propiedad_meta_nonce');

	$schema = momentum_propiedad_meta_schema();
	$values = [];

	foreach ($schema as $key => $field) {
		$values[$key] = get_post_meta($post->ID, $key, true);
	}
	?>
	<div class="momentum-meta-grid">
		<div class="momentum-meta-field">
			<label for="momentum_codigo_propiedad">Código de propiedad</label>
			<input type="text" id="momentum_codigo_propiedad" name="momentum_codigo_propiedad"
				value="<?php echo esc_attr($values['codigo_propiedad']); ?>" placeholder="MC-001">
		</div>

		<div class="momentum-meta-field">
			<label for="momentum_precio">Precio (MXN)</label>
			<input type="number" step="0.01" min="0" id="momentum_precio" name="momentum_precio"
				value="<?php echo esc_attr($values['precio']); ?>">
		</div>

		<div class="momentum-meta-field">
			<label for="momentum_tipo_operacion">Tipo de operación</label>
			<select id="momentum_tipo_operacion" name="momentum_tipo_operacion">
				<option value="">Selecciona</option>
				<?php foreach ($schema['tipo_operacion']['options'] as $option) : ?>
					<option value="<?php echo esc_attr($option); ?>" <?php selected($values['tipo_operacion'], $option); ?>>
						<?php echo esc_html($option); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="momentum-meta-field">
			<label for="momentum_recamaras">Recámaras</label>
			<input type="number" step="1" min="0" id="momentum_recamaras" name="momentum_recamaras"
				value="<?php echo esc_attr($values['recamaras']); ?>">
		</div>

		<div class="momentum-meta-field">
			<label for="momentum_banos">Baños</label>
			<input type="number" step="0.5" min="0" id="momentum_banos" name="momentum_banos"
				value="<?php echo esc_attr($values['banos']); ?>">
		</div>

		<div class="momentum-meta-field">
			<label for="momentum_terreno_m2">Terreno (m²)</label>
			<input type="number" step="1" min="0" id="momentum_terreno_m2" name="momentum_terreno_m2"
				value="<?php echo esc_attr($values['terreno_m2']); ?>">
		</div>

		<div class="momentum-meta-field">
			<label for="momentum_construccion_m2">Construcción (m²)</label>
			<input type="number" step="1" min="0" id="momentum_construccion_m2" name="momentum_construccion_m2"
				value="<?php echo esc_attr($values['construccion_m2']); ?>">
		</div>

		<div class="momentum-meta-field">
			<label for="momentum_zona">Zona</label>
			<select id="momentum_zona" name="momentum_zona">
				<option value="">Selecciona una zona</option>
				<?php foreach ($schema['zona']['options_map'] as $value => $label) : ?>
					<option value="<?php echo esc_attr($value); ?>" <?php selected($values['zona'], $value); ?>>
						<?php echo esc_html($label); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="momentum-meta-field">
			<label for="momentum_estado">Estado de la propiedad</label>
			<select id="momentum_estado" name="momentum_estado">
				<option value="">Selecciona</option>
				<?php foreach ($schema['estado']['options'] as $option) : ?>
					<option value="<?php echo esc_attr($option); ?>" <?php selected($values['estado'], $option); ?>>
						<?php echo esc_html($option); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="momentum-meta-field">
			<label for="momentum_taxes_usd">Taxes USD</label>
			<input type="number" step="0.01" min="0" id="momentum_taxes_usd" name="momentum_taxes_usd"
				value="<?php echo esc_attr($values['taxes_usd']); ?>">
			<p class="momentum-meta-field-hint">Impuestos expresados en USD</p>
		</div>

		<div class="momentum-meta-field">
			<label for="momentum_video_url">Video</label>
			<input type="url" id="momentum_video_url" name="momentum_video_url"
				value="<?php echo esc_attr($values['video_url']); ?>" placeholder="https://www.youtube.com/watch?v=...">
		</div>

		<div class="momentum-meta-field">
			<label for="momentum_video_url_en">Video en inglés</label>
			<input type="url" id="momentum_video_url_en" name="momentum_video_url_en"
				value="<?php echo esc_attr($values['video_url_en']); ?>" placeholder="https://www.youtube.com/watch?v=...">
		</div>

		<div class="momentum-meta-field momentum-meta-field--full">
			<label for="momentum_caracteristicas">Características (una por línea)</label>
			<textarea id="momentum_caracteristicas" name="momentum_caracteristicas" rows="6"
				placeholder="Alberca con calefacción&#10;Jardín amplio&#10;Terraza techada"><?php echo esc_textarea($values['caracteristicas']); ?></textarea>
		</div>

		<div class="momentum-meta-field momentum-meta-field--full">
			<label>Galería de imágenes</label>
			<input type="hidden" id="momentum_galeria" name="momentum_galeria" value="<?php echo esc_attr($values['galeria']); ?>">
			<div id="momentum-galeria-preview" class="momentum-galeria-preview">
				<?php foreach (array_filter(explode(',', $values['galeria'])) as $attachment_id) : ?>
					<?php echo wp_get_attachment_image((int) $attachment_id, 'thumbnail'); ?>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button" id="momentum-galeria-select">Seleccionar imágenes</button>
		</div>
	</div>

	<style>
		.momentum-meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 8px; }
		.momentum-meta-field label { display: block; font-weight: 600; margin-bottom: 4px; }
		.momentum-meta-field input,
		.momentum-meta-field select,
		.momentum-meta-field textarea { width: 100%; }
		.momentum-meta-field--full { grid-column: 1 / -1; }
		.momentum-meta-field-hint { margin: 4px 0 0; font-size: 12px; color: #666; }
		.momentum-galeria-preview { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; }
		.momentum-galeria-preview img { width: 80px; height: 80px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; }
	</style>
	<?php
}

function momentum_enqueue_propiedad_admin_assets($hook)
{
	if (! in_array($hook, ['post.php', 'post-new.php'], true) || get_post_type() !== 'propiedades') {
		return;
	}

	wp_enqueue_media();
	wp_enqueue_script(
		'momentum-admin-gallery',
		get_stylesheet_directory_uri() . '/assets/js/admin-gallery.js',
		['jquery'],
		wp_get_theme()->get('Version'),
		true
	);
}
add_action('admin_enqueue_scripts', 'momentum_enqueue_propiedad_admin_assets');

/**
 * Guarda en post meta los campos de $schema presentes en $_POST (nombre
 * esperado: "momentum_{key}"), aplicando el sanitizador correspondiente al
 * tipo de cada campo. Compartida por Propiedades (momentum_save_propiedad_meta())
 * y Terrenos (momentum_save_terreno_meta() en inc/cpt/terrenos.php) para no
 * duplicar el switch de sanitización entre ambos CPT.
 *
 * @param string[] $skip_keys Claves a ignorar aunque lleguen por POST (p. ej.
 *                             'codigo_propiedad' en Terrenos, que se autogenera
 *                             y nunca debe aceptarse desde el formulario).
 */
function momentum_save_meta_fields(int $post_id, array $schema, array $skip_keys = []): void
{
	foreach ($schema as $key => $field) {
		if (in_array($key, $skip_keys, true)) {
			continue;
		}

		// Deprecados: ya no se renderizan en ningún formulario, así que no
		// deberían llegar por POST; se ignoran explícitamente por seguridad.
		if (! empty($field['deprecated'])) {
			continue;
		}

		$post_key = "momentum_$key";

		if (! isset($_POST[$post_key])) {
			continue;
		}

		$raw = wp_unslash($_POST[$post_key]);

		if (! empty($field['options_map'])) {
			update_post_meta($post_id, $key, momentum_sanitize_zona($raw));
			continue;
		}

		if (! empty($field['url'])) {
			update_post_meta($post_id, $key, momentum_sanitize_video_url($raw));
			continue;
		}

		if ($field['type'] === 'number') {
			$sanitize = ! empty($field['nonnegative']) ? 'momentum_sanitize_nonnegative_number' : 'momentum_sanitize_meta_number';
			update_post_meta($post_id, $key, $sanitize($raw));
			continue;
		}

		if (! empty($field['options'])) {
			if (in_array($raw, $field['options'], true)) {
				update_post_meta($post_id, $key, $raw);
			}
			continue;
		}

		if (! empty($field['textarea'])) {
			update_post_meta($post_id, $key, sanitize_textarea_field($raw));
			continue;
		}

		if (! empty($field['gallery'])) {
			update_post_meta($post_id, $key, momentum_sanitize_gallery_ids($raw));
			continue;
		}

		update_post_meta($post_id, $key, sanitize_text_field($raw));
	}
}

function momentum_save_propiedad_meta($post_id)
{
	if (
		! isset($_POST['momentum_propiedad_meta_nonce'])
		|| ! wp_verify_nonce($_POST['momentum_propiedad_meta_nonce'], 'momentum_propiedad_meta_save')
	) {
		return;
	}

	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}

	if (! current_user_can('edit_post', $post_id)) {
		return;
	}

	momentum_save_meta_fields($post_id, momentum_propiedad_meta_schema());
}
add_action('save_post_propiedades', 'momentum_save_propiedad_meta');

/**
 * Admin list table columns so listings can be scanned without opening each post.
 */
function momentum_propiedad_admin_columns($columns)
{
	$columns['precio']         = 'Precio';
	$columns['tipo_operacion'] = 'Operación';
	$columns['zona']           = 'Zona';
	$columns['estado']         = 'Estado';

	return $columns;
}
add_filter('manage_propiedades_posts_columns', 'momentum_propiedad_admin_columns');

function momentum_propiedad_admin_column_content($column, $post_id)
{
	switch ($column) {
		case 'precio':
			$precio = get_post_meta($post_id, 'precio', true);
			echo $precio ? esc_html(momentum_format_price((float) $precio)) : '—';
			break;

		case 'zona':
			$zona   = (string) get_post_meta($post_id, 'zona', true);
			$schema = momentum_propiedad_meta_schema();
			echo $zona !== '' && isset($schema['zona']['options_map'][$zona])
				? esc_html($schema['zona']['options_map'][$zona])
				: '—';
			break;

		case 'tipo_operacion':
		case 'estado':
			$value = get_post_meta($post_id, $column, true);
			echo $value ? esc_html($value) : '—';
			break;
	}
}
add_action('manage_propiedades_posts_custom_column', 'momentum_propiedad_admin_column_content', 10, 2);
