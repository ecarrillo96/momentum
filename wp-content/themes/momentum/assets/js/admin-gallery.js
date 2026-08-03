(function ($) {
	'use strict';

	$(function () {
		var $button = $('#momentum-galeria-select');
		var $input = $('#momentum_galeria');
		var $preview = $('#momentum-galeria-preview');

		if (! $button.length) {
			return;
		}

		var frame;

		$button.on('click', function (e) {
			e.preventDefault();

			if (frame) {
				frame.open();
				return;
			}

			frame = wp.media({
				title: 'Seleccionar imágenes de la galería',
				button: { text: 'Usar estas imágenes' },
				multiple: true,
			});

			// Solo debe permitirse subir imágenes nuevas, nunca elegir una ya
			// existente en la biblioteca de medios.
			frame.on('open', function () {
				frame.content.mode('upload');
			});

			frame.on('content:activate:browse', function () {
				frame.content.mode('upload');
			});

			frame.on('select', function () {
				var attachments = frame.state().get('selection').toJSON();
				var ids = attachments.map(function (a) { return a.id; });

				$input.val(ids.join(','));

				$preview.empty();
				attachments.forEach(function (a) {
					var url = (a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url;
					$preview.append($('<img>').attr('src', url));
				});
			});

			frame.open();
		});
	});
})(jQuery);
