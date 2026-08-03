/**
 * Restringe el cuadro nativo "Imagen destacada" de WordPress (Propiedades,
 * Terrenos, Blog) a solo subir archivos nuevos: nunca elegir una imagen ya
 * existente en la biblioteca de medios.
 */
(function ($) {
	'use strict';

	$(function () {
		if (typeof wp === 'undefined' || !wp.media || !wp.media.featuredImage) {
			return;
		}

		var originalFrame = wp.media.featuredImage.frame;

		wp.media.featuredImage.frame = function () {
			var isNew = !this._frame;
			var frame = originalFrame.apply(this, arguments);

			if (isNew) {
				frame.on('open', function () {
					frame.content.mode('upload');
				});

				frame.on('content:activate:browse', function () {
					frame.content.mode('upload');
				});
			}

			return frame;
		};
	});
})(jQuery);
