<?php
/**
 * Custom Post Type: Propiedades.
 *
 * Meta fields are stored as native post meta (no ACF) using field keys
 * that map 1:1 to ACF field names, so the data can be migrated to
 * ACF Pro later without renaming anything.
 *
 * has_archive es false a propósito (mismo criterio que 'terrenos' en
 * inc/cpt/terrenos.php): el archivo nativo del CPT en /propiedades/
 * competía por SEO con /venta/ y /renta/ -las páginas reales que muestran
 * este catálogo filtrado por operación, cada una con su propio título y
 * descripción-, generando contenido duplicado e indexable sin metadatos
 * propios. Con has_archive=false, el slug del CPT solo sigue generando
 * las rutas de detalle (/propiedades/{slug-de-la-propiedad}/), sin tocar
 * las fichas individuales ni /venta//renta/. Las visitas a /propiedades/
 * que queden de antes de este cambio se redirigen 301 a /venta/ (ver
 * inc/legacy-redirects.php).
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
		'has_archive'     => false,
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
		'precio'           => ['type' => 'number', 'label' => 'Precio (MXN)', 'nonnegative' => true],
		'tipo_operacion'   => ['type' => 'string', 'label' => 'Tipo de operación', 'options' => ['Venta', 'Renta']],
		// Deprecado: ya no se usa en formularios, listados ni la página pública.
		// Se conserva registrado para no perder los valores históricos guardados.
		'tipo_propiedad'   => ['type' => 'string', 'label' => 'Tipo de propiedad', 'options' => ['Casa', 'Departamento', 'Terreno'], 'deprecated' => true],
		'recamaras'        => ['type' => 'number', 'label' => 'Recámaras', 'nonnegative' => true],
		// Texto libre a propósito: el cliente captura formatos como "6 2/2"
		// (baños completos + medios baños) que no se pueden representar como
		// número. No se filtra, ordena ni calcula nada por este campo, así que
		// no hace falta forzarlo a numérico.
		'banos'            => ['type' => 'string', 'label' => 'Baños'],
		'terreno_m2'       => ['type' => 'number', 'label' => 'Terreno (m²)', 'nonnegative' => true],
		'construccion_m2'  => ['type' => 'number', 'label' => 'Construcción (m²)', 'nonnegative' => true],
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
 * Normaliza saltos de línea a '\n' puro. Un <textarea> real de navegador
 * envía '\r\n' en el POST -por especificación HTML-, mientras que el texto
 * que Momentum_Property_Translation_Service escribe (título/descripción/
 * características ya traducidos por DeepL) siempre usa '\n' puro. Sin este
 * paso, volver a guardar una ficha desde un formulario real -aunque el
 * usuario solo haya tocado OTRO campo, como el precio- deja el contenido de
 * texto con '\r\n', que ya no coincide en bytes con la huella md5 guardada
 * en '_momentum_sync_snapshot' -eso hacía que el campo pareciera "editado a
 * mano" y quedara protegido para siempre, aunque nadie lo hubiera tocado
 * -bug real detectado en pruebas QA: una propiedad de prueba dejó de
 * re-traducirse por completo (0 caracteres enviados a DeepL) después de un
 * segundo guardado normal-. Aplicar esto ANTES de sanear/guardar hace que
 * el contenido quede en el mismo formato sin importar si vino de PHP o de
 * un navegador real, así el snapshot deja de dar falsos positivos.
 */
