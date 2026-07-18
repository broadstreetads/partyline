/**
 * Partyline PWA — client bootstrap.
 *
 * Phase 2: service-worker registration, install-prompt handling, shell boot.
 * Phase 3 will add the capture flow (photo + filters, voice record -> 16kHz WAV
 * -> /transcribe -> /generate -> editable preview -> /submit).
 */
(function () {
	'use strict';

	var CFG = window.PARTYLINE_PWA || {};

	/* ---- Service worker ---- */
	function registerServiceWorker() {
		if (!('serviceWorker' in navigator) || !CFG.swUrl) {
			return;
		}
		navigator.serviceWorker.register(CFG.swUrl, { scope: CFG.scope || '/' })
			.catch(function (err) {
				console.warn('[Partyline] SW registration failed:', err);
			});
	}

	/* ---- Install prompt (Android/desktop Chromium) ---- */
	var deferredPrompt = null;

	function isStandalone() {
		return window.matchMedia('(display-mode: standalone)').matches ||
			window.navigator.standalone === true;
	}

	function setupInstall() {
		var banner = document.querySelector('#pl-install');
		var button = document.querySelector('#pl-install-btn');

		if (isStandalone() || !banner || !button) {
			return;
		}

		window.addEventListener('beforeinstallprompt', function (e) {
			e.preventDefault();
			deferredPrompt = e;
			banner.classList.add('show');
		});

		button.addEventListener('click', function () {
			if (!deferredPrompt) {
				return;
			}
			deferredPrompt.prompt();
			deferredPrompt.userChoice.finally(function () {
				deferredPrompt = null;
				banner.classList.remove('show');
			});
		});

		window.addEventListener('appinstalled', function () {
			banner.classList.remove('show');
		});
	}

	/* ---- REST helper (used from Phase 3) ---- */
	function api(path, options) {
		options = options || {};
		options.headers = options.headers || {};
		options.headers['X-WP-Nonce'] = CFG.nonce;
		options.credentials = 'same-origin';
		return fetch((CFG.restBase || '') + path, options).then(function (res) {
			return res.json().then(function (data) {
				if (!res.ok) {
					var msg = (data && data.message) ? data.message : ('Request failed (' + res.status + ')');
					throw new Error(msg);
				}
				return data;
			});
		});
	}
	// Expose for the Phase 3 capture module.
	window.PartylineAPI = api;

	/* ---- Boot ---- */
	function boot() {
		registerServiceWorker();
		setupInstall();

		var start = document.querySelector('#pl-start');
		if (start) {
			start.addEventListener('click', function () {
				// Phase 3 replaces this with the capture flow.
				alert('The camera + voice capture flow lands in the next update.');
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
