/**
 * Partyline PWA — client app.
 *
 * Flow: photo (+ filter) and/or voice -> record -> 16kHz WAV -> /transcribe
 * -> /generate (AI title/body) -> editable preview -> /submit -> draft post.
 */
(function () {
	'use strict';

	var CFG = window.PARTYLINE_PWA || {};
	var $ = function (sel) { return document.querySelector(sel); };

	/* --------------------------------------------------------------- */
	/* REST helpers                                                     */
	/* --------------------------------------------------------------- */
	function api(path, opts) {
		opts = opts || {};
		opts.headers = opts.headers || {};
		opts.headers['X-WP-Nonce'] = CFG.nonce;
		opts.credentials = 'same-origin';
		return fetch(CFG.restBase + path, opts).then(function (res) {
			return res.json().then(function (data) {
				if (!res.ok) {
					throw new Error(data && data.message ? data.message : ('Request failed (' + res.status + ')'));
				}
				return data;
			});
		});
	}
	window.PartylineAPI = api;

	/* --------------------------------------------------------------- */
	/* State + screen navigation                                        */
	/* --------------------------------------------------------------- */
	var state = { photoImg: null, filter: 'none', transcript: '', title: '', body: '' };

	var FILTERS = {
		none:  'none',
		bw:    'grayscale(1) contrast(1.06)',
		warm:  'saturate(1.25) sepia(0.12) brightness(1.02)',
		cool:  'saturate(1.1) hue-rotate(-12deg) brightness(1.03)',
		vivid: 'saturate(1.55) contrast(1.12)'
	};

	function show(id) {
		var screens = document.querySelectorAll('.pl-screen');
		for (var i = 0; i < screens.length; i++) {
			screens[i].classList.toggle('pl-hidden', screens[i].id !== id);
		}
		window.scrollTo(0, 0);
	}

	/* --------------------------------------------------------------- */
	/* Photo capture + filters                                          */
	/* --------------------------------------------------------------- */
	var canvas, cctx;

	function handlePhotoFile(file) {
		var url = URL.createObjectURL(file);
		var img = new Image();
		img.onload = function () {
			URL.revokeObjectURL(url);
			var max = 1600;
			var scale = Math.min(1, max / Math.max(img.naturalWidth, img.naturalHeight));
			canvas.width = Math.round(img.naturalWidth * scale);
			canvas.height = Math.round(img.naturalHeight * scale);
			cctx = canvas.getContext('2d');
			cctx.drawImage(img, 0, 0, canvas.width, canvas.height);
			state.photoImg = img;
			canvas.classList.remove('pl-hidden');
			$('#pl-filters').classList.remove('pl-hidden');
			$('#pl-photo-camera').innerHTML = '<span>🔄</span> Retake';
			$('#pl-photo-library').innerHTML = '<span>🖼️</span> Replace';
			applyFilter(state.filter);
		};
		img.onerror = function () { URL.revokeObjectURL(url); setStatus('Could not load that image.', 'error'); };
		img.src = url;
	}

	function applyFilter(name) {
		state.filter = name;
		// Live preview via CSS filter on the canvas element (works everywhere).
		canvas.style.filter = FILTERS[name] === 'none' ? '' : FILTERS[name];
		var chips = document.querySelectorAll('.pl-chip');
		for (var i = 0; i < chips.length; i++) {
			chips[i].classList.toggle('is-active', chips[i].getAttribute('data-filter') === name);
		}
	}

	// Bake the current photo + filter into a JPEG blob for upload.
	function bakePhoto() {
		return new Promise(function (resolve) {
			if (!state.photoImg) { resolve(null); return; }
			var c = document.createElement('canvas');
			c.width = canvas.width;
			c.height = canvas.height;
			var x = c.getContext('2d');
			if ('filter' in x) { x.filter = FILTERS[state.filter]; } // no-op filter support on old Safari
			x.drawImage(state.photoImg, 0, 0, c.width, c.height);
			c.toBlob(function (blob) { resolve(blob); }, 'image/jpeg', 0.9);
		});
	}

	/* --------------------------------------------------------------- */
	/* Voice record -> 16kHz WAV                                        */
	/* --------------------------------------------------------------- */
	var recorder = null, chunks = [], stream = null, recording = false;

	function setStatus(msg, kind) {
		var el = $('#pl-rec-status');
		el.textContent = msg;
		el.className = 'pl-status' + (kind ? ' is-' + kind : '');
	}

	// Acquire the mic once and keep it for the whole capture session, so tapping
	// record again doesn't re-prompt / re-acquire the device. Released only when
	// the contributor leaves the capture flow (submit / cancel / send another).
	function ensureStream() {
		if (stream && stream.active) { return Promise.resolve(stream); }
		return navigator.mediaDevices.getUserMedia({ audio: true }).then(function (s) {
			stream = s;
			return s;
		});
	}

	function releaseStream() {
		if (stream) {
			stream.getTracks().forEach(function (t) { t.stop(); });
			stream = null;
		}
	}

	function toggleRecord() {
		if (recording) { stopRecording(); return; }
		if (!navigator.mediaDevices || !window.MediaRecorder) {
			setStatus('Recording is not supported on this browser — tap "Write it myself" to type your story.', 'error');
			return;
		}
		ensureStream().then(function (s) {
			chunks = [];
			recorder = new MediaRecorder(s);
			recorder.ondataavailable = function (e) { if (e.data && e.data.size) { chunks.push(e.data); } };
			recorder.onstop = onRecordingStopped;
			recorder.start();
			recording = true;
			$('#pl-rec-btn').classList.add('is-recording');
			setStatus('Recording… tap to stop.', 'busy');
		}).catch(function () {
			setStatus('Microphone access was blocked. Allow it in your browser settings, or tap "Write it myself".', 'error');
		});
	}

	function stopRecording() {
		recording = false;
		$('#pl-rec-btn').classList.remove('is-recording');
		if (recorder && recorder.state !== 'inactive') { recorder.stop(); }
		// Intentionally keep the mic stream open — released on exit via releaseStream().
	}

	function onRecordingStopped() {
		var type = chunks[0] ? chunks[0].type : ((recorder && recorder.mimeType) || 'audio/webm');
		var blob = new Blob(chunks, { type: type });
		setStatus('Transcribing…', 'busy');
		$('#pl-rec-btn').setAttribute('disabled', 'disabled');

		// Upload the native recording (webm/mp4/…) — OpenAI Whisper accepts it
		// directly, so no client-side resampling/encoding is needed.
		var fd = new FormData();
		fd.append('audio', blob, 'recording.' + extForType(type));

		api('transcribe', { method: 'POST', body: fd }).then(function (res) {
			state.transcript = res.text || '';
			setStatus('Writing it up…', 'busy');
			// Send the transcript + the photo (if any) so the model can use both.
			var gfd = new FormData();
			gfd.append('transcript', state.transcript);
			if (state.photoImg) {
				return bakePhoto().then(function (blob) {
					if (blob) { gfd.append('image', blob, 'photo.jpg'); }
					return api('generate', { method: 'POST', body: gfd });
				});
			}
			return api('generate', { method: 'POST', body: gfd });
		}).then(function (gen) {
			state.title = gen.title || '';
			state.body = gen.body || '';
			setStatus('✓ Got it!', null);
			goToPreview(); // auto-advance to the (editable) review screen
		}).catch(function (err) {
			setStatus(err.message || 'Transcription failed.', 'error');
		}).then(function () {
			$('#pl-rec-btn').removeAttribute('disabled');
		});
	}

	// Map a MediaRecorder mime type to a file extension Whisper recognizes.
	function extForType(t) {
		t = (t || '').toLowerCase();
		if (t.indexOf('webm') > -1) { return 'webm'; }
		if (t.indexOf('ogg') > -1 || t.indexOf('opus') > -1) { return 'ogg'; }
		if (t.indexOf('mp4') > -1 || t.indexOf('m4a') > -1 || t.indexOf('aac') > -1) { return 'mp4'; }
		if (t.indexOf('mpeg') > -1 || t.indexOf('mp3') > -1) { return 'mp3'; }
		if (t.indexOf('wav') > -1) { return 'wav'; }
		return 'webm';
	}

	/* --------------------------------------------------------------- */
	/* Continue -> preview -> submit                                    */
	/* --------------------------------------------------------------- */
	function goToPreview() {
		var img = $('#pl-preview-img');
		bakePhoto().then(function (blob) {
			state.photoBlob = blob;
			if (blob) {
				img.src = URL.createObjectURL(blob);
				img.classList.remove('pl-hidden');
			} else {
				img.classList.add('pl-hidden');
			}
			$('#pl-title').value = state.title;
			$('#pl-body').value = state.body;
			show('screen-preview');
		});
	}

	function submit() {
		var btn = $('#pl-submit');
		btn.disabled = true;
		btn.textContent = 'Submitting…';

		var fd = new FormData();
		fd.append('title', $('#pl-title').value);
		fd.append('body', $('#pl-body').value);
		if (state.photoBlob) { fd.append('image', state.photoBlob, 'partyline.jpg'); }

		api('submit', { method: 'POST', body: fd }).then(function (res) {
			releaseStream(); // done capturing — free the mic
			if (res.message) { $('#pl-success-msg').textContent = res.message; }
			show('screen-success');
		}).catch(function (err) {
			alert(err.message || 'Submit failed.');
		}).then(function () {
			btn.disabled = false;
			btn.textContent = 'Submit Partyline';
		});
	}

	function reset() {
		releaseStream(); // leaving the capture flow — free the mic
		state = { photoImg: null, filter: 'none', transcript: '', title: '', body: '' };
		if (canvas) { canvas.classList.add('pl-hidden'); canvas.style.filter = ''; }
		$('#pl-filters').classList.add('pl-hidden');
		$('#pl-photo-camera').innerHTML = '<span>📷</span> Take photo';
		$('#pl-photo-library').innerHTML = '<span>🖼️</span> Choose photo';
		$('#pl-input-camera').value = '';
		$('#pl-input-library').value = '';
		$('#pl-title').value = '';
		$('#pl-body').value = '';
		setStatus('Tap to dictate — we\'ll write it up for you.', null);
		applyFilter('none');
	}

	/* --------------------------------------------------------------- */
	/* Service worker + install prompt                                  */
	/* --------------------------------------------------------------- */
	function registerServiceWorker() {
		if (!('serviceWorker' in navigator) || !CFG.swUrl) { return; }
		navigator.serviceWorker.register(CFG.swUrl, { scope: CFG.scope || '/' })
			.catch(function (err) { console.warn('[Partyline] SW registration failed:', err); });
	}

	var deferredPrompt = null;
	function isStandalone() {
		return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
	}
	function isIos() {
		return /iphone|ipad|ipod/i.test(navigator.userAgent) ||
			(navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1); // iPadOS
	}
	function setupInstall() {
		var banner = $('#pl-install'), button = $('#pl-install-btn'), msg = $('#pl-install-msg');
		// Already installed — nothing to prompt.
		if (isStandalone() || !banner || !button) { return; }

		// iOS Safari never fires beforeinstallprompt, so show manual instructions.
		if (isIos()) {
			msg.innerHTML = 'Install Partyline: tap the Share button, then <strong>Add to Home Screen</strong>.';
			button.classList.add('pl-hidden');
			banner.classList.add('show');
			return;
		}

		// Android/desktop Chromium: use the native prompt when offered.
		window.addEventListener('beforeinstallprompt', function (e) {
			e.preventDefault();
			deferredPrompt = e;
			banner.classList.add('show');
		});
		button.addEventListener('click', function () {
			if (!deferredPrompt) { return; }
			deferredPrompt.prompt();
			deferredPrompt.userChoice.finally(function () { deferredPrompt = null; banner.classList.remove('show'); });
		});
		window.addEventListener('appinstalled', function () { banner.classList.remove('show'); });
	}

	/* --------------------------------------------------------------- */
	/* Wire up                                                          */
	/* --------------------------------------------------------------- */
	function boot() {
		canvas = $('#pl-photo-canvas');
		registerServiceWorker();
		setupInstall();

		$('#pl-start').addEventListener('click', function () { show('screen-capture'); });
		$('#pl-cancel').addEventListener('click', function () { reset(); show('screen-home'); });
		$('#pl-again').addEventListener('click', function () { reset(); show('screen-home'); });
		$('#pl-recagain').addEventListener('click', function () {
			setStatus('Tap to dictate — we\'ll write it up for you.', null);
			show('screen-capture');
		});
		$('#pl-submit').addEventListener('click', submit);

		$('#pl-photo-camera').addEventListener('click', function () { $('#pl-input-camera').click(); });
		$('#pl-photo-library').addEventListener('click', function () { $('#pl-input-library').click(); });
		function onPick(e) { if (e.target.files && e.target.files[0]) { handlePhotoFile(e.target.files[0]); } }
		$('#pl-input-camera').addEventListener('change', onPick);
		$('#pl-input-library').addEventListener('change', onPick);
		$('#pl-write').addEventListener('click', goToPreview);

		var chips = document.querySelectorAll('.pl-chip');
		for (var i = 0; i < chips.length; i++) {
			chips[i].addEventListener('click', function () { applyFilter(this.getAttribute('data-filter')); });
		}

		$('#pl-rec-btn').addEventListener('click', toggleRecord);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
