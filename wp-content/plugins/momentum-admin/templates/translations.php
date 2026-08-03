<?php
/**
 * @var array  $usage            momentum_get_translation_usage()
 * @var float|null $usage_percent
 * @var string $threshold        normal|warning|critical|blocked
 * @var string $connection       connected|not_configured|auth_error|quota_exceeded|temporary_error
 * @var array  $status_counts    Momentum_Property_Translation_Service::get_status_counts()
 * @var array  $log_stats        momentum_get_translation_log_stats()
 * @var array  $recent_log       momentum_get_translation_log_recent()
 * @var bool   $translation_ok   Momentum_Property_Translation_Service::is_translation_allowed()
 * @var bool   $has_estimate
 * @var int|null $remaining_chars
 * @var int|null $estimated_listings
 * @var string $mensaje_code
 * @var string $error_code
 * @var array  $mensajes
 * @var array  $errores
 */
if (! defined('ABSPATH')) {
	exit;
}

$connection_labels = [
	'connected'      => __('Conectado', 'momentum-admin'),
	'not_configured' => __('No configurado', 'momentum-admin'),
	'auth_error'     => __('Error de autenticación', 'momentum-admin'),
	'quota_exceeded' => __('Cuota agotada', 'momentum-admin'),
	'temporary_error' => __('Error temporal', 'momentum-admin'),
];

$connection_badge_class = [
	'connected'      => 'is-complete',
	'not_configured' => 'is-pending',
	'auth_error'     => 'is-error',
	'quota_exceeded' => 'is-quota_exceeded',
	'temporary_error' => 'is-translating',
][$connection] ?? 'is-pending';

$status_labels_display = [
	'complete'        => __('Traducida', 'momentum-admin'),
	'manual'          => __('Editada a mano', 'momentum-admin'),
	'pending'         => __('Pendiente', 'momentum-admin'),
	'translating'     => __('Traduciendo', 'momentum-admin'),
	'error'           => __('Con error', 'momentum-admin'),
	'quota_exceeded'  => __('Cuota agotada', 'momentum-admin'),
	'not_configured'  => __('Sin proveedor', 'momentum-admin'),
	'no_translation'  => __('Sin traducción todavía', 'momentum-admin'),
];

$result_labels = [
	'ok'      => __('Éxito', 'momentum-admin'),
	'error'   => __('Error', 'momentum-admin'),
	'blocked' => __('Bloqueada (sin cuota)', 'momentum-admin'),
];
?>
<div class="momentum-admin-page-header">
	<h1><?php esc_html_e('Traducciones', 'momentum-admin'); ?></h1>
	<p><?php esc_html_e('Estado de DeepL, consumo de cuota y actividad de traducción automática de Propiedades y Terrenos.', 'momentum-admin'); ?></p>
</div>

<?php if ($mensaje_code && isset($mensajes[$mensaje_code])) : ?>
	<p class="momentum-admin-alert momentum-admin-alert--success"><?php echo esc_html($mensajes[$mensaje_code]); ?></p>
<?php endif; ?>
<?php if ($error_code && isset($errores[$error_code])) : ?>
	<p class="momentum-admin-alert momentum-admin-alert--error"><?php echo esc_html($errores[$error_code]); ?></p>
<?php endif; ?>

<?php if ($threshold === 'blocked') : ?>
	<p class="momentum-admin-alert momentum-admin-alert--error"><?php esc_html_e('La cuota de DeepL está agotada (100% consumido). Las traducciones nuevas quedan como borrador pendiente hasta que vuelva a haber cuota disponible — nunca se publica contenido incompleto.', 'momentum-admin'); ?></p>
<?php elseif ($threshold === 'critical') : ?>
	<p class="momentum-admin-alert momentum-admin-alert--error"><?php esc_html_e('Advertencia: el consumo de DeepL superó el 90%. Queda muy poca cuota disponible este mes.', 'momentum-admin'); ?></p>
<?php elseif ($threshold === 'warning') : ?>
	<p class="momentum-admin-alert momentum-admin-alert--warning"><?php esc_html_e('Aviso: el consumo de DeepL superó el 80%. Las traducciones automáticas siguen funcionando con normalidad.', 'momentum-admin'); ?></p>
<?php endif; ?>

