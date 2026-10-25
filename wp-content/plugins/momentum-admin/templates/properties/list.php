<?php
/**
 * @var array $result             Resultado de Momentum_Admin_Properties::query_properties()
 * @var array $schema             momentum_propiedad_meta_schema()
 * @var int   $total_registradas  Total de propiedades registradas (sin filtros)
 */
if (! defined('ABSPATH')) {
	exit;
}

$query = $result['query'];

$mensaje_code = isset($_GET['mensaje']) ? sanitize_key(wp_unslash($_GET['mensaje'])) : '';
$error_code   = isset($_GET['error']) ? sanitize_key(wp_unslash($_GET['error'])) : '';

$mensajes = [
	'creada'      => __('Propiedad creada correctamente.', 'momentum-admin'),
	'actualizada' => __('Propiedad actualizada correctamente.', 'momentum-admin'),
	'papelera'    => __('Propiedad eliminada.', 'momentum-admin'),
];

$errores = [
	'nonce'    => __('La acción no se pudo verificar. Intenta de nuevo.', 'momentum-admin'),
	'permisos' => __('No tienes permiso para realizar esa acción.', 'momentum-admin'),
	'titulo'   => __('El título es obligatorio.', 'momentum-admin'),
	'guardado' => __('Ocurrió un error al guardar la propiedad.', 'momentum-admin'),
	'papelera' => __('No se pudo eliminar la propiedad.', 'momentum-admin'),
];

$has_filters = $result['search'] !== '' || $result['estado'] !== '' || $result['tipo_operacion'] !== '' || $result['zona'] !== '' || $result['visibilidad'] !== '';
?>
<div class="momentum-admin-page-header">
	<h1>
		<?php esc_html_e('Propiedades', 'momentum-admin'); ?>
		<span class="momentum-admin-count-badge">
			<?php
			printf(
				/* translators: %d: número total de propiedades registradas */
				esc_html(_n('%d registrada', '%d registradas', $total_registradas, 'momentum-admin')),
				(int) $total_registradas
			);
			?>
		</span>
	</h1>
	<a class="momentum-admin-button" href="<?php echo esc_url(add_query_arg('accion', 'nueva', home_url('/admin/propiedades/'))); ?>">
		<?php esc_html_e('+ Nueva propiedad', 'momentum-admin'); ?>
	</a>
</div>

<?php if ($mensaje_code && isset($mensajes[$mensaje_code])) : ?>
	<p class="momentum-admin-alert momentum-admin-alert--success"><?php echo esc_html($mensajes[$mensaje_code]); ?></p>
<?php endif; ?>

<?php if ($error_code && isset($errores[$error_code])) : ?>
	<p class="momentum-admin-alert momentum-admin-alert--error"><?php echo esc_html($errores[$error_code]); ?></p>
<?php endif; ?>

