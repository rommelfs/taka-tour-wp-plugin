(function () {
	'use strict';

	function closeLanguageDropdowns() {
		document.querySelectorAll('.taka-language-dropdown.is-open').forEach(function (dropdown) {
			dropdown.classList.remove('is-open');
			var trigger = dropdown.querySelector('[data-taka-language-dropdown]');
			if (trigger) {
				trigger.setAttribute('aria-expanded', 'false');
			}
		});
	}

	function currentContextHash() {
		if (window.location.hash) {
			return window.location.hash;
		}

		var tickets = document.getElementById('tickets');
		if (!tickets) {
			return '';
		}

		var rect = tickets.getBoundingClientRect();
		var viewportHeight = window.innerHeight || document.documentElement.clientHeight;
		var ticketSectionIsVisible = rect.top < viewportHeight * 0.75 && rect.bottom > viewportHeight * 0.2;
		if (!ticketSectionIsVisible) {
			return '';
		}

		var activeTicketTab = tickets.querySelector('[data-taka-tabs] [data-tab].is-active');
		if (!activeTicketTab) {
			return '';
		}

		var tabName = activeTicketTab.getAttribute('data-tab');
		return tabName ? '#tickets/' + encodeURIComponent(tabName) : '';
	}

	function currentSectionAnchor() {
		var candidates = Array.prototype.slice.call(document.querySelectorAll('section[id], [data-panel].is-active[id], .taka-content-section[id], .taka-hero[id]'));
		var viewportHeight = window.innerHeight || document.documentElement.clientHeight;
		var best = null;
		candidates.forEach(function (element) {
			if (!element.id || element.id === 'top') {
				return;
			}
			var rect = element.getBoundingClientRect();
			if (rect.bottom < 0 || rect.top > viewportHeight) {
				return;
			}
			var distance = Math.abs(rect.top);
			if (!best || distance < best.distance) {
				best = { id: element.id, distance: distance };
			}
		});
		return best ? best.id : '';
	}

	function updateLanguageLinkContext(link) {
		if (!link) {
			return;
		}

		try {
			var url = new URL(link.getAttribute('href'), window.location.href);
			var hash = currentContextHash();
			if (hash) {
				url.hash = hash.replace(/^#/, '');
			}
			url.searchParams.set('taka_scroll', String(Math.max(0, Math.round(window.scrollY || window.pageYOffset || 0))));
			var anchor = currentSectionAnchor();
			if (anchor) {
				url.searchParams.set('taka_anchor', anchor);
			} else {
				url.searchParams.delete('taka_anchor');
			}
			link.href = url.toString();
		} catch (error) {
			// Leave the original href intact for older browsers or malformed URLs.
		}
	}

	function restoreLanguageScroll() {
		if (!window.URL || !window.history || !window.history.replaceState) {
			return;
		}

		var url = new URL(window.location.href);
		var scrollValue = url.searchParams.get('taka_scroll');
		var anchor = url.searchParams.get('taka_anchor');
		if (scrollValue === null && !anchor) {
			return;
		}

		var targetY = scrollValue !== null && /^\d+$/.test(scrollValue) ? parseInt(scrollValue, 10) : null;
		var anchorElement = anchor ? document.getElementById(anchor) : null;
		var restore = function () {
			if (anchorElement) {
				anchorElement.scrollIntoView({ block: 'start', behavior: 'auto' });
			}
			if (targetY !== null) {
				window.scrollTo({ top: targetY, left: 0, behavior: 'auto' });
			}
			url.searchParams.delete('taka_scroll');
			url.searchParams.delete('taka_anchor');
			window.history.replaceState(null, '', url.pathname + (url.search ? url.search : '') + url.hash);
		};

		window.requestAnimationFrame(function () {
			window.requestAnimationFrame(restore);
		});
	}

	function scrollToPageTop() {
		var target = document.getElementById('top') || document.body;
		var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		target.scrollIntoView({
			behavior: reduceMotion ? 'auto' : 'smooth',
			block: 'start'
		});
		if (window.history && window.history.replaceState) {
			window.history.replaceState(null, '', window.location.pathname + window.location.search + '#top');
		}
	}

	document.addEventListener('click', function (event) {
		var topLink = event.target.closest('[data-taka-scroll-top]');
		if (topLink) {
			event.preventDefault();
			scrollToPageTop();
			closeLanguageDropdowns();
			return;
		}

		var languageLink = event.target.closest('[data-taka-language-link]');
		if (languageLink) {
			updateLanguageLinkContext(languageLink);
			closeLanguageDropdowns();
			return;
		}

		var languageTrigger = event.target.closest('[data-taka-language-dropdown]');
		if (languageTrigger) {
			var dropdown = languageTrigger.closest('.taka-language-dropdown');
			var isOpen = dropdown.classList.contains('is-open');
			closeLanguageDropdowns();
			dropdown.classList.toggle('is-open', !isOpen);
			languageTrigger.setAttribute('aria-expanded', String(!isOpen));
			return;
		}

		if (!event.target.closest('.taka-language-dropdown')) {
			closeLanguageDropdowns();
		}
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			closeLanguageDropdowns();
		}
	});

	if (document.readyState === 'complete') {
		restoreLanguageScroll();
	} else {
		window.addEventListener('load', restoreLanguageScroll);
	}
}());
