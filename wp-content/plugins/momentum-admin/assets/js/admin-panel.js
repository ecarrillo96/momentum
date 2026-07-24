(function ($) {
	'use strict';

	$(function () {
		// Menú móvil.
		var $menuToggle = $('#momentum-admin-menu-toggle');
		var $sidebar = $('#momentum-admin-sidebar');

		$menuToggle.on('click', function () {
			var isOpen = $sidebar.hasClass('is-open');
			$sidebar.toggleClass('is-open', !isOpen);
			$menuToggle.attr('aria-expanded', String(!isOpen));
		});

		$(document).on('click', function (e) {
			if (
				$sidebar.hasClass('is-open') &&
				!$(e.target).closest('#momentum-admin-sidebar, #momentum-admin-menu-toggle').length
			) {
				$sidebar.removeClass('is-open');
				$menuToggle.attr('aria-expanded', 'false');
			}
		});

		if (typeof wp !== 'undefined' && wp.media) {

			// Selector de imagen única (destacada), reutilizado por Propiedades,
			// Blog y la imagen de redes sociales del bloque SEO: misma lógica,
			// distintos ids de campo, para no duplicar el bloque wp.media tres veces.
			// El botón de quitar es el mismo patrón "×" flotante que la galería
			// (.momentum-gallery-item / .momentum-gallery-remove), delegado sobre
			// el preview en vez de un botón de texto fijo, porque el preview se
			// reconstruye por completo en cada selección.
			function initSingleImagePicker(config) {
				var $button = $('#' + config.buttonId);
				var $input = $('#' + config.inputId);
				var $preview = $('#' + config.previewId);
				var frame;

				if (!$button.length) {
					return;
				}

				function renderPreview(url, alt) {
					var $item = $('<div>', { 'class': 'momentum-gallery-item' });

					$('<img>').attr('src', url).attr('alt', alt || '').appendTo($item);

					$('<button>', {
						type: 'button',
						'class': 'momentum-gallery-remove',
						'aria-label': 'Quitar imagen',
					}).html('&times;').appendTo($item);

					$preview.empty().append($item);
				}

				$button.on('click', function (e) {
					e.preventDefault();

					if (frame) {
						frame.open();
						return;
					}

					frame = wp.media({
						title: config.title,
						button: { text: 'Usar esta imagen' },
						multiple: false,
					});

					frame.on('select', function () {
						var attachment = frame.state().get('selection').first().toJSON();
						var url = (attachment.sizes && attachment.sizes.thumbnail) ? attachment.sizes.thumbnail.url : attachment.url;

						$input.val(attachment.id);
						renderPreview(url, attachment.alt);
					});

					frame.open();
				});

				$preview.on('click', '.momentum-gallery-remove', function (e) {
					e.preventDefault();
					$input.val('');
					$preview.empty();
				});
			}

			initSingleImagePicker({
				buttonId: 'momentum-featured-select',
				inputId: 'momentum_property_featured_image',
				previewId: 'momentum-featured-preview',
				title: 'Seleccionar imagen destacada',
			});

			initSingleImagePicker({
				buttonId: 'momentum-terreno-featured-select',
				inputId: 'momentum_terreno_featured_image',
				previewId: 'momentum-terreno-featured-preview',
				title: 'Seleccionar imagen destacada',
			});

			initSingleImagePicker({
				buttonId: 'momentum-article-featured-select',
				inputId: 'momentum_article_featured_image',
				previewId: 'momentum-article-featured-preview',
				title: 'Seleccionar imagen destacada',
			});

			initSingleImagePicker({
				buttonId: 'momentum-og-image-select',
				inputId: 'momentum_seo_og_imagen',
				previewId: 'momentum-og-image-preview',
				title: 'Seleccionar imagen para redes sociales',
			});

			// Galería (selección múltiple, acumulativa, sin duplicados).
			var $galleryButton = $('#momentum-galeria-select');
			var $galleryInput = $('#momentum_galeria');
			var $galleryPreview = $('#momentum-galeria-preview');
			var galleryFrame;

			// Única fuente de verdad: se siembra leyendo el markup ya renderizado
			// por PHP (form.php), así no se depende de una petición extra para
			// conocer las imágenes que la propiedad ya tenía guardadas.
			var galleryItems = $galleryPreview.find('.momentum-gallery-item').map(function () {
				var $item = $(this);
				return {
					id: parseInt($item.data('id'), 10),
					url: $item.find('img').attr('src') || '',
				};
			}).get();

			function syncGalleryInput() {
				$galleryInput.val(galleryItems.map(function (item) { return item.id; }).join(','));
			}

			function renderGalleryPreview() {
				$galleryPreview.empty();

				galleryItems.forEach(function (item) {
					var $item = $('<div>', { 'class': 'momentum-gallery-item' }).attr('data-id', item.id);

					$('<img>').attr('src', item.url).appendTo($item);

					$('<button>', {
						type: 'button',
						'class': 'momentum-gallery-remove',
						'aria-label': 'Eliminar imagen',
					}).html('&times;').appendTo($item);

					$galleryPreview.append($item);
				});
			}

			// Combina lo ya existente con lo recién seleccionado; nunca reemplaza.
			function addGalleryAttachments(attachments) {
				attachments.forEach(function (attachment) {
					var alreadyThere = galleryItems.some(function (item) {
						return item.id === attachment.id;
					});

					if (alreadyThere) {
						return;
					}

					var url = (attachment.sizes && attachment.sizes.thumbnail) ? attachment.sizes.thumbnail.url : attachment.url;
					galleryItems.push({ id: attachment.id, url: url });
				});

				renderGalleryPreview();
				syncGalleryInput();
			}

			function removeGalleryItem(id) {
				galleryItems = galleryItems.filter(function (item) {
					return item.id !== id;
				});

				renderGalleryPreview();
				syncGalleryInput();

				// Mantiene la selección interna del frame sincronizada, para que si
				// se reabre el modal sin recargar la página no siga marcada.
				if (galleryFrame) {
					var selection = galleryFrame.state().get('selection');
					var model = selection.get(id);

					if (model) {
						selection.remove(model);
					}
				}
			}

			// Delegado: cubre tanto las miniaturas renderizadas por PHP al cargar
			// como las que esta función añade dinámicamente después.
			$galleryPreview.on('click', '.momentum-gallery-remove', function (e) {
				e.preventDefault();

				var id = parseInt($(this).closest('.momentum-gallery-item').data('id'), 10);
				removeGalleryItem(id);
			});

			$galleryButton.on('click', function (e) {
				e.preventDefault();

				if (!galleryFrame) {
					galleryFrame = wp.media({
						title: 'Seleccionar imágenes de la galería',
						button: { text: 'Usar estas imágenes' },
						multiple: true,
					});

					// Preselecciona las imágenes ya guardadas cada vez que se abre
					// el modal (no solo al crearlo), para reflejar eliminaciones u
					// adiciones hechas desde la última apertura.
					galleryFrame.on('open', function () {
						var selection = galleryFrame.state().get('selection');
						selection.reset();

						galleryItems.forEach(function (item) {
							var attachment = wp.media.attachment(item.id);
							attachment.fetch();
							selection.add(attachment);
						});
					});

					galleryFrame.on('select', function () {
						var attachments = galleryFrame.state().get('selection').toJSON();
						addGalleryAttachments(attachments);
					});
				}

				galleryFrame.open();
			});
		}

		// Slug del artículo: se sigue del título en español mientras el
		// administrador no lo haya tocado a mano; en cuanto lo edita, deja de
		// seguirlo (así no se reescribe solo al editar un artículo existente).
		var $titleField = $('[data-slug-source]');
		var $slugField = $('#momentum_article_slug');

		if ($titleField.length && $slugField.length) {
			var slugTouched = $slugField.val() !== '';

			function slugify(text) {
				return text
					.toString()
					.normalize('NFD').replace(/[̀-ͯ]/g, '')
					.toLowerCase()
					.trim()
					.replace(/[^a-z0-9\s-]/g, '')
					.replace(/[\s-]+/g, '-')
					.replace(/^-+|-+$/g, '');
			}

			$slugField.on('input', function () {
				slugTouched = true;
			});

			$titleField.on('input', function () {
				if (!slugTouched) {
					$slugField.val(slugify($titleField.val()));
				}
			});
		}

		// Botones "Guardar como borrador" / "Publicar artículo": ambos
		// envían el mismo <form>; el estado deseado viaja en un campo oculto
		// que cada botón fija justo antes del submit.
		$('#momentum-admin-article-form button[data-status]').on('click', function () {
			$('#momentum_article_status').val($(this).data('status'));
		});

		// Etiquetas: input de texto con chips, respaldado por un solo campo
		// oculto de valores separados por coma (mismo criterio que Galería:
		// una fuente de verdad en JS, sembrada desde el valor ya guardado).
		var $tagsInput = $('#momentum_article_tags');
		var $tagsPreview = $('#momentum-tags-preview');
		var $tagsTextInput = $('#momentum-tags-input');

		if ($tagsInput.length) {
			var tags = $tagsInput.val()
				? $tagsInput.val().split(',').map(function (t) { return t.trim(); }).filter(Boolean)
				: [];

			function syncTags() {
				$tagsInput.val(tags.join(', '));
			}

			function renderTags() {
				$tagsPreview.empty();

				tags.forEach(function (tag) {
					var $chip = $('<span>', { 'class': 'momentum-admin-chip' }).text(tag);

					$('<button>', { type: 'button', 'class': 'momentum-admin-chip-remove', 'aria-label': 'Quitar etiqueta' })
						.html('&times;')
						.appendTo($chip);

					$tagsPreview.append($chip);
				});
			}

			$tagsPreview.on('click', '.momentum-admin-chip-remove', function () {
				var tag = $(this).closest('.momentum-admin-chip').text().replace('×', '').trim();
				tags = tags.filter(function (t) { return t !== tag; });
				renderTags();
				syncTags();
			});

			$tagsTextInput.on('keydown', function (e) {
				if (e.key !== 'Enter' && e.key !== ',') {
					return;
				}

				e.preventDefault();

				var value = $tagsTextInput.val().replace(/,$/, '').trim();

				if (value && tags.indexOf(value) === -1) {
					tags.push(value);
					renderTags();
					syncTags();
				}

				$tagsTextInput.val('');
			});

			renderTags();
		}

		// SEO: contador de caracteres bajo cada campo con recomendación,
		// sin bloquear el guardado si se supera.
		$('[data-counter]').each(function () {
			var $field = $(this);
			var $counter = $('#' + $field.data('counter'));
			var recommended = parseInt($field.data('recommended'), 10);

			function update() {
				var length = $field.val().length;
				$counter.text(length + ' / ' + recommended + ' caracteres recomendados');
				$counter.toggleClass('is-over', length > recommended);
			}

			$field.on('input', update);
			update();
		});

		// -----------------------------------------------------------------
		// Zonas: modales de Agregar / Confirmar eliminación (sin recargar).
		// window.momentumAdminZonas lo imprime footer.php solo en esta página
		// (admin-panel.js se sirve como <script src> plano, sin
		// wp_localize_script, así que los datos viajan por ese global).
		// -----------------------------------------------------------------
		if (window.momentumAdminZonas) {
			var zonasConfig = window.momentumAdminZonas;

			function openModal($modal) {
				$modal.prop('hidden', false).attr('aria-hidden', 'false');
				$('body').addClass('momentum-admin-modal-open');
				$modal.find('input, button').filter(':visible').first().trigger('focus');
			}

			function closeModal($modal) {
				$modal.prop('hidden', true).attr('aria-hidden', 'true');
				$('body').removeClass('momentum-admin-modal-open');
			}

			$('[data-momentum-modal-close]').on('click', function () {
				closeModal($(this).closest('.momentum-admin-modal'));
			});

			$(document).on('keydown', function (e) {
				if (e.key === 'Escape') {
					$('.momentum-admin-modal').not('[hidden]').each(function () {
						closeModal($(this));
					});
				}
			});

			function showSuccess(message) {
				var $success = $('#momentum-zona-success');
				$success.text(message).prop('hidden', false);

				window.clearTimeout($success.data('hideTimeout'));
				var timeout = window.setTimeout(function () {
					$success.prop('hidden', true);
				}, 4000);
				$success.data('hideTimeout', timeout);
			}

			// ---- Agregar zona ----

			var $addModal = $('#momentum-zona-add-modal');
			var $addForm = $('#momentum-zona-add-form');
			var $addError = $('#momentum-zona-add-error');
			var $addInput = $('#momentum-zona-nombre');

			$('#momentum-zona-add-open').on('click', function () {
				$addError.prop('hidden', true).text('');
				$addForm[0].reset();
				openModal($addModal);
			});

			function insertZonaRow(zona) {
				$('#momentum-zonas-empty').prop('hidden', true);

				var templateContent = document.getElementById('momentum-zona-row-template').content;
				var $row = $(templateContent.cloneNode(true)).find('tr');

				$row.attr('data-zona-id', zona.id);
				$row.find('.momentum-zona-row-name').text(zona.name);
				$row.find('.momentum-zona-row-date').text(zona.date);
				$row.find('.momentum-zona-delete-open').attr('data-zona-id', zona.id).attr('data-zona-name', zona.name);

				var $rows = $('#momentum-zonas-tbody tr');
				var inserted = false;

				$rows.each(function () {
					var existingName = $(this).find('td').first().text();

					if (zona.name.localeCompare(existingName, 'es', { sensitivity: 'base' }) < 0) {
						$(this).before($row);
						inserted = true;
						return false;
					}
				});

				if (!inserted) {
					$('#momentum-zonas-tbody').append($row);
				}
			}

			$addForm.on('submit', function (e) {
				e.preventDefault();

				var $submit = $addForm.find('button[type="submit"]');
				$addError.prop('hidden', true).text('');
				$submit.prop('disabled', true);

				$.post(zonasConfig.ajaxUrl, {
					action: 'momentum_admin_add_zona',
					nonce: zonasConfig.nonce,
					nombre: $addInput.val(),
				}).done(function (response) {
					if (!response || !response.success) {
						var message = (response && response.data && response.data.message) || 'Ocurrió un error al guardar la zona.';
						$addError.text(message).prop('hidden', false);
						return;
					}

					insertZonaRow(response.data.zona);
					closeModal($addModal);
					showSuccess(response.data.message);
				}).fail(function () {
					$addError.text('Ocurrió un error al guardar la zona. Intenta de nuevo.').prop('hidden', false);
				}).always(function () {
					$submit.prop('disabled', false);
				});
			});

			// ---- Eliminar zona ----

			var $deleteModal = $('#momentum-zona-delete-modal');
			var $deleteError = $('#momentum-zona-delete-error');
			var $deleteConfirm = $('#momentum-zona-delete-confirm');
			var pendingDeleteId = null;

			$(document).on('click', '.momentum-zona-delete-open', function () {
				pendingDeleteId = $(this).data('zona-id');
				$deleteError.prop('hidden', true).text('');
				openModal($deleteModal);
			});

			$deleteConfirm.on('click', function () {
				if (!pendingDeleteId) {
					return;
				}

				$deleteError.prop('hidden', true).text('');
				$deleteConfirm.prop('disabled', true);

				$.post(zonasConfig.ajaxUrl, {
					action: 'momentum_admin_delete_zona',
					nonce: zonasConfig.nonce,
					zona_id: pendingDeleteId,
				}).done(function (response) {
					if (!response || !response.success) {
						var message = (response && response.data && response.data.message) || 'Ocurrió un error al eliminar la zona.';
						$deleteError.text(message).prop('hidden', false);
						return;
					}

					$('#momentum-zonas-tbody tr[data-zona-id="' + pendingDeleteId + '"]').remove();

					if (!$('#momentum-zonas-tbody tr').length) {
						$('#momentum-zonas-empty').prop('hidden', false);
					}

					closeModal($deleteModal);
					showSuccess(response.data.message);
					pendingDeleteId = null;
				}).fail(function () {
					$deleteError.text('Ocurrió un error al eliminar la zona. Intenta de nuevo.').prop('hidden', false);
				}).always(function () {
					$deleteConfirm.prop('disabled', false);
				});
			});
		}

		// -----------------------------------------------------------------
		// General: deshabilita el botón de guardar al enviar, para evitar un
		// doble submit mientras options.php procesa la recarga completa de
		// página (este formulario no usa AJAX, así que no hay "restaurar el
		// botón tras error" que programar: si algo falla, la página vuelve a
		// cargar entera y el botón nace habilitado de nuevo).
		// -----------------------------------------------------------------
		$('#momentum-general-form').on('submit', function () {
			$(this).find('#momentum-general-submit')
				.prop('disabled', true)
				.text('Guardando...');
		});
	});
})(jQuery);
