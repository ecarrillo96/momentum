<?php
/**
 * @var array $status Momentum_GA4_Diagnostics::get_status()
 */
if (! defined('ABSPATH')) {
	exit;
}

$is_configured = $status['property_id_configured'] && $status['credentials_configured'];
?>
<div class="momentum-admin-page-header">
	<h1><?php esc_html_e('Estadísticas', 'momentum-admin'); ?></h1>
	<p><?php esc_html_e('Datos reales de Google Analytics 4, consultados en vivo mediante la Analytics Data API — sin salir de WordPress.', 'momentum-admin'); ?></p>
</div>

<section class="momentum-ga4-diagnostics" id="momentum-ga4-diagnostics">
	<h2><?php esc_html_e('Estado de la integración', 'momentum-admin'); ?></h2>
	<div class="momentum-ga4-diagnostics__grid">
		<div class="momentum-ga4-diag-item">
			<span class="momentum-ga4-diag-item__label"><?php esc_html_e('Property ID', 'momentum-admin'); ?></span>
			<span class="momentum-ga4-badge <?php echo $status['property_id_configured'] ? 'is-ok' : 'is-error'; ?>">
				<?php echo $status['property_id_configured'] ? esc_html__('Configurado', 'momentum-admin') : esc_html__('No configurado', 'momentum-admin'); ?>
			</span>
		</div>
		<div class="momentum-ga4-diag-item">
			<span class="momentum-ga4-diag-item__label"><?php esc_html_e('Credenciales', 'momentum-admin'); ?></span>
			<span class="momentum-ga4-badge <?php echo $status['credentials_configured'] ? 'is-ok' : 'is-error'; ?>">
				<?php echo $status['credentials_configured'] ? esc_html__('Configuradas', 'momentum-admin') : esc_html__('No configuradas', 'momentum-admin'); ?>
			</span>
		</div>
		<div class="momentum-ga4-diag-item">
			<span class="momentum-ga4-diag-item__label"><?php esc_html_e('Archivo de credenciales', 'momentum-admin'); ?></span>
			<span class="momentum-ga4-badge <?php echo $status['credentials_accessible'] ? 'is-ok' : 'is-error'; ?>">
				<?php echo $status['credentials_accessible'] ? esc_html__('Accesible', 'momentum-admin') : esc_html__('No accesible', 'momentum-admin'); ?>
			</span>
		</div>
		<div class="momentum-ga4-diag-item">
			<span class="momentum-ga4-diag-item__label"><?php esc_html_e('Última conexión probada', 'momentum-admin'); ?></span>
			<span class="momentum-ga4-badge <?php echo ($status['last_check']['success'] ?? false) ? 'is-ok' : 'is-pending'; ?>" id="momentum-ga4-last-check">
				<?php
				if ($status['last_check']) {
					printf(
						'%s (%s)',
						esc_html(wp_date('d/m/Y H:i', $status['last_check']['time'])),
						$status['last_check']['success'] ? esc_html__('exitosa', 'momentum-admin') : esc_html__('falló', 'momentum-admin')
					);
				} else {
					esc_html_e('Todavía no se ha probado', 'momentum-admin');
				}
				?>
			</span>
		</div>
		<div class="momentum-ga4-diag-item">
			<span class="momentum-ga4-diag-item__label"><?php esc_html_e('Última actualización de caché', 'momentum-admin'); ?></span>
			<span class="momentum-ga4-badge is-pending" id="momentum-ga4-last-cache">
				<?php echo $status['last_cache_refresh'] ? esc_html(wp_date('d/m/Y H:i', $status['last_cache_refresh'])) : esc_html__('Nunca', 'momentum-admin'); ?>
			</span>
		</div>
	</div>

	<?php if ($status['last_error']) : ?>
		<p class="momentum-ga4-diag-error">
			<strong><?php esc_html_e('Último error:', 'momentum-admin'); ?></strong>
			<?php echo esc_html($status['last_error']['message']); ?>
			<span class="momentum-ga4-diag-error__time">(<?php echo esc_html(wp_date('d/m/Y H:i', $status['last_error']['time'])); ?>)</span>
		</p>
	<?php endif; ?>

	<button type="button" class="momentum-admin-button momentum-admin-button--secondary" id="momentum-ga4-test-connection">
		<?php esc_html_e('Probar conexión ahora', 'momentum-admin'); ?>
	</button>
	<span id="momentum-ga4-test-connection-result"></span>
</section>

<?php if (! $is_configured) : ?>
	<div class="momentum-admin-alert momentum-admin-alert--warning">
		<p>
			<?php esc_html_e('Google Analytics todavía no está configurado. Agrega MOMENTUM_GA4_PROPERTY_ID y las credenciales de la cuenta de servicio en wp-config.php para activar este panel.', 'momentum-admin'); ?>
			<?php esc_html_e('Consulta docs/18-panel-google-analytics.md en el proyecto para la guía completa paso a paso.', 'momentum-admin'); ?>
		</p>
	</div>
