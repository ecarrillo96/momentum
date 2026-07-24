<?php
/**
 * @var array $result Resultado de Momentum_Admin_Terrenos::query_terrenos()
 * @var array $schema momentum_terreno_meta_schema()
 */
if (! defined('ABSPATH')) {
	exit;
}

$query = $result['query'];

$mensaje_code = isset($_GET['mensaje']) ? sanitize_key(wp_unslash($_GET['mensaje'])) : '';
$error_code   = isset($_GET['error']) ? sanitize_key(wp_unslash($_GET['error'])) : '';

$mensajes = [
	'creada'      => __('Terreno creado correctamente.', 'momentum-admin'),
	'actualizada' => __('Terreno actualizado correctamente.', 'momentum-admin'),
	'papelera'    => __('Terreno enviado a la papelera.', 'momentum-admin'),
];

$errores = [
	'nonce'    => __('La acción no se pudo verificar. Intenta de nuevo.', 'momentum-admin'),
	'permisos' => __('No tienes permiso para realizar esa acción.', 'momentum-admin'),
	'titulo'   => __('El título es obligatorio.', 'momentum-admin'),
	'guardado' => __('Ocurrió un error al guardar el terreno.', 'momentum-admin'),
	'papelera' => __('No se pudo enviar el terreno a la papelera.', 'momentum-admin'),
];

$has_filters = $result['search'] !== '' || $result['estado'] !== '';
?>
<div class="momentum-admin-page-header">
	<h1><?php esc_html_e('Terrenos', 'momentum-admin'); ?></h1>
	<a class="momentum-admin-button" href="<?php echo esc_url(add_query_arg('accion', 'nueva', home_url('/admin/terrenos/'))); ?>">
		<?php esc_html_e('+ Nuevo terreno', 'momentum-admin'); ?>
	</a>
</div>

<?php if ($mensaje_code && isset($mensajes[$mensaje_code])) : ?>
	<p class="momentum-admin-alert momentum-admin-alert--success"><?php echo esc_html($mensajes[$mensaje_code]); ?></p>
<?php endif; ?>

<?php if ($error_code && isset($errores[$error_code])) : ?>
	<p class="momentum-admin-alert momentum-admin-alert--error"><?php echo esc_html($errores[$error_code]); ?></p>
<?php endif; ?>

<form method="get" action="<?php echo esc_url(home_url('/admin/terrenos/')); ?>" class="momentum-admin-filters">
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

	<button type="submit" class="momentum-admin-button momentum-admin-button--secondary"><?php esc_html_e('Filtrar', 'momentum-admin'); ?></button>

	<?php if ($has_filters) : ?>
		<a class="momentum-admin-link" href="<?php echo esc_url(home_url('/admin/terrenos/')); ?>"><?php esc_html_e('Limpiar', 'momentum-admin'); ?></a>
	<?php endif; ?>
</form>

<div class="momentum-admin-table-wrap">
<table class="momentum-admin-table">
	<thead>
		<tr>
			<th><?php esc_html_e('Imagen', 'momentum-admin'); ?></th>
			<th><?php esc_html_e('Clave', 'momentum-admin'); ?></th>
			<th><?php esc_html_e('Título', 'momentum-admin'); ?></th>
			<th><?php esc_html_e('Zona', 'momentum-admin'); ?></th>
			<th><?php esc_html_e('Superficie', 'momentum-admin'); ?></th>
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
			$zona           = (string) get_post_meta($id, 'zona', true);
			$zona_label     = $zona !== '' && isset($schema['zona']['options_map'][$zona]) ? $schema['zona']['options_map'][$zona] : '';
			$superficie     = (float) get_post_meta($id, 'terreno_m2', true);
			$estado_terreno = (string) get_post_meta($id, 'estado', true);
			$codigo         = (string) get_post_meta($id, 'codigo_propiedad', true);
			$edit_url       = add_query_arg(['accion' => 'editar', 'id' => $id], home_url('/admin/terrenos/'));
			$precio_label   = $precio > 0
				? (function_exists('momentum_format_price') ? momentum_format_price($precio) : number_format($precio, 0, '.', ','))
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
						<span class="momentum-admin-tag"><?php esc_html_e('Borrador', 'momentum-admin'); ?></span>
					<?php endif; ?>
				</td>
				<td data-label="<?php esc_attr_e('Zona', 'momentum-admin'); ?>"><?php echo $zona_label !== '' ? esc_html($zona_label) : '—'; ?></td>
				<td data-label="<?php esc_attr_e('Superficie', 'momentum-admin'); ?>"><?php echo $superficie > 0 ? esc_html(number_format($superficie, 0, '.', ',') . ' m²') : '—'; ?></td>
				<td data-label="<?php esc_attr_e('Precio', 'momentum-admin'); ?>"><?php echo esc_html($precio_label); ?></td>
				<td data-label="<?php esc_attr_e('Estado', 'momentum-admin'); ?>"><span class="momentum-admin-badge"><?php echo $estado_terreno !== '' ? esc_html($estado_terreno) : '—'; ?></span></td>
				<td data-label="<?php esc_attr_e('Fecha', 'momentum-admin'); ?>"><?php echo esc_html(get_the_date()); ?></td>
				<td data-label="<?php esc_attr_e('Acciones', 'momentum-admin'); ?>" class="momentum-admin-actions">
					<div class="momentum-admin-actions__group">
						<a href="<?php echo esc_url($edit_url); ?>"><?php esc_html_e('Editar', 'momentum-admin'); ?></a>
						<a href="<?php echo esc_url(get_permalink($id)); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Ver', 'momentum-admin'); ?></a>
						<form method="post" action="<?php echo esc_url(home_url('/admin/terrenos/')); ?>" class="momentum-admin-inline-form" onsubmit="return confirm('<?php echo esc_js(__('¿Enviar este terreno a la papelera?', 'momentum-admin')); ?>');">
							<?php wp_nonce_field(Momentum_Admin_Terrenos::TRASH_NONCE_ACTION . '_' . $id, Momentum_Admin_Terrenos::TRASH_NONCE_FIELD); ?>
							<input type="hidden" name="momentum_terreno_id" value="<?php echo esc_attr((string) $id); ?>">
							<button type="submit" name="momentum_admin_terreno_trash" value="1" class="momentum-admin-link-danger"><?php esc_html_e('Papelera', 'momentum-admin'); ?></button>
						</form>
					</div>
				</td>
			</tr>
		<?php endwhile; ?>
		<?php wp_reset_postdata(); ?>
	<?php else : ?>
		<tr>
			<td colspan="9" class="momentum-admin-empty"><?php esc_html_e('No se encontraron terrenos.', 'momentum-admin'); ?></td>
		</tr>
	<?php endif; ?>
	</tbody>
</table>
</div>

<?php
$total_pages = (int) $query->max_num_pages;

if ($total_pages > 1) :
	$base_args = array_filter([
		'buscar' => $result['search'],
		'estado' => $result['estado'],
	], static function ($value) {
		return $value !== '';
	});
	?>
	<nav class="momentum-admin-pagination" aria-label="<?php esc_attr_e('Paginación', 'momentum-admin'); ?>">
		<?php for ($i = 1; $i <= $total_pages; $i++) : ?>
			<a
				class="<?php echo $i === $result['paged'] ? 'is-active' : ''; ?>"
				href="<?php echo esc_url(add_query_arg(array_merge($base_args, ['pg' => $i]), home_url('/admin/terrenos/'))); ?>"
			><?php echo esc_html((string) $i); ?></a>
		<?php endfor; ?>
	</nav>
<?php endif; ?>
