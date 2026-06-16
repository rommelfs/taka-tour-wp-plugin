(function () {
	'use strict';

	document.addEventListener('click', function (event) {
		var toggle = event.target.closest('[data-taka-toggle]');
		if (toggle) {
			var panel = toggle.parentElement.nextElementSibling;
			var isOpen = toggle.getAttribute('aria-expanded') === 'true';
			toggle.setAttribute('aria-expanded', String(!isOpen));
			toggle.textContent = isOpen ? 'Tickets anzeigen' : 'Tickets ausblenden';
			if (panel) {
				panel.hidden = isOpen;
			}
		}

		var tab = event.target.closest('[data-taka-tabs] [data-tab]');
		if (tab) {
			var tabs = tab.closest('[data-taka-tabs]');
			var name = tab.getAttribute('data-tab');
			tabs.querySelectorAll('[data-tab]').forEach(function (button) {
				button.classList.toggle('is-active', button === tab);
			});
			tabs.querySelectorAll('[data-panel]').forEach(function (panel) {
				panel.classList.toggle('is-active', panel.getAttribute('data-panel') === name);
			});
		}
	});
}());
