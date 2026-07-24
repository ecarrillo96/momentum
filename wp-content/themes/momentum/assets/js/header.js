(function () {
	'use strict';

	var toggle = document.querySelector('.momentum-site-header__toggle');
	var nav = document.getElementById('momentum-mobile-nav');

	if (! toggle || ! nav) {
		return;
	}

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
})();
