(function () {
	'use strict';

	var STORAGE_KEY = 'momentumIntroVideoSeen';
	var root = document.querySelector('[data-momentum-intro-video]');

	if (! root) {
		return;
	}

	// Ya se vio u omitió en esta pestaña/sesión: no se vuelve a mostrar en
	// cada página vista mientras dure la sesión del navegador.
	if (sessionStorage.getItem(STORAGE_KEY)) {
		root.remove();
		return;
	}

	var player = root.querySelector('[data-momentum-intro-player]');
	var skipButton = root.querySelector('[data-momentum-intro-skip]');

	function close() {
		sessionStorage.setItem(STORAGE_KEY, '1');
		root.setAttribute('hidden', '');
		document.body.classList.remove('momentum-intro-video-open');

		if (player) {
			player.pause();
		}
	}

	root.removeAttribute('hidden');
	document.body.classList.add('momentum-intro-video-open');

	if (skipButton) {
		skipButton.addEventListener('click', close);
	}

	if (player) {
		player.addEventListener('ended', close);

		// El HTML llega con preload="none" y las <source> sin `src` (solo
		// `data-src`): con esto, ni un byte de video se descarga hasta este
		// punto -el poster ya se pintó solo, es una <img> aparte del video.
		// Mover data-src -> src no dispara descarga por sí solo (sigue
		// respetando preload="none"); recién empieza a bajar cuando algo pide
		// reproducir, sea player.play() de abajo o el botón nativo si el
		// usuario decide arrancarlo a mano.
		var sources = player.querySelectorAll('source[data-src]');
		sources.forEach(function (source) {
			source.src = source.getAttribute('data-src');
			source.removeAttribute('data-src');
		});
		player.load();

		var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

		// Con reducción de movimiento activada, el video queda listo para
		// reproducirse (controles nativos funcionan) pero no se autoinicia:
		// el visitante decide si lo quiere ver.
		if (! reducedMotion) {
			var playPromise = player.play();

			// Autoplay bloqueado por el navegador (política de cada
			// navegador, no un error): el overlay se queda con el poster y
			// los controles nativos visibles, listo para que el visitante lo
			// arranque manualmente. "Omitir intro" y Escape siguen
			// funcionando igual, sin depender de que el video haya cargado.
			if (playPromise && typeof playPromise.catch === 'function') {
				playPromise.catch(function () {});
			}
		}
	}

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && ! root.hasAttribute('hidden')) {
			close();
		}
	});
})();