function momentum_normalize_line_endings(string $value): string
{
	return str_replace(["\r\n", "\r"], "\n", $value);
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
 * Quita la imagen destacada de la galería si quedó duplicada ahí -no
 * debería estar en ambos lados a la vez-. Detectado en auditoría (2026-08-22,
 * ficha MC-102): ~80% del catálogo migrado (94 de 118 fichas) tenía la
 * destacada repetida dentro de 'galeria', probablemente arrastrado de la
 * importación original de contenido, no de un guardado nuevo desde este
 * formulario -pero nada impedía que un guardado futuro lo repitiera, así
 * que se limpia aquí en cada guardado, no solo una vez sobre los datos
 * existentes-.
 *
 * Enganchado a 'save_post_propiedades'/'save_post_terrenos' en prioridad
 * 40 -después de que la meta ya se guardó (prioridad 10) y después de que
 * Momentum_Property_Translation_Service sincronizó 'galeria' hacia la
 * traducción (prioridad 30)-, así que limpia tanto la ficha que se acaba
 * de guardar como su traducción, sin lógica aparte para cada una.
 *
 * No cubre el flujo del panel propio (Momentum_Admin_Properties/Terrenos):
 * ahí la imagen destacada se guarda con set_post_thumbnail() DESPUÉS de
 * este mismo hook (ver el comentario en handle_save() de esas clases), así
 * que en el momento en que este hook corre para ese guardado todavía no
 * hay imagen destacada nueva que comparar. Por eso ambas clases llaman
 * esta misma función otra vez, explícitamente, justo después de guardar la
 * imagen destacada -mismo saneamiento, sin duplicar la lógica-.
 */
function momentum_strip_featured_image_from_gallery(int $post_id): void
{
	$thumbnail_id = get_post_thumbnail_id($post_id);

	if (! $thumbnail_id) {
		return;
	}

	$gallery_ids = momentum_get_galeria_ids($post_id);

	if (! in_array($thumbnail_id, $gallery_ids, true)) {
		return;
	}

	$gallery_ids = array_values(array_diff($gallery_ids, [$thumbnail_id]));

	update_post_meta($post_id, 'galeria', implode(',', $gallery_ids));
}
add_action('save_post_propiedades', 'momentum_strip_featured_image_from_gallery', 40);
add_action('save_post_terrenos', 'momentum_strip_featured_image_from_gallery', 40);

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
			<input type="number" step="1" min="0" id="momentum_precio" name="momentum_precio"
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
			<input type="text" id="momentum_banos" name="momentum_banos" placeholder="6 2/2"
				value="<?php echo esc_attr($values['banos']); ?>">
			<p class="momentum-meta-field-hint">Captura los baños con el formato utilizado actualmente. Ejemplo: 6 2/2.</p>
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
 * Evita que el scroll del mouse sobre un <input type="number"> con foco
 * (precio, taxes_usd, etc.) cambie su valor sin que el usuario se dé
 * cuenta -comportamiento nativo del navegador en Chrome/Firefox, no un bug
 * de este metabox-. Mismo campo, mismo mecanismo que ya se protege en el
 * panel de administración propio (assets/js/admin-panel.js): detectado en
 * MC-393, donde el precio bajó de 48,000,000 a 47,999,999.88 -una
 * diferencia de exactamente 12 × el step del campo-.
 *
 * Cubre tanto Propiedades como Terrenos porque ambos comparten este mismo
 * metabox nativo (ver momentum_render_propiedad_metabox()).
 */
function momentum_guard_number_inputs_from_wheel_scroll($hook)
{
	if (! in_array($hook, ['post.php', 'post-new.php'], true) || ! in_array(get_post_type(), ['propiedades', 'terrenos'], true)) {
		return;
	}

	wp_add_inline_script(
		'jquery-core',
		"jQuery(document).on('wheel', 'input[type=\"number\"]', function () { if (document.activeElement === this) { jQuery(this).blur(); } });"
	);
}
add_action('admin_enqueue_scripts', 'momentum_guard_number_inputs_from_wheel_scroll');

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
			update_post_meta($post_id, $key, sanitize_textarea_field(momentum_normalize_line_endings($raw)));
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
	// Momentum_Property_Translation_Service::sync_textual_fields() llama
	// wp_update_post() sobre la TRADUCCIÓN en inglés, lo que dispara este
	// mismo hook otra vez para ese post -con $_POST todavía lleno con los
	// campos del formulario de la ficha en ESPAÑOL que originó la
	// sincronización-. Sin esta guarda, esa segunda ejecución reprocesaba
	// $_POST como si fuera un guardado legítimo de la traducción y
	// sobreescribía su meta (en particular 'video_url') con los valores en
	// español, deshaciendo lo que sync_shared_fields() ya había sincronizado
	// correctamente -bug real detectado en la ficha MC-124-.
	if (class_exists('Momentum_Property_Translation_Service') && Momentum_Property_Translation_Service::is_syncing()) {
		return;
	}

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
 * Elimina la imagen destacada y las de galería (post_parent = $post_id)
 * cuando una Propiedad o Terreno se elimina PERMANENTEMENTE -no al enviarla
 * a la papelera-. Sin esto, `wp_delete_post($id, true)` deja huérfanos
 * tanto el registro en la Biblioteca de medios como el archivo físico en
 * wp-content/uploads/{propiedades|terrenos}/{CODIGO}/ -confirmado en
 * pruebas QA con un caso de un solo adjunto: WordPress NO cascada el
 * borrado de adjuntos hijos por sí solo para un post type que no sea
 * 'attachment' (esa limpieza solo ocurre, de fábrica, desde la pantalla
 * "Vaciar papelera" del listado nativo de wp-admin, no desde una llamada
 * directa a wp_delete_post())-.
 *
 * `before_delete_post`, no `deleted_post`: corre ANTES de que el post
 * mismo se borre, para poder seguir leyendo sus adjuntos con
 * get_children() con normalidad.
 */
function momentum_delete_child_attachments_on_permanent_delete(int $post_id): void
{
	if (! in_array(get_post_type($post_id), ['propiedades', 'terrenos'], true)) {
		return;
	}

	$attachments = get_children([
		'post_parent' => $post_id,
		'post_type'   => 'attachment',
		'numberposts' => -1,
		'fields'      => 'ids',
	]);

	foreach ($attachments as $attachment_id) {
		// Salvaguarda: el plugin momentum-property-media-organizer permite
		// reutilizar un adjunto ya existente en OTRA Propiedad/Terreno (misma
		// imagen en dos galerías, por ejemplo) sin duplicarlo ni reasignar su
		// post_parent -sigue apuntando al inmueble donde se subió
		// originalmente-. Si ese inmueble "dueño" es justo el que se está
		// borrando ahora, no se borra el adjunto sin antes confirmar que
		// ningún OTRO inmueble lo siga usando como destacada o en su galería.
		if (momentum_attachment_used_elsewhere($attachment_id, $post_id)) {
			continue;
		}

		wp_delete_attachment($attachment_id, true);
	}
}
add_action('before_delete_post', 'momentum_delete_child_attachments_on_permanent_delete');

/**
 * true si $attachment_id aparece como imagen destacada o en la galería de
 * alguna Propiedad/Terreno DISTINTA de $exclude_post_id. Consulta directa
 * (sin WP_Query) porque 'galeria' guarda una lista de IDs separada por
 * coma, no comparable con un meta_query normal.
 */
function momentum_attachment_used_elsewhere(int $attachment_id, int $exclude_post_id): bool
{
	global $wpdb;

	$thumbnail_owner = $wpdb->get_var($wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta}
		WHERE meta_key = '_thumbnail_id' AND meta_value = %d AND post_id != %d
		LIMIT 1",
		$attachment_id,
		$exclude_post_id
	));

	if ($thumbnail_owner) {
		return true;
	}

	$gallery_rows = $wpdb->get_col($wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta}
		WHERE meta_key = 'galeria' AND post_id != %d AND meta_value LIKE %s",
		$exclude_post_id,
		'%' . $wpdb->esc_like((string) $attachment_id) . '%'
	));

	foreach ($gallery_rows as $other_post_id) {
		$ids = array_map('trim', explode(',', (string) get_post_meta((int) $other_post_id, 'galeria', true)));

		if (in_array((string) $attachment_id, $ids, true)) {
			return true;
		}
	}

	return false;
}

