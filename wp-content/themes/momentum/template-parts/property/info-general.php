<?php
/**
 * Property detail — sidebar "Información general" table.
 */

if (! defined('ABSPATH')) {
	exit;
}

$data = momentum_get_propiedad(get_the_ID());

$rows = [
	__('Clave', 'momentum')             => $data['codigo_propiedad'],
	__('Tipo de operación', 'momentum') => momentum_get_tipo_operacion_label($data['tipo_operacion']),
	__('Estado', 'momentum')            => momentum_get_estado_label($data['estado']),
];

// Taxes solo se muestra fuera del idioma por defecto (oculto en español,
// visible en inglés y en cualquier otro idioma que se agregue más adelante
// -no hardcodeado a 'en'-), y solo si hay un monto capturado.
$show_taxes = momentum_current_language() !== momentum_default_language() && $data['taxes_usd'] > 0;

if ($show_taxes) {
	$rows[__('Annual Property Tax', 'momentum')] = '$' . number_format($data['taxes_usd'], 0, '.', ',') . ' USD';
}

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

	<?php if ($show_taxes) : ?>
		<p class="momentum-property-info-general__note"><?php echo esc_html(momentum_usd_estimate_note()); ?></p>
	<?php endif; ?>
</div>
