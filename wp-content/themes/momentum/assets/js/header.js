(function () {
	'use strict';

	var toggle = document.querySelector('.momentum-site-header__toggle');
	var nav = document.getElementById('momentum-mobile-nav');

	if (toggle && nav) {
		toggle.addEventListener('click', function () {
			var isOpen = document.body.classList.toggle('momentum-mobile-nav-open');
			toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
		});

		nav.addEventListener('click', function (event) {
			if (event.target.tagName === 'A') {
				document.body.classList.remove('momentum-mobile-nav-open');
				toggle.setAttribute('aria-expanded', 'false');
			}
		});
	}

	// Selector de idioma: botón (bandera + idioma actual) que despliega el
	// menú con la(s) otra(s) opción(es). El marcado se repite tal cual en la
	// versión de escritorio y en el panel móvil (momentum_language_switcher()
	// en inc/language.php), así que se inicializa cada instancia por
	// separado en vez de asumir que solo existe una en la página.
	var langSwitchers = document.querySelectorAll('[data-momentum-lang-switcher]');

	langSwitchers.forEach(function (root) {
		var langToggle = root.querySelector('[data-momentum-lang-toggle]');
		var menu = root.querySelector('[data-momentum-lang-menu]');

		if (! langToggle || ! menu) {
			return;
		}

		function closeLangSwitcher() {
			root.classList.remove('is-open');
			langToggle.setAttribute('aria-expanded', 'false');
		}

		function openLangSwitcher() {
			// Cierra cualquier otra instancia abierta (ej. si por algún motivo
			// ambas -escritorio y móvil- estuvieran visibles a la vez).
			langSwitchers.forEach(function (other) {
				if (other !== root) {
					other.classList.remove('is-open');
					var otherToggle = other.querySelector('[data-momentum-lang-toggle]');
					if (otherToggle) {
						otherToggle.setAttribute('aria-expanded', 'false');
					}
				}
			});

			root.classList.add('is-open');
			langToggle.setAttribute('aria-expanded', 'true');
		}

		langToggle.addEventListener('click', function (event) {
			event.stopPropagation();

			if (root.classList.contains('is-open')) {
				closeLangSwitcher();
			} else {
				openLangSwitcher();
			}
		});

		document.addEventListener('click', function (event) {
			if (! root.contains(event.target)) {
				closeLangSwitcher();
			}
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && root.classList.contains('is-open')) {
				closeLangSwitcher();
				langToggle.focus();
			}
		});
	});
})();