<form method="get" action="<?php echo esc_url(home_url('/admin/propiedades/')); ?>" class="momentum-admin-filters">
	<input
		type="search"
		name="buscar"
		value="<?php echo esc_attr($result['search']); ?>"
		placeholder="<?php esc_attr_e('Buscar por título o clave', 'momentum-admin'); ?>"
		class="momentum-admin-filters-search"
	>

	<select name="estado">
		<option value=""><?php esc_html_e('Todos los estados', 'momentum-admin'); ?></option>
		<?php foreach ($schema['estado']['options'] as $option) : ?>
			<option value="<?php echo esc_attr($option); ?>" <?php selected($result['estado'], $option); ?>><?php echo esc_html($option); ?></option>
		<?php endforeach; ?>
	</select>

	<select name="tipo_operacion">
		<option value=""><?php esc_html_e('Toda operación', 'momentum-admin'); ?></option>
		<?php foreach ($schema['tipo_operacion']['options'] as $option) : ?>
			<option value="<?php echo esc_attr($option); ?>" <?php selected($result['tipo_operacion'], $option); ?>><?php echo esc_html($option); ?></option>
		<?php endforeach; ?>
	</select>

	<select name="zona">
		<option value=""><?php esc_html_e('Toda zona', 'momentum-admin'); ?></option>
		<?php foreach ($schema['zona']['options_map'] as $slug => $label) : ?>
			<option value="<?php echo esc_attr($slug); ?>" <?php selected($result['zona'], $slug); ?>><?php echo esc_html($label); ?></option>
		<?php endforeach; ?>
	</select>

	<select name="visibilidad">
		<option value=""><?php esc_html_e('Toda visibilidad', 'momentum-admin'); ?></option>
		<option value="publish" <?php selected($result['visibilidad'], 'publish'); ?>><?php esc_html_e('Publicada', 'momentum-admin'); ?></option>
		<option value="draft" <?php selected($result['visibilidad'], 'draft'); ?>><?php esc_html_e('No publicada', 'momentum-admin'); ?></option>
	</select>

	<button type="submit" class="momentum-admin-button momentum-admin-button--secondary"><?php esc_html_e('Filtrar', 'momentum-admin'); ?></button>

	<?php if ($has_filters) : ?>
		<a class="momentum-admin-link" href="<?php echo esc_url(home_url('/admin/propiedades/')); ?>"><?php esc_html_e('Limpiar', 'momentum-admin'); ?></a>
	<?php endif; ?>
</form>

