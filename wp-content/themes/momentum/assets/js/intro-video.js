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
	}

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && ! root.hasAttribute('hidden')) {
			close();
		}
	});
})();