<!-- 1. Estado de DeepL -->
<section class="momentum-admin-settings-section">
	<h2><?php esc_html_e('Estado de DeepL', 'momentum-admin'); ?></h2>
	<div class="momentum-admin-stats">
		<div class="momentum-admin-stat-card">
			<span class="momentum-admin-badge <?php echo esc_attr($connection_badge_class); ?>"><?php echo esc_html($connection_labels[$connection] ?? $connection); ?></span>
			<span class="momentum-admin-stat-label">
				<?php if ($usage['fetched_at']) : ?>
					<?php
					printf(
						/* translators: %s: fecha y hora de la última consulta a DeepL */
						esc_html__('Última consulta: %s', 'momentum-admin'),
						esc_html(date_i18n('d/m/Y H:i', $usage['fetched_at']))
					);
					?>
					<?php if ($usage['stale']) : ?>
						<br><em><?php esc_html_e('(puede estar desactualizado — la última consulta en vivo falló)', 'momentum-admin'); ?></em>
					<?php endif; ?>
				<?php else : ?>
					<?php esc_html_e('Todavía no se ha consultado DeepL.', 'momentum-admin'); ?>
				<?php endif; ?>
			</span>
		</div>
	</div>

	<form method="post" action="<?php echo esc_url(home_url('/admin/traducciones/')); ?>">
		<?php wp_nonce_field(Momentum_Admin_Translations::REFRESH_NONCE_ACTION, Momentum_Admin_Translations::REFRESH_NONCE_FIELD); ?>
		<button type="submit" name="momentum_translation_refresh" value="1" class="momentum-admin-button momentum-admin-button--secondary"><?php esc_html_e('Actualizar ahora', 'momentum-admin'); ?></button>
	</form>
</section>

<!-- 2. Uso mensual -->
<section class="momentum-admin-settings-section">
	<h2><?php esc_html_e('Uso mensual', 'momentum-admin'); ?></h2>

	<?php if ($usage['character_count'] !== null && $usage['character_limit'] !== null) : ?>
		<div class="momentum-admin-stats">
			<div class="momentum-admin-stat-card">
				<span class="momentum-admin-stat-number"><?php echo esc_html(number_format_i18n($usage['character_count'])); ?></span>
				<span class="momentum-admin-stat-label"><?php esc_html_e('Caracteres usados', 'momentum-admin'); ?></span>
			</div>
			<div class="momentum-admin-stat-card">
				<span class="momentum-admin-stat-number"><?php echo esc_html(number_format_i18n(max(0, $usage['character_limit'] - $usage['character_count']))); ?></span>
				<span class="momentum-admin-stat-label"><?php esc_html_e('Caracteres restantes', 'momentum-admin'); ?></span>
			</div>
			<div class="momentum-admin-stat-card">
				<span class="momentum-admin-stat-number"><?php echo esc_html(number_format_i18n($usage['character_limit'])); ?></span>
				<span class="momentum-admin-stat-label"><?php esc_html_e('Límite del plan', 'momentum-admin'); ?></span>
			</div>
			<div class="momentum-admin-stat-card">
				<span class="momentum-admin-stat-number"><?php echo esc_html(number_format($usage_percent, 1)); ?>%</span>
				<span class="momentum-admin-stat-label"><?php esc_html_e('Consumido', 'momentum-admin'); ?></span>
			</div>
		</div>

		<div class="momentum-admin-progress" role="progressbar" aria-valuenow="<?php echo esc_attr((string) round($usage_percent)); ?>" aria-valuemin="0" aria-valuemax="100">
			<div class="momentum-admin-progress__bar is-<?php echo esc_attr($threshold); ?>" style="width: <?php echo esc_attr((string) round($usage_percent, 1)); ?>%;"></div>
		</div>
	<?php else : ?>
		<p class="momentum-admin-placeholder">
			<?php
			if ($connection === 'not_configured') {
				esc_html_e('Todavía no hay una API key de DeepL configurada (Admin > General > Traducción automática).', 'momentum-admin');
			} else {
				esc_html_e('No se pudo obtener el consumo de DeepL todavía. Usa "Actualizar ahora" para reintentar.', 'momentum-admin');
			}
			?>
		</p>
	<?php endif; ?>
</section>

