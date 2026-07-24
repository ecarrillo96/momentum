<?php
/**
 * @var WP_Post[] $zonas
 * @var string    $nonce
 */
if (! defined('ABSPATH')) {
	exit;
}
?>
<div class="momentum-admin-page-header">
	<h1><?php esc_html_e('Zonas', 'momentum-admin'); ?></h1>
	<button type="button" class="momentum-admin-button" id="momentum-zona-add-open">
		<?php esc_html_e('+ Agregar zona', 'momentum-admin'); ?>
	</button>
</div>

<p class="momentum-admin-alert momentum-admin-alert--success" id="momentum-zona-success" hidden></p>

<div class="momentum-admin-table-wrap">
	<table class="momentum-admin-table" id="momentum-zonas-table">
		<thead>
			<tr>
				<th><?php esc_html_e('Nombre de la zona', 'momentum-admin'); ?></th>
				<th><?php esc_html_e('Fecha de creación', 'momentum-admin'); ?></th>
				<th><?php esc_html_e('Acciones', 'momentum-admin'); ?></th>
			</tr>
		</thead>
		<tbody id="momentum-zonas-tbody">
		<?php if ($zonas) : ?>
			<?php foreach ($zonas as $zona) : ?>
				<tr data-zona-id="<?php echo esc_attr((string) $zona->ID); ?>">
					<td data-label="<?php esc_attr_e('Nombre de la zona', 'momentum-admin'); ?>"><?php echo esc_html($zona->post_title); ?></td>
					<td data-label="<?php esc_attr_e('Fecha de creación', 'momentum-admin'); ?>"><?php echo esc_html(date_i18n('d/m/Y', strtotime($zona->post_date))); ?></td>
					<td data-label="<?php esc_attr_e('Acciones', 'momentum-admin'); ?>" class="momentum-admin-actions">
						<div class="momentum-admin-actions__group">
							<button type="button" class="momentum-admin-link-danger momentum-zona-delete-open" data-zona-id="<?php echo esc_attr((string) $zona->ID); ?>" data-zona-name="<?php echo esc_attr($zona->post_title); ?>">
								<?php esc_html_e('Eliminar', 'momentum-admin'); ?>
							</button>
						</div>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>
	<p class="momentum-admin-empty" id="momentum-zonas-empty" <?php echo $zonas ? 'hidden' : ''; ?>>
		<?php esc_html_e('Todavía no hay zonas registradas.', 'momentum-admin'); ?>
	</p>
</div>

<?php // Fila oculta usada como plantilla por JS al insertar una zona nueva sin recargar. ?>
<template id="momentum-zona-row-template">
	<tr data-zona-id="">
		<td data-label="<?php esc_attr_e('Nombre de la zona', 'momentum-admin'); ?>" class="momentum-zona-row-name"></td>
		<td data-label="<?php esc_attr_e('Fecha de creación', 'momentum-admin'); ?>" class="momentum-zona-row-date"></td>
		<td data-label="<?php esc_attr_e('Acciones', 'momentum-admin'); ?>" class="momentum-admin-actions">
			<div class="momentum-admin-actions__group">
				<button type="button" class="momentum-admin-link-danger momentum-zona-delete-open">
					<?php esc_html_e('Eliminar', 'momentum-admin'); ?>
				</button>
			</div>
		</td>
	</tr>
</template>

<?php // ---------------------------------------------------------------- Modal: Agregar zona ?>
<div class="momentum-admin-modal" id="momentum-zona-add-modal" hidden aria-hidden="true">
	<div class="momentum-admin-modal__backdrop" data-momentum-modal-close></div>
	<div class="momentum-admin-modal__panel" role="dialog" aria-modal="true" aria-labelledby="momentum-zona-add-title">
		<h2 id="momentum-zona-add-title"><?php esc_html_e('Agregar zona', 'momentum-admin'); ?></h2>

		<form id="momentum-zona-add-form">
			<p class="momentum-admin-alert momentum-admin-alert--error" id="momentum-zona-add-error" hidden></p>

			<div class="momentum-admin-field">
				<label for="momentum-zona-nombre"><?php esc_html_e('Nombre de la zona', 'momentum-admin'); ?></label>
				<input type="text" id="momentum-zona-nombre" name="nombre" maxlength="60" placeholder="<?php esc_attr_e('Lomas de Cocoyoc', 'momentum-admin'); ?>" autocomplete="off">
			</div>

			<div class="momentum-admin-modal__actions">
				<button type="button" class="momentum-admin-button momentum-admin-button--secondary" data-momentum-modal-close><?php esc_html_e('Cancelar', 'momentum-admin'); ?></button>
				<button type="submit" class="momentum-admin-button"><?php esc_html_e('Guardar', 'momentum-admin'); ?></button>
			</div>
		</form>
	</div>
</div>

<?php // ---------------------------------------------------------------- Modal: Confirmar eliminación ?>
<div class="momentum-admin-modal" id="momentum-zona-delete-modal" hidden aria-hidden="true">
	<div class="momentum-admin-modal__backdrop" data-momentum-modal-close></div>
	<div class="momentum-admin-modal__panel" role="dialog" aria-modal="true" aria-labelledby="momentum-zona-delete-title">
		<h2 id="momentum-zona-delete-title"><?php esc_html_e('Eliminar zona', 'momentum-admin'); ?></h2>

		<p id="momentum-zona-delete-message">
			<?php esc_html_e('¿Deseas eliminar esta zona?', 'momentum-admin'); ?>
			<br>
			<strong><?php esc_html_e('Esta acción no se puede deshacer.', 'momentum-admin'); ?></strong>
		</p>

		<p class="momentum-admin-alert momentum-admin-alert--error" id="momentum-zona-delete-error" hidden></p>

		<div class="momentum-admin-modal__actions">
			<button type="button" class="momentum-admin-button momentum-admin-button--secondary" data-momentum-modal-close><?php esc_html_e('Cancelar', 'momentum-admin'); ?></button>
			<button type="button" class="momentum-admin-button momentum-admin-button--danger" id="momentum-zona-delete-confirm"><?php esc_html_e('Eliminar', 'momentum-admin'); ?></button>
		</div>
	</div>
</div>
