<?php
/**
 * Property detail — sidebar "Información general" table.
 */

if (! defined('ABSPATH')) {
	exit;
}

$data = momentum_get_propiedad(get_the_ID());

// Taxes no se muestra por ahora: se reserva para cuando exista la versión en
// inglés del sitio (el dato ya se guarda en 'taxes_usd', solo se oculta aquí).
$rows = [
	'Clave'               => $data['codigo_propiedad'],
	'Tipo de operación'   => $data['tipo_operacion'],
	'Estado'              => $data['estado'],
];

$rows = array_filter($rows);

if (! $rows) {
	return;
}
?>
<div class="momentum-property-info-general">
	<h3>Información general</h3>
	<dl>
		<?php foreach ($rows as $label => $value) : ?>
			<div class="momentum-property-info-general__row">
				<dt><?php echo esc_html($label); ?></dt>
				<dd><?php echo esc_html($value); ?></dd>
			</div>
		<?php endforeach; ?>
	</dl>
</div>
