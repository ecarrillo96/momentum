<?php
/**
 * @var array $guias Lista de ['titulo', 'descripcion', 'archivo'] (ver class-guias.php)
 */
if (! defined('ABSPATH')) {
	exit;
}
?>
<div class="momentum-admin-page-header">
	<h1><?php esc_html_e('Guías', 'momentum-admin'); ?></h1>
</div>

<div class="momentum-admin-table-wrap">
	<table class="momentum-admin-table">
		<thead>
			<tr>
				<th><?php esc_html_e('Guía', 'momentum-admin'); ?></th>
				<th><?php esc_html_e('Descripción', 'momentum-admin'); ?></th>
				<th><?php esc_html_e('Acciones', 'momentum-admin'); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ($guias as $guia) : ?>
			<tr>
				<td data-label="<?php esc_attr_e('Guía', 'momentum-admin'); ?>"><?php echo esc_html($guia['titulo']); ?></td>
				<td data-label="<?php esc_attr_e('Descripción', 'momentum-admin'); ?>"><?php echo esc_html($guia['descripcion']); ?></td>
				<td data-label="<?php esc_attr_e('Acciones', 'momentum-admin'); ?>" class="momentum-admin-actions">
					<div class="momentum-admin-actions__group">
						<a href="<?php echo esc_url(MOMENTUM_ADMIN_URL . 'assets/guias/' . rawurlencode($guia['archivo'])); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e('Ver PDF', 'momentum-admin'); ?>
						</a>
						<a href="<?php echo esc_url(MOMENTUM_ADMIN_URL . 'assets/guias/' . rawurlencode($guia['archivo'])); ?>" download>
							<?php esc_html_e('Descargar', 'momentum-admin'); ?>
						</a>
					</div>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