<div class="momentum-admin-table-wrap">
<table class="momentum-admin-table">
	<thead>
		<tr>
			<th><?php esc_html_e('Imagen', 'momentum-admin'); ?></th>
			<th><?php esc_html_e('Clave', 'momentum-admin'); ?></th>
			<th><?php esc_html_e('Título', 'momentum-admin'); ?></th>
			<th><?php esc_html_e('Operación', 'momentum-admin'); ?></th>
			<th><?php esc_html_e('Zona', 'momentum-admin'); ?></th>
			<th><?php esc_html_e('Precio', 'momentum-admin'); ?></th>
			<th><?php esc_html_e('Estado', 'momentum-admin'); ?></th>
			<th><?php esc_html_e('Fecha', 'momentum-admin'); ?></th>
			<th><?php esc_html_e('Acciones', 'momentum-admin'); ?></th>
		</tr>
	</thead>
	<tbody>
	<?php if ($query->have_posts()) : ?>
		<?php while ($query->have_posts()) : $query->the_post(); ?>
			<?php
			$id             = get_the_ID();
			$precio         = (float) get_post_meta($id, 'precio', true);
			$tipo_operacion = (string) get_post_meta($id, 'tipo_operacion', true);
			$zona           = (string) get_post_meta($id, 'zona', true);
			$zona_label     = $zona !== '' && isset($schema['zona']['options_map'][$zona]) ? $schema['zona']['options_map'][$zona] : '';
			$estado_prop    = (string) get_post_meta($id, 'estado', true);
			$codigo         = (string) get_post_meta($id, 'codigo_propiedad', true);
			$edit_url       = add_query_arg(['accion' => 'editar', 'id' => $id], home_url('/admin/propiedades/'));
			$precio_label   = $precio > 0
				? (function_exists('momentum_format_price') ? momentum_format_price($precio, $tipo_operacion) : number_format($precio, 0, '.', ','))
				: '—';
			?>
			<tr>
				<td data-label="<?php esc_attr_e('Imagen', 'momentum-admin'); ?>">
					<?php if (has_post_thumbnail($id)) : ?>
						<?php echo get_the_post_thumbnail($id, 'thumbnail', ['class' => 'momentum-admin-thumb']); ?>
					<?php else : ?>
						<span class="momentum-admin-thumb momentum-admin-thumb--empty" aria-hidden="true"></span>
					<?php endif; ?>
				</td>
				<td data-label="<?php esc_attr_e('Clave', 'momentum-admin'); ?>"><?php echo $codigo !== '' ? esc_html($codigo) : '—'; ?></td>
				<td data-label="<?php esc_attr_e('Título', 'momentum-admin'); ?>">
					<a href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html(get_the_title()); ?></a>
					<?php if (get_post_status($id) === 'draft') : ?>
						<span class="momentum-admin-tag"><?php esc_html_e('No publicada', 'momentum-admin'); ?></span>
					<?php endif; ?>
				</td>
				<td data-label="<?php esc_attr_e('Operación', 'momentum-admin'); ?>"><?php echo $tipo_operacion !== '' ? esc_html($tipo_operacion) : '—'; ?></td>
				<td data-label="<?php esc_attr_e('Zona', 'momentum-admin'); ?>"><?php echo $zona_label !== '' ? esc_html($zona_label) : '—'; ?></td>
				<td data-label="<?php esc_attr_e('Precio', 'momentum-admin'); ?>"><?php echo esc_html($precio_label); ?></td>
				<td data-label="<?php esc_attr_e('Estado', 'momentum-admin'); ?>"><span class="momentum-admin-badge"><?php echo $estado_prop !== '' ? esc_html($estado_prop) : '—'; ?></span></td>
				<td data-label="<?php esc_attr_e('Fecha', 'momentum-admin'); ?>"><?php echo esc_html(get_the_date()); ?></td>
				<td data-label="<?php esc_attr_e('Acciones', 'momentum-admin'); ?>" class="momentum-admin-actions">
					<div class="momentum-admin-actions__group">
						<a href="<?php echo esc_url($edit_url); ?>"><?php esc_html_e('Editar', 'momentum-admin'); ?></a>
						<a href="<?php echo esc_url(get_permalink($id)); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Ver', 'momentum-admin'); ?></a>
						<form method="post" action="<?php echo esc_url(home_url('/admin/propiedades/')); ?>" class="momentum-admin-inline-form" onsubmit="return confirm('<?php echo esc_js(__('¿Eliminar esta propiedad?', 'momentum-admin')); ?>');">
							<?php wp_nonce_field(Momentum_Admin_Properties::TRASH_NONCE_ACTION . '_' . $id, Momentum_Admin_Properties::TRASH_NONCE_FIELD); ?>
							<input type="hidden" name="momentum_property_id" value="<?php echo esc_attr((string) $id); ?>">
							<button type="submit" name="momentum_admin_property_trash" value="1" class="momentum-admin-link-danger"><?php esc_html_e('Eliminar', 'momentum-admin'); ?></button>
						</form>
					</div>
				</td>
			</tr>
		<?php endwhile; ?>
		<?php wp_reset_postdata(); ?>
	<?php else : ?>
		<tr>
			<td colspan="9" class="momentum-admin-empty"><?php esc_html_e('No se encontraron propiedades.', 'momentum-admin'); ?></td>
		</tr>
	<?php endif; ?>
	</tbody>
</table>
</div>

<?php
$total_pages = (int) $query->max_num_pages;

if ($total_pages > 1) :
	$base_args = array_filter([
		'buscar'         => $result['search'],
		'estado'         => $result['estado'],
		'tipo_operacion' => $result['tipo_operacion'],
		'zona'           => $result['zona'],
		'visibilidad'    => $result['visibilidad'],
	], static function ($value) {
		return $value !== '';
	});
	?>
	<nav class="momentum-admin-pagination" aria-label="<?php esc_attr_e('Paginación', 'momentum-admin'); ?>">
		<?php for ($i = 1; $i <= $total_pages; $i++) : ?>
			<a
				class="<?php echo $i === $result['paged'] ? 'is-active' : ''; ?>"
				href="<?php echo esc_url(add_query_arg(array_merge($base_args, ['pg' => $i]), home_url('/admin/propiedades/'))); ?>"
			><?php echo esc_html((string) $i); ?></a>
		<?php endfor; ?>
	</nav>
<?php endif; ?>
