<?php
/**
 * @var array $result Resultado de Momentum_Admin_Blog::query_articulos()
 */
if (! defined('ABSPATH')) {
	exit;
}

$query = $result['query'];

$mensaje_code = isset($_GET['mensaje']) ? sanitize_key(wp_unslash($_GET['mensaje'])) : '';
$error_code   = isset($_GET['err']) ? sanitize_key(wp_unslash($_GET['err'])) : '';

$mensajes = [
	'creado'            => __('Artículo creado correctamente.', 'momentum-admin'),
	'actualizado'       => __('Artículo actualizado correctamente.', 'momentum-admin'),
	'publicado'         => __('Artículo publicado correctamente.', 'momentum-admin'),
	'despublicado'      => __('Artículo despublicado.', 'momentum-admin'),
	'guardado_borrador' => __('Artículo guardado como borrador.', 'momentum-admin'),
	'papelera'          => __('Artículo enviado a la papelera.', 'momentum-admin'),
];

$errores = [
	'nonce'    => __('La acción no se pudo verificar. Intenta de nuevo.', 'momentum-admin'),
	'permisos' => __('No tienes permiso para realizar esa acción.', 'momentum-admin'),
	'titulo'   => __('El título es obligatorio.', 'momentum-admin'),
	'guardado' => __('Ocurrió un error al guardar el artículo.', 'momentum-admin'),
	'papelera' => __('No se pudo enviar el artículo a la papelera.', 'momentum-admin'),
];

$has_filters = $result['search'] !== '' || $result['estado'] !== '';
?>
<div class="momentum-admin-page-header">
	<h1><?php esc_html_e('Blog', 'momentum-admin'); ?></h1>
	<a class="momentum-admin-button" href="<?php echo esc_url(add_query_arg('accion', 'nueva', home_url('/admin/blog/'))); ?>">
		<?php esc_html_e('+ Nuevo artículo', 'momentum-admin'); ?>
	</a>
</div>

<?php if ($mensaje_code && isset($mensajes[$mensaje_code])) : ?>
	<p class="momentum-admin-alert momentum-admin-alert--success"><?php echo esc_html($mensajes[$mensaje_code]); ?></p>
<?php endif; ?>

<?php if ($error_code && isset($errores[$error_code])) : ?>
	<p class="momentum-admin-alert momentum-admin-alert--error"><?php echo esc_html($errores[$error_code]); ?></p>
<?php endif; ?>

<form method="get" action="<?php echo esc_url(home_url('/admin/blog/')); ?>" class="momentum-admin-filters">
	<input
		type="search"
		name="buscar"
		value="<?php echo esc_attr($result['search']); ?>"
		placeholder="<?php esc_attr_e('Buscar por título', 'momentum-admin'); ?>"
		class="momentum-admin-filters-search"
	>

	<select name="estado">
		<option value=""><?php esc_html_e('Todos los estados', 'momentum-admin'); ?></option>
		<option value="borrador" <?php selected($result['estado'], 'borrador'); ?>><?php esc_html_e('Borrador', 'momentum-admin'); ?></option>
		<option value="publicado" <?php selected($result['estado'], 'publicado'); ?>><?php esc_html_e('Publicado', 'momentum-admin'); ?></option>
		<option value="despublicado" <?php selected($result['estado'], 'despublicado'); ?>><?php esc_html_e('Despublicado', 'momentum-admin'); ?></option>
	</select>

	<select name="orden">
		<option value="desc" <?php selected($result['orden'], 'desc'); ?>><?php esc_html_e('Más recientes primero', 'momentum-admin'); ?></option>
		<option value="asc" <?php selected($result['orden'], 'asc'); ?>><?php esc_html_e('Más antiguos primero', 'momentum-admin'); ?></option>
	</select>

	<button type="submit" class="momentum-admin-button momentum-admin-button--secondary"><?php esc_html_e('Filtrar', 'momentum-admin'); ?></button>

	<?php if ($has_filters) : ?>
		<a class="momentum-admin-link" href="<?php echo esc_url(home_url('/admin/blog/')); ?>"><?php esc_html_e('Limpiar', 'momentum-admin'); ?></a>
	<?php endif; ?>
</form>