<?php else : ?>

	<section class="momentum-ga4-controls" id="momentum-ga4-controls">
		<div class="momentum-ga4-controls__row">
			<label for="momentum-ga4-period"><?php esc_html_e('Periodo', 'momentum-admin'); ?></label>
			<select id="momentum-ga4-period">
				<option value="today"><?php esc_html_e('Hoy', 'momentum-admin'); ?></option>
				<option value="yesterday"><?php esc_html_e('Ayer', 'momentum-admin'); ?></option>
				<option value="7d" selected><?php esc_html_e('Últimos 7 días', 'momentum-admin'); ?></option>
				<option value="28d"><?php esc_html_e('Últimos 28 días', 'momentum-admin'); ?></option>
				<option value="30d"><?php esc_html_e('Últimos 30 días', 'momentum-admin'); ?></option>
				<option value="90d"><?php esc_html_e('Últimos 90 días', 'momentum-admin'); ?></option>
				<option value="custom"><?php esc_html_e('Rango personalizado', 'momentum-admin'); ?></option>
			</select>

			<span id="momentum-ga4-custom-range" hidden>
				<label for="momentum-ga4-start"><?php esc_html_e('Desde', 'momentum-admin'); ?></label>
				<input type="date" id="momentum-ga4-start">
				<label for="momentum-ga4-end"><?php esc_html_e('Hasta', 'momentum-admin'); ?></label>
				<input type="date" id="momentum-ga4-end">
			</span>

			<label class="momentum-ga4-compare-toggle">
				<input type="checkbox" id="momentum-ga4-compare">
				<?php esc_html_e('Comparar contra el periodo anterior', 'momentum-admin'); ?>
			</label>

			<button type="button" class="momentum-admin-button" id="momentum-ga4-apply">
				<?php esc_html_e('Aplicar', 'momentum-admin'); ?>
			</button>

			<button type="button" class="momentum-admin-button momentum-admin-button--secondary" id="momentum-ga4-refresh">
				<?php esc_html_e('Actualizar datos', 'momentum-admin'); ?>
			</button>

			<button type="button" class="momentum-admin-button momentum-admin-button--secondary" id="momentum-ga4-clear-cache">
				<?php esc_html_e('Borrar caché de Analytics', 'momentum-admin'); ?>
			</button>
			<span id="momentum-ga4-clear-cache-result"></span>
		</div>
		<p class="momentum-ga4-controls__meta">
			<?php esc_html_e('Última actualización de caché:', 'momentum-admin'); ?>
			<span id="momentum-ga4-controls-last-refresh"><?php echo $status['last_cache_refresh'] ? esc_html(wp_date('d/m/Y H:i', $status['last_cache_refresh'])) : esc_html__('Nunca', 'momentum-admin'); ?></span>
			— <?php esc_html_e('los datos se cachean 20 minutos; usa "Actualizar datos" para forzar una consulta nueva, o "Borrar caché de Analytics" para solo borrar lo cacheado sin volver a consultar todavía.', 'momentum-admin'); ?>
		</p>
	</section>

	<section class="momentum-ga4-summary" id="momentum-ga4-summary" aria-live="polite">
		<div class="momentum-ga4-loading"><?php esc_html_e('Cargando…', 'momentum-admin'); ?></div>
	</section>

	<section class="momentum-ga4-chart-section">
		<div class="momentum-admin-page-header momentum-admin-page-header--section">
			<h2><?php esc_html_e('Evolución diaria', 'momentum-admin'); ?></h2>
		</div>
		<div class="momentum-ga4-chart-toggles">
			<label><input type="checkbox" class="momentum-ga4-metric-toggle" value="activeUsers" checked> <?php esc_html_e('Usuarios', 'momentum-admin'); ?></label>
			<label><input type="checkbox" class="momentum-ga4-metric-toggle" value="sessions" checked> <?php esc_html_e('Sesiones', 'momentum-admin'); ?></label>
			<label><input type="checkbox" class="momentum-ga4-metric-toggle" value="screenPageViews" checked> <?php esc_html_e('Vistas', 'momentum-admin'); ?></label>
		</div>
		<div class="momentum-ga4-chart-wrap">
			<canvas id="momentum-ga4-chart" height="90"></canvas>
		</div>
	</section>

	<?php
	$tables = [
		'top_pages'       => __('Páginas más visitadas', 'momentum-admin'),
		'top_properties'  => __('Propiedades más visitadas', 'momentum-admin'),
		'top_terrenos'    => __('Terrenos más visitados', 'momentum-admin'),
		'traffic_sources' => __('Fuentes de tráfico', 'momentum-admin'),
		'source_medium'   => __('Source / Medium', 'momentum-admin'),
		'geo'             => __('Ubicación de visitantes', 'momentum-admin'),
		'devices'         => __('Dispositivos', 'momentum-admin'),
		'browsers'        => __('Navegadores', 'momentum-admin'),
		'languages'       => __('Idiomas (Español / Inglés)', 'momentum-admin'),
		'events'          => __('Eventos y conversiones', 'momentum-admin'),
	];
	foreach ($tables as $report_key => $title) :
		?>
		<section class="momentum-ga4-section" data-report-section="<?php echo esc_attr($report_key); ?>">
			<div class="momentum-admin-page-header momentum-admin-page-header--section">
				<h2><?php echo esc_html($title); ?></h2>
			</div>
			<div class="momentum-ga4-section__body" id="momentum-ga4-<?php echo esc_attr(str_replace('_', '-', $report_key)); ?>">
				<div class="momentum-ga4-loading"><?php esc_html_e('Cargando…', 'momentum-admin'); ?></div>
			</div>
		</section>
	<?php endforeach; ?>

<?php endif; ?>