/**
 * Admin list table columns so listings can be scanned without opening each post.
 */
function momentum_propiedad_admin_columns($columns)
{
	$columns['precio']         = 'Precio';
	$columns['tipo_operacion'] = 'Operación';
	$columns['zona']           = 'Zona';
	$columns['estado']         = 'Estado';

	if (momentum_is_multilingual()) {
		$columns['momentum_translation'] = 'Traducción EN';
	}

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

		case 'momentum_translation':
			momentum_render_translation_status_column($post_id);
			break;
	}
}
add_action('manage_propiedades_posts_custom_column', 'momentum_propiedad_admin_column_content', 10, 2);

/**
 * Contenido de la columna "Traducción EN", compartido por Propiedades y
 * Terrenos (ver inc/cpt/terrenos.php) — solo tiene sentido si Polylang está
 * activo y la traducción a 'en' ya existe (Momentum_Property_Translation_Service
 * la crea al guardar; si el post nunca se guardó desde que se activó
 * Polylang, o Polylang está apagado, no hay nada que mostrar).
 */
function momentum_render_translation_status_column(int $post_id): void
{
	if (! momentum_is_multilingual()) {
		echo '—';

		return;
	}

	$translation_id = momentum_find_translation($post_id, 'en');

	if (! $translation_id) {
		echo '—';

		return;
	}

	$status = (string) get_post_meta($translation_id, Momentum_Property_Translation_Service::status_meta_key(), true);

	// Estilo en línea a propósito: esta columna vive en el listado NATIVO de
	// wp-admin (wp-admin/edit.php), que no carga el CSS del panel /admin/*
	// -las clases .momentum-admin-badge de ese panel no tendrían efecto aquí-.
	$colors = [
		'complete'       => ['#e3f5ee', '#146c48'],
		'manual'         => ['#e8effa', '#1c4f9c'],
		'translating'    => ['#fff6e5', '#8a5a00'],
		'error'          => ['#fdecea', '#a52c1e'],
		'quota_exceeded' => ['#fdecea', '#a52c1e'],
	];
	[$bg, $color] = $colors[$status] ?? ['#f1f1f1', '#555'];

	printf(
		'<span style="display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;background:%s;color:%s;">%s</span>',
		esc_attr($bg),
		esc_attr($color),
		esc_html(Momentum_Property_Translation_Service::status_label($status))
	);
}

/**
 * Desactivar has_archive requiere un flush_rewrite_rules() para que
 * WordPress deje de reconocer /propiedades/ como el archivo del CPT -de lo
 * contrario sigue resolviendo con las reglas cacheadas de antes de este
 * cambio-. Mismo patrón que momentum_maybe_flush_terrenos_rewrite_rules()
 * en inc/cpt/terrenos.php: una opción persistente como guardia, para que el
 * flush ocurra una sola vez (en el primer request tras desplegar este
 * cambio) y no en cada carga.
 */
function momentum_maybe_flush_propiedades_rewrite_rules(): void
{
	if (get_option('momentum_propiedades_archive_disabled_flushed')) {
		return;
	}

	flush_rewrite_rules();
	update_option('momentum_propiedades_archive_disabled_flushed', 1);
}
add_action('init', 'momentum_maybe_flush_propiedades_rewrite_rules', 20);