<div class="momentum-admin-table-wrap">
<table class="momentum-admin-table">
	<thead>
		<tr>
			<th><?php esc_html_e('Imagen', 'momentum-admin'); ?></th>
			<th><?php esc_html_e('Título', 'momentum-admin'); ?></th>
			<th><?php esc_html_e('Estado', 'momentum-admin'); ?></th>
			<th><?php esc_html_e('Creado', 'momentum-admin'); ?></th>
			<th><?php esc_html_e('Publicado', 'momentum-admin'); ?></th>
			<th><?php esc_html_e('Actualizado', 'momentum-admin'); ?></th>
			<th><?php esc_html_e('Acciones', 'momentum-admin'); ?></th>
		</tr>
	</thead>
	<tbody>
	<?php if ($query->have_posts()) : ?>
		<?php while ($query->have_posts()) : $query->the_post(); ?>
			<?php
			$id           = get_the_ID();
			$edit_url     = add_query_arg(['accion' => 'editar', 'id' => $id], home_url('/admin/blog/'));
			$estado_label = momentum_articulo_display_status($id);
			$estado_class = [
				'Publicado'    => 'is-publicado',
				'Despublicado' => 'is-despublicado',
				'Borrador'     => 'is-borrador',
			][$estado_label] ?? '';
			$preview_url  = get_post_status($id) === 'publish' ? get_permalink($id) : get_preview_post_link($id);
			?>
			<tr>
				<td data-label="<?php esc_attr_e('Imagen', 'momentum-admin'); ?>">
					<?php if (has_post_thumbnail($id)) : ?>
						<?php echo get_the_post_thumbnail($id, 'thumbnail', ['class' => 'momentum-admin-thumb']); ?>
					<?php else : ?>
						<span class="momentum-admin-thumb momentum-admin-thumb--empty" aria-hidden="true"></span>
					<?php endif; ?>
				</td>
				<td data-label="<?php esc_attr_e('Título', 'momentum-admin'); ?>">
					<a href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html(get_the_title()); ?></a>
					<?php if (momentum_articulo_is_destacado($id)) : ?>
						<span class="momentum-admin-tag"><?php esc_html_e('Destacado', 'momentum-admin'); ?></span>
					<?php endif; ?>
				</td>
				<td data-label="<?php esc_attr_e('Estado', 'momentum-admin'); ?>">
					<span class="momentum-admin-badge <?php echo esc_attr($estado_class); ?>"><?php echo esc_html($estado_label); ?></span>
				</td>
				<td data-label="<?php esc_attr_e('Creado', 'momentum-admin'); ?>"><?php echo esc_html(get_the_date()); ?></td>
				<td data-label="<?php esc_attr_e('Publicado', 'momentum-admin'); ?>">
					<?php echo get_post_status($id) === 'publish' ? esc_html(get_the_date()) : '—'; ?>
				</td>
				<td data-label="<?php esc_attr_e('Actualizado', 'momentum-admin'); ?>"><?php echo esc_html(get_the_modified_date()); ?></td>
				<td data-label="<?php esc_attr_e('Acciones', 'momentum-admin'); ?>" class="momentum-admin-actions">
					<div class="momentum-admin-actions__group">
						<a href="<?php echo esc_url($edit_url); ?>"><?php esc_html_e('Editar', 'momentum-admin'); ?></a>
						<a href="<?php echo esc_url($preview_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Previsualizar', 'momentum-admin'); ?></a>

						<form method="post" action="<?php echo esc_url(home_url('/admin/blog/')); ?>" class="momentum-admin-inline-form">
							<?php wp_nonce_field(Momentum_Admin_Blog::STATUS_NONCE_ACTION . '_' . $id, Momentum_Admin_Blog::STATUS_NONCE_FIELD); ?>
							<input type="hidden" name="momentum_article_id" value="<?php echo esc_attr((string) $id); ?>">
							<button type="submit" name="momentum_admin_article_toggle_status" value="1" class="momentum-admin-link">
								<?php echo get_post_status($id) === 'publish' ? esc_html__('Despublicar', 'momentum-admin') : esc_html__('Publicar', 'momentum-admin'); ?>
							</button>
						</form>

						<form method="post" action="<?php echo esc_url(home_url('/admin/blog/')); ?>" class="momentum-admin-inline-form" onsubmit="return confirm('<?php echo esc_js(__('¿Enviar este artículo a la papelera?', 'momentum-admin')); ?>');">
							<?php wp_nonce_field(Momentum_Admin_Blog::TRASH_NONCE_ACTION . '_' . $id, Momentum_Admin_Blog::TRASH_NONCE_FIELD); ?>
							<input type="hidden" name="momentum_article_id" value="<?php echo esc_attr((string) $id); ?>">
							<button type="submit" name="momentum_admin_article_trash" value="1" class="momentum-admin-link-danger"><?php esc_html_e('Eliminar', 'momentum-admin'); ?></button>
						</form>
					</div>
				</td>
			</tr>
		<?php endwhile; ?>
		<?php wp_reset_postdata(); ?>
	<?php else : ?>
		<tr>
			<td colspan="7" class="momentum-admin-empty"><?php esc_html_e('No se encontraron artículos.', 'momentum-admin'); ?></td>
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
		'orden'  => $result['orden'],
	], static function ($value) {
		return $value !== '';
	});
	?>
	<nav class="momentum-admin-pagination" aria-label="<?php esc_attr_e('Paginación', 'momentum-admin'); ?>">
		<?php for ($i = 1; $i <= $total_pages; $i++) : ?>
			<a
				class="<?php echo $i === $result['paged'] ? 'is-active' : ''; ?>"
				href="<?php echo esc_url(add_query_arg(array_merge($base_args, ['pg' => $i]), home_url('/admin/blog/'))); ?>"
			><?php echo esc_html((string) $i); ?></a>
		<?php endfor; ?>
	</nav>
<?php endif; ?>
