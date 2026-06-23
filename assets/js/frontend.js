(function () {
	'use strict';

	function activateTab(tab, options) {
		if (!tab) {
			return;
		}
		options = options || {};

		var tabs = tab.closest('[data-taka-tabs]');
		if (!tabs) {
			return;
		}

		var name = tab.getAttribute('data-tab');
		tabs.querySelectorAll('[data-tab]').forEach(function (button) {
			var isActive = button === tab;
			button.classList.toggle('is-active', isActive);
			button.setAttribute('aria-selected', String(isActive));
		});
		tabs.querySelectorAll('[data-panel]').forEach(function (panel) {
			var isActive = panel.getAttribute('data-panel') === name;
			panel.classList.toggle('is-active', isActive);
			panel.toggleAttribute('hidden', !isActive);
		});
		setActiveLocation(name);
		if (options.updateUrl) {
			updateTicketUrl(name, !!options.replaceUrl);
		}
	}

	function setActiveLocation(name) {
		document.querySelectorAll('[data-taka-ticket-tab]').forEach(function (link) {
			var isActive = link.getAttribute('data-taka-ticket-tab') === name;
			link.classList.toggle('is-active', isActive);
			if (isActive) {
				link.setAttribute('aria-current', 'true');
			} else {
				link.removeAttribute('aria-current');
			}
		});
	}

	function takaCssEscape(value) {
		if (window.CSS && CSS.escape) {
			return CSS.escape(value);
		}
		return String(value).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
	}

	function ticketTabByName(name) {
		var ticketSection = document.getElementById('tickets');
		return ticketSection && name ? ticketSection.querySelector('[data-tab="' + takaCssEscape(name) + '"]') : null;
	}

	function requestedTicketTab() {
		try {
			return new URLSearchParams(window.location.search).get('taka_event') || '';
		} catch (error) {
			var match = window.location.search.match(/[?&]taka_event=([^&]+)/);
			return match ? decodeURIComponent(match[1].replace(/\+/g, ' ')) : '';
		}
	}

	function updateTicketUrl(name, replaceUrl) {
		if (!name || !window.history || !window.history.pushState) {
			return;
		}
		try {
			var url = new URL(window.location.href);
			url.searchParams.set('taka_event', name);
			url.hash = 'tickets';
			window.history[replaceUrl ? 'replaceState' : 'pushState']({ takaEvent: name }, '', url.toString());
		} catch (error) {
			return;
		}
	}

	function activateRequestedTicketTab(replaceUrl) {
		var requested = requestedTicketTab();
		var tab = ticketTabByName(requested);
		if (!tab) {
			return false;
		}
		activateTab(tab, { updateUrl: !!replaceUrl, replaceUrl: !!replaceUrl });
		return true;
	}

	function copyText(text) {
		if (navigator.clipboard && window.isSecureContext) {
			return navigator.clipboard.writeText(text);
		}
		return new Promise(function (resolve, reject) {
			var textarea = document.createElement('textarea');
			textarea.value = text;
			textarea.setAttribute('readonly', 'readonly');
			textarea.style.position = 'fixed';
			textarea.style.left = '-9999px';
			document.body.appendChild(textarea);
			textarea.select();
			try {
				if (document.execCommand('copy')) {
					resolve();
				} else {
					reject(new Error('Copy failed'));
				}
			} catch (error) {
				reject(error);
			}
			document.body.removeChild(textarea);
		});
	}

	function showShareFeedback(button) {
		var label = button.getAttribute('data-share-label') || button.textContent;
		var copiedLabel = button.getAttribute('data-share-copied-label') || label;
		button.textContent = copiedLabel;
		window.setTimeout(function () {
			button.textContent = label;
		}, 1800);
	}

	document.addEventListener('click', function (event) {
		var shareButton = event.target.closest('[data-taka-share-event]');
		if (shareButton) {
			event.preventDefault();
			var shareUrl = shareButton.getAttribute('data-share-url') || window.location.href;
			var shareTitle = shareButton.getAttribute('data-share-title') || document.title;
			if (navigator.share) {
				navigator.share({ title: shareTitle, url: shareUrl }).catch(function () {});
				return;
			}
			copyText(shareUrl).then(function () {
				showShareFeedback(shareButton);
			}).catch(function () {
				window.prompt(shareButton.getAttribute('data-share-prompt-label') || 'Copy link', shareUrl);
			});
			return;
		}

		var stationLink = event.target.closest('[data-taka-ticket-tab]');
		if (stationLink) {
			var target = stationLink.getAttribute('data-taka-ticket-tab');
			var ticketSection = document.getElementById('tickets');
			var tab = ticketTabByName(target);
			if (tab) {
				event.preventDefault();
				activateTab(tab, { updateUrl: true });
				ticketSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
				tab.focus({ preventScroll: true });
			}
			return;
		}

		var tab = event.target.closest('[data-taka-tabs] [data-tab]');
		if (!tab) {
			return;
		}

		activateTab(tab, { updateUrl: true });
	});

	document.addEventListener('DOMContentLoaded', function () {
		if (activateRequestedTicketTab(true)) {
			return;
		}
		var activeTab = document.querySelector('[data-taka-tabs] [data-tab].is-active');
		if (activeTab) {
			setActiveLocation(activeTab.getAttribute('data-tab'));
		}
	});

	window.addEventListener('popstate', function () {
		activateRequestedTicketTab(false);
	});
}());