<!-- 3. Actividad -->
<section class="momentum-admin-settings-section">
	<h2><?php esc_html_e('Actividad', 'momentum-admin'); ?></h2>

	<?php foreach (['propiedades' => __('Propiedades', 'momentum-admin'), 'terrenos' => __('Terrenos', 'momentum-admin')] as $post_type => $label) : ?>
		<?php $counts = $status_counts[$post_type] ?? []; ?>
		<h3 style="margin-bottom:8px;"><?php echo esc_html($label); ?></h3>
		<div class="momentum-admin-stats" style="margin-bottom:24px;">
			<div class="momentum-admin-stat-card">
				<span class="momentum-admin-stat-number"><?php echo esc_html((string) ($counts['complete'] ?? 0)); ?></span>
				<span class="momentum-admin-stat-label"><?php esc_html_e('Traducidas', 'momentum-admin'); ?></span>
			</div>
			<div class="momentum-admin-stat-card">
				<span class="momentum-admin-stat-number"><?php echo esc_html((string) (($counts['pending'] ?? 0) + ($counts['no_translation'] ?? 0) + ($counts['not_configured'] ?? 0) + ($counts['translating'] ?? 0))); ?></span>
				<span class="momentum-admin-stat-label"><?php esc_html_e('Pendientes', 'momentum-admin'); ?></span>
			</div>
			<div class="momentum-admin-stat-card">
				<span class="momentum-admin-stat-number"><?php echo esc_html((string) (($counts['error'] ?? 0) + ($counts['quota_exceeded'] ?? 0))); ?></span>
				<span class="momentum-admin-stat-label"><?php esc_html_e('Con error / cuota agotada', 'momentum-admin'); ?></span>
			</div>
			<div class="momentum-admin-stat-card">
				<span class="momentum-admin-stat-number"><?php echo esc_html((string) ($counts['manual'] ?? 0)); ?></span>
				<span class="momentum-admin-stat-label"><?php esc_html_e('Manuales protegidas', 'momentum-admin'); ?></span>
			</div>
		</div>
	<?php endforeach; ?>

	<p class="momentum-admin-field-hint">
		<?php if ($log_stats['last_success_at']) : ?>
			<?php
			printf(
				/* translators: %s: fecha y hora de la última traducción exitosa */
				esc_html__('Última traducción exitosa: %s', 'momentum-admin'),
				esc_html(mysql2date('d/m/Y H:i', $log_stats['last_success_at']))
			);
			?>
		<?php else : ?>
			<?php esc_html_e('Todavía no se ha completado ninguna traducción.', 'momentum-admin'); ?>
		<?php endif; ?>
	</p>

	<?php if ($log_stats['last_error']) : ?>
		<p class="momentum-admin-field-hint">
			<?php
			printf(
				/* translators: 1: fecha del último error, 2: mensaje saneado del error */
				esc_html__('Último error registrado (%1$s): %2$s', 'momentum-admin'),
				esc_html(mysql2date('d/m/Y H:i', $log_stats['last_error']->created_at)),
				esc_html($log_stats['last_error']->error_message ?: __('sin detalle', 'momentum-admin'))
			);
			?>
		</p>
	<?php endif; ?>
</section>

<!-- 4. Estimación -->
<section class="momentum-admin-settings-section">
	<h2><?php esc_html_e('Estimación', 'momentum-admin'); ?></h2>

	<?php if ($has_estimate) : ?>
		<div class="momentum-admin-stats">
			<div class="momentum-admin-stat-card">
				<span class="momentum-admin-stat-number"><?php echo esc_html(number_format_i18n((int) round($log_stats['avg_chars_per_success']))); ?></span>
				<span class="momentum-admin-stat-label"><?php esc_html_e('Caracteres promedio por ficha traducida', 'momentum-admin'); ?></span>
			</div>
			<div class="momentum-admin-stat-card">
				<span class="momentum-admin-stat-number"><?php echo esc_html(number_format_i18n($estimated_listings)); ?></span>
				<span class="momentum-admin-stat-label"><?php esc_html_e('Fichas adicionales estimadas con la cuota restante', 'momentum-admin'); ?></span>
			</div>
		</div>
		<p class="momentum-admin-field-hint"><?php esc_html_e('Calculado con el promedio real de las traducciones exitosas registradas en este sitio, no con un número fijo.', 'momentum-admin'); ?></p>
	<?php else : ?>
		<p class="momentum-admin-placeholder"><?php esc_html_e('Todavía no hay suficientes datos para calcular una estimación.', 'momentum-admin'); ?></p>
	<?php endif; ?>
</section>

