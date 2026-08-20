/**
 * Interactividad del panel "Estadísticas" (GA4). Vanilla JS + fetch(), sin
 * jQuery -módulo autocontenido, solo se carga en esta página-. Cada sección
 * pide su propio reporte por AJAX a wp_ajax_momentum_ga4_get_report; el
 * servidor decide qué dimensiones/métricas pedir a Google, este archivo solo
 * pide un "report" por nombre y pinta la respuesta ya normalizada.
 */
(function () {
	'use strict';

	if (typeof momentumGA4 === 'undefined') {
		return;
	}

	var state = {
		period: '7d',
		compare: false,
		start: '',
		end: '',
	};

	var chart = null;
	var chartData = null;

	var METRIC_LABELS = {
		activeUsers: 'Usuarios activos',
		totalUsers: 'Usuarios totales',
		newUsers: 'Usuarios nuevos',
		sessions: 'Sesiones',
		engagedSessions: 'Sesiones con interacción',
		engagementRate: '% de interacción',
		screenPageViews: 'Vistas',
		eventCount: 'Número de eventos',
		averageSessionDuration: 'Duración media de sesión',
	};

	var PERCENT_METRICS = ['engagementRate'];
	var DURATION_METRICS = ['averageSessionDuration'];

	document.addEventListener('DOMContentLoaded', init);

	function init() {
		var periodSelect = document.getElementById('momentum-ga4-period');
		var testBtn = document.getElementById('momentum-ga4-test-connection');

		if (testBtn) {
			testBtn.addEventListener('click', handleTestConnection);
		}

		// Sin controles (GA4 no configurado todavía): solo queda el bloque de
		// diagnóstico con el botón de prueba de conexión, ya conectado arriba.
		if (!periodSelect) {
			return;
		}

		var customRange = document.getElementById('momentum-ga4-custom-range');
		var applyBtn = document.getElementById('momentum-ga4-apply');
		var refreshBtn = document.getElementById('momentum-ga4-refresh');
		var clearCacheBtn = document.getElementById('momentum-ga4-clear-cache');

		periodSelect.addEventListener('change', function () {
			customRange.hidden = periodSelect.value !== 'custom';
		});

		if (applyBtn) {
			applyBtn.addEventListener('click', function () {
				applyState();
				loadAll();
			});
		}

		if (refreshBtn) {
			refreshBtn.addEventListener('click', handleRefresh);
		}

		if (clearCacheBtn) {
			clearCacheBtn.addEventListener('click', handleClearCache);
		}

		document.querySelectorAll('.momentum-ga4-metric-toggle').forEach(function (el) {
			el.addEventListener('change', function () {
				if (chartData) {
					renderChart(chartData);
				}
			});
		});

		applyState();
		loadAll();
	}

	function applyState() {
		var periodSelect = document.getElementById('momentum-ga4-period');
		var compareCheckbox = document.getElementById('momentum-ga4-compare');
		var startInput = document.getElementById('momentum-ga4-start');
		var endInput = document.getElementById('momentum-ga4-end');

		state.period = periodSelect.value;
		state.compare = compareCheckbox ? compareCheckbox.checked : false;
		state.start = startInput ? startInput.value : '';
		state.end = endInput ? endInput.value : '';
	}

	function loadAll() {
		loadSummary();
		loadTimeseries();
		loadSection('top_pages', 'momentum-ga4-top-pages', renderPagesTable);
		loadSection('top_properties', 'momentum-ga4-top-properties', renderPropertiesTable);
		loadSection('top_terrenos', 'momentum-ga4-top-terrenos', renderPropertiesTable);
		loadSection('traffic_sources', 'momentum-ga4-traffic-sources', renderChannelsTable);
		loadSection('source_medium', 'momentum-ga4-source-medium', renderSourceMediumTable);
		loadSection('geo', 'momentum-ga4-geo', renderGeoTable);
		loadSection('devices', 'momentum-ga4-devices', renderDevicesTable);
		loadSection('browsers', 'momentum-ga4-browsers', renderBrowsersTable);
		loadSection('languages', 'momentum-ga4-languages', renderLanguages);
		loadSection('events', 'momentum-ga4-events', renderEvents);
	}

	// -----------------------------------------------------------------
	// Peticiones
	// -----------------------------------------------------------------

	function request(report) {
		var params = new URLSearchParams();
		params.set('action', 'momentum_ga4_get_report');
		params.set('nonce', momentumGA4.nonce);
		params.set('report', report);
		params.set('period', state.period);
		params.set('compare', state.compare ? '1' : '0');

		if (state.period === 'custom') {
			params.set('start', state.start);
			params.set('end', state.end);
		}

		return fetch(momentumGA4.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: params.toString(),
		})
			.then(function (res) {
				return res.json();
			})
			.then(function (json) {
				var payload = json && json.data ? json.data : {};

				if (!json || !json.success) {
					payload.success = false;

					if (!payload.error_message) {
						payload.error_message = momentumGA4.i18n.error;
					}
				}

				return payload;
			})
			.catch(function () {
				return { success: false, error_message: momentumGA4.i18n.error, data: null };
			});
	}

	function loadSection(report, containerId, renderFn) {
		var el = document.getElementById(containerId);

		if (!el) {
			return;
		}

		el.innerHTML = '<div class="momentum-ga4-loading">' + momentumGA4.i18n.loading + '</div>';

		request(report).then(function (result) {
			if (!result.success) {
				el.innerHTML = '<div class="momentum-ga4-error">' + escapeHtml(result.error_message) + '</div>';

				return;
			}

			updateLastRefresh(result.meta);
			renderFn(el, result.data);
		});
	}

	function loadSummary() {
		var el = document.getElementById('momentum-ga4-summary');

		if (!el) {
			return;
		}

		el.innerHTML = '<div class="momentum-ga4-loading">' + momentumGA4.i18n.loading + '</div>';

		request('summary').then(function (result) {
			if (!result.success) {
				el.innerHTML = '<div class="momentum-ga4-error">' + escapeHtml(result.error_message) + '</div>';

				return;
			}

			updateLastRefresh(result.meta);
			renderSummary(el, result.data);
		});
	}

	function loadTimeseries() {
		request('timeseries').then(function (result) {
			if (!result.success) {
				return;
			}

			updateLastRefresh(result.meta);
			chartData = result.data;
			renderChart(chartData);
		});
	}

	function handleRefresh() {
		var btn = document.getElementById('momentum-ga4-refresh');

		if (btn) {
			btn.disabled = true;
		}

		var params = new URLSearchParams();
		params.set('action', 'momentum_ga4_refresh_cache');
		params.set('nonce', momentumGA4.nonce);

		fetch(momentumGA4.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: params.toString(),
		})
			.then(function () {
				loadAll();
			})
			.finally(function () {
				if (btn) {
					btn.disabled = false;
				}
			});
	}

	/**
	 * "Borrar caché de Analytics": a diferencia de handleRefresh() (que
	 * borra y de inmediato vuelve a pedir todo), este solo borra y
	 * refleja el estado "sin caché" en pantalla -"Última actualización de
	 * caché" vuelve a "Nunca"-, sin volver a consultar GA4. El usuario
	 * decide aparte, presionando "Actualizar datos", cuándo pedir datos
	 * frescos otra vez.
	 */
	function handleClearCache() {
		var btn = document.getElementById('momentum-ga4-clear-cache');
		var resultEl = document.getElementById('momentum-ga4-clear-cache-result');

		if (btn) {
			btn.disabled = true;
		}

		var params = new URLSearchParams();
		params.set('action', 'momentum_ga4_clear_cache');
		params.set('nonce', momentumGA4.nonce);

		fetch(momentumGA4.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: params.toString(),
		})
			.then(function (res) {
				return res.json();
			})
			.then(function (json) {
				if (resultEl) {
					resultEl.textContent = (json && json.success)
						? momentumGA4.i18n.cacheCleared
						: momentumGA4.i18n.error;
				}

				var never = momentumGA4.i18n.never;
				var el1 = document.getElementById('momentum-ga4-last-cache');
				var el2 = document.getElementById('momentum-ga4-controls-last-refresh');

				if (el1) {
					el1.textContent = never;
				}

				if (el2) {
					el2.textContent = never;
				}
			})
			.catch(function () {
				if (resultEl) {
					resultEl.textContent = momentumGA4.i18n.error;
				}
			})
			.finally(function () {
				if (btn) {
					btn.disabled = false;
				}
			});
	}

	function handleTestConnection() {
		var btn = document.getElementById('momentum-ga4-test-connection');
		var resultEl = document.getElementById('momentum-ga4-test-connection-result');

		if (btn) {
			btn.disabled = true;
		}

		if (resultEl) {
			resultEl.textContent = momentumGA4.i18n.loading;
		}

		var params = new URLSearchParams();
		params.set('action', 'momentum_ga4_test_connection');
		params.set('nonce', momentumGA4.nonce);

		fetch(momentumGA4.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: params.toString(),
		})
			.then(function (res) {
				return res.json();
			})
			.then(function (json) {
				var payload = json && json.data ? json.data : {};
				var badge  = document.getElementById('momentum-ga4-last-check');
				var now    = formatDateTime(new Date());

				if (payload.success) {
					if (resultEl) {
						resultEl.textContent = 'Conexión exitosa.';
					}

					if (badge) {
						badge.textContent = now + ' (exitosa)';
						badge.className   = 'momentum-ga4-badge is-ok';
					}
				} else {
					if (resultEl) {
						resultEl.textContent = payload.message || momentumGA4.i18n.error;
					}

					if (badge) {
						badge.textContent = now + ' (falló)';
						badge.className   = 'momentum-ga4-badge is-error';
					}
				}
			})
			.catch(function () {
				if (resultEl) {
					resultEl.textContent = momentumGA4.i18n.error;
				}
			})
			.finally(function () {
				if (btn) {
					btn.disabled = false;
				}
			});
	}

	function updateLastRefresh(meta) {
		if (!meta || !meta.cached_at) {
			return;
		}

		var formatted = formatDateTime(new Date(meta.cached_at * 1000));
		var el1 = document.getElementById('momentum-ga4-last-cache');
		var el2 = document.getElementById('momentum-ga4-controls-last-refresh');

		if (el1) {
			el1.textContent = formatted;
		}

		if (el2) {
			el2.textContent = formatted;
		}
	}

	// -----------------------------------------------------------------
	// Render: resumen
	// -----------------------------------------------------------------

	function renderSummary(el, cards) {
		var html = '';

		Object.keys(METRIC_LABELS).forEach(function (metric) {
			var card = cards[metric];

			if (!card) {
				return;
			}

			html += '<div class="momentum-ga4-card">';
			html += '<div class="momentum-ga4-card__label">' + escapeHtml(METRIC_LABELS[metric]) + '</div>';
			html += '<div class="momentum-ga4-card__value">' + formatMetricValue(metric, card.current) + '</div>';

			if (card.percent !== undefined) {
				var dirClass    = 'is-' + card.direction;
				var arrow       = card.direction === 'up' ? '▲' : card.direction === 'down' ? '▼' : '—';
				var percentText = card.percent === null
					? 'sin datos previos'
					: (card.percent > 0 ? '+' : '') + card.percent + '%';

				html += '<div class="momentum-ga4-card__compare">';
				html += '<span class="' + dirClass + '">' + arrow + ' ' + percentText + '</span>';
				html += '<span class="momentum-ga4-card__compare-prev">(' + formatMetricValue(metric, card.previous) + ' antes)</span>';
				html += '</div>';
			}

			html += '</div>';
		});

		el.innerHTML = html || '<div class="momentum-ga4-empty">' + momentumGA4.i18n.noData + '</div>';
	}

	function formatMetricValue(metric, value) {
		if (PERCENT_METRICS.indexOf(metric) !== -1) {
			return (Number(value) * 100).toFixed(1) + '%';
		}

		if (DURATION_METRICS.indexOf(metric) !== -1) {
			return formatDuration(value);
		}

		return formatNumber(value);
	}

	// -----------------------------------------------------------------
	// Render: gráfica
	// -----------------------------------------------------------------

	function renderChart(points) {
		var canvas = document.getElementById('momentum-ga4-chart');

		if (!canvas || typeof Chart === 'undefined') {
			return;
		}

		var seriesConfig = {
			activeUsers:      { label: 'Usuarios', color: '#2563eb' },
			sessions:         { label: 'Sesiones', color: '#16a34a' },
			screenPageViews:  { label: 'Vistas', color: '#d97706' },
		};

		var labels   = points.map(function (p) { return p.date; });
		var datasets = [];

		document.querySelectorAll('.momentum-ga4-metric-toggle').forEach(function (toggle) {
			if (!toggle.checked) {
				return;
			}

			var config = seriesConfig[toggle.value];

			if (!config) {
				return;
			}

			datasets.push({
				label: config.label,
				data: points.map(function (p) { return p[toggle.value] || 0; }),
				borderColor: config.color,
				backgroundColor: config.color,
				tension: 0.25,
				pointRadius: 2,
			});
		});

		if (chart) {
			chart.destroy();
		}

		chart = new Chart(canvas.getContext('2d'), {
			type: 'line',
			data: { labels: labels, datasets: datasets },
			options: {
				responsive: true,
				interaction: { mode: 'index', intersect: false },
				scales: { y: { beginAtZero: true } },
			},
		});
	}

	// -----------------------------------------------------------------
	// Render: tablas
	// -----------------------------------------------------------------

	function buildTable(columns, rows, emptyMessage) {
		if (!rows || rows.length === 0) {
			return '<div class="momentum-ga4-empty">' + escapeHtml(emptyMessage || momentumGA4.i18n.noData) + '</div>';
		}

		var html = '<table class="momentum-ga4-table"><thead><tr>';

		columns.forEach(function (col) {
			html += '<th>' + escapeHtml(col.label) + '</th>';
		});

		html += '</tr></thead><tbody>';

		rows.forEach(function (row) {
			html += '<tr>';
			columns.forEach(function (col) {
				html += '<td' + (col.wrap ? ' class="momentum-ga4-col-title"' : '') + '>' + col.render(row) + '</td>';
			});
			html += '</tr>';
		});

		html += '</tbody></table>';

		return html;
	}

	function renderPagesTable(el, rows) {
		el.innerHTML = buildTable([
			{ label: 'Página', wrap: true, render: function (r) {
				return escapeHtml(r.title || '(sin título)') + '<br><span class="momentum-ga4-muted">' + escapeHtml(r.path) + '</span>';
			} },
			{ label: 'Vistas', render: function (r) { return formatNumber(r.views); } },
			{ label: 'Usuarios', render: function (r) { return formatNumber(r.users); } },
			{ label: 'Sesiones', render: function (r) { return formatNumber(r.sessions); } },
			{ label: 'Duración media', render: function (r) { return formatDuration(r.avg_engagement_seconds); } },
			{ label: '% Interacción', render: function (r) { return r.engagement_rate + '%'; } },
		], rows);
	}

	function renderPropertiesTable(el, rows) {
		el.innerHTML = buildTable([
			{ label: 'Ficha', wrap: true, render: function (r) {
				var name = r.wp_title || r.title || '(sin título)';
				var out  = escapeHtml(name) + '<br><span class="momentum-ga4-muted">' + escapeHtml(r.path) + '</span>';

				if (r.edit_link) {
					out += ' <a href="' + escapeAttr(r.edit_link) + '" target="_blank" rel="noopener">Editar</a>';
				} else {
					out += ' <span class="momentum-ga4-badge is-pending">Sin vincular</span>';
				}

				return out;
			} },
			{ label: 'Vistas', render: function (r) { return formatNumber(r.views); } },
			{ label: 'Usuarios', render: function (r) { return formatNumber(r.users); } },
			{ label: 'Sesiones', render: function (r) { return formatNumber(r.sessions); } },
			{ label: 'Tiempo medio de interacción', render: function (r) { return formatDuration(r.avg_engagement_seconds); } },
		], rows, 'No se detectaron visitas a fichas en este periodo.');
	}

	function renderChannelsTable(el, rows) {
		el.innerHTML = buildTable([
			{ label: 'Canal', render: function (r) { return escapeHtml(r.channel); } },
			{ label: 'Usuarios', render: function (r) { return formatNumber(r.users); } },
			{ label: 'Sesiones', render: function (r) { return formatNumber(r.sessions); } },
		], rows);
	}

	function renderSourceMediumTable(el, rows) {
		el.innerHTML = buildTable([
			{ label: 'Source', render: function (r) { return escapeHtml(r.source); } },
			{ label: 'Medium', render: function (r) { return escapeHtml(r.medium); } },
			{ label: 'Usuarios', render: function (r) { return formatNumber(r.users); } },
			{ label: 'Sesiones', render: function (r) { return formatNumber(r.sessions); } },
			{ label: 'Key events', render: function (r) { return formatNumber(r.key_events); } },
		], rows);
	}

	function renderGeoTable(el, rows) {
		el.innerHTML = buildTable([
			{ label: 'País', render: function (r) { return escapeHtml(r.country); } },
			{ label: 'Ciudad', render: function (r) { return escapeHtml(r.city); } },
			{ label: 'Usuarios', render: function (r) { return formatNumber(r.users); } },
			{ label: 'Sesiones', render: function (r) { return formatNumber(r.sessions); } },
		], rows);
	}

	function renderDevicesTable(el, rows) {
		el.innerHTML = buildTable([
			{ label: 'Dispositivo', render: function (r) { return escapeHtml(r.device); } },
			{ label: 'Usuarios', render: function (r) { return formatNumber(r.users); } },
			{ label: 'Sesiones', render: function (r) { return formatNumber(r.sessions); } },
			{ label: 'Vistas', render: function (r) { return formatNumber(r.views || 0); } },
			{ label: '% del total', render: function (r) { return r.percent + '%'; } },
		], rows);
	}

	function renderBrowsersTable(el, rows) {
		el.innerHTML = buildTable([
			{ label: 'Navegador', render: function (r) { return escapeHtml(r.browser); } },
			{ label: 'Usuarios', render: function (r) { return formatNumber(r.users); } },
			{ label: 'Sesiones', render: function (r) { return formatNumber(r.sessions); } },
		], rows);
	}

	function renderLanguages(el, data) {
		var totals   = data.totals || {};
		var topPages = data.top_pages || {};

		function col(langKey, label) {
			var t     = totals[langKey] || { users: 0, sessions: 0, views: 0 };
			var pages = topPages[langKey] || [];

			var html = '<div class="momentum-ga4-lang-col"><h3>' + escapeHtml(label) + '</h3>';
			html += '<div class="momentum-ga4-lang-totals">';
			html += '<div><strong>' + formatNumber(t.users) + '</strong>Usuarios</div>';
			html += '<div><strong>' + formatNumber(t.sessions) + '</strong>Sesiones</div>';
			html += '<div><strong>' + formatNumber(t.views) + '</strong>Vistas</div>';
			html += '</div>';
			html += buildTable([
				{ label: 'Página más visitada', wrap: true, render: function (r) { return escapeHtml(r.title || r.path); } },
				{ label: 'Vistas', render: function (r) { return formatNumber(r.views); } },
			], pages, 'Sin datos.');
			html += '</div>';

			return html;
		}

		el.innerHTML = '<div class="momentum-ga4-lang-grid">' + col('es', 'Español') + col('en', 'Inglés') + '</div>';
	}

	function renderEvents(el, data) {
		var html        = '';
		var recommended = data.recommended || {};
		var keys        = Object.keys(recommended);

		if (keys.length) {
			html += '<div class="momentum-ga4-recommended-events"><strong>Eventos recomendados</strong><ul>';

			keys.forEach(function (key) {
				var item = recommended[key];
				var cls  = item.configured ? 'is-ok' : 'is-error';
				var text = item.configured
					? item.label + ' — detectado como "' + item.matched_event + '"'
					: item.label + ' — no configurado todavía';

				html += '<li><span class="momentum-ga4-badge ' + cls + '">' + escapeHtml(text) + '</span></li>';
			});

			html += '</ul></div>';
		}

		if (data.key_events && data.key_events.length) {
			html += '<div style="padding:16px 20px 0"><strong>Key events</strong></div>';
			html += buildTable([
				{ label: 'Evento', render: function (r) { return escapeHtml(r.name); } },
				{ label: 'Key events', render: function (r) { return formatNumber(r.key_events); } },
				{ label: 'Usuarios', render: function (r) { return formatNumber(r.users); } },
			], data.key_events);
		}

		html += '<div style="padding:16px 20px 0"><strong>Todos los eventos</strong></div>';
		html += buildTable([
			{ label: 'Evento', render: function (r) { return escapeHtml(r.name); } },
			{ label: 'Eventos', render: function (r) { return formatNumber(r.event_count); } },
			{ label: 'Usuarios', render: function (r) { return formatNumber(r.users); } },
			{ label: 'Eventos / usuario', render: function (r) { return r.events_per_user; } },
		], data.all, 'No hay eventos registrados en este periodo.');

		el.innerHTML = html;
	}

	// -----------------------------------------------------------------
	// Utilidades
	// -----------------------------------------------------------------

	function escapeHtml(str) {
		if (str === null || str === undefined) {
			return '';
		}

		return String(str).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function escapeAttr(str) {
		return escapeHtml(str);
	}

	function formatNumber(value) {
		var n = Number(value) || 0;

		return n.toLocaleString('es-MX', { maximumFractionDigits: 0 });
	}

	function formatDuration(seconds) {
		var s   = Math.round(Number(seconds) || 0);
		var m   = Math.floor(s / 60);
		var rem = s % 60;

		return m + 'm ' + rem + 's';
	}

	function formatDateTime(date) {
		function pad(n) {
			return String(n).padStart(2, '0');
		}

		return pad(date.getDate()) + '/' + pad(date.getMonth() + 1) + '/' + date.getFullYear() + ' ' + pad(date.getHours()) + ':' + pad(date.getMinutes());
	}
})();
