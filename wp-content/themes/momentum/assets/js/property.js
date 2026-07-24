(function () {
	'use strict';

	var galleryRoot = document.querySelector('[data-momentum-gallery]');

	if (! galleryRoot) {
		return;
	}

	var images = [];

	try {
		images = JSON.parse(galleryRoot.getAttribute('data-gallery-images') || '[]');
	} catch (e) {
		images = [];
	}

	if (! images.length) {
		return;
	}

	var lightbox = document.querySelector('[data-momentum-lightbox]');

	if (! lightbox) {
		return;
	}

	var stageImage  = lightbox.querySelector('[data-momentum-lightbox-image]');
	var counter     = lightbox.querySelector('[data-momentum-lightbox-counter]');
	var prevButton  = lightbox.querySelector('[data-momentum-lightbox-prev]');
	var nextButton  = lightbox.querySelector('[data-momentum-lightbox-next]');
	var closeButton = lightbox.querySelector('.momentum-lightbox__close');
	var content     = lightbox.querySelector('.momentum-lightbox__content');
	var stage       = lightbox.querySelector('.momentum-lightbox__stage');
	var closers     = lightbox.querySelectorAll('[data-momentum-lightbox-close]');
	var thumbButtons = lightbox.querySelectorAll('[data-momentum-lightbox-goto]');

	var currentIndex  = 0;
	var lastFocusedEl = null;
	var hasMultiple   = images.length > 1;

	function renderSlide(index) {
		var image = images[index];

		if (! image) {
			return;
		}

		currentIndex = index;
		stageImage.src = image.large;
		stageImage.alt = image.alt || '';

		if (counter) {
			counter.textContent = (index + 1) + ' / ' + images.length;
		}

		thumbButtons.forEach(function (btn, i) {
			btn.classList.toggle('is-active', i === index);
		});
	}

	function showNext() {
		renderSlide((currentIndex + 1) % images.length);
	}

	function showPrev() {
		renderSlide((currentIndex - 1 + images.length) % images.length);
	}

	function focusableElements() {
		return Array.prototype.filter.call(
			lightbox.querySelectorAll('button'),
			function (el) { return ! el.hidden && el.offsetParent !== null; }
		);
	}

	function trapFocus(e) {
		var focusable = focusableElements();

		if (! focusable.length) {
			return;
		}

		var first = focusable[0];
		var last  = focusable[focusable.length - 1];

		if (e.shiftKey && document.activeElement === first) {
			e.preventDefault();
			last.focus();
		} else if (! e.shiftKey && document.activeElement === last) {
			e.preventDefault();
			first.focus();
		}
	}

	function onKeydown(e) {
		if (e.key === 'Escape') {
			e.preventDefault();
			closeLightbox();
		} else if (hasMultiple && e.key === 'ArrowRight') {
			e.preventDefault();
			showNext();
		} else if (hasMultiple && e.key === 'ArrowLeft') {
			e.preventDefault();
			showPrev();
		} else if (e.key === 'Tab') {
			trapFocus(e);
		}
	}

	function openLightbox(index) {
		lastFocusedEl = document.activeElement;

		renderSlide(index);
		lightbox.hidden = false;
		document.body.classList.add('momentum-lightbox-open');
		document.addEventListener('keydown', onKeydown);

		if (closeButton) {
			closeButton.focus();
		}
	}

	function closeLightbox() {
		lightbox.hidden = true;
		document.body.classList.remove('momentum-lightbox-open');
		document.removeEventListener('keydown', onKeydown);

		if (lastFocusedEl && typeof lastFocusedEl.focus === 'function') {
			lastFocusedEl.focus();
		}
	}

	// Sin navegación ni "+N fotos" cuando solo hay una imagen.
	if (! hasMultiple) {
		if (prevButton) { prevButton.hidden = true; }
		if (nextButton) { nextButton.hidden = true; }
	}

	// Un único listener delegado cubre imagen principal, miniaturas y el
	// overlay "+N fotos" (todas comparten esta clase + data-gallery-index).
	document.querySelectorAll('.momentum-property-gallery-open').forEach(function (trigger) {
		trigger.addEventListener('click', function () {
			var index = parseInt(trigger.getAttribute('data-gallery-index'), 10) || 0;
			openLightbox(index);
		});
	});

	closers.forEach(function (btn) {
		btn.addEventListener('click', closeLightbox);
	});

	// El contenido (imagen, botones) nunca debe propagar el clic hasta el
	// backdrop; solo el fondo cierra la galería.
	if (content) {
		content.addEventListener('click', function (e) {
			e.stopPropagation();
		});
	}

	if (prevButton) {
		prevButton.addEventListener('click', showPrev);
	}

	if (nextButton) {
		nextButton.addEventListener('click', showNext);
	}

	thumbButtons.forEach(function (btn, index) {
		btn.addEventListener('click', function () {
			renderSlide(index);
		});
	});

	// Soporte táctil básico: swipe horizontal sobre la imagen grande.
	if (stage && hasMultiple) {
		var touchStartX = null;

		stage.addEventListener('touchstart', function (e) {
			touchStartX = e.changedTouches[0].clientX;
		}, { passive: true });

		stage.addEventListener('touchend', function (e) {
			if (touchStartX === null) {
				return;
			}

			var deltaX = e.changedTouches[0].clientX - touchStartX;

			if (Math.abs(deltaX) > 40) {
				deltaX < 0 ? showNext() : showPrev();
			}

			touchStartX = null;
		}, { passive: true });
	}
})();