<!-- Historial -->
<section class="momentum-admin-settings-section">
	<h2><?php esc_html_e('Historial (últimas 50 operaciones)', 'momentum-admin'); ?></h2>

	<div class="momentum-admin-table-wrap">
		<table class="momentum-admin-table">
			<thead>
				<tr>
					<th><?php esc_html_e('Fecha', 'momentum-admin'); ?></th>
					<th><?php esc_html_e('Contenido', 'momentum-admin'); ?></th>
					<th><?php esc_html_e('Tipo', 'momentum-admin'); ?></th>
					<th><?php esc_html_e('Caracteres', 'momentum-admin'); ?></th>
					<th><?php esc_html_e('Resultado', 'momentum-admin'); ?></th>
					<th><?php esc_html_e('Estado', 'momentum-admin'); ?></th>
					<th><?php esc_html_e('Detalle', 'momentum-admin'); ?></th>
					<th><?php esc_html_e('Acciones', 'momentum-admin'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ($recent_log) : ?>
					<?php foreach ($recent_log as $row) : ?>
						<?php
						$es_title    = get_the_title($row->es_post_id) ?: ('#' . $row->es_post_id);
						$edit_base   = home_url('/admin/' . $row->post_type . '/');
						$view_url    = add_query_arg(['accion' => 'editar', 'id' => $row->en_post_id], $edit_base);
						$can_retry   = in_array($row->result, ['error', 'blocked'], true);
						?>
						<tr>
							<td data-label="<?php esc_attr_e('Fecha', 'momentum-admin'); ?>"><?php echo esc_html(mysql2date('d/m/Y H:i', $row->created_at)); ?></td>
							<td data-label="<?php esc_attr_e('Contenido', 'momentum-admin'); ?>"><?php echo esc_html($es_title); ?></td>
							<td data-label="<?php esc_attr_e('Tipo', 'momentum-admin'); ?>"><?php echo esc_html($row->post_type === 'terrenos' ? __('Terreno', 'momentum-admin') : __('Propiedad', 'momentum-admin')); ?><?php echo $row->is_new ? ' <span class="momentum-admin-tag">' . esc_html__('nueva', 'momentum-admin') . '</span>' : ''; ?></td>
							<td data-label="<?php esc_attr_e('Caracteres', 'momentum-admin'); ?>"><?php echo esc_html(number_format_i18n((int) $row->char_count)); ?></td>
							<td data-label="<?php esc_attr_e('Resultado', 'momentum-admin'); ?>"><?php echo esc_html($result_labels[$row->result] ?? $row->result); ?></td>
							<td data-label="<?php esc_attr_e('Estado', 'momentum-admin'); ?>"><span class="momentum-admin-badge is-<?php echo esc_attr($row->status); ?>"><?php echo esc_html($status_labels_display[$row->status] ?? $row->status); ?></span></td>
							<td data-label="<?php esc_attr_e('Detalle', 'momentum-admin'); ?>"><?php echo esc_html($row->error_message ?: '—'); ?></td>
							<td data-label="<?php esc_attr_e('Acciones', 'momentum-admin'); ?>" class="momentum-admin-actions">
								<div class="momentum-admin-actions__group">
									<a href="<?php echo esc_url($view_url); ?>"><?php esc_html_e('Ver contenido', 'momentum-admin'); ?></a>
									<?php if ($can_retry) : ?>
										<form method="post" action="<?php echo esc_url(home_url('/admin/traducciones/')); ?>" class="momentum-admin-inline-form">
											<?php wp_nonce_field(Momentum_Admin_Translations::RETRY_NONCE_ACTION . '_' . $row->es_post_id, Momentum_Admin_Translations::RETRY_NONCE_FIELD); ?>
											<input type="hidden" name="momentum_es_post_id" value="<?php echo esc_attr((string) $row->es_post_id); ?>">
											<button
												type="submit"
												name="momentum_translation_retry"
												value="1"
												class="momentum-admin-link"
												<?php echo $translation_ok ? '' : 'disabled title="' . esc_attr__('Sin cuota o sin proveedor configurado', 'momentum-admin') . '"'; ?>
											><?php esc_html_e('Reintentar', 'momentum-admin'); ?></button>
										</form>
									<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="8" class="momentum-admin-empty"><?php esc_html_e('Todavía no hay operaciones registradas.', 'momentum-admin'); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</section>
