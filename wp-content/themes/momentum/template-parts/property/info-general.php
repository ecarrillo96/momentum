<?php
/**
 * Property detail — sidebar "Información general" table.
 */

if (! defined('ABSPATH')) {
	exit;
}

$data = momentum_get_propiedad(get_the_ID());

// Annual Property Tax vive en el header de la ficha (template-parts/property/header.php),
// junto al precio -no aquí-, para la versión en inglés.
$rows = [
	__('Clave', 'momentum')             => $data['codigo_propiedad'],
	__('Tipo de operación', 'momentum') => momentum_get_tipo_operacion_label($data['tipo_operacion']),
	__('Estado', 'momentum')            => momentum_get_estado_label($data['estado']),
];

$rows = array_filter($rows);

if (! $rows) {
	return;
}
?>
<div class="momentum-property-info-general">
	<h3><?php esc_html_e('Información general', 'momentum'); ?></h3>
	<dl>
		<?php foreach ($rows as $label => $value) : ?>
			<div class="momentum-property-info-general__row">
				<dt><?php echo esc_html($label); ?></dt>
				<dd><?php echo esc_html($value); ?></dd>
			</div>
		<?php endforeach; ?>
	</dl>
</div>
